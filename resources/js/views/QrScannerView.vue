<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AEmpty from "ant-design-vue/es/empty";
import AInput from "ant-design-vue/es/input";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import ASpin from "ant-design-vue/es/spin";
import ATag from "ant-design-vue/es/tag";
import { Camera, CameraOff, CheckCircle2, Clock3, Keyboard, QrCode, ScanLine } from "lucide-vue-next";
import { createAttendanceSession, getAttendanceSessions, getTeacherClasses } from "../api/teacher";
import { scanAttendanceQr, type QrScanResult } from "../api/qr";
import { useAuthStore } from "../stores/authStore";
import type { AttendanceSession, CatechismClass } from "../types/api";
import type { IScannerControls } from "@zxing/browser";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";

interface ScanHistoryItem extends QrScanResult { message: string }

const classes = ref<CatechismClass[]>([]);
const auth = useAuthStore();
const sessions = ref<AttendanceSession[]>([]);
const classId = ref<number>();
const sessionId = ref<number>();
const video = ref<HTMLVideoElement | null>(null);
const controls = ref<IScannerControls | null>(null);
const loadingClasses = ref(true);
const loadingSessions = ref(false);
const startingCamera = ref(false);
const scanning = ref(false);
const processing = ref(false);
const manualToken = ref("");
const createOpen = ref(false);
const createHeldAt = ref("");
const createNote = ref("");
const creatingSession = ref(false);
const errorMessage = ref("");
const feedback = ref<{ type: "success" | "warning"; message: string } | null>(null);
const history = ref<ScanHistoryItem[]>([]);
let lastToken = "";
let lastTokenAt = 0;

const classOptions = computed(() => classes.value.map((item) => ({ value: item.id, label: `${item.name} · ${item.code}` })));
const sessionOptions = computed(() => sessions.value.map((item) => ({
    value: item.id,
    label: new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(item.held_at)),
})));
const canStart = computed(() => Boolean(sessionId.value) && !startingCamera.value);
const canCreateSession = computed(() => auth.hasPermission("create-attendance-session"));

const apiMessage = (error: unknown, fallback: string) =>
    (error as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;

async function loadClasses() {
    loadingClasses.value = true;
    try {
        classes.value = (await getTeacherClasses()).data.data;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải danh sách lớp được phân công.");
    } finally {
        loadingClasses.value = false;
    }
}

async function loadSessions() {
    stopCamera();
    sessionId.value = undefined;
    sessions.value = [];
    feedback.value = null;
    if (!classId.value) return;
    loadingSessions.value = true;
    try {
        sessions.value = (await getAttendanceSessions(classId.value)).data.data.data;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải phiên điểm danh của lớp.");
    } finally {
        loadingSessions.value = false;
    }
}

function openCreateSession() {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    createHeldAt.value = now.toISOString().slice(0, 16);
    createNote.value = "";
    createOpen.value = true;
}

async function createSession() {
    if (!classId.value || !createHeldAt.value || creatingSession.value) return;
    creatingSession.value = true;
    errorMessage.value = "";
    try {
        const session = (await createAttendanceSession(classId.value, {
            held_at: new Date(createHeldAt.value).toISOString(),
            note: createNote.value.trim() || undefined,
        })).data.data;
        sessions.value.unshift(session);
        sessionId.value = session.id;
        createOpen.value = false;
        feedback.value = { type: "success", message: "Đã tạo phiên điểm danh. Bạn có thể mở camera để quét." };
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tạo phiên điểm danh.");
    } finally {
        creatingSession.value = false;
    }
}
function closeCreateSession() { if (!creatingSession.value) createOpen.value = false; }

async function submitToken(token: string) {
    const normalized = token.trim();
    if (!sessionId.value || !normalized || processing.value) return;
    const now = Date.now();
    if (normalized === lastToken && now - lastTokenAt < 1800) return;
    lastToken = normalized;
    lastTokenAt = now;
    processing.value = true;
    errorMessage.value = "";
    try {
        const response = await scanAttendanceQr(sessionId.value, normalized);
        const result = response.data.data;
        feedback.value = { type: result.was_duplicate ? "warning" : "success", message: response.data.message };
        history.value.unshift({ ...result, message: response.data.message });
        history.value = history.value.slice(0, 8);
        manualToken.value = "";
        navigator.vibrate?.(result.was_duplicate ? [80, 60, 80] : 100);
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể điểm danh bằng mã QR này.");
        navigator.vibrate?.([120, 80, 120]);
    } finally {
        processing.value = false;
    }
}

async function startCamera() {
    if (!canStart.value || !video.value) return;
    errorMessage.value = "";
    startingCamera.value = true;
    try {
        const { BrowserQRCodeReader } = await import("@zxing/browser");
        const reader = new BrowserQRCodeReader(undefined, { delayBetweenScanAttempts: 200, delayBetweenScanSuccess: 900 });
        controls.value = await reader.decodeFromConstraints(
            { audio: false, video: { facingMode: { ideal: "environment" } } },
            video.value,
            (result) => { if (result) void submitToken(result.getText()); },
        );
        scanning.value = true;
    } catch (error) {
        errorMessage.value = error instanceof DOMException && error.name === "NotAllowedError"
            ? "Trình duyệt chưa được cấp quyền sử dụng camera."
            : "Không thể mở camera. Bạn vẫn có thể nhập mã thủ công bên dưới.";
        stopCamera();
    } finally {
        startingCamera.value = false;
    }
}

function stopCamera() {
    controls.value?.stop();
    controls.value = null;
    scanning.value = false;
    if (video.value?.srcObject instanceof MediaStream) {
        video.value.srcObject.getTracks().forEach((track) => track.stop());
        video.value.srcObject = null;
    }
}

watch(classId, loadSessions);
watch(sessionId, () => { feedback.value = null; errorMessage.value = ""; stopCamera(); });
onMounted(loadClasses);
onBeforeUnmount(stopCamera);
</script>

<template>
    <section class="teacher-page-stack">
        <TeacherPageHeader title="Điểm danh bằng QR" description="Chọn đúng lớp và phiên trước khi quét mã của thiếu nhi." :count="history.length ? `${history.length} lượt vừa quét` : undefined" />

        <ACard :bordered="false" class="teacher-card">
            <div class="teacher-toolbar qr-filter-toolbar">
                <div class="teacher-toolbar-main qr-filter-grid">
                    <label class="grid gap-1.5 text-xs font-semibold text-slate-700">Lớp được phân công<ASelect v-model:value="classId" show-search option-filter-prop="label" size="large" placeholder="Chọn lớp" :options="classOptions" :loading="loadingClasses" /></label>
                    <div class="grid gap-1.5 text-xs font-semibold text-slate-700"><div class="flex items-center justify-between gap-2"><span>Phiên điểm danh</span><button v-if="canCreateSession" type="button" class="text-xs font-semibold text-blue-600 transition-colors hover:text-blue-800 disabled:text-slate-400" :disabled="!classId" @click="openCreateSession">+ Tạo phiên</button></div><ASelect v-model:value="sessionId" size="large" placeholder="Chọn ngày và giờ" :options="sessionOptions" :loading="loadingSessions" :disabled="!classId" /></div>
                </div>
            </div>
        </ACard>

        <AAlert v-if="errorMessage" type="error" show-icon closable :message="errorMessage" class="mb-4" @close="errorMessage=''" />
        <AAlert v-if="feedback" :type="feedback.type" show-icon :message="feedback.message" class="mb-4" />

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1.35fr)_minmax(18rem,.65fr)]">
            <ACard :bordered="false" class="admin-card overflow-hidden">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div><h3 class="m-0 text-base font-bold text-blue-950">Camera quét mã</h3><p class="mt-1 text-xs text-slate-500">Giữ mã nằm gọn trong khung hình.</p></div>
                    <AButton v-if="!scanning" type="primary" size="large" :disabled="!sessionId" :loading="startingCamera" @click="startCamera"><template #icon><Camera class="size-4" /></template>Mở camera</AButton>
                    <AButton v-else danger size="large" @click="stopCamera"><template #icon><CameraOff class="size-4" /></template>Tắt camera</AButton>
                </div>
                <div class="relative aspect-[4/3] overflow-hidden rounded-xl bg-slate-950 sm:aspect-video">
                    <video ref="video" muted playsinline class="size-full object-cover" />
                    <div v-if="!scanning" class="absolute inset-0 grid place-items-center p-6 text-center text-slate-300"><div><QrCode class="mx-auto mb-3 size-10 text-slate-500" /><p class="m-0 text-sm">Chọn phiên điểm danh và mở camera để bắt đầu.</p></div></div>
                    <div v-else class="pointer-events-none absolute inset-0 grid place-items-center bg-slate-950/10"><div class="scanner-frame relative aspect-square w-[min(65%,18rem)] rounded-2xl border border-white/30"><span class="scan-line absolute inset-x-3 top-3 h-0.5 bg-cyan-300 shadow-[0_0_12px_#67e8f9]" /></div></div>
                    <div v-if="processing" class="absolute inset-0 grid place-items-center bg-slate-950/50"><ASpin size="large" /></div>
                </div>
                <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="mb-2 flex items-center gap-2 text-xs font-semibold text-slate-700"><Keyboard class="size-4" />Nhập mã thủ công</div>
                    <AInput v-model:value="manualToken" size="large" placeholder="Dán chuỗi mã QR khi camera không khả dụng" :disabled="!sessionId || processing" @press-enter="submitToken(manualToken)">
                        <template #suffix><AButton type="primary" :disabled="!manualToken.trim()||!sessionId" :loading="processing" @click="submitToken(manualToken)"><template #icon><ScanLine class="size-4" /></template>Điểm danh</AButton></template>
                    </AInput>
                </div>
            </ACard>

            <ACard :bordered="false" class="admin-card">
                <div class="mb-4"><h3 class="m-0 text-base font-bold text-blue-950">Vừa quét</h3><p class="mt-1 text-xs text-slate-500">Tối đa 8 lượt gần nhất trên thiết bị này.</p></div>
                <AEmpty v-if="!history.length" :image="AEmpty.PRESENTED_IMAGE_SIMPLE" description="Chưa có lượt quét" />
                <ol v-else class="m-0 grid list-none gap-2 p-0">
                    <li v-for="item in history" :key="`${item.attendance.id}-${item.scanned_at}`" class="flex items-center gap-3 rounded-xl border border-slate-200 p-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg" :class="item.was_duplicate?'bg-amber-50 text-amber-600':'bg-emerald-50 text-emerald-600'"><Clock3 v-if="item.was_duplicate" class="size-4" /><CheckCircle2 v-else class="size-4" /></span>
                        <span class="min-w-0 flex-1"><b class="block truncate text-sm text-blue-950">{{ item.child.full_name }}</b><small class="block truncate text-slate-500">{{ item.child.code }} · {{ new Date(item.scanned_at).toLocaleTimeString('vi-VN') }}</small></span>
                        <ATag :color="item.attendance.status==='late'?'orange':'green'">{{ item.attendance.status==='late'?'Đi trễ':'Có mặt' }}</ATag>
                    </li>
                </ol>
            </ACard>
        </div>
    </section>

    <AModal :open="createOpen" centered title="Tạo phiên điểm danh" :confirm-loading="creatingSession" :closable="!creatingSession" :keyboard="!creatingSession" :mask-closable="false" :cancel-button-props="{ disabled: creatingSession }" ok-text="Tạo phiên" cancel-text="Hủy" :ok-button-props="{ disabled: !createHeldAt }" @cancel="closeCreateSession" @ok="createSession">
        <div class="grid gap-4 pt-2">
            <label class="grid gap-1.5 text-xs font-semibold text-slate-700">Ngày và giờ bắt đầu <span class="text-red-500">*</span><AInput v-model:value="createHeldAt" type="datetime-local" size="large" :disabled="creatingSession" /></label>
            <label class="grid gap-1.5 text-xs font-semibold text-slate-700">Ghi chú <AInput v-model:value="createNote" size="large" placeholder="Không bắt buộc" :maxlength="500" :disabled="creatingSession" /></label>
        </div>
    </AModal>
</template>

<style scoped>
.qr-filter-grid{display:grid;grid-template-columns:repeat(2,minmax(15rem,1fr));max-width:58rem}.scanner-frame::before,.scanner-frame::after{content:"";position:absolute;inset:-2px;border:3px solid transparent;border-radius:1rem}.scanner-frame::before{border-top-color:#fff;border-left-color:#fff}.scanner-frame::after{border-right-color:#fff;border-bottom-color:#fff}.scan-line{animation:scan 2.2s cubic-bezier(.4,0,.2,1) infinite}@keyframes scan{0%,100%{top:.75rem;opacity:.55}50%{top:calc(100% - .85rem);opacity:1}}@media(max-width:639px){.qr-filter-grid{grid-template-columns:1fr;max-width:none}}@media(prefers-reduced-motion:reduce){.scan-line{animation:none;top:50%}}
</style>

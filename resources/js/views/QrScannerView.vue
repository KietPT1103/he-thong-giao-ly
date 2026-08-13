<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AInput from "ant-design-vue/es/input";
import ASelect from "ant-design-vue/es/select";
import ATag from "ant-design-vue/es/tag";
import { CalendarClock, Download, QrCode, RefreshCw, ShieldCheck, Timer } from "lucide-vue-next";
import QrcodeVue from "qrcode.vue";
import { createAttendanceQr, getAttendanceSessionQr, type AttendanceSessionQrPayload } from "../api/qr";
import { getAttendanceSessions, getTeacherClasses } from "../api/teacher";
import type { AttendanceSession, CatechismClass } from "../types/api";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";

const classes = ref<CatechismClass[]>([]);
const sessions = ref<AttendanceSession[]>([]);
const classId = ref<number>();
const previousSessionId = ref<number>();
const heldAt = ref("");
const expiresAt = ref("");
const note = ref("");
const payload = ref<AttendanceSessionQrPayload | null>(null);
const qrContainer = ref<HTMLElement | null>(null);
const loadingClasses = ref(true);
const loadingSessions = ref(false);
const generating = ref(false);
const loadingQr = ref(false);
const errorMessage = ref("");
const nowMs = ref(Date.now());
let ticker: number | undefined;

const classOptions = computed(() => classes.value.map((item) => ({
    value: item.id,
    label: `${item.name} · ${item.code}`,
})));
const previousSessionOptions = computed(() => sessions.value
    .filter((item) => item.qr_expires_at)
    .map((item) => ({
        value: item.id,
        label: `${formatDateTime(item.held_at)} · hết hạn ${formatTime(item.qr_expires_at!)}`,
    })));
const remainingSeconds = computed(() => payload.value
    ? Math.max(0, Math.ceil((new Date(payload.value.session.qr_expires_at).getTime() - nowMs.value) / 1000))
    : 0);
const expired = computed(() => Boolean(payload.value) && remainingSeconds.value === 0);
const countdown = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60).toString().padStart(2, "0");
    const seconds = (remainingSeconds.value % 60).toString().padStart(2, "0");
    return `${minutes}:${seconds}`;
});
const canGenerate = computed(() => Boolean(classId.value && heldAt.value && expiresAt.value) && !generating.value);

const apiMessage = (error: unknown, fallback: string) =>
    (error as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;

function toLocalInput(date: Date) {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
    return local.toISOString().slice(0, 16);
}

function setDefaultTimes() {
    const start = new Date();
    start.setSeconds(0, 0);
    const expiry = new Date(start.getTime() + 15 * 60_000);
    heldAt.value = toLocalInput(start);
    expiresAt.value = toLocalInput(expiry);
}

function formatDateTime(value: string) {
    return new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}

function formatTime(value: string) {
    return new Intl.DateTimeFormat("vi-VN", { hour: "2-digit", minute: "2-digit" }).format(new Date(value));
}

async function loadClasses() {
    try {
        classes.value = (await getTeacherClasses()).data.data;
        if (classes.value.length === 1) classId.value = classes.value[0].id;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải danh sách lớp được phân công.");
    } finally {
        loadingClasses.value = false;
    }
}

async function loadSessions() {
    sessions.value = [];
    previousSessionId.value = undefined;
    payload.value = null;
    if (!classId.value) return;
    loadingSessions.value = true;
    try {
        sessions.value = (await getAttendanceSessions(classId.value)).data.data.data;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải các mã QR đã tạo.");
    } finally {
        loadingSessions.value = false;
    }
}

async function generateQr() {
    if (!canGenerate.value || !classId.value) return;
    generating.value = true;
    errorMessage.value = "";
    try {
        payload.value = (await createAttendanceQr(classId.value, {
            held_at: new Date(heldAt.value).toISOString(),
            qr_expires_at: new Date(expiresAt.value).toISOString(),
            note: note.value.trim() || undefined,
        })).data.data;
        previousSessionId.value = payload.value.session.id;
        await loadSessionsAfterCreate();
        navigator.vibrate?.(80);
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tạo mã QR điểm danh.");
    } finally {
        generating.value = false;
    }
}

async function loadSessionsAfterCreate() {
    if (!classId.value || !payload.value) return;
    const current = payload.value;
    try {
        sessions.value = (await getAttendanceSessions(classId.value)).data.data.data;
    } finally {
        payload.value = current;
        previousSessionId.value = current.session.id;
    }
}

async function loadPreviousQr() {
    if (!previousSessionId.value) return;
    loadingQr.value = true;
    errorMessage.value = "";
    try {
        payload.value = (await getAttendanceSessionQr(previousSessionId.value)).data.data;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải lại mã QR này.");
    } finally {
        loadingQr.value = false;
    }
}

function downloadQr() {
    const canvas = qrContainer.value?.querySelector("canvas");
    if (!canvas || !payload.value) return;
    const link = document.createElement("a");
    link.download = `qr-diem-danh-${payload.value.session.class.code}-${payload.value.session.id}.png`;
    link.href = canvas.toDataURL("image/png");
    link.click();
}

watch(classId, loadSessions);
watch(heldAt, (value, previous) => {
    if (!value || !previous) return;
    const oldStart = new Date(previous).getTime();
    const oldExpiry = new Date(expiresAt.value).getTime();
    const duration = Number.isFinite(oldExpiry - oldStart) ? Math.max(5 * 60_000, oldExpiry - oldStart) : 15 * 60_000;
    expiresAt.value = toLocalInput(new Date(new Date(value).getTime() + duration));
});
onMounted(() => {
    setDefaultTimes();
    void loadClasses();
    ticker = window.setInterval(() => { nowMs.value = Date.now(); }, 1000);
});
onBeforeUnmount(() => { if (ticker) window.clearInterval(ticker); });
</script>

<template>
    <section class="teacher-page-stack">
        <TeacherPageHeader title="Tạo QR điểm danh" description="Chọn lớp, giờ học và thời hạn. Thiếu nhi đăng nhập rồi quét mã này để tự điểm danh." />

        <AAlert v-if="errorMessage" type="error" show-icon closable :message="errorMessage" class="mb-4" @close="errorMessage=''" />

        <div class="grid gap-4 xl:grid-cols-[minmax(20rem,.82fr)_minmax(28rem,1.18fr)]">
            <ACard :bordered="false" class="teacher-card h-fit">
                <div class="mb-5 flex items-start gap-3">
                    <span class="grid size-11 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600"><CalendarClock class="size-5" /></span>
                    <div><h3 class="m-0 text-base font-bold text-blue-950">Thông tin buổi học</h3><p class="mt-1 text-xs leading-5 text-slate-500">Mã chỉ dùng cho một lớp và tự khóa khi hết thời gian.</p></div>
                </div>

                <div class="grid gap-4">
                    <label class="grid gap-1.5 text-xs font-semibold text-slate-700">Lớp được phân công <span class="text-red-500">*</span><ASelect v-model:value="classId" show-search option-filter-prop="label" size="large" placeholder="Chọn lớp" :options="classOptions" :loading="loadingClasses" /></label>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="grid gap-1.5 text-xs font-semibold text-slate-700">Ngày và giờ học <span class="text-red-500">*</span><AInput v-model:value="heldAt" type="datetime-local" size="large" /></label>
                        <label class="grid gap-1.5 text-xs font-semibold text-slate-700">QR hết hạn lúc <span class="text-red-500">*</span><AInput v-model:value="expiresAt" type="datetime-local" size="large" /></label>
                    </div>
                    <label class="grid gap-1.5 text-xs font-semibold text-slate-700">Ghi chú<AInput v-model:value="note" size="large" placeholder="Ví dụ: Điểm danh đầu giờ" :maxlength="500" /></label>
                    <AButton type="primary" size="large" class="min-h-11 active:scale-[.96] transition-transform" :disabled="!canGenerate" :loading="generating" @click="generateQr"><template #icon><QrCode class="size-4" /></template>Tạo mã QR</AButton>
                </div>

                <div v-if="previousSessionOptions.length" class="mt-6 border-t border-slate-100 pt-5">
                    <label class="grid gap-1.5 text-xs font-semibold text-slate-700">Mở lại QR đã tạo<ASelect v-model:value="previousSessionId" size="large" placeholder="Chọn một phiên" :options="previousSessionOptions" :loading="loadingSessions" @change="loadPreviousQr" /></label>
                </div>
            </ACard>

            <ACard :bordered="false" class="teacher-card overflow-hidden">
                <div v-if="payload" class="grid gap-6 md:grid-cols-[minmax(15rem,18rem)_minmax(0,1fr)] md:items-center">
                    <div ref="qrContainer" class="relative mx-auto grid w-fit place-items-center rounded-2xl bg-white p-5 shadow-[0_12px_34px_rgba(15,23,42,.10)] outline outline-1 outline-black/10">
                        <QrcodeVue :value="payload.token" :size="248" level="M" render-as="canvas" />
                        <div v-if="expired" class="absolute inset-0 grid place-items-center rounded-2xl bg-white/92 p-5 text-center backdrop-blur-sm"><div><Timer class="mx-auto size-8 text-rose-500" /><b class="mt-2 block text-sm text-rose-700">Mã QR đã hết hạn</b></div></div>
                    </div>
                    <div class="min-w-0">
                        <ATag :color="expired ? 'red' : 'green'">{{ expired ? 'Đã hết hạn' : 'Đang nhận điểm danh' }}</ATag>
                        <h3 class="mt-3 text-balance text-xl font-bold text-blue-950">{{ payload.session.class.name }}</h3>
                        <p class="mt-1 text-sm font-medium text-slate-500">{{ payload.session.class.code }} · {{ formatDateTime(payload.session.held_at) }}</p>
                        <div class="mt-5 rounded-xl bg-slate-950 px-4 py-3 text-white">
                            <span class="text-xs text-slate-300">Thời gian còn lại</span>
                            <strong class="mt-1 block font-mono text-3xl font-bold tabular-nums tracking-tight">{{ countdown }}</strong>
                        </div>
                        <div class="mt-4 flex gap-3 rounded-xl bg-blue-50 p-3 text-xs leading-5 text-blue-950"><ShieldCheck class="mt-0.5 size-4 shrink-0 text-blue-600" /><span>Thiếu nhi phải đăng nhập đúng tài khoản và thuộc lớp này. Mỗi em chỉ được ghi nhận một lần.</span></div>
                        <AButton size="large" class="mt-4 min-h-11 active:scale-[.96] transition-transform" @click="downloadQr"><template #icon><Download class="size-4" /></template>Tải ảnh QR</AButton>
                    </div>
                </div>
                <div v-else class="grid min-h-[24rem] place-items-center px-5 text-center">
                    <div class="max-w-sm"><span class="mx-auto grid size-16 place-items-center rounded-2xl bg-slate-100 text-slate-400"><QrCode class="size-8" /></span><h3 class="mt-4 text-base font-bold text-blue-950">Chưa có mã QR</h3><p class="mt-1 text-pretty text-sm leading-6 text-slate-500">Điền thông tin buổi học rồi chọn “Tạo mã QR”. Mã sẽ xuất hiện tại đây để trình chiếu cho cả lớp.</p></div>
                </div>
                <div v-if="loadingQr" class="absolute inset-0 grid place-items-center bg-white/70"><RefreshCw class="size-6 animate-spin text-blue-600" /></div>
            </ACard>
        </div>

        <AAlert type="info" show-icon message="Mẹo sử dụng" description="Có thể trình chiếu mã QR trên màn hình lớp. Khi đồng hồ về 00:00, mọi lượt quét mới sẽ bị từ chối." />
    </section>
</template>

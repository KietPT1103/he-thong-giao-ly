<script setup lang="ts">
import { onBeforeUnmount, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AInput from "ant-design-vue/es/input";
import ASpin from "ant-design-vue/es/spin";
import ATag from "ant-design-vue/es/tag";
import { Camera, CameraOff, CheckCircle2, Clock3, Keyboard, QrCode, ScanLine, ShieldCheck } from "lucide-vue-next";
import type { IScannerControls } from "@zxing/browser";
import { checkInAttendanceQr, type AttendanceQrCheckInResult } from "../api/qr";

const video = ref<HTMLVideoElement | null>(null);
const controls = ref<IScannerControls | null>(null);
const scanning = ref(false);
const startingCamera = ref(false);
const processing = ref(false);
const manualToken = ref("");
const errorMessage = ref("");
const result = ref<AttendanceQrCheckInResult | null>(null);
const resultMessage = ref("");
let lastToken = "";
let lastTokenAt = 0;

const apiMessage = (error: unknown, fallback: string) =>
    (error as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;

function formatDateTime(value: string) {
    return new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}

async function submitToken(token: string) {
    const normalized = token.trim();
    if (!normalized || processing.value) return;
    const now = Date.now();
    if (normalized === lastToken && now - lastTokenAt < 1800) return;
    lastToken = normalized;
    lastTokenAt = now;
    processing.value = true;
    errorMessage.value = "";
    try {
        const response = await checkInAttendanceQr(normalized);
        result.value = response.data.data;
        resultMessage.value = response.data.message;
        manualToken.value = "";
        stopCamera();
        navigator.vibrate?.(result.value.was_duplicate ? [80, 60, 80] : 100);
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể điểm danh bằng mã QR này.");
        navigator.vibrate?.([120, 80, 120]);
    } finally {
        processing.value = false;
    }
}

async function startCamera() {
    if (!video.value || startingCamera.value) return;
    errorMessage.value = "";
    result.value = null;
    startingCamera.value = true;
    try {
        const { BrowserQRCodeReader } = await import("@zxing/browser");
        const reader = new BrowserQRCodeReader(undefined, { delayBetweenScanAttempts: 200, delayBetweenScanSuccess: 900 });
        controls.value = await reader.decodeFromConstraints(
            { audio: false, video: { facingMode: { ideal: "environment" } } },
            video.value,
            (scanResult) => { if (scanResult) void submitToken(scanResult.getText()); },
        );
        scanning.value = true;
    } catch (error) {
        errorMessage.value = error instanceof DOMException && error.name === "NotAllowedError"
            ? "Trình duyệt chưa được cấp quyền camera. Hãy cho phép camera trong cài đặt của trang."
            : "Không thể mở camera. Bạn có thể nhập mã thủ công bên dưới.";
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

function scanAnother() {
    result.value = null;
    resultMessage.value = "";
    errorMessage.value = "";
}

onBeforeUnmount(stopCamera);
</script>

<template>
    <section class="mx-auto w-full max-w-4xl">
        <div class="mb-5">
            <h2 class="m-0 text-balance text-xl font-bold tracking-tight text-blue-950 sm:text-2xl">Quét QR điểm danh</h2>
            <p class="mt-1.5 text-pretty text-sm leading-6 text-slate-500">Quét mã QR do giáo lý viên hiển thị. Hệ thống sẽ tự xác nhận đúng lớp và buổi học.</p>
        </div>

        <AAlert v-if="errorMessage" type="error" show-icon closable :message="errorMessage" class="mb-4" @close="errorMessage=''" />

        <ACard v-if="result" :bordered="false" class="overflow-hidden rounded-2xl shadow-sm">
            <div class="mx-auto max-w-xl py-6 text-center sm:py-10">
                <span class="mx-auto grid size-16 place-items-center rounded-2xl" :class="result.was_duplicate ? 'bg-amber-50 text-amber-600' : 'bg-emerald-50 text-emerald-600'">
                    <Clock3 v-if="result.was_duplicate" class="size-8" />
                    <CheckCircle2 v-else class="size-8" />
                </span>
                <ATag :color="result.was_duplicate ? 'orange' : 'green'" class="mt-4">{{ result.was_duplicate ? 'Đã điểm danh trước đó' : 'Điểm danh thành công' }}</ATag>
                <h3 class="mt-3 text-balance text-xl font-bold text-blue-950">{{ result.session.class.name }}</h3>
                <p class="mt-1 text-sm text-slate-500">{{ result.session.class.code }} · {{ formatDateTime(result.session.held_at) }}</p>
                <div class="mx-auto mt-5 grid max-w-sm grid-cols-2 gap-3 text-left">
                    <div class="rounded-xl bg-slate-50 p-3"><span class="block text-xs text-slate-500">Trạng thái</span><b class="mt-1 block text-sm text-blue-950">{{ result.attendance.status === 'late' ? 'Đi trễ' : 'Có mặt' }}</b></div>
                    <div class="rounded-xl bg-slate-50 p-3"><span class="block text-xs text-slate-500">Ghi nhận lúc</span><b class="mt-1 block text-sm tabular-nums text-blue-950">{{ new Date(result.checked_in_at).toLocaleTimeString('vi-VN') }}</b></div>
                </div>
                <p class="mt-5 text-pretty text-sm leading-6 text-slate-600">{{ resultMessage }}</p>
                <AButton size="large" class="mt-3 min-h-11 active:scale-[.96] transition-transform" @click="scanAnother"><template #icon><ScanLine class="size-4" /></template>Quét mã khác</AButton>
            </div>
        </ACard>

        <div v-else class="grid gap-4 lg:grid-cols-[minmax(0,1.25fr)_minmax(18rem,.75fr)]">
            <ACard :bordered="false" class="overflow-hidden rounded-2xl shadow-sm">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div><h3 class="m-0 text-base font-bold text-blue-950">Camera quét mã</h3><p class="mt-1 text-xs text-slate-500">Giữ mã QR nằm trọn trong khung hình.</p></div>
                    <AButton v-if="!scanning" type="primary" size="large" class="min-h-11 active:scale-[.96] transition-transform" :loading="startingCamera" @click="startCamera"><template #icon><Camera class="size-4" /></template>Mở camera</AButton>
                    <AButton v-else danger size="large" class="min-h-11 active:scale-[.96] transition-transform" @click="stopCamera"><template #icon><CameraOff class="size-4" /></template>Tắt camera</AButton>
                </div>
                <div class="relative aspect-[4/3] overflow-hidden rounded-xl bg-slate-950 sm:aspect-video">
                    <video ref="video" muted playsinline class="size-full object-cover" />
                    <div v-if="!scanning" class="absolute inset-0 grid place-items-center p-6 text-center text-slate-300"><div><QrCode class="mx-auto mb-3 size-10 text-slate-500" /><p class="m-0 text-sm">Nhấn “Mở camera” và hướng máy về mã QR của giáo lý viên.</p></div></div>
                    <div v-else class="pointer-events-none absolute inset-0 grid place-items-center bg-slate-950/10"><div class="scanner-frame relative aspect-square w-[min(65%,18rem)] rounded-2xl border border-white/30"><span class="scan-line absolute inset-x-3 top-3 h-0.5 bg-cyan-300 shadow-[0_0_12px_#67e8f9]" /></div></div>
                    <div v-if="processing" class="absolute inset-0 grid place-items-center bg-slate-950/55"><ASpin size="large" /></div>
                </div>
            </ACard>

            <div class="grid content-start gap-4">
                <ACard :bordered="false" class="rounded-2xl shadow-sm">
                    <div class="mb-3 flex items-center gap-2 text-sm font-bold text-blue-950"><Keyboard class="size-4 text-blue-600" />Nhập mã thủ công</div>
                    <p class="mb-3 text-xs leading-5 text-slate-500">Dùng khi thiết bị không có camera hoặc camera không khả dụng.</p>
                    <AInput v-model:value="manualToken" size="large" placeholder="Dán chuỗi mã QR" :disabled="processing" @press-enter="submitToken(manualToken)" />
                    <AButton block type="primary" size="large" class="mt-3 min-h-11 active:scale-[.96] transition-transform" :disabled="!manualToken.trim()" :loading="processing" @click="submitToken(manualToken)"><template #icon><ScanLine class="size-4" /></template>Điểm danh</AButton>
                </ACard>
                <div class="flex gap-3 rounded-2xl bg-blue-50 p-4 text-sm leading-6 text-blue-950"><ShieldCheck class="mt-0.5 size-5 shrink-0 text-blue-600" /><p class="m-0 text-pretty">Mỗi tài khoản chỉ được điểm danh một lần cho mỗi buổi học. Không chia sẻ tài khoản cho người khác.</p></div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.scanner-frame::before,.scanner-frame::after{content:"";position:absolute;inset:-2px;border:3px solid transparent;border-radius:1rem}.scanner-frame::before{border-top-color:#fff;border-left-color:#fff}.scanner-frame::after{border-right-color:#fff;border-bottom-color:#fff}.scan-line{animation:scan 2.2s cubic-bezier(.4,0,.2,1) infinite}@keyframes scan{0%,100%{top:.75rem;opacity:.55}50%{top:calc(100% - .85rem);opacity:1}}@media(prefers-reduced-motion:reduce){.scan-line{animation:none;top:50%}}
</style>

<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AInput from "ant-design-vue/es/input";
import APopconfirm from "ant-design-vue/es/popconfirm";
import ASpin from "ant-design-vue/es/spin";
import ATag from "ant-design-vue/es/tag";
import { Camera, CameraOff, CheckCircle2, Clock3, Keyboard, QrCode, ScanLine, ShieldCheck, Smartphone } from "lucide-vue-next";
import type { IScannerControls } from "@zxing/browser";
import { toast } from "vue-sonner";
import { getCsrfCookie } from "../api/auth";
import { activateChildDevice, getChildDevice, revokeChildDevice, type ChildDeviceStatus } from "../api/childDevice";
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
const deviceStatus = ref<ChildDeviceStatus | null>(null);
const deviceLoading = ref(true);
const deviceSaving = ref(false);
const browserCameraAvailable = window.isSecureContext && Boolean(navigator.mediaDevices?.getUserMedia);
let lastToken = "";
let lastTokenAt = 0;

const apiMessage = (error: unknown, fallback: string) => {
    const response = (error as { response?: { status?: number; data?: { message?: string } } }).response;
    return response?.status && response.status >= 500 ? fallback : response?.data?.message ?? fallback;
};

function formatDateTime(value: string) {
    return new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}

function normalizeToken(value: string) {
    const normalized = value.trim();
    try {
        const url = new URL(normalized);
        if (url.origin === window.location.origin && url.pathname === "/attendance/scan") {
            return url.searchParams.get("token")?.trim() || normalized;
        }
    } catch {
        // Raw signed tokens are still supported for manual fallback.
    }

    return normalized;
}

async function loadDeviceStatus() {
    deviceLoading.value = true;
    try {
        deviceStatus.value = (await getChildDevice()).data.data;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể kiểm tra trạng thái điện thoại.");
    } finally {
        deviceLoading.value = false;
    }
}

async function activateDevice() {
    if (deviceSaving.value) return;
    deviceSaving.value = true;
    errorMessage.value = "";
    try {
        deviceStatus.value = (await activateChildDevice()).data.data;
        toast.success("Điện thoại này đã sẵn sàng để điểm danh.");
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể kích hoạt điện thoại.");
    } finally {
        deviceSaving.value = false;
    }
}

async function revokeDevice() {
    if (deviceSaving.value) return;
    deviceSaving.value = true;
    errorMessage.value = "";
    try {
        deviceStatus.value = (await revokeChildDevice()).data.data;
        stopCamera();
        toast.success("Đã thu hồi quyền điểm danh của điện thoại.");
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể thu hồi điện thoại.");
    } finally {
        deviceSaving.value = false;
    }
}

async function submitToken(token: string) {
    const normalized = normalizeToken(token);
    if (!normalized || processing.value) return;
    const now = Date.now();
    if (normalized === lastToken && now - lastTokenAt < 1800) return;
    lastToken = normalized;
    lastTokenAt = now;
    processing.value = true;
    errorMessage.value = "";
    try {
        await getCsrfCookie();
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
    if (!browserCameraAvailable) {
        errorMessage.value = "Camera trong trình duyệt yêu cầu HTTPS. Hãy dùng ứng dụng Camera mặc định của điện thoại để quét QR giáo viên.";
        return;
    }
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
onMounted(loadDeviceStatus);
</script>

<template>
    <section class="child-qr-page mx-auto w-full">
        <AAlert v-if="errorMessage" type="error" show-icon closable :message="errorMessage" @close="errorMessage=''" />

        <ACard :bordered="false" class="scan-card device-card overflow-hidden">
            <div class="device-status-row">
                <span class="device-status-icon" :class="deviceStatus?.is_current_device ? 'is-active' : ''"><Smartphone aria-hidden="true" /></span>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="m-0 text-sm font-bold text-blue-950">Điện thoại điểm danh</h3>
                        <ATag v-if="deviceLoading" color="processing">Đang kiểm tra</ATag>
                        <ATag v-else :color="deviceStatus?.is_current_device ? 'green' : deviceStatus?.is_active ? 'orange' : 'default'">{{ deviceStatus?.is_current_device ? "Đã kích hoạt" : deviceStatus?.is_active ? "Đang dùng điện thoại khác" : "Chưa kích hoạt" }}</ATag>
                    </div>
                    <p>{{ deviceStatus?.is_current_device ? "Bạn có thể quét QR trực tiếp bằng camera điện thoại mà không cần đăng nhập lại." : deviceStatus?.is_active ? "Kích hoạt điện thoại này sẽ thu hồi quyền của điện thoại cũ." : "Kích hoạt một lần để những buổi sau chỉ cần quét QR của giáo lý viên." }}</p>
                </div>
                <div v-if="!deviceLoading" class="device-actions">
                    <APopconfirm v-if="deviceStatus?.is_current_device" title="Thu hồi điện thoại này?" description="Bạn sẽ phải đăng nhập và kích hoạt lại trước lần điểm danh sau." ok-text="Thu hồi" cancel-text="Hủy" @confirm="revokeDevice">
                        <AButton danger :loading="deviceSaving">Thu hồi</AButton>
                    </APopconfirm>
                    <AButton v-else type="primary" :loading="deviceSaving" @click="activateDevice"><template #icon><Smartphone aria-hidden="true" class="size-4" /></template>Kích hoạt điện thoại này</AButton>
                </div>
            </div>
        </ACard>

        <ACard v-if="result" :bordered="false" class="scan-card overflow-hidden">
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

        <div v-else class="child-scan-layout">
            <ACard :bordered="false" class="scan-card scan-camera-card overflow-hidden">
                <div class="scan-camera-header mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div><h3 class="m-0 text-base font-bold text-blue-950">Camera quét mã</h3><p class="mt-1 text-xs text-slate-500">Giữ mã QR nằm trọn trong khung hình.</p></div>
                    <AButton v-if="!scanning" type="primary" size="large" class="min-h-11 active:scale-[.96] transition-transform" :disabled="!deviceStatus?.is_current_device || !browserCameraAvailable" :loading="startingCamera" @click="startCamera"><template #icon><CameraOff v-if="!browserCameraAvailable" class="size-4" /><Camera v-else class="size-4" /></template>{{ browserCameraAvailable ? "Mở camera" : "Camera cần HTTPS" }}</AButton>
                    <AButton v-else danger size="large" class="min-h-11 active:scale-[.96] transition-transform" @click="stopCamera"><template #icon><CameraOff class="size-4" /></template>Tắt camera</AButton>
                </div>
                <div class="scanner-surface relative overflow-hidden rounded-xl bg-slate-950">
                    <video ref="video" muted playsinline class="size-full object-cover" />
                    <div v-if="!scanning" class="absolute inset-0 grid place-items-center p-6 text-center text-slate-300"><div><QrCode class="mx-auto mb-3 size-10 text-slate-500" /><p class="m-0 text-sm">{{ browserCameraAvailable ? "Nhấn “Mở camera” và hướng máy về mã QR của giáo lý viên." : "Dùng ứng dụng Camera mặc định của điện thoại để quét QR giáo viên. Camera trong website sẽ hoạt động khi dùng HTTPS." }}</p></div></div>
                    <div v-else class="pointer-events-none absolute inset-0 grid place-items-center bg-slate-950/10"><div class="scanner-frame relative aspect-square w-[min(65%,18rem)] rounded-2xl border border-white/30"><span class="scan-line absolute inset-x-3 top-3 h-0.5 bg-cyan-300 shadow-[0_0_12px_#67e8f9]" /></div></div>
                    <div v-if="processing" class="absolute inset-0 grid place-items-center bg-slate-950/55"><ASpin size="large" /></div>
                </div>
            </ACard>

            <div class="scan-side-panel">
                <ACard :bordered="false" class="scan-card scan-manual-card">
                    <div class="mb-3 flex items-center gap-2 text-sm font-bold text-blue-950"><Keyboard class="size-4 text-blue-600" />Nhập mã thủ công</div>
                    <p class="mb-3 text-xs leading-5 text-slate-500">Dùng khi thiết bị không có camera hoặc camera không khả dụng.</p>
                    <div class="scan-manual-form">
                        <AInput v-model:value="manualToken" size="large" placeholder="Dán đường dẫn hoặc mã QR" :disabled="processing || !deviceStatus?.is_current_device" @press-enter="submitToken(manualToken)" />
                        <AButton block type="primary" size="large" class="min-h-11 active:scale-[.96] transition-transform" :disabled="!manualToken.trim() || !deviceStatus?.is_current_device" :loading="processing" @click="submitToken(manualToken)"><template #icon><ScanLine class="size-4" /></template>Điểm danh</AButton>
                    </div>
                </ACard>
                <div class="scan-security-note"><ShieldCheck class="mt-0.5 size-5 shrink-0 text-blue-600" /><p class="m-0 text-pretty">Mỗi tài khoản chỉ được điểm danh một lần cho mỗi buổi học. Không chia sẻ tài khoản cho người khác.</p></div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.child-qr-page{max-width:72rem}.child-scan-layout,.scan-side-panel{display:grid;gap:1rem}.scan-card{border-radius:1rem;box-shadow:0 1px 2px rgba(15,23,42,.05),0 10px 28px rgba(15,23,42,.04)}.scan-card :deep(.ant-card-body){padding:1.25rem}.device-status-row{display:flex;align-items:center;gap:1rem}.device-status-icon{display:grid;width:3rem;height:3rem;flex:none;place-items:center;border-radius:.75rem;background:#f1f5f9;color:#64748b}.device-status-icon.is-active{background:#ecfdf5;color:#059669}.device-status-icon svg{width:1.25rem;height:1.25rem}.device-status-row p{max-width:62ch;margin:.35rem 0 0;color:#64748b;font-size:.75rem;line-height:1.6;text-wrap:pretty}.device-actions{display:flex;flex:none;align-items:center}.device-actions :deep(.ant-btn),.scan-camera-header :deep(.ant-btn){min-height:2.75rem}.scanner-surface{aspect-ratio:4/3}.scan-side-panel{align-content:start}.scan-security-note{display:flex;gap:.75rem;border-radius:1rem;background:#eff6ff;padding:1rem;color:#1e3a8a;font-size:.8rem;line-height:1.65}.scanner-frame::before,.scanner-frame::after{content:"";position:absolute;inset:-2px;border:3px solid transparent;border-radius:1rem}.scanner-frame::before{border-top-color:#fff;border-left-color:#fff}.scanner-frame::after{border-right-color:#fff;border-bottom-color:#fff}.scan-line{animation:scan 2.2s cubic-bezier(.4,0,.2,1) infinite}@keyframes scan{0%,100%{top:.75rem;opacity:.55}50%{top:calc(100% - .85rem);opacity:1}}@media(min-width:640px){.scanner-surface{aspect-ratio:16/9}}@media(min-width:768px) and (max-width:1179px){.scan-side-panel{grid-template-columns:repeat(2,minmax(0,1fr));align-items:start}}@media(min-width:1180px){.child-scan-layout{grid-template-columns:minmax(0,1.55fr) minmax(20rem,.75fr);align-items:start}.scan-camera-card{min-width:0}.scan-side-panel{min-width:0}}@media(min-width:1600px){.child-scan-layout{grid-template-columns:minmax(0,1.7fr) 22rem}}@media(max-width:639px){.scan-card :deep(.ant-card-body){padding:1rem}.device-status-row{align-items:flex-start;flex-wrap:wrap}.device-actions{width:100%;padding-left:4rem}.device-actions :deep(.ant-btn),.scan-camera-header :deep(.ant-btn){width:100%}.scan-camera-header>div{width:100%}.scan-security-note{font-size:.75rem}}@media(max-width:419px){.device-actions{padding-left:0}}@media(pointer:coarse){.device-actions :deep(.ant-btn),.scan-camera-header :deep(.ant-btn),.scan-manual-card :deep(.ant-btn){min-height:2.75rem}}@media(prefers-reduced-motion:reduce){.scan-line{animation:none;top:50%}}
.scan-manual-form{display:grid;gap:.75rem}
.child-qr-page{display:grid;gap:1rem}@media(min-width:768px){.child-qr-page{gap:1.25rem}}@media(min-width:1600px){.child-qr-page{gap:1.5rem}.child-scan-layout,.scan-side-panel{gap:1.25rem}}
</style>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import AButton from "ant-design-vue/es/button";
import ASpin from "ant-design-vue/es/spin";
import ATag from "ant-design-vue/es/tag";
import { CheckCircle2, Clock3, LogIn, QrCode, RefreshCw, ShieldCheck, Smartphone } from "lucide-vue-next";
import { getCsrfCookie } from "../api/auth";
import { activateChildDevice } from "../api/childDevice";
import { checkInAttendanceQr, type AttendanceQrCheckInResult } from "../api/qr";
import { useAuthStore } from "../stores/authStore";

type Phase = "checking" | "activation" | "success" | "error";

const route = useRoute();
const auth = useAuthStore();
const phase = ref<Phase>("checking");
const activating = ref(false);
const result = ref<AttendanceQrCheckInResult | null>(null);
const resultMessage = ref("");
const errorMessage = ref("");
const errorCode = ref("");
const token = computed(() => typeof route.query.token === "string" ? route.query.token.trim() : "");
const loginTarget = computed(() => ({ path: "/login", query: { redirect: route.fullPath } }));
const isChildAccount = computed(() => auth.hasRole("child"));

function formatDateTime(value: string) {
    return new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}

function errorDetails(error: unknown) {
    const response = (error as { response?: { status?: number; data?: { code?: string; message?: string } } }).response;
    if (response?.status && response.status >= 500) {
        return { code: "SERVER_ERROR", message: "Hệ thống đang gặp sự cố. Vui lòng thử lại sau." };
    }
    const data = response?.data;
    return {
        code: data?.code ?? "CHECK_IN_FAILED",
        message: data?.message ?? "Không thể điểm danh bằng mã QR này.",
    };
}

async function checkIn() {
    if (!token.value) {
        phase.value = "error";
        errorCode.value = "INVALID_QR_LINK";
        errorMessage.value = "Đường dẫn QR không chứa mã điểm danh hợp lệ.";
        return;
    }

    phase.value = "checking";
    errorMessage.value = "";
    try {
        await getCsrfCookie();
        const response = await checkInAttendanceQr(token.value);
        result.value = response.data.data;
        resultMessage.value = response.data.message;
        phase.value = "success";
        navigator.vibrate?.(result.value.was_duplicate ? [80, 60, 80] : 100);
    } catch (error) {
        const details = errorDetails(error);
        errorCode.value = details.code;
        errorMessage.value = details.message;
        phase.value = details.code === "DEVICE_ACTIVATION_REQUIRED" ? "activation" : "error";
    }
}

async function activateAndCheckIn() {
    if (!isChildAccount.value || activating.value) return;
    activating.value = true;
    errorMessage.value = "";
    try {
        await activateChildDevice();
        await checkIn();
    } catch (error) {
        const details = errorDetails(error);
        errorCode.value = details.code;
        errorMessage.value = details.message;
        phase.value = "activation";
    } finally {
        activating.value = false;
    }
}

onMounted(checkIn);
</script>

<template>
    <main class="quick-attendance-page">
        <header class="quick-brand">
            <img :src="'/favicon-htgl.png'" alt="">
            <div><b>Hành Trang Đức Tin</b><span>Điểm danh lớp giáo lý</span></div>
        </header>

        <section class="quick-panel" aria-live="polite">
            <template v-if="phase === 'checking'">
                <span class="quick-icon is-processing"><QrCode aria-hidden="true" /></span>
                <ASpin size="large" />
                <h1>Đang xác minh mã QR</h1>
                <p>Hệ thống đang kiểm tra thiết bị, lớp học và thời hạn của mã.</p>
            </template>

            <template v-else-if="phase === 'activation'">
                <span class="quick-icon is-warning"><Smartphone aria-hidden="true" /></span>
                <h1>Điện thoại chưa được kích hoạt</h1>
                <p v-if="!auth.isAuthenticated">Đăng nhập tài khoản thiếu nhi một lần để liên kết điện thoại này. Những buổi sau bạn chỉ cần quét QR.</p>
                <p v-else-if="!isChildAccount">Phiên hiện tại không phải tài khoản thiếu nhi. Hãy đăng nhập đúng tài khoản cần điểm danh.</p>
                <p v-else>Xác nhận liên kết điện thoại này với hồ sơ thiếu nhi đang đăng nhập.</p>
                <p v-if="errorMessage" class="quick-inline-error">{{ errorMessage }}</p>
                <RouterLink v-if="!auth.isAuthenticated" :to="loginTarget" class="quick-primary-link"><LogIn aria-hidden="true" />Đăng nhập để kích hoạt</RouterLink>
                <AButton v-else-if="isChildAccount" type="primary" size="large" :loading="activating" class="quick-action" @click="activateAndCheckIn"><template #icon><Smartphone aria-hidden="true" class="size-4" /></template>Kích hoạt và điểm danh</AButton>
                <RouterLink v-else to="/dashboard" class="quick-secondary-link">Về trang tài khoản</RouterLink>
            </template>

            <template v-else-if="phase === 'success' && result">
                <span class="quick-icon" :class="result.was_duplicate ? 'is-duplicate' : 'is-success'">
                    <Clock3 v-if="result.was_duplicate" aria-hidden="true" />
                    <CheckCircle2 v-else aria-hidden="true" />
                </span>
                <ATag :color="result.was_duplicate ? 'orange' : 'green'">{{ result.was_duplicate ? "Đã điểm danh trước đó" : "Điểm danh thành công" }}</ATag>
                <h1>{{ result.session.class.name }}</h1>
                <p>{{ result.session.class.code }} · {{ formatDateTime(result.session.held_at) }}</p>
                <dl class="quick-result-grid">
                    <div><dt>Trạng thái</dt><dd>{{ result.attendance.status === "late" ? "Đi trễ" : "Có mặt" }}</dd></div>
                    <div><dt>Ghi nhận lúc</dt><dd>{{ new Date(result.checked_in_at).toLocaleTimeString("vi-VN") }}</dd></div>
                </dl>
                <p class="quick-message">{{ resultMessage }}</p>
                <div class="quick-security"><ShieldCheck aria-hidden="true" /><span>Thông tin đã được ghi nhận cho đúng thiết bị và đúng lớp.</span></div>
            </template>

            <template v-else>
                <span class="quick-icon is-error"><QrCode aria-hidden="true" /></span>
                <h1>Chưa thể điểm danh</h1>
                <p>{{ errorMessage }}</p>
                <AButton v-if="token" size="large" class="quick-action" @click="checkIn"><template #icon><RefreshCw aria-hidden="true" class="size-4" /></template>Thử lại</AButton>
                <small v-if="errorCode">Mã lỗi: {{ errorCode }}</small>
            </template>
        </section>
    </main>
</template>

<style scoped>
.quick-attendance-page{display:grid;min-height:100vh;min-height:100svh;place-items:center;background:#f8fafc;padding:1.25rem}.quick-brand{position:absolute;top:1.25rem;left:1.25rem;display:flex;align-items:center;gap:.65rem;color:#172554}.quick-brand img{width:2.25rem;height:2.25rem}.quick-brand div{display:flex;flex-direction:column}.quick-brand b{font-size:.78rem}.quick-brand span{margin-top:.1rem;color:#64748b;font-size:.65rem}.quick-panel{display:flex;width:min(100%,32rem);min-height:28rem;flex-direction:column;align-items:center;justify-content:center;border:1px solid #e2e8f0;border-radius:1rem;background:#fff;padding:2rem;text-align:center;box-shadow:0 1px 2px rgba(15,23,42,.04),0 16px 40px rgba(15,23,42,.06)}.quick-icon{display:grid;width:4rem;height:4rem;place-items:center;border-radius:1rem;background:#eff6ff;color:#2563eb}.quick-icon svg{width:2rem;height:2rem}.quick-icon.is-processing{margin-bottom:1rem}.quick-icon.is-warning,.quick-icon.is-duplicate{background:#fffbeb;color:#d97706}.quick-icon.is-success{background:#ecfdf5;color:#059669}.quick-icon.is-error{background:#fff1f2;color:#e11d48}.quick-panel>.ant-tag{margin:1rem 0 0}.quick-panel h1{margin:1rem 0 0;color:#172554;font-size:1.25rem;font-weight:700;line-height:1.35;text-wrap:balance}.quick-panel>p{max-width:26rem;margin:.5rem 0 0;color:#64748b;font-size:.82rem;line-height:1.7;text-wrap:pretty}.quick-panel>.quick-inline-error{color:#be123c;font-weight:600}.quick-action,.quick-primary-link{display:inline-flex;min-height:2.75rem;align-items:center;justify-content:center;gap:.5rem;margin-top:1.25rem;border-radius:.75rem;font-size:.8rem;font-weight:600}.quick-action{min-width:13rem}.quick-primary-link{background:#2563eb;padding:.7rem 1rem;color:#fff}.quick-primary-link svg{width:1rem;height:1rem}.quick-primary-link:hover{background:#1d4ed8;color:#fff}.quick-secondary-link{margin-top:1rem;color:#2563eb;font-size:.78rem;font-weight:600}.quick-result-grid{display:grid;width:100%;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;margin:1.25rem 0 0;overflow:hidden;border:1px solid #e2e8f0;border-radius:.75rem;background:#e2e8f0}.quick-result-grid>div{background:#f8fafc;padding:.85rem;text-align:left}.quick-result-grid dt{color:#64748b;font-size:.68rem}.quick-result-grid dd{margin:.25rem 0 0;color:#172554;font-size:.82rem;font-weight:700;font-variant-numeric:tabular-nums}.quick-message{color:#334155!important;font-weight:500}.quick-security{display:flex;align-items:flex-start;gap:.5rem;margin-top:1rem;color:#1e3a8a;font-size:.7rem;line-height:1.6;text-align:left}.quick-security svg{width:1rem;height:1rem;flex:none;margin-top:.1rem;color:#2563eb}.quick-panel small{margin-top:1rem;color:#94a3b8;font-size:.65rem}@media(max-width:639px){.quick-attendance-page{align-content:start;padding:5.5rem .75rem 1rem}.quick-brand{top:1rem;left:1rem}.quick-panel{min-height:calc(100svh - 7rem);padding:1.5rem 1rem}.quick-result-grid{grid-template-columns:1fr}.quick-action,.quick-primary-link{width:100%}}@media(prefers-reduced-motion:reduce){.quick-primary-link{transition:none}}
</style>

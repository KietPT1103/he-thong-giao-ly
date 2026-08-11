<script setup lang="ts">
import { computed, ref, watch } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import AModal from "ant-design-vue/es/modal";
import ASkeleton from "ant-design-vue/es/skeleton";
import { Download, QrCode, RefreshCw, ShieldCheck } from "lucide-vue-next";
import QrcodeVue from "qrcode.vue";
import { toast } from "vue-sonner";
import { getChildQr, rotateChildQr, type ChildQrPayload } from "../api/qr";
import type { AdminChild } from "../api/admin";
import { useAuthStore } from "../stores/authStore";
import AdminActionConfirmModal from "./AdminActionConfirmModal.vue";

const props = defineProps<{ open: boolean; child: AdminChild | null; canRotate: boolean }>();
const emit = defineEmits<{ close: [] }>();
const auth = useAuthStore();
const payload = ref<ChildQrPayload | null>(null);
const loading = ref(false);
const errorMessage = ref("");
const confirmOpen = ref(false);
const confirmError = ref("");
const rotating = ref(false);
const confirmedUntil = ref(0);
const qrContainer = ref<HTMLElement | null>(null);
const needsPassword = computed(() => Date.now() >= confirmedUntil.value);

const apiMessage = (error: unknown, fallback: string) =>
    (error as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;

async function load() {
    if (!props.open || !props.child) return;
    loading.value = true;
    errorMessage.value = "";
    payload.value = null;
    try {
        payload.value = (await getChildQr(props.child.id)).data.data;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải mã QR thiếu nhi.");
    } finally {
        loading.value = false;
    }
}

function downloadQr() {
    const canvas = qrContainer.value?.querySelector("canvas");
    if (!canvas || !payload.value) return;
    const link = document.createElement("a");
    link.download = `ma-qr-${payload.value.child.code}.png`;
    link.href = canvas.toDataURL("image/png");
    link.click();
}

async function confirmRotate(password: string) {
    if (!props.child || rotating.value) return;
    rotating.value = true;
    confirmError.value = "";
    try {
        if (needsPassword.value) {
            await auth.confirmPassword(password);
            confirmedUntil.value = Date.now() + 15 * 60 * 1000;
        }
        payload.value = (await rotateChildQr(props.child.id)).data.data;
        confirmOpen.value = false;
        toast.success("Đã thu hồi mã cũ và tạo mã QR mới.");
    } catch (error) {
        confirmError.value = apiMessage(error, "Không thể tạo lại mã QR.");
    } finally {
        rotating.value = false;
    }
}
function close() { if (!rotating.value) emit("close"); }
function closeConfirm() { if (!rotating.value) confirmOpen.value = false; }

watch(() => [props.open, props.child?.id], load);
</script>

<template>
    <AModal :open="open" :width="720" centered :footer="null" :closable="!rotating" :keyboard="!rotating" :mask-closable="!rotating" wrap-class-name="child-qr-modal" title="Mã QR điểm danh" @cancel="close">
        <ASkeleton v-if="loading" active :paragraph="{ rows: 5 }" />
        <AAlert v-else-if="errorMessage" type="error" show-icon :message="errorMessage" />
        <div v-else-if="payload" class="grid gap-6 py-1 sm:grid-cols-[minmax(0,1fr)_15rem] sm:items-center">
            <div class="order-2 sm:order-1">
                <span class="mb-4 grid size-10 place-items-center rounded-xl bg-blue-50 text-blue-600"><QrCode class="size-5" /></span>
                <h3 class="m-0 text-lg font-bold text-blue-950">{{ payload.child.full_name }}</h3>
                <p class="mt-1 text-sm text-slate-500">Mã thiếu nhi: {{ payload.child.code }}</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    <AButton type="primary" size="large" :disabled="rotating" @click="downloadQr"><template #icon><Download class="size-4" /></template>Tải ảnh QR</AButton>
                    <AButton v-if="canRotate" size="large" danger :loading="rotating" @click="confirmOpen=true"><template #icon><RefreshCw class="size-4" /></template>Tạo mã mới</AButton>
                </div>
                <div class="mt-5 flex gap-2.5 rounded-xl bg-slate-50 p-3 text-xs leading-5 text-slate-600"><ShieldCheck class="mt-0.5 size-4 shrink-0 text-blue-600" /><span>Tạo mã mới sẽ làm mã QR cũ mất hiệu lực ngay lập tức.</span></div>
            </div>
            <div ref="qrContainer" class="order-1 mx-auto grid w-fit place-items-center rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_10px_28px_rgba(15,23,42,0.08)] sm:order-2">
                <QrcodeVue :value="payload.token" :size="208" level="M" render-as="canvas" />
            </div>
        </div>
    </AModal>

    <AdminActionConfirmModal
        :open="confirmOpen"
        title="Thu hồi mã QR hiện tại?"
        description="Mã cũ sẽ ngừng hoạt động ngay. Hãy tải hoặc in lại mã mới cho thiếu nhi."
        confirm-text="Tạo mã QR mới"
        :target-name="child?.full_name"
        :target-email="child?.code"
        danger
        :require-password="needsPassword"
        :loading="rotating"
        :error-message="confirmError"
        @close="closeConfirm"
        @confirm="confirmRotate"
    />
</template>

<style scoped>
:global(.child-qr-modal .ant-modal-content){overflow:hidden;border:1px solid #e2e8f0;border-radius:1rem;box-shadow:0 24px 72px rgba(15,23,42,.2)}
@media(max-width:639px){:global(.child-qr-modal .ant-modal){max-width:calc(100vw - 1rem);margin:0 auto}:global(.child-qr-modal .ant-modal-body){max-height:calc(100dvh - 8rem);overflow-y:auto}}
</style>

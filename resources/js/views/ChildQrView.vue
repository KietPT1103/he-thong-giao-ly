<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import ASkeleton from "ant-design-vue/es/skeleton";
import { Download, Printer, QrCode, ShieldCheck } from "lucide-vue-next";
import QrcodeVue from "qrcode.vue";
import { getChildQr, type ChildQrPayload } from "../api/qr";
import { useAuthStore } from "../stores/authStore";

const auth = useAuthStore();
const payload = ref<ChildQrPayload | null>(null);
const loading = ref(true);
const errorMessage = ref("");
const qrContainer = ref<HTMLElement | null>(null);
const childId = computed(() => auth.user?.child_profile_id ?? null);

const apiMessage = (error: unknown, fallback: string) =>
    (error as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;

async function loadQr() {
    if (!childId.value) {
        errorMessage.value = "Tài khoản chưa được liên kết với hồ sơ thiếu nhi.";
        loading.value = false;
        return;
    }
    loading.value = true;
    errorMessage.value = "";
    try {
        payload.value = (await getChildQr(childId.value)).data.data;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải mã QR. Vui lòng thử lại.");
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

function printQr() {
    window.print();
}

onMounted(loadQr);
</script>

<template>
    <section class="mx-auto w-full max-w-3xl">
        <div class="mb-5">
            <h2 class="m-0 text-xl font-bold tracking-tight text-blue-950 sm:text-2xl">Mã QR điểm danh</h2>
            <p class="mt-1.5 text-sm leading-6 text-slate-500">Đưa mã này cho giáo lý viên quét khi vào lớp.</p>
        </div>

        <AAlert v-if="errorMessage" type="error" show-icon :message="errorMessage" class="mb-4" />

        <ACard :bordered="false" class="overflow-hidden rounded-2xl border border-slate-200 shadow-sm">
            <ASkeleton v-if="loading" active :paragraph="{ rows: 5 }" />
            <div v-else-if="payload" class="grid gap-8 md:grid-cols-[minmax(0,1fr)_17rem] md:items-center">
                <div class="order-2 md:order-1">
                    <span class="mb-4 grid size-11 place-items-center rounded-xl bg-blue-50 text-blue-600"><QrCode class="size-5" /></span>
                    <h3 class="m-0 text-lg font-bold text-blue-950">{{ payload.child.full_name }}</h3>
                    <p class="mt-1 text-sm font-medium text-slate-500">Mã thiếu nhi: {{ payload.child.code }}</p>
                    <div class="mt-6 flex flex-col gap-2.5 sm:flex-row print:hidden">
                        <AButton type="primary" size="large" @click="downloadQr"><template #icon><Download class="size-4" /></template>Tải ảnh QR</AButton>
                        <AButton size="large" @click="printQr"><template #icon><Printer class="size-4" /></template>In mã QR</AButton>
                    </div>
                    <div class="mt-6 flex gap-3 rounded-xl border border-blue-100 bg-blue-50/70 p-4 text-sm leading-6 text-blue-950">
                        <ShieldCheck class="mt-0.5 size-5 shrink-0 text-blue-600" />
                        <p class="m-0">Mã không chứa thông tin cá nhân. Không chia sẻ công khai hoặc đăng lên mạng xã hội.</p>
                    </div>
                </div>
                <div ref="qrContainer" class="order-1 mx-auto grid w-fit place-items-center rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_12px_34px_rgba(15,23,42,0.08)] md:order-2">
                    <QrcodeVue :value="payload.token" :size="224" level="M" render-as="canvas" />
                </div>
            </div>
        </ACard>
    </section>
</template>

<style scoped>
@media print {
    :global(#app-sidebar), :global(.app-shell-header) { display: none !important; }
    :global(.lg\:pl-70) { padding-left: 0 !important; }
    :global(.app-content) { max-width: none !important; padding: 0 !important; }
}
</style>

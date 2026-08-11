<script setup lang="ts">
import { onMounted, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AEmpty from "ant-design-vue/es/empty";
import AModal from "ant-design-vue/es/modal";
import ASpin from "ant-design-vue/es/spin";
import { Download, QrCode, ShieldCheck, UserRound } from "lucide-vue-next";
import QrcodeVue from "qrcode.vue";
import { getChildQr, getMyFamilyChildren, type ChildQrPayload, type FamilyChild } from "../api/qr";

const children = ref<FamilyChild[]>([]);
const loading = ref(true);
const errorMessage = ref("");
const selected = ref<FamilyChild | null>(null);
const payload = ref<ChildQrPayload | null>(null);
const qrLoading = ref(false);
const qrContainer = ref<HTMLElement | null>(null);

const apiMessage = (error: unknown, fallback: string) =>
    (error as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;

async function load() {
    loading.value = true;
    try {
        children.value = (await getMyFamilyChildren()).data.data;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải danh sách thiếu nhi.");
    } finally {
        loading.value = false;
    }
}

async function openQr(child: FamilyChild) {
    selected.value = child;
    payload.value = null;
    qrLoading.value = true;
    try {
        payload.value = (await getChildQr(child.id)).data.data;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải mã QR thiếu nhi.");
        selected.value = null;
    } finally {
        qrLoading.value = false;
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

onMounted(load);
</script>

<template>
    <section class="mx-auto w-full max-w-5xl">
        <div class="mb-5"><h2 class="m-0 text-xl font-bold tracking-tight text-blue-950 sm:text-2xl">Các con của tôi</h2><p class="mt-1.5 text-sm leading-6 text-slate-500">Xem và tải mã QR điểm danh của thiếu nhi đã liên kết.</p></div>
        <AAlert v-if="errorMessage" type="error" show-icon closable :message="errorMessage" class="mb-4" @close="errorMessage=''" />
        <div v-if="loading" class="grid min-h-56 place-items-center"><ASpin size="large" /></div>
        <AEmpty v-else-if="!children.length" description="Chưa có hồ sơ thiếu nhi được liên kết." />
        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <ACard v-for="child in children" :key="child.id" :bordered="false" class="rounded-2xl border border-slate-200 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                <div class="flex items-center gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600"><UserRound class="size-5" /></span><div class="min-w-0"><h3 class="m-0 truncate text-sm font-bold text-blue-950">{{ child.full_name }}</h3><p class="mt-1 truncate text-xs text-slate-500">{{ child.code }}<template v-if="child.saint_name"> · {{ child.saint_name }}</template></p></div></div>
                <AButton block type="primary" size="large" class="mt-5" @click="openQr(child)"><template #icon><QrCode class="size-4" /></template>Xem mã QR</AButton>
            </ACard>
        </div>
    </section>

    <AModal :open="Boolean(selected)" centered :width="520" :footer="null" title="Mã QR điểm danh" @cancel="selected=null">
        <div v-if="qrLoading" class="grid min-h-72 place-items-center"><ASpin size="large" /></div>
        <div v-else-if="payload" class="grid justify-items-center gap-4 py-2 text-center">
            <div ref="qrContainer" class="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_10px_28px_rgba(15,23,42,0.08)]"><QrcodeVue :value="payload.token" :size="224" level="M" render-as="canvas" /></div>
            <div><h3 class="m-0 text-base font-bold text-blue-950">{{ payload.child.full_name }}</h3><p class="mt-1 text-sm text-slate-500">Mã thiếu nhi: {{ payload.child.code }}</p></div>
            <AButton type="primary" size="large" @click="downloadQr"><template #icon><Download class="size-4" /></template>Tải ảnh QR</AButton>
            <div class="flex max-w-sm gap-2 rounded-xl bg-blue-50 p-3 text-left text-xs leading-5 text-blue-950"><ShieldCheck class="mt-0.5 size-4 shrink-0 text-blue-600" /><span>Không đăng mã QR lên mạng xã hội hoặc chia sẻ ngoài phạm vi gia đình.</span></div>
        </div>
    </AModal>
</template>

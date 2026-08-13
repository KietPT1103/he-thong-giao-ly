<script setup lang="ts">
import { onMounted, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import ACard from "ant-design-vue/es/card";
import AEmpty from "ant-design-vue/es/empty";
import ASpin from "ant-design-vue/es/spin";
import ATag from "ant-design-vue/es/tag";
import { ShieldCheck, UserRound } from "lucide-vue-next";
import { getMyFamilyChildren, type FamilyChild } from "../api/qr";

const children = ref<FamilyChild[]>([]);
const loading = ref(true);
const errorMessage = ref("");
const apiMessage = (error: unknown, fallback: string) =>
    (error as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;

onMounted(async () => {
    try {
        children.value = (await getMyFamilyChildren()).data.data;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải danh sách thiếu nhi.");
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <section class="mx-auto w-full max-w-5xl">
        <div class="mb-5"><h2 class="m-0 text-balance text-xl font-bold tracking-tight text-blue-950 sm:text-2xl">Các con của tôi</h2><p class="mt-1.5 text-pretty text-sm leading-6 text-slate-500">Theo dõi các hồ sơ thiếu nhi đã liên kết với gia đình.</p></div>
        <AAlert v-if="errorMessage" type="error" show-icon closable :message="errorMessage" class="mb-4" @close="errorMessage=''" />
        <div v-if="loading" class="grid min-h-56 place-items-center"><ASpin size="large" /></div>
        <AEmpty v-else-if="!children.length" description="Chưa có hồ sơ thiếu nhi được liên kết." />
        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <ACard v-for="child in children" :key="child.id" :bordered="false" class="rounded-2xl shadow-sm">
                <div class="flex items-center gap-3"><span class="grid size-11 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600"><UserRound class="size-5" /></span><div class="min-w-0 flex-1"><h3 class="m-0 truncate text-sm font-bold text-blue-950">{{ child.full_name }}</h3><p class="mt-1 truncate text-xs text-slate-500">{{ child.code }}<template v-if="child.saint_name"> · {{ child.saint_name }}</template></p></div><ATag :color="child.status === 'studying' ? 'green' : 'default'">{{ child.status === 'studying' ? 'Đang học' : 'Tạm nghỉ' }}</ATag></div>
            </ACard>
        </div>
        <div class="mt-4 flex gap-3 rounded-2xl bg-blue-50 p-4 text-sm leading-6 text-blue-950"><ShieldCheck class="mt-0.5 size-5 shrink-0 text-blue-600" /><p class="m-0 text-pretty">Mã QR điểm danh hiện do giáo lý viên tạo cho từng buổi học. Thiếu nhi đăng nhập tài khoản của mình để quét mã.</p></div>
    </section>
</template>

<script setup lang="ts">
import { ref, watch } from "vue";
import AButton from "ant-design-vue/es/button";
import AInputPassword from "ant-design-vue/es/input/Password";
import AModal from "ant-design-vue/es/modal";
import { ShieldCheck, TriangleAlert } from "lucide-vue-next";

const props = withDefaults(defineProps<{
    open: boolean;
    title: string;
    description: string;
    confirmText?: string;
    targetName?: string;
    targetEmail?: string;
    requirePassword?: boolean;
    danger?: boolean;
    loading?: boolean;
    errorMessage?: string;
}>(), {
    confirmText: "Xác nhận",
    targetName: "",
    targetEmail: "",
    requirePassword: false,
    danger: false,
    loading: false,
    errorMessage: "",
});

const emit = defineEmits<{
    close: [];
    confirm: [password: string];
}>();
const password = ref("");

watch(() => props.open, (open) => {
    if (open) password.value = "";
});

function close() {
    if (!props.loading) emit("close");
}

function confirm() {
    if (props.requirePassword && !password.value) return;
    emit("confirm", password.value);
}
</script>

<template>
    <AModal :open="open" :closable="!loading" :mask-closable="!loading" :keyboard="!loading" :footer="null" width="440px" wrap-class-name="admin-confirm-modal" centered @cancel="close">
        <div class="flex items-start gap-4 pt-2">
            <span class="grid size-12 shrink-0 place-items-center rounded-[14px]" :class="danger ? 'bg-rose-50 text-rose-600' : 'bg-blue-50 text-blue-600'">
                <TriangleAlert v-if="danger" aria-hidden="true" class="size-6 stroke-[1.75]" />
                <ShieldCheck v-else aria-hidden="true" class="size-6 stroke-[1.75]" />
            </span>
            <div class="min-w-0">
                <h2 class="m-0 text-balance text-[17px] font-bold leading-6 text-blue-950">{{ title }}</h2>
                <p class="mt-1.5 mb-0 text-pretty text-[13px] leading-5 text-slate-500">{{ description }}</p>
            </div>
        </div>

        <div v-if="targetName" class="mt-4 flex min-w-0 flex-col rounded-xl bg-slate-50 px-4 py-3">
            <strong class="truncate text-[13px] font-semibold text-blue-950">{{ targetName }}</strong>
            <span v-if="targetEmail" class="mt-0.5 truncate text-xs text-slate-500">{{ targetEmail }}</span>
        </div>

        <label v-if="requirePassword" class="mt-4 block">
            <span class="mb-2 block text-xs font-semibold text-slate-700">Mật khẩu quản trị</span>
            <AInputPassword
                v-model:value="password"
                size="large"
                autocomplete="current-password"
                placeholder="Nhập mật khẩu để tiếp tục"
                class="!h-11 !rounded-xl !border-slate-300 !px-3 !shadow-none transition-[border-color,box-shadow] duration-150 focus-within:!border-blue-500 focus-within:!shadow-[0_0_0_3px_rgba(37,99,235,0.12)] [&_.ant-input]:!h-auto [&_.ant-input]:!border-0 [&_.ant-input]:!py-0 [&_.ant-input]:text-sm [&_.ant-input-password-icon]:grid [&_.ant-input-password-icon]:size-6 [&_.ant-input-password-icon]:place-items-center [&_.ant-input-password-icon]:text-slate-400"
                @press-enter="confirm"
            />
        </label>

        <p v-if="errorMessage" class="mt-3 mb-0 rounded-[10px] bg-rose-50 px-3 py-2.5 text-xs leading-[1.5] text-rose-700" role="alert">{{ errorMessage }}</p>

        <div class="mt-5 grid grid-cols-2 gap-2.5 sm:flex sm:justify-end">
            <AButton size="large" class="w-full min-w-0 rounded-xl font-semibold sm:w-auto sm:min-w-28" :disabled="loading" @click="close">Hủy</AButton>
            <AButton size="large" class="w-full min-w-0 rounded-xl font-semibold sm:w-auto sm:min-w-40" :type="danger ? 'default' : 'primary'" :danger="danger" :loading="loading" :disabled="requirePassword && !password" @click="confirm">{{ confirmText }}</AButton>
        </div>
    </AModal>
</template>

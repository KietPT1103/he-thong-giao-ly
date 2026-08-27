<script setup lang="ts">
import { computed, ref, watch } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import { ArrowRightLeft, UserMinus } from "lucide-vue-next";
import type { Child } from "../types/api";
import type { TeacherEnrollmentAction } from "../api/teacher";

const props = withDefaults(defineProps<{
    open: boolean;
    child: Child | null;
    transferClasses: Array<{ id: number; name: string; code: string }>;
    loading?: boolean;
    errorMessage?: string;
}>(), { loading: false, errorMessage: "" });
const emit = defineEmits<{ close: []; submit: [payload: TeacherEnrollmentAction] }>();
const action = ref<"transfer" | "remove" | "stop">("transfer");
const targetClassId = ref<number>();
const actionOptions = computed(() => [
    { value: "transfer", label: "Chuyển sang lớp khác", disabled: !props.transferClasses.length },
    { value: "remove", label: "Gỡ khỏi lớp hiện tại" },
    { value: "stop", label: "Ghi nhận thôi học" },
]);
const description = computed(() => ({
    transfer: "Ghi danh hiện tại sẽ kết thúc và em được thêm vào lớp mới trong cùng niên khóa.",
    remove: "Em được gỡ khỏi lớp nhưng hồ sơ thiếu nhi vẫn được giữ nguyên trong hệ thống.",
    stop: "Chỉ ghi nhận kết thúc việc học tại lớp này; thông tin hồ sơ không bị sửa hoặc xóa.",
}[action.value]));
const canSubmit = computed(() => action.value !== "transfer" || Boolean(targetClassId.value));

watch(() => props.open, (open) => {
    if (!open) return;
    action.value = props.transferClasses.length ? "transfer" : "remove";
    targetClassId.value = undefined;
});
watch(() => props.transferClasses.length, (count) => {
    if (props.open && count > 0 && !targetClassId.value) action.value = "transfer";
});

function submit() {
    if (!canSubmit.value || props.loading) return;
    emit("submit", action.value === "transfer"
        ? { action: "transfer", target_class_id: targetClassId.value as number }
        : { action: action.value });
}
</script>

<template>
    <AModal :open="open" :footer="null" width="480px" centered :mask-closable="!loading" :closable="!loading" :keyboard="!loading" @cancel="emit('close')">
        <header class="action-modal-header">
            <span><ArrowRightLeft v-if="action === 'transfer'" aria-hidden="true" /><UserMinus v-else aria-hidden="true" /></span>
            <div><h2>Quản lý xếp lớp</h2><p v-if="child">{{ child.full_name }} · {{ child.code }}</p></div>
        </header>

        <label class="action-field"><span>Thao tác</span><ASelect v-model:value="action" size="large" :disabled="loading" :options="actionOptions" /></label>
        <label v-if="action === 'transfer'" class="action-field"><span>Lớp chuyển đến</span><ASelect v-model:value="targetClassId" size="large" show-search option-filter-prop="label" :disabled="loading" placeholder="Chọn lớp trong cùng niên khóa" :options="transferClasses.map(item => ({value:item.id,label:`${item.name} · ${item.code}`}))" /></label>
        <AAlert type="info" show-icon :message="description" />
        <p v-if="errorMessage" class="action-error" role="alert">{{ errorMessage }}</p>

        <footer class="action-modal-footer">
            <AButton size="large" :disabled="loading" @click="emit('close')">Hủy</AButton>
            <AButton size="large" :type="action === 'transfer' ? 'primary' : 'default'" :danger="action !== 'transfer'" :loading="loading" :disabled="!canSubmit" @click="submit">{{ action === "transfer" ? "Chuyển lớp" : action === "stop" ? "Xác nhận thôi học" : "Gỡ khỏi lớp" }}</AButton>
        </footer>
    </AModal>
</template>

<style scoped>
.action-modal-header{display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;padding-right:28px}.action-modal-header>span{display:grid;width:42px;height:42px;flex:none;place-items:center;border-radius:11px;background:#edf4ff;color:#185fce}.action-modal-header svg{width:19px;height:19px}.action-modal-header h2,.action-modal-header p{margin:0}.action-modal-header h2{color:#0b214d;font-size:16px;font-weight:750}.action-modal-header p{margin-top:3px;color:#64748b;font-size:12px}.action-field{display:grid;gap:7px;margin-bottom:14px}.action-field>span{color:#334155;font-size:12px;font-weight:650}.action-field :deep(.ant-select){width:100%}.action-field :deep(.ant-select-selector){border-radius:10px!important}.action-error{margin:12px 0 0;border-radius:10px;background:#fff1f2;padding:10px 12px;color:#be123c;font-size:12px;line-height:1.5}.action-modal-footer{display:flex;justify-content:flex-end;gap:10px;margin-top:20px}@media(max-width:479px){.action-modal-footer{display:grid;grid-template-columns:1fr 1fr}.action-modal-footer :deep(.ant-btn){width:100%}}
</style>

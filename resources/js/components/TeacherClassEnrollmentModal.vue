<script setup lang="ts">
import { ref, watch } from "vue";
import AButton from "ant-design-vue/es/button";
import AEmpty from "ant-design-vue/es/empty";
import AInput from "ant-design-vue/es/input";
import AModal from "ant-design-vue/es/modal";
import ASkeleton from "ant-design-vue/es/skeleton";
import ATag from "ant-design-vue/es/tag";
import { Search, UserPlus, Users } from "lucide-vue-next";
import type { EnrollmentCandidate } from "../types/api";
import UserAvatar from "./UserAvatar.vue";

const props = withDefaults(defineProps<{
    open: boolean;
    classId: number;
    className: string;
    children: EnrollmentCandidate[];
    loading?: boolean;
    savingChildId?: number | null;
}>(), {
    loading: false,
    savingChildId: null,
});
const emit = defineEmits<{
    close: [];
    search: [query: string];
    add: [childId: number];
}>();
const search = ref("");
let searchTimer: ReturnType<typeof setTimeout> | undefined;

watch(() => props.open, (open) => {
    if (!open) return;
    search.value = "";
    emit("search", "");
});
watch(search, (value) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => emit("search", value.trim()), 350);
});

function close() {
    if (props.savingChildId === null) emit("close");
}
</script>

<template>
    <AModal :open="open" :footer="null" width="680px" centered :mask-closable="savingChildId === null" :closable="savingChildId === null" :keyboard="savingChildId === null" @cancel="close">
        <header class="enrollment-modal-header">
            <span><Users aria-hidden="true" /></span>
            <div><h2>Thêm thiếu nhi vào lớp</h2><p>Chọn hồ sơ có sẵn trong giáo xứ để thêm vào {{ className }}.</p></div>
        </header>

        <AInput v-model:value="search" size="large" allow-clear aria-label="Tìm thiếu nhi để thêm vào lớp" placeholder="Tìm theo tên, tên thánh hoặc mã học viên">
            <template #prefix><Search class="size-4 text-slate-400" /></template>
        </AInput>

        <div class="candidate-list" :aria-busy="loading">
            <div v-if="loading" class="candidate-loading"><ASkeleton active :paragraph="{ rows: 4 }" /></div>
            <template v-else-if="children.length">
                <article v-for="child in children" :key="child.id" class="candidate-row">
                    <UserAvatar size="sm" :name="child.full_name" :avatar-url="child.avatar_url" />
                    <div class="candidate-copy"><b>{{ child.full_name }}</b><small>{{ child.code }}<template v-if="child.saint_name"> · {{ child.saint_name }}</template></small></div>
                    <ATag v-if="child.current_class" :color="child.current_class.id === classId ? 'success' : 'orange'">{{ child.current_class.id === classId ? "Đã trong lớp" : child.current_class.name }}</ATag>
                    <AButton v-if="!child.current_class" type="primary" :loading="savingChildId === child.id" :disabled="savingChildId !== null" @click="emit('add', child.id)"><template #icon><UserPlus class="size-4" /></template>Thêm vào lớp</AButton>
                </article>
            </template>
            <AEmpty v-else description="Không tìm thấy hồ sơ thiếu nhi phù hợp." class="py-8" />
        </div>

        <footer class="enrollment-modal-footer">
            <p>Không thấy hồ sơ cần tìm? Quản trị viên cần tạo hồ sơ trước khi xếp lớp.</p>
            <AButton :disabled="savingChildId !== null" @click="close">Đóng</AButton>
        </footer>
    </AModal>
</template>

<style scoped>
.enrollment-modal-header{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;padding-right:28px}.enrollment-modal-header>span{display:grid;width:42px;height:42px;flex:none;place-items:center;border-radius:11px;background:#edf4ff;color:#185fce}.enrollment-modal-header svg{width:19px;height:19px}.enrollment-modal-header h2,.enrollment-modal-header p{margin:0}.enrollment-modal-header h2{color:#0b214d;font-size:16px;font-weight:750}.enrollment-modal-header p{margin-top:3px;color:#64748b;font-size:12px;line-height:1.5}.candidate-list{min-height:250px;max-height:min(52vh,430px);margin-top:12px;overflow-y:auto;border-block:1px solid #e2e8f0}.candidate-loading{padding:16px 4px}.candidate-row{display:grid;grid-template-columns:auto minmax(0,1fr) auto auto;min-height:68px;align-items:center;gap:10px;padding:10px 4px;border-bottom:1px solid #edf1f6}.candidate-row:last-child{border-bottom:0}.candidate-copy{min-width:0}.candidate-copy b,.candidate-copy small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.candidate-copy b{color:#15284e;font-size:12px;font-weight:700}.candidate-copy small{margin-top:2px;color:#64748b;font-size:10px}.candidate-row :deep(.ant-tag){max-width:140px;overflow:hidden;margin:0;text-overflow:ellipsis}.enrollment-modal-footer{display:flex;align-items:center;justify-content:space-between;gap:16px;padding-top:14px}.enrollment-modal-footer p{max-width:48ch;margin:0;color:#64748b;font-size:11px;line-height:1.5}@media(max-width:639px){.candidate-row{grid-template-columns:auto minmax(0,1fr)}.candidate-row :deep(.ant-tag),.candidate-row :deep(.ant-btn){grid-column:2;justify-self:start}.candidate-row :deep(.ant-btn){width:100%}.enrollment-modal-footer{align-items:stretch;flex-direction:column}.enrollment-modal-footer :deep(.ant-btn){width:100%}}
</style>

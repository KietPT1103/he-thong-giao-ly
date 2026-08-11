<script setup lang="ts">
import { computed, ref, watch } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACheckbox from "ant-design-vue/es/checkbox";
import AEmpty from "ant-design-vue/es/empty";
import AInputSearch from "ant-design-vue/es/input/Search";
import AModal from "ant-design-vue/es/modal";
import ASpin from "ant-design-vue/es/spin";
import ATag from "ant-design-vue/es/tag";
import { Search, UserRoundPlus } from "lucide-vue-next";
import { getAdminDirectory, type AdminListItem, type Parish } from "../api/admin";

const props = withDefaults(defineProps<{open:boolean;parish:Parish|null;saving?:boolean}>(), { saving:false });
const emit = defineEmits<{close:[];submit:[teacherIds:number[]]}>();

const teachers = ref<AdminListItem[]>([]);
const selectedIds = ref<number[]>([]);
const search = ref("");
const loading = ref(false);
const error = ref("");
const currentIds = computed(() => new Set(props.parish?.teachers?.map((teacher) => teacher.id) ?? []));

const apiMessage = (cause: unknown, fallback: string) =>
    (cause as {response?:{data?:{message?:string}}}).response?.data?.message ?? fallback;

async function load() {
    if (loading.value || props.saving) return;
    loading.value = true;
    error.value = "";
    try {
        const response = await getAdminDirectory("teachers", {
            search:search.value || undefined,
            per_page:50,
        });
        teachers.value = response.data.data;
    } catch (cause) {
        error.value = apiMessage(cause, "Không thể tải danh sách giáo lý viên.");
    } finally {
        loading.value = false;
    }
}

function toggle(teacherId:number, checked:boolean) {
    if (props.saving) return;
    selectedIds.value = checked
        ? [...selectedIds.value, teacherId]
        : selectedIds.value.filter((id) => id !== teacherId);
}
function close() {
    if (!props.saving) emit("close");
}
function submit() {
    if (!props.saving && selectedIds.value.length) emit("submit", [...selectedIds.value]);
}

watch(() => props.open, (open) => {
    if (!open) return;
    selectedIds.value = [];
    search.value = "";
    void load();
});
</script>

<template>
    <AModal :open="open" :footer="null" width="680px" :mask-closable="false" :closable="!saving" :keyboard="!saving" centered @cancel="close">
        <template #title>
            <div class="flex items-center gap-3 pr-8">
                <span class="grid size-10 shrink-0 place-items-center rounded-[10px] bg-blue-50 text-blue-600"><UserRoundPlus class="size-5" /></span>
                <div class="min-w-0"><strong class="block truncate text-[15px] text-blue-950">Phân giáo lý viên</strong><span class="block truncate text-xs font-normal text-slate-500">{{ parish?.name }}</span></div>
            </div>
        </template>

        <AAlert v-if="error" type="error" show-icon :message="error" class="mb-3" />
        <AInputSearch v-model:value="search" allow-clear size="large" :disabled="saving" placeholder="Tìm theo tên hoặc email" @search="load">
            <template #enterButton><AButton type="primary" :loading="loading"><Search class="size-4" /></AButton></template>
        </AInputSearch>

        <ASpin :spinning="loading">
            <div v-if="teachers.length" class="teacher-picker" role="list">
                <label v-for="teacher in teachers" :key="teacher.id" class="teacher-option" :class="currentIds.has(teacher.id) ? 'is-current' : ''">
                    <ACheckbox :checked="currentIds.has(teacher.id) || selectedIds.includes(teacher.id)" :disabled="currentIds.has(teacher.id) || saving" @change="toggle(teacher.id, $event.target.checked)" />
                    <span class="teacher-option-avatar">{{ teacher.name.slice(0, 1).toUpperCase() }}</span>
                    <span class="min-w-0 flex-1"><b>{{ teacher.name }}</b><small>{{ teacher.secondary }}</small></span>
                    <span class="teacher-option-meta"><ATag v-if="currentIds.has(teacher.id)" color="success">Đang thuộc giáo xứ</ATag><ATag v-else>{{ teacher.details[0] }}</ATag><small>{{ teacher.code }}</small></span>
                </label>
            </div>
            <AEmpty v-else-if="!loading" description="Không tìm thấy giáo lý viên phù hợp." class="py-8" />
        </ASpin>

        <div class="mt-4 flex flex-col-reverse gap-2 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
            <span class="text-xs text-slate-500">Đã chọn {{ selectedIds.length }} giáo lý viên</span>
            <div class="grid grid-cols-2 gap-2 sm:flex">
                <AButton size="large" :disabled="saving" @click="close">Hủy</AButton>
                <AButton type="primary" size="large" :disabled="selectedIds.length === 0" :loading="saving" @click="submit">Tiếp tục</AButton>
            </div>
        </div>
    </AModal>
</template>

<style scoped>
.teacher-picker{max-height:min(52vh,28rem);margin-top:.75rem;overflow-y:auto;border-block:1px solid #e2e8f0}.teacher-option{display:grid;grid-template-columns:auto auto minmax(0,1fr) auto;align-items:center;gap:.75rem;min-height:4.25rem;padding:.7rem .25rem;border-bottom:1px solid #e2e8f0;cursor:pointer}.teacher-option:last-child{border-bottom:0}.teacher-option:hover:not(.is-current){background:#f8fafc}.teacher-option.is-current{cursor:default}.teacher-option-avatar{display:grid;width:2.25rem;height:2.25rem;place-items:center;border-radius:.5rem;background:#edf4ff;color:#185fce;font-size:.75rem;font-weight:700}.teacher-option b,.teacher-option small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.teacher-option b{color:#1e293b;font-size:.78rem}.teacher-option small{margin-top:.15rem;color:#64748b;font-size:.68rem}.teacher-option-meta{display:flex;max-width:12rem;flex-direction:column;align-items:flex-end}.teacher-option-meta :deep(.ant-tag){margin:0}@media(max-width:639px){.teacher-option{grid-template-columns:auto auto minmax(0,1fr)}.teacher-option-meta{grid-column:2/4;max-width:none;align-items:flex-start;padding-left:0}.teacher-picker{max-height:48vh}}
</style>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import ADrawer from "ant-design-vue/es/drawer";
import AEmpty from "ant-design-vue/es/empty";
import AInput from "ant-design-vue/es/input";
import APagination from "ant-design-vue/es/pagination";
import ASpin from "ant-design-vue/es/spin";
import ATable from "ant-design-vue/es/table";
import ATag from "ant-design-vue/es/tag";
import ATooltip from "ant-design-vue/es/tooltip";
import type { ColumnsType } from "ant-design-vue/es/table/interface";
import { Building2, Eye, Mail, Pencil, Phone, Plus, RefreshCw, Search, Trash2, UserRoundPlus, UsersRound } from "lucide-vue-next";
import { toast } from "vue-sonner";
import { assignParishTeachers, createParish, deleteParish, getParish, listParishes, updateParish, type AdminListMeta, type Parish, type ParishDependencyCounts, type ParishInput } from "../api/admin";
import AdminActionConfirmModal from "../components/AdminActionConfirmModal.vue";
import AdminParishFormModal from "../components/AdminParishFormModal.vue";
import AdminParishTeacherModal from "../components/AdminParishTeacherModal.vue";

const parishes = ref<Parish[]>([]);
const selected = ref<Parish | null>(null);
const meta = ref<AdminListMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const search = ref("");
const loading = ref(true);
const detailLoading = ref(false);
const listError = ref("");
const detailError = ref("");
const formOpen = ref(false);
const editing = ref<Parish|null>(null);
const saving = ref(false);
const discardOpen = ref(false);
const formErrors = ref<Partial<Record<keyof ParishInput, string>>>({});
const assignmentOpen = ref(false);
const assignmentConfirmOpen = ref(false);
const assignmentSaving = ref(false);
const pendingTeacherIds = ref<number[]>([]);
const assignmentError = ref("");
const deleteConfirmOpen = ref(false);
const deleteSaving = ref(false);
const deleteError = ref("");

const columns: ColumnsType<Parish> = [
    { title: "Giáo xứ", key: "parish", width: 280 },
    { title: "Liên hệ", key: "contact", responsive: ["md"] },
    { title: "Quy mô", key: "scale", width: 250, responsive: ["lg"] },
    { title: "", key: "action", width: 64, fixed: "right", align: "center" },
];

const dependencyItems = computed(() => selected.value ? [
    { label: "Giáo lý viên", value: selected.value.dependency_counts.teachers },
    { label: "Thiếu nhi", value: selected.value.dependency_counts.children },
    { label: "Niên khóa", value: selected.value.dependency_counts.academic_years },
    { label: "Khối giáo lý", value: selected.value.dependency_counts.levels },
    { label: "Phòng học", value: selected.value.dependency_counts.classrooms },
    { label: "Thông báo", value: selected.value.dependency_counts.announcements },
] : []);

const apiMessage = (error: unknown, fallback: string) =>
    (error as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;

async function load(page = 1) {
    loading.value = true;
    listError.value = "";
    try {
        const response = await listParishes({ search: search.value || undefined, page });
        parishes.value = response.data.data;
        meta.value = response.data.meta as unknown as AdminListMeta;
    } catch (error) {
        listError.value = apiMessage(error, "Không thể tải danh sách giáo xứ.");
    } finally {
        loading.value = false;
    }
}

async function openDetails(parish: Parish) {
    selected.value = parish;
    detailLoading.value = true;
    detailError.value = "";
    try {
        selected.value = (await getParish(parish.id)).data.data;
    } catch (error) {
        detailError.value = apiMessage(error, "Không thể tải thông tin giáo xứ.");
    } finally {
        detailLoading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    formErrors.value = {};
    formOpen.value = true;
}

function openEdit(parish: Parish) {
    editing.value = parish;
    formErrors.value = {};
    formOpen.value = true;
}

function closeForm() {
    formOpen.value = false;
    editing.value = null;
    formErrors.value = {};
    discardOpen.value = false;
}

function requestFormClose(dirty: boolean) {
    if (saving.value) return;
    if (dirty) discardOpen.value = true;
    else closeForm();
}

function validationErrors(error: unknown) {
    const errors = (error as {response?:{data?:{errors?:Record<string,string[]>}}}).response?.data?.errors ?? {};
    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]])) as Partial<Record<keyof ParishInput,string>>;
}

async function saveParish(payload: ParishInput) {
    if (saving.value) return;
    saving.value = true;
    formErrors.value = {};
    try {
        const editedParish = editing.value;
        const response = editedParish
            ? await updateParish(editedParish.id, payload)
            : await createParish(payload);
        const saved = response.data.data;
        toast.success(editedParish ? "Đã cập nhật giáo xứ." : "Đã tạo giáo xứ.");
        closeForm();
        await load(editedParish ? meta.value.current_page : 1);
        if (selected.value?.id === saved.id) selected.value = (await getParish(saved.id)).data.data;
    } catch (error) {
        formErrors.value = validationErrors(error);
        toast.error(apiMessage(error, "Không thể lưu thông tin giáo xứ."));
    } finally {
        saving.value = false;
    }
}

function requestAssignment(teacherIds:number[]) {
    pendingTeacherIds.value = teacherIds;
    assignmentError.value = "";
    assignmentConfirmOpen.value = true;
}

async function confirmAssignment() {
    if (!selected.value || pendingTeacherIds.value.length === 0 || assignmentSaving.value) return;
    assignmentSaving.value = true;
    assignmentError.value = "";
    try {
        selected.value = (await assignParishTeachers(selected.value.id, pendingTeacherIds.value)).data.data;
        toast.success("Đã phân giáo lý viên vào giáo xứ.");
        assignmentConfirmOpen.value = false;
        assignmentOpen.value = false;
        pendingTeacherIds.value = [];
        await load(meta.value.current_page);
    } catch (error) {
        assignmentError.value = apiMessage(error, "Không thể phân giáo lý viên.");
        toast.error(assignmentError.value);
    } finally {
        assignmentSaving.value = false;
    }
}

function dependencyMessage(counts:ParishDependencyCounts) {
    const labels:Record<keyof ParishDependencyCounts,string> = {
        teachers:"giáo lý viên",
        children:"thiếu nhi",
        academic_years:"niên khóa",
        levels:"khối giáo lý",
        classrooms:"phòng học",
        announcements:"thông báo",
    };
    const blockers = (Object.entries(counts) as [keyof ParishDependencyCounts,number][])
        .filter(([, count]) => count > 0)
        .map(([key, count]) => `${count} ${labels[key]}`);
    return `Chưa thể xóa vì giáo xứ còn ${blockers.join(", ")}. Hãy chuyển hoặc xử lý các dữ liệu này trước.`;
}

function requestDelete() {
    deleteError.value = "";
    deleteConfirmOpen.value = true;
}

async function confirmDelete() {
    if (!selected.value || deleteSaving.value) return;
    deleteSaving.value = true;
    deleteError.value = "";
    try {
        await deleteParish(selected.value.id);
        toast.success("Đã xóa giáo xứ.");
        deleteConfirmOpen.value = false;
        selected.value = null;
        const previousPage = parishes.value.length === 1 && meta.value.current_page > 1
            ? meta.value.current_page - 1
            : meta.value.current_page;
        await load(previousPage);
    } catch (error) {
        const response = (error as {response?:{data?:{code?:string;message?:string;data?:{dependency_counts?:ParishDependencyCounts}}}}).response?.data;
        deleteError.value = response?.code === "PARISH_HAS_DEPENDENCIES" && response.data?.dependency_counts
            ? dependencyMessage(response.data.dependency_counts)
            : response?.message ?? "Không thể xóa giáo xứ.";
        toast.error(deleteError.value);
    } finally {
        deleteSaving.value = false;
    }
}

function rowInteractions(parish: Parish) {
    return {
        class: "parish-row",
        tabindex: 0,
        "aria-label": `Xem giáo xứ ${parish.name}`,
        onClick: () => openDetails(parish),
        onKeydown: (event: KeyboardEvent) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                void openDetails(parish);
            }
        },
    };
}

onMounted(() => load());
</script>

<template>
    <section class="parish-management-page">
        <AAlert v-if="listError" type="error" show-icon closable :message="listError" class="mb-4" @close="listError = ''" />

        <ACard :bordered="false" class="admin-card admin-table-card">
            <div class="parish-toolbar">
                <div class="min-w-0">
                    <h2>Danh mục giáo xứ</h2>
                    <p>{{ meta.total.toLocaleString("vi-VN") }} giáo xứ trong hệ thống</p>
                </div>
                <AButton type="primary" size="large" @click="openCreate">
                    <template #icon><Plus class="size-4" /></template>Tạo giáo xứ
                </AButton>
            </div>

            <div class="parish-filters">
                <AInput v-model:value="search" allow-clear size="large" placeholder="Tìm theo tên hoặc mã giáo xứ" @press-enter="load(1)">
                    <template #prefix><Search aria-hidden="true" class="size-4 text-slate-400" /></template>
                </AInput>
                <AButton type="primary" size="large" :loading="loading" @click="load(1)">
                    <template #icon><Search class="size-4" /></template>Tìm kiếm
                </AButton>
                <ATooltip title="Tải lại dữ liệu">
                    <AButton size="large" :loading="loading" aria-label="Tải lại dữ liệu" @click="load(meta.current_page)">
                        <template #icon><RefreshCw class="size-4" /></template>
                    </AButton>
                </ATooltip>
            </div>

            <ATable
                :columns="columns"
                :custom-row="rowInteractions"
                :data-source="parishes"
                :loading="loading"
                :pagination="false"
                :scroll="{ x: 760 }"
                row-key="id"
            >
                <template #emptyText><AEmpty description="Không có giáo xứ phù hợp." /></template>
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'parish'">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="parish-mark"><Building2 aria-hidden="true" class="size-5" /></span>
                            <div class="min-w-0">
                                <b class="block truncate text-[13px] text-blue-950">{{ record.name }}</b>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ record.code }}</span>
                            </div>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'contact'">
                        <div class="space-y-1 text-xs text-slate-600">
                            <div class="flex items-center gap-2"><Phone class="size-3.5 text-slate-400" />{{ record.phone || "Chưa có số điện thoại" }}</div>
                            <div class="flex items-center gap-2"><Mail class="size-3.5 text-slate-400" />{{ record.email || "Chưa có email" }}</div>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'scale'">
                        <div class="flex flex-wrap gap-1.5">
                            <ATag color="blue">{{ record.teacher_count }} GLV</ATag>
                            <ATag>{{ record.children_count }} thiếu nhi</ATag>
                            <ATag>{{ record.academic_years_count }} niên khóa</ATag>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'action'">
                        <ATooltip title="Xem chi tiết">
                            <AButton type="text" class="icon-action-button" aria-label="Xem chi tiết giáo xứ" @click.stop="openDetails(record as Parish)">
                                <template #icon><Eye class="size-4" /></template>
                            </AButton>
                        </ATooltip>
                    </template>
                </template>
            </ATable>

            <div v-if="meta.total > meta.per_page" class="flex justify-end border-t border-slate-100 px-4 py-3">
                <APagination :current="meta.current_page" :page-size="meta.per_page" :total="meta.total" :show-size-changer="false" responsive @change="load" />
            </div>
        </ACard>
    </section>

    <ADrawer :open="Boolean(selected)" width="min(620px, 100vw)" title="Chi tiết giáo xứ" placement="right" @close="selected = null">
        <template #extra>
            <div v-if="selected" class="flex gap-2">
                <ATooltip title="Xóa giáo xứ"><AButton danger aria-label="Xóa giáo xứ" @click="requestDelete"><template #icon><Trash2 class="size-4" /></template></AButton></ATooltip>
                <AButton @click="assignmentOpen = true"><template #icon><UserRoundPlus class="size-4" /></template>Phân GLV</AButton>
                <AButton @click="openEdit(selected)"><template #icon><Pencil class="size-4" /></template>Chỉnh sửa</AButton>
            </div>
        </template>
        <ASpin :spinning="detailLoading">
            <AAlert v-if="detailError" type="error" show-icon :message="detailError" class="mb-4" />
            <template v-if="selected">
                <div class="parish-detail-heading">
                    <span class="parish-mark parish-mark--large"><Building2 aria-hidden="true" class="size-6" /></span>
                    <div class="min-w-0"><h2>{{ selected.name }}</h2><p>{{ selected.code }}</p></div>
                </div>

                <div class="parish-contact-band">
                    <div><Phone aria-hidden="true" /><span><small>Số điện thoại</small>{{ selected.phone || "Chưa cập nhật" }}</span></div>
                    <div><Mail aria-hidden="true" /><span><small>Email</small>{{ selected.email || "Chưa cập nhật" }}</span></div>
                </div>

                <div class="parish-section-heading"><h3>Dữ liệu liên quan</h3><span>Quy mô hiện tại</span></div>
                <div class="dependency-grid">
                    <div v-for="item in dependencyItems" :key="item.label"><strong>{{ item.value }}</strong><span>{{ item.label }}</span></div>
                </div>

                <div class="parish-section-heading"><h3>Giáo lý viên</h3><span>{{ selected.teachers?.length ?? 0 }} người</span></div>
                <div v-if="selected.teachers?.length" class="teacher-list">
                    <div v-for="teacher in selected.teachers" :key="teacher.id" class="teacher-row">
                        <span><UsersRound aria-hidden="true" /></span>
                        <div><b>{{ teacher.user.name }}</b><small>{{ teacher.user.email }}</small></div>
                        <span class="teacher-code">{{ teacher.code || "Chưa cấp mã" }}</span>
                    </div>
                </div>
                <AEmpty v-else description="Chưa có giáo lý viên thuộc giáo xứ." />
            </template>
        </ASpin>
    </ADrawer>

    <AdminParishFormModal :open="formOpen" :parish="editing" :saving="saving" :errors="formErrors" @close="requestFormClose" @submit="saveParish" />
    <AdminParishTeacherModal :open="assignmentOpen" :parish="selected" :saving="assignmentSaving" @close="assignmentOpen = false" @submit="requestAssignment" />
    <AdminActionConfirmModal :open="assignmentConfirmOpen" title="Chuyển giáo lý viên vào giáo xứ này?" description="Các giáo lý viên đã chọn sẽ được chuyển khỏi giáo xứ hiện tại và thuộc giáo xứ mới ngay sau khi xác nhận." confirm-text="Xác nhận phân công" :target-name="selected?.name" :loading="assignmentSaving" :error-message="assignmentError" @close="assignmentConfirmOpen = false" @confirm="confirmAssignment" />
    <AdminActionConfirmModal :open="deleteConfirmOpen" title="Xóa giáo xứ này?" description="Chỉ giáo xứ không còn dữ liệu liên quan mới có thể bị xóa. Thao tác này không thể hoàn tác." confirm-text="Xóa giáo xứ" :target-name="selected?.name" :target-email="selected?.code" danger :loading="deleteSaving" :error-message="deleteError" @close="deleteConfirmOpen = false" @confirm="confirmDelete" />
    <AdminActionConfirmModal :open="discardOpen" title="Bỏ thay đổi chưa lưu?" description="Thông tin vừa nhập sẽ không được lưu lại." confirm-text="Bỏ thay đổi" danger @close="discardOpen = false" @confirm="closeForm" />
</template>

<style scoped>
.parish-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 1.25rem .9rem}.parish-toolbar h2{margin:0;color:#0b214d;font-size:1rem;font-weight:700}.parish-toolbar p{margin:.25rem 0 0;color:#64748b;font-size:.75rem}.parish-filters{display:grid;grid-template-columns:minmax(0,1fr) auto auto;gap:.625rem;padding:0 1.25rem 1rem}.parish-mark{display:grid;width:2.5rem;height:2.5rem;flex:none;place-items:center;border-radius:.625rem;background:#edf4ff;color:#185fce}.parish-mark--large{width:3rem;height:3rem}.parish-detail-heading{display:flex;align-items:center;gap:.875rem;padding-bottom:1.25rem}.parish-detail-heading h2{margin:0;color:#0b214d;font-size:1.125rem;font-weight:750}.parish-detail-heading p{margin:.2rem 0 0;color:#64748b;font-size:.75rem}.parish-contact-band{display:grid;grid-template-columns:1fr 1fr;border-block:1px solid #e2e8f0}.parish-contact-band>div{display:flex;min-width:0;align-items:center;gap:.75rem;padding:1rem}.parish-contact-band>div+div{border-left:1px solid #e2e8f0}.parish-contact-band svg{width:1rem;height:1rem;color:#64748b}.parish-contact-band span{display:flex;min-width:0;flex-direction:column;color:#1e293b;font-size:.8rem;overflow-wrap:anywhere}.parish-contact-band small{margin-bottom:.2rem;color:#64748b;font-size:.68rem}.parish-section-heading{display:flex;align-items:baseline;justify-content:space-between;margin:1.5rem 0 .75rem}.parish-section-heading h3{margin:0;color:#0b214d;font-size:.875rem;font-weight:700}.parish-section-heading span{color:#64748b;font-size:.7rem}.dependency-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));overflow:hidden;border:1px solid #e2e8f0;border-radius:.75rem}.dependency-grid div{display:flex;min-width:0;flex-direction:column;padding:.875rem;border-left:1px solid #e2e8f0;border-top:1px solid #e2e8f0}.dependency-grid div:nth-child(-n+3){border-top:0}.dependency-grid div:nth-child(3n+1){border-left:0}.dependency-grid strong{color:#0b214d;font-size:1rem;font-variant-numeric:tabular-nums}.dependency-grid span{margin-top:.15rem;color:#64748b;font-size:.68rem}.teacher-list{border-top:1px solid #e2e8f0}.teacher-row{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.75rem;padding:.75rem 0;border-bottom:1px solid #e2e8f0}.teacher-row>span:first-child{display:grid;width:2.25rem;height:2.25rem;place-items:center;border-radius:.5rem;background:#f1f5f9;color:#475569}.teacher-row svg{width:1rem;height:1rem}.teacher-row div{display:flex;min-width:0;flex-direction:column}.teacher-row b{overflow:hidden;color:#1e293b;font-size:.78rem;text-overflow:ellipsis;white-space:nowrap}.teacher-row small{overflow:hidden;margin-top:.15rem;color:#64748b;font-size:.68rem;text-overflow:ellipsis;white-space:nowrap}.teacher-code{display:inline-flex;min-height:1.5rem;max-width:9rem;align-items:center;justify-content:center;overflow:hidden;padding:.2rem .5rem;border:1px solid #dbe3ee;border-radius:.375rem;background:#f8fafc;color:#475569;font-size:.7rem;font-weight:600;font-variant-numeric:tabular-nums;line-height:1;text-overflow:ellipsis;white-space:nowrap}@media(max-width:639px){.parish-toolbar{padding:1rem}.parish-filters{grid-template-columns:minmax(0,1fr) auto;padding:0 1rem 1rem}.parish-filters>:last-child{display:none}.parish-contact-band{grid-template-columns:1fr}.parish-contact-band>div+div{border-top:1px solid #e2e8f0;border-left:0}.dependency-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.dependency-grid div:nth-child(-n+3){border-top:1px solid #e2e8f0}.dependency-grid div:nth-child(-n+2){border-top:0}.dependency-grid div:nth-child(3n+1){border-left:1px solid #e2e8f0}.dependency-grid div:nth-child(2n+1){border-left:0}}
</style>

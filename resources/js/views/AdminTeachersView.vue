<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import ADrawer from "ant-design-vue/es/drawer";
import AEmpty from "ant-design-vue/es/empty";
import AInput from "ant-design-vue/es/input";
import APagination from "ant-design-vue/es/pagination";
import ASelect from "ant-design-vue/es/select";
import ASpin from "ant-design-vue/es/spin";
import ATable from "ant-design-vue/es/table";
import ATag from "ant-design-vue/es/tag";
import ATooltip from "ant-design-vue/es/tooltip";
import type { ColumnsType } from "ant-design-vue/es/table/interface";
import { Archive, BookOpen, Building2, Eye, Mail, Pencil, Phone, Plus, RefreshCw, RotateCcw, Search, UserRound } from "lucide-vue-next";
import { toast } from "vue-sonner";
import {
    archiveTeacher, createTeacher, getTeacher, listParishes, listTeachers, restoreTeacher, updateTeacher,
    type AdminListMeta, type Parish, type Teacher, type TeacherClass, type TeacherCreateInput, type TeacherUpdateInput,
} from "../api/admin";
import AdminActionConfirmModal from "../components/AdminActionConfirmModal.vue";
import AdminTeacherFormModal, { type TeacherFormErrors } from "../components/AdminTeacherFormModal.vue";

const teachers = ref<Teacher[]>([]);
const route = useRoute();
const router = useRouter();
const parishes = ref<Parish[]>([]);
const selected = ref<Teacher|null>(null);
const meta = ref<AdminListMeta>({ current_page:1, last_page:1, per_page:15, total:0 });
const search = ref("");
const parishId = ref<number|undefined>();
const status = ref<"active"|"blocked"|"archived"|undefined>();
const loading = ref(true);
const detailLoading = ref(false);
const listError = ref("");
const detailError = ref("");
const formOpen = ref(false);
const editing = ref<Teacher|null>(null);
const saving = ref(false);
const formErrors = ref<TeacherFormErrors>({});
const discardOpen = ref(false);
const actionType = ref<"archive"|"restore"|null>(null);
const actionSaving = ref(false);
const actionError = ref("");

const columns:ColumnsType<Teacher> = [
    { title:"Giáo lý viên", key:"teacher", width:280 },
    { title:"Liên hệ", key:"contact", responsive:["md"] },
    { title:"Giáo xứ & lớp", key:"assignment", width:260, responsive:["lg"] },
    { title:"Trạng thái", key:"status", width:145 },
    { title:"", key:"action", width:64, fixed:"right", align:"center" },
];

const apiMessage = (error:unknown, fallback:string) =>
    (error as {response?:{data?:{message?:string}}}).response?.data?.message ?? fallback;

function statusTag(teacher:Teacher) {
    if (teacher.is_archived) return { color:"default", label:"Đã lưu trữ" };
    if (teacher.account_status === "blocked") return { color:"error", label:"Đã khóa" };
    return { color:"success", label:"Hoạt động" };
}

function classRole(role:string) {
    return role === "assistant" ? "Trợ giảng" : "Phụ trách chính";
}

async function load(page = 1) {
    loading.value = true;
    listError.value = "";
    try {
        const response = await listTeachers({
            search:search.value.trim() || undefined,
            parish_id:parishId.value,
            status:status.value,
            page,
        });
        teachers.value = response.data.data;
        meta.value = response.data.meta as unknown as AdminListMeta;
    } catch (error) {
        listError.value = apiMessage(error, "Không thể tải danh sách giáo lý viên.");
    } finally {
        loading.value = false;
    }
}

async function loadParishOptions() {
    try {
        const response = await listParishes({ per_page:50 });
        parishes.value = response.data.data;
    } catch {
        toast.error("Không thể tải danh sách giáo xứ.");
    }
}

async function openDetails(teacher:Teacher) {
    selected.value = teacher;
    detailLoading.value = true;
    detailError.value = "";
    try {
        selected.value = (await getTeacher(teacher.id)).data.data;
    } catch (error) {
        detailError.value = apiMessage(error, "Không thể tải thông tin giáo lý viên.");
    } finally {
        detailLoading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    formErrors.value = {};
    formOpen.value = true;
}

function openEdit(teacher:Teacher) {
    editing.value = teacher;
    selected.value = null;
    formErrors.value = {};
    formOpen.value = true;
}

function closeForm() {
    formOpen.value = false;
    editing.value = null;
    formErrors.value = {};
    discardOpen.value = false;
}

function requestFormClose(dirty:boolean) {
    if (saving.value) return;
    if (dirty) discardOpen.value = true;
    else closeForm();
}

function validationErrors(error:unknown):TeacherFormErrors {
    const errors = (error as {response?:{data?:{errors?:Record<string,string[]>}}}).response?.data?.errors ?? {};
    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]])) as TeacherFormErrors;
}

async function saveTeacher(payload:TeacherCreateInput|TeacherUpdateInput) {
    if (saving.value) return;
    saving.value = true;
    formErrors.value = {};
    try {
        const editedTeacher = editing.value;
        const response = editedTeacher
            ? await updateTeacher(editedTeacher.id, payload as TeacherUpdateInput)
            : await createTeacher(payload as TeacherCreateInput);
        const saved = response.data.data;
        toast.success(editedTeacher ? "Đã cập nhật giáo lý viên." : "Đã tạo giáo lý viên.");
        closeForm();
        await load(editedTeacher ? meta.value.current_page : 1);
        if (selected.value?.id === saved.id) selected.value = (await getTeacher(saved.id)).data.data;
    } catch (error) {
        formErrors.value = validationErrors(error);
        toast.error(apiMessage(error, "Không thể lưu thông tin giáo lý viên."));
    } finally {
        saving.value = false;
    }
}

function requestAction(type:"archive"|"restore") {
    actionType.value = type;
    actionError.value = "";
}

function blockingClassMessage(classes:Array<Pick<TeacherClass,"name"|"code">>) {
    const names = classes.map(item => `${item.name} (${item.code})`).join(", ");
    return `Chưa thể lưu trữ vì giáo lý viên đang phụ trách: ${names}. Hãy chuyển các lớp này cho giáo lý viên khác trước.`;
}

async function confirmAction() {
    if (!selected.value || !actionType.value || actionSaving.value) return;
    actionSaving.value = true;
    actionError.value = "";
    try {
        if (actionType.value === "archive") {
            await archiveTeacher(selected.value.id);
            toast.success("Đã lưu trữ giáo lý viên.");
            selected.value = null;
        } else {
            selected.value = (await restoreTeacher(selected.value.id)).data.data;
            toast.success("Đã khôi phục giáo lý viên.");
        }
        actionType.value = null;
        await load(meta.value.current_page);
    } catch (error) {
        const response = (error as {response?:{data?:{code?:string;message?:string;data?:{classes?:Array<Pick<TeacherClass,"name"|"code">>}}}}).response?.data;
        actionError.value = response?.code === "TEACHER_HAS_CLASSES" && response.data?.classes
            ? blockingClassMessage(response.data.classes)
            : response?.message ?? "Không thể thực hiện thao tác.";
        toast.error(actionError.value);
    } finally {
        actionSaving.value = false;
    }
}

function rowInteractions(teacher:Teacher) {
    return {
        class:"teacher-management-row",
        tabindex:0,
        "aria-label":`Xem giáo lý viên ${teacher.name}`,
        onClick:() => openDetails(teacher),
        onKeydown:(event:KeyboardEvent) => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                void openDetails(teacher);
            }
        },
    };
}

onMounted(async () => {
    await Promise.all([load(), loadParishOptions()]);
    if (route.query.create === "1") {
        openCreate();
        const { create: _create, ...query } = route.query;
        await router.replace({ query });
    }
});
</script>

<template>
    <section class="teacher-management-page">
        <AAlert v-if="listError" type="error" show-icon closable :message="listError" class="mb-4" @close="listError = ''" />

        <ACard :bordered="false" class="admin-card admin-table-card">
            <div class="teacher-toolbar">
                <div class="min-w-0">
                    <h2>Danh sách giáo lý viên</h2>
                    <p>{{ meta.total.toLocaleString("vi-VN") }} hồ sơ phù hợp</p>
                </div>
                <AButton type="primary" size="large" @click="openCreate">
                    <template #icon><Plus class="size-4" /></template>Tạo giáo lý viên
                </AButton>
            </div>

            <div class="teacher-filters">
                <AInput v-model:value="search" allow-clear size="large" placeholder="Tìm theo tên, email hoặc mã" @press-enter="load(1)">
                    <template #prefix><Search aria-hidden="true" class="size-4 text-slate-400" /></template>
                </AInput>
                <ASelect v-model:value="parishId" allow-clear show-search option-filter-prop="label" size="large" placeholder="Tất cả giáo xứ" :options="parishes.map(parish => ({value:parish.id,label:parish.name}))" @change="load(1)" />
                <ASelect v-model:value="status" allow-clear size="large" placeholder="Đang sử dụng" :options="[{value:'active',label:'Đang hoạt động'},{value:'blocked',label:'Đã khóa'},{value:'archived',label:'Đã lưu trữ'}]" @change="load(1)" />
                <AButton type="primary" size="large" :loading="loading" @click="load(1)">
                    <template #icon><Search class="size-4" /></template>Tìm kiếm
                </AButton>
                <ATooltip title="Tải lại dữ liệu">
                    <AButton size="large" :loading="loading" aria-label="Tải lại dữ liệu" @click="load(meta.current_page)"><template #icon><RefreshCw class="size-4" /></template></AButton>
                </ATooltip>
            </div>

            <ATable :columns="columns" :custom-row="rowInteractions" :data-source="teachers" :loading="loading" :pagination="false" :scroll="{x:900}" row-key="id">
                <template #emptyText><AEmpty description="Không có giáo lý viên phù hợp." /></template>
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'teacher'">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="teacher-mark"><UserRound aria-hidden="true" class="size-5" /></span>
                            <div class="min-w-0"><b class="block truncate text-[13px] text-blue-950">{{ record.name }}</b><span class="mt-0.5 block text-xs text-slate-500">{{ record.code }}</span></div>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'contact'">
                        <div class="space-y-1 text-xs text-slate-600"><div class="flex items-center gap-2"><Mail class="size-3.5 text-slate-400" />{{ record.email }}</div><div class="flex items-center gap-2"><Phone class="size-3.5 text-slate-400" />{{ record.phone || "Chưa có số điện thoại" }}</div></div>
                    </template>
                    <template v-else-if="column.key === 'assignment'">
                        <div class="min-w-0 text-xs"><div class="flex items-center gap-2 text-slate-700"><Building2 class="size-3.5 shrink-0 text-slate-400" /><span class="truncate">{{ record.parish.name }}</span></div><div class="mt-1 flex items-center gap-2 text-slate-500"><BookOpen class="size-3.5" />{{ record.classes_count }} lớp phụ trách</div></div>
                    </template>
                    <template v-else-if="column.key === 'status'"><ATag :color="statusTag(record as Teacher).color">{{ statusTag(record as Teacher).label }}</ATag></template>
                    <template v-else-if="column.key === 'action'"><ATooltip title="Xem chi tiết"><AButton type="text" class="icon-action-button" aria-label="Xem chi tiết giáo lý viên" @click.stop="openDetails(record as Teacher)"><template #icon><Eye class="size-4" /></template></AButton></ATooltip></template>
                </template>
            </ATable>

            <div v-if="meta.total > meta.per_page" class="flex justify-end border-t border-slate-100 px-4 py-3"><APagination :current="meta.current_page" :page-size="meta.per_page" :total="meta.total" :show-size-changer="false" responsive @change="load" /></div>
        </ACard>
    </section>

    <ADrawer :open="Boolean(selected)" width="min(620px, 100vw)" title="Chi tiết giáo lý viên" placement="right" @close="selected = null">
        <template #extra>
            <div v-if="selected" class="flex gap-2">
                <AButton v-if="selected.is_archived" @click="requestAction('restore')"><template #icon><RotateCcw class="size-4" /></template>Khôi phục</AButton>
                <template v-else>
                    <ATooltip title="Lưu trữ giáo lý viên"><AButton danger aria-label="Lưu trữ giáo lý viên" @click="requestAction('archive')"><template #icon><Archive class="size-4" /></template></AButton></ATooltip>
                    <AButton @click="openEdit(selected)"><template #icon><Pencil class="size-4" /></template>Chỉnh sửa</AButton>
                </template>
            </div>
        </template>
        <ASpin :spinning="detailLoading">
            <AAlert v-if="detailError" type="error" show-icon :message="detailError" class="mb-4" />
            <template v-if="selected">
                <div class="teacher-detail-heading"><span class="teacher-mark teacher-mark--large"><UserRound aria-hidden="true" class="size-6" /></span><div class="min-w-0"><h2>{{ selected.name }}</h2><p>{{ selected.code }} · {{ selected.parish.name }}</p></div><ATag class="ml-auto" :color="statusTag(selected).color">{{ statusTag(selected).label }}</ATag></div>
                <div class="teacher-contact-band"><div><Mail aria-hidden="true" /><span><small>Email</small>{{ selected.email }}</span></div><div><Phone aria-hidden="true" /><span><small>Số điện thoại</small>{{ selected.phone || "Chưa cập nhật" }}</span></div></div>
                <div class="teacher-section-heading"><h3>Lớp đang phụ trách</h3><span>{{ selected.classes_count }} lớp</span></div>
                <div v-if="selected.classes?.length" class="teacher-class-list">
                    <div v-for="item in selected.classes" :key="item.id" class="teacher-class-row"><span><BookOpen aria-hidden="true" /></span><div><b>{{ item.name }}</b><small>{{ item.code }}<template v-if="item.academic_year"> · {{ item.academic_year.name }}</template><template v-if="item.level"> · {{ item.level.name }}</template></small></div><ATag>{{ classRole(item.role) }}</ATag></div>
                </div>
                <AEmpty v-else description="Chưa được phân công lớp." />
            </template>
        </ASpin>
    </ADrawer>

    <AdminTeacherFormModal :open="formOpen" :teacher="editing" :parishes="parishes" :saving="saving" :errors="formErrors" @close="requestFormClose" @submit="saveTeacher" />
    <AdminActionConfirmModal :open="actionType === 'archive'" title="Lưu trữ giáo lý viên này?" description="Tài khoản sẽ không thể đăng nhập. Chỉ giáo lý viên không còn lớp phụ trách mới có thể được lưu trữ." confirm-text="Lưu trữ" :target-name="selected?.name" :target-email="selected?.email" danger :loading="actionSaving" :error-message="actionError" @close="actionType = null" @confirm="confirmAction" />
    <AdminActionConfirmModal :open="actionType === 'restore'" title="Khôi phục giáo lý viên này?" description="Tài khoản sẽ được mở lại với trạng thái trước khi lưu trữ." confirm-text="Khôi phục" :target-name="selected?.name" :target-email="selected?.email" :loading="actionSaving" :error-message="actionError" @close="actionType = null" @confirm="confirmAction" />
    <AdminActionConfirmModal :open="discardOpen" title="Bỏ thay đổi chưa lưu?" description="Thông tin vừa nhập sẽ không được lưu lại." confirm-text="Bỏ thay đổi" danger @close="discardOpen = false" @confirm="closeForm" />
</template>

<style scoped>
.teacher-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 1.25rem .9rem}.teacher-toolbar h2{margin:0;color:#0b214d;font-size:1rem;font-weight:700}.teacher-toolbar p{margin:.25rem 0 0;color:#64748b;font-size:.75rem}.teacher-filters{display:grid;grid-template-columns:minmax(14rem,1fr) minmax(10rem,.55fr) minmax(9rem,.45fr) auto auto;gap:.625rem;padding:0 1.25rem 1rem}.teacher-mark{display:grid;width:2.5rem;height:2.5rem;flex:none;place-items:center;border-radius:.625rem;background:#edf4ff;color:#185fce}.teacher-mark--large{width:3rem;height:3rem}.teacher-detail-heading{display:flex;align-items:center;gap:.875rem;padding-bottom:1.25rem}.teacher-detail-heading h2{margin:0;color:#0b214d;font-size:1.125rem;font-weight:750}.teacher-detail-heading p{margin:.2rem 0 0;color:#64748b;font-size:.75rem}.teacher-contact-band{display:grid;grid-template-columns:1.25fr 1fr;border-block:1px solid #e2e8f0}.teacher-contact-band>div{display:flex;min-width:0;align-items:center;gap:.75rem;padding:1rem}.teacher-contact-band>div+div{border-left:1px solid #e2e8f0}.teacher-contact-band svg{width:1rem;height:1rem;flex:none;color:#64748b}.teacher-contact-band span{display:flex;min-width:0;flex-direction:column;color:#1e293b;font-size:.8rem;overflow-wrap:anywhere}.teacher-contact-band small{margin-bottom:.2rem;color:#64748b;font-size:.68rem}.teacher-section-heading{display:flex;align-items:baseline;justify-content:space-between;margin:1.5rem 0 .75rem}.teacher-section-heading h3{margin:0;color:#0b214d;font-size:.875rem;font-weight:700}.teacher-section-heading span{color:#64748b;font-size:.7rem}.teacher-class-list{border-top:1px solid #e2e8f0}.teacher-class-row{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.75rem;padding:.75rem 0;border-bottom:1px solid #e2e8f0}.teacher-class-row>span:first-child{display:grid;width:2.25rem;height:2.25rem;place-items:center;border-radius:.5rem;background:#f1f5f9;color:#475569}.teacher-class-row svg{width:1rem;height:1rem}.teacher-class-row div{display:flex;min-width:0;flex-direction:column}.teacher-class-row b,.teacher-class-row small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.teacher-class-row b{color:#1e293b;font-size:.78rem}.teacher-class-row small{margin-top:.15rem;color:#64748b;font-size:.68rem}@media(max-width:1023px){.teacher-filters{grid-template-columns:minmax(0,1fr) minmax(10rem,.6fr) auto}.teacher-filters>:nth-child(3){grid-column:1/2}.teacher-filters>:nth-child(4){grid-column:2/3}.teacher-filters>:nth-child(5){grid-column:3/4}}@media(max-width:639px){.teacher-toolbar{align-items:flex-start;padding:1rem}.teacher-toolbar .ant-btn{padding-inline:.75rem}.teacher-filters{grid-template-columns:minmax(0,1fr) auto;padding:0 1rem 1rem}.teacher-filters>:nth-child(2),.teacher-filters>:nth-child(3),.teacher-filters>:nth-child(4){grid-column:1/3}.teacher-filters>:nth-child(5){grid-column:auto}.teacher-contact-band{grid-template-columns:1fr}.teacher-contact-band>div+div{border-top:1px solid #e2e8f0;border-left:0}}
</style>

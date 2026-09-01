<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AEmpty from "ant-design-vue/es/empty";
import AForm, { type FormInstance } from "ant-design-vue/es/form";
import AFormItem from "ant-design-vue/es/form/FormItem";
import AInput from "ant-design-vue/es/input";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import ASkeleton from "ant-design-vue/es/skeleton";
import ATag from "ant-design-vue/es/tag";
import {
    ArrowLeft, BookOpen, Building2, CalendarDays, CheckCircle2, Clock3,
    DoorOpen, GraduationCap, Hash, Info, Pencil, RotateCcw, Save,
    UserRoundCheck, UsersRound, X,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import {
    assignClassTeachers, getClass, getClassOptions, restoreClass,
    updateClass, updateClassEnrollments, updateClassSchedules,
    type AdminClass, type BusinessApiError, type ClassInput,
    type ClassOptions, type ClassScheduleInput,
} from "../api/admin";
import AdminActionConfirmModal from "../components/AdminActionConfirmModal.vue";
import AdminClassEnrollmentModal from "../components/AdminClassEnrollmentModal.vue";
import AdminClassScheduleModal from "../components/AdminClassScheduleModal.vue";
import AdminClassTeacherModal from "../components/AdminClassTeacherModal.vue";

const route = useRoute();
const router = useRouter();
const id = Number(route.params.id);
const formRef = ref<FormInstance>();
const model = ref<AdminClass | null>(null);
const options = ref<ClassOptions>({ parishes:[], academic_years:[], levels:[], classrooms:[], teachers:[], children:[] });
const loading = ref(true);
const saving = ref(false);
const pageError = ref("");
const formErrors = ref<Record<string,string>>({});
const teacherOpen = ref(false);
const enrollmentOpen = ref(false);
const scheduleOpen = ref(false);
const action = ref<"restore"|null>(null);
const actionError = ref("");
const form = reactive({
    name:"", code:"", parish_id:undefined as number|undefined,
    academic_year_id:undefined as number|undefined,
    catechism_level_id:undefined as number|undefined,
    classroom_id:undefined as number|undefined,
    status:"active" as "active"|"inactive",
});

const archived = computed(() => Boolean(model.value?.is_archived));
const weekday = ["","Thứ hai","Thứ ba","Thứ tư","Thứ năm","Thứ sáu","Thứ bảy","Chủ nhật"];
const apiData = (error:unknown) => (error as BusinessApiError).response?.data;
const apiMessage = (error:unknown, fallback:string) => apiData(error)?.message ?? fallback;
const optionList = (items:Array<{id:number;name:string;code?:string}>) => items.map(item => ({ value:item.id, label:item.code ? `${item.name} (${item.code})` : item.name }));

function syncForm(value:AdminClass) {
    Object.assign(form, {
        name:value.name,
        code:value.code,
        parish_id:value.parish?.id,
        academic_year_id:value.academic_year_id,
        catechism_level_id:value.catechism_level_id,
        classroom_id:value.classroom_id ?? undefined,
        status:value.status,
    });
    formErrors.value = {};
    formRef.value?.clearValidate();
}

async function loadOptions(search?:string) {
    if (!model.value) return;
    try {
        options.value = (await getClassOptions(model.value.parish?.id, search)).data.data;
    } catch {
        toast.error("Không thể tải dữ liệu lựa chọn của lớp học.");
    }
}

async function load() {
    loading.value = true;
    pageError.value = "";
    try {
        if (!Number.isInteger(id) || id <= 0) throw new Error("INVALID_CLASS_ID");
        model.value = (await getClass(id, true)).data.data;
        syncForm(model.value);
        await loadOptions();
    } catch (error) {
        pageError.value = apiMessage(error, "Không thể tải lớp học cần chỉnh sửa.");
    } finally {
        loading.value = false;
    }
}

function validationErrors(error:unknown) {
    return Object.fromEntries(Object.entries(apiData(error)?.errors ?? {}).map(([key,value]) => [key,value[0]]));
}

async function saveInfo() {
    if (!model.value || saving.value || archived.value) return;
    try {
        await formRef.value?.validate();
    } catch { return; }
    saving.value = true;
    formErrors.value = {};
    try {
        const payload:ClassInput = {
            name:form.name.trim(), code:form.code.trim(), status:form.status,
            academic_year_id:form.academic_year_id as number,
            catechism_level_id:form.catechism_level_id as number,
            classroom_id:form.classroom_id ?? null,
        };
        model.value = (await updateClass(model.value.id, payload)).data.data;
        syncForm(model.value);
        toast.success("Đã cập nhật lớp học.");
    } catch (error) {
        formErrors.value = validationErrors(error);
        toast.error(apiMessage(error, "Không thể cập nhật lớp học."));
    } finally { saving.value = false; }
}

async function saveTeachers(rows:Array<{teacher_id:number;role:"primary"|"assistant"}>, allow = false) {
    if (!model.value || saving.value || archived.value) return;
    saving.value = true;
    try {
        model.value = (await assignClassTeachers(model.value.id, rows, allow)).data.data;
        teacherOpen.value = false;
        toast.success("Đã cập nhật giáo lý viên phụ trách.");
    } catch (error) {
        const data = apiData(error);
        if (data?.code === "TEACHER_SCHEDULE_CONFLICT" && !allow) {
            AModal.confirm({
                title:"Giáo lý viên bị trùng lịch",
                content:`${data.data?.conflicts?.map(item => `${item.teacher_name}: ${item.class_name}`).join("; ")}. Vẫn lưu phân công?`,
                okText:"Vẫn phân công", cancelText:"Kiểm tra lại", onOk:() => saveTeachers(rows,true),
            });
        } else toast.error(data?.message ?? "Không thể cập nhật phân công.");
    } finally { saving.value = false; }
}

async function saveEnrollments(rows:Array<{child_id:number;status:"active"|"inactive"}>) {
    if (!model.value || saving.value || archived.value) return;
    saving.value = true;
    try {
        model.value = (await updateClassEnrollments(model.value.id, rows)).data.data;
        enrollmentOpen.value = false;
        toast.success("Đã cập nhật danh sách ghi danh.");
    } catch (error) { toast.error(apiMessage(error, "Không thể cập nhật ghi danh.")); }
    finally { saving.value = false; }
}

async function saveSchedules(rows:ClassScheduleInput[], allow = false) {
    if (!model.value || saving.value || archived.value) return;
    saving.value = true;
    try {
        model.value = (await updateClassSchedules(model.value.id, rows, allow)).data.data;
        scheduleOpen.value = false;
        toast.success("Đã cập nhật lịch học.");
    } catch (error) {
        const data = apiData(error);
        if (data?.code === "TEACHER_SCHEDULE_CONFLICT" && !allow) {
            AModal.confirm({
                title:"Giáo lý viên bị trùng lịch",
                content:`${data.data?.conflicts?.map(item => `${item.teacher_name}: ${item.class_name}`).join("; ")}. Vẫn lưu lịch này?`,
                okText:"Vẫn lưu lịch", cancelText:"Chỉnh lại", onOk:() => saveSchedules(rows,true),
            });
        } else toast.error(data?.message ?? "Không thể cập nhật lịch học.");
    } finally { saving.value = false; }
}

async function confirmAction() {
    if (!model.value || !action.value || saving.value) return;
    saving.value = true;
    actionError.value = "";
    try {
        model.value = (await restoreClass(model.value.id)).data.data;
        syncForm(model.value);
        toast.success("Đã khôi phục lớp học.");
        action.value = null;
    } catch (error) { actionError.value = apiMessage(error, "Không thể thực hiện thao tác."); }
    finally { saving.value = false; }
}

function closeTeacherModal() { if (!saving.value) teacherOpen.value = false; }
function closeEnrollmentModal() { if (!saving.value) enrollmentOpen.value = false; }
function closeScheduleModal() { if (!saving.value) scheduleOpen.value = false; }
function resetInfo() { if (model.value && !saving.value) syncForm(model.value); }
onMounted(load);
</script>

<template>
    <section class="class-edit-page mx-auto w-full max-w-[1500px]">
        <header class="class-admin-hero">
            <div class="class-hero-icon" aria-hidden="true">
                <BookOpen />
                <span><UsersRound /></span>
            </div>
            <div class="class-admin-identity">
                <p>Đang quản lý</p>
                <h2>{{ model?.name || "Đang tải lớp học" }}</h2>
                <div v-if="model" class="class-meta" aria-label="Thông tin tóm tắt lớp học">
                    <span>{{ model.code }}</span><i />
                    <span>{{ model.parish?.name || "Chưa xác định giáo xứ" }}</span><i />
                    <span>{{ model.academic_year?.name || "Chưa có niên khóa" }}</span>
                </div>
            </div>
            <div class="class-admin-actions">
                <ATag v-if="model" class="class-status" :color="archived ? 'default' : model.status === 'active' ? 'success' : 'warning'">{{ archived ? "Đã lưu trữ" : model.status === "active" ? "Đang hoạt động" : "Tạm ngưng" }}</ATag>
                <AButton v-if="model && archived" :loading="saving" @click="action='restore'"><template #icon><RotateCcw /></template>Khôi phục</AButton>
                <AButton class="class-back-button" aria-label="Quay lại danh sách lớp học" @click="router.push('/admin/classes')">
                    <template #icon><ArrowLeft /></template>
                    Quay lại danh sách
                </AButton>
            </div>
        </header>

        <AAlert v-if="pageError" type="error" show-icon :message="pageError" class="mb-4"><template #action><AButton size="small" @click="load">Thử lại</AButton></template></AAlert>
        <ACard v-if="loading" :bordered="false" class="admin-card class-loading-card"><ASkeleton active :paragraph="{rows:10}" /></ACard>

        <template v-else-if="model">
            <AAlert v-if="archived" type="warning" show-icon message="Lớp đã được lưu trữ. Khôi phục lớp trước khi thay đổi thông tin, thành viên hoặc lịch học." class="mb-4" />
            <ACard :bordered="false" class="admin-card edit-info-card">
                <div class="section-heading">
                    <h2>Thông tin lớp học</h2>
                    <p>Cập nhật các thông tin, cấu hình và trạng thái của lớp học.</p>
                </div>
                <AForm ref="formRef" :model="form" :disabled="saving || archived" layout="vertical" @finish="saveInfo">
                    <div class="edit-form-grid">
                        <AFormItem label="Tên lớp" name="name" :rules="[{required:true,message:'Hãy nhập tên lớp.'}]" :help="formErrors.name" :validate-status="formErrors.name?'error':undefined">
                            <AInput v-model:value="form.name" size="large" placeholder="Ví dụ: Thiếu Nhi 1A"><template #prefix><Pencil class="control-icon" aria-hidden="true" /></template></AInput>
                        </AFormItem>
                        <AFormItem label="Mã lớp" name="code" :rules="[{required:true,message:'Hãy nhập mã lớp.'}]" :help="formErrors.code" :validate-status="formErrors.code?'error':undefined">
                            <AInput v-model:value="form.code" size="large" placeholder="Ví dụ: TN-1A"><template #prefix><Hash class="control-icon" aria-hidden="true" /></template></AInput>
                        </AFormItem>
                        <AFormItem label="Giáo xứ">
                            <div class="select-with-icon"><Building2 aria-hidden="true" /><ASelect v-model:value="form.parish_id" size="large" disabled :options="optionList(options.parishes)" /></div>
                        </AFormItem>
                        <AFormItem label="Trạng thái" name="status" required>
                            <div class="select-with-icon"><CheckCircle2 aria-hidden="true" /><ASelect v-model:value="form.status" size="large" :options="[{value:'active',label:'Đang hoạt động'},{value:'inactive',label:'Tạm ngưng'}]" /></div>
                        </AFormItem>
                        <AFormItem label="Niên khóa" name="academic_year_id" :rules="[{required:true,message:'Hãy chọn niên khóa.'}]" :help="formErrors.academic_year_id" :validate-status="formErrors.academic_year_id?'error':undefined">
                            <div class="select-with-icon"><CalendarDays aria-hidden="true" /><ASelect v-model:value="form.academic_year_id" size="large" :options="optionList(options.academic_years)" /></div>
                        </AFormItem>
                        <AFormItem label="Khối giáo lý" name="catechism_level_id" :rules="[{required:true,message:'Hãy chọn khối giáo lý.'}]" :help="formErrors.catechism_level_id" :validate-status="formErrors.catechism_level_id?'error':undefined">
                            <div class="select-with-icon"><GraduationCap aria-hidden="true" /><ASelect v-model:value="form.catechism_level_id" size="large" :options="optionList(options.levels)" /></div>
                        </AFormItem>
                        <AFormItem class="edit-room-field" label="Phòng học" name="classroom_id" :help="formErrors.classroom_id" :validate-status="formErrors.classroom_id?'error':undefined">
                            <div class="select-with-icon"><DoorOpen aria-hidden="true" /><ASelect v-model:value="form.classroom_id" allow-clear size="large" placeholder="Chưa xếp phòng" :options="optionList(options.classrooms)" /></div>
                        </AFormItem>
                    </div>
                    <div class="class-change-note" role="note"><Info aria-hidden="true" /><span><b>Lưu ý</b>Các thay đổi sẽ được áp dụng cho toàn bộ dữ liệu của lớp học này.</span></div>
                    <div class="form-save-row">
                        <AButton size="large" :disabled="archived" @click="resetInfo"><template #icon><X /></template>Hủy bỏ</AButton>
                        <AButton type="primary" size="large" html-type="submit" :loading="saving" :disabled="archived"><template #icon><Save /></template>Cập nhật lớp học</AButton>
                    </div>
                </AForm>
            </ACard>

            <ACard :bordered="false" class="admin-card class-operations-card">
                <div class="operations-heading">
                    <h2>Tổng quan lớp học</h2>
                    <p>Thông tin tổng hợp và thành viên của lớp học.</p>
                </div>
                <div class="operations-layout">
                    <section class="roster-section">
                        <div class="operation-heading">
                            <div><span><UsersRound /></span><div><h3>Danh sách thiếu nhi</h3><p>{{ model.enrollments_count }} em đang được xếp vào lớp</p></div></div>
                            <AButton :disabled="archived" @click="enrollmentOpen=true"><template #icon><Pencil /></template>Quản lý danh sách</AButton>
                        </div>
                        <div v-if="model.enrollments?.length" class="record-list roster-list">
                            <div v-for="enrollment in model.enrollments" :key="enrollment.id"><i>{{ enrollment.child.full_name.slice(0,1) }}</i><span><b>{{ enrollment.child.full_name }}</b><small>{{ enrollment.child.code }}</small></span><ATag :color="enrollment.status==='active'?'success':'default'">{{ enrollment.status==='active'?'Đang học':'Đã rút' }}</ATag></div>
                        </div>
                        <AEmpty v-else description="Chưa có thiếu nhi trong lớp." class="py-10" />
                    </section>

                    <aside class="operations-aside">
                        <section class="operation-panel">
                            <div class="operation-heading">
                                <div><span><UserRoundCheck /></span><div><h3>Giáo lý viên</h3><p>{{ model.teachers_count }} người phụ trách</p></div></div>
                                <AButton type="text" :disabled="archived" @click="teacherOpen=true">Phân công</AButton>
                            </div>
                            <div v-if="model.teachers?.length" class="record-list compact-record-list"><div v-for="teacher in model.teachers" :key="teacher.id"><i>{{ teacher.name.slice(0,1) }}</i><span><b>{{ teacher.name }}</b><small>{{ teacher.email }}</small></span><ATag>{{ teacher.role === 'primary' ? 'Phụ trách chính' : 'Phụ tá' }}</ATag></div></div>
                            <AEmpty v-else description="Chưa phân công giáo lý viên." class="py-6" />
                        </section>
                        <section class="operation-panel">
                            <div class="operation-heading">
                                <div><span><CalendarDays /></span><div><h3>Lịch học định kỳ</h3><p>{{ model.schedules.length }} khung giờ đã thiết lập</p></div></div>
                                <AButton type="text" :disabled="archived" @click="scheduleOpen=true">Thiết lập</AButton>
                            </div>
                            <div v-if="model.schedules.length" class="schedule-records"><div v-for="schedule in model.schedules" :key="schedule.id"><span><Clock3 /></span><div><b>{{ weekday[schedule.weekday] }}</b><small>{{ schedule.starts_at }}–{{ schedule.ends_at }}</small></div></div></div>
                            <AEmpty v-else description="Chưa thiết lập lịch học." class="py-6" />
                        </section>
                    </aside>
                </div>
            </ACard>
        </template>
    </section>

    <AdminClassTeacherModal :open="teacherOpen" :model="model" :teachers="options.teachers" :saving="saving" @close="closeTeacherModal" @search="loadOptions" @submit="saveTeachers" />
    <AdminClassEnrollmentModal :open="enrollmentOpen" :model="model" :children="options.children" :saving="saving" @close="closeEnrollmentModal" @search="loadOptions" @submit="saveEnrollments" />
    <AdminClassScheduleModal :open="scheduleOpen" :model="model" :saving="saving" @close="closeScheduleModal" @submit="saveSchedules" />
    <AdminActionConfirmModal :open="action==='restore'" title="Khôi phục lớp học này?" description="Lớp sẽ hoạt động trở lại cùng toàn bộ dữ liệu đã lưu." confirm-text="Khôi phục lớp" :target-name="model?.name" :loading="saving" :error-message="actionError" @close="action=null" @confirm="confirmAction" />
</template>

<style scoped>
.class-edit-page {
    container-name: class-edit;
    container-type: inline-size;
    display: grid;
    width: 100%;
    gap: 18px;
}

.class-admin-hero {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 22px;
    min-height: 110px;
    padding: 20px 24px;
    border: 1px solid #dbe3ee;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .035);
}

.class-hero-icon {
    position: relative;
    display: grid;
    width: 64px;
    height: 64px;
    place-items: center;
    border-radius: 16px;
    background: #e8f3ff;
    color: #174f9f;
}

.class-hero-icon > svg {
    width: 31px;
    height: 31px;
    stroke-width: 1.9;
}

.class-hero-icon > span {
    position: absolute;
    right: -5px;
    bottom: -4px;
    display: grid;
    width: 25px;
    height: 25px;
    place-items: center;
    border: 3px solid #fff;
    border-radius: 50%;
    background: #1677ff;
    color: #fff;
}

.class-hero-icon > span svg {
    width: 13px;
    height: 13px;
}

.class-admin-identity {
    min-width: 0;
}

.class-admin-identity p,
.class-admin-identity h2,
.class-admin-identity .class-meta {
    margin: 0;
}

.class-admin-identity p {
    color: #5b6b84;
    font-size: 11px;
    font-weight: 750;
    letter-spacing: .055em;
    text-transform: uppercase;
}

.class-admin-identity h2 {
    overflow: hidden;
    margin-top: 2px;
    color: #0b214d;
    font-size: 25px;
    font-weight: 780;
    letter-spacing: -.025em;
    line-height: 1.22;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.class-meta {
    display: flex;
    min-width: 0;
    flex-wrap: wrap;
    align-items: center;
    gap: 7px;
    margin-top: 5px !important;
    color: #64748b;
    font-size: 12px;
    line-height: 1.45;
}

.class-meta i {
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: #aab6c7;
}

.class-admin-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
}

.class-admin-actions :deep(.ant-btn) {
    display: inline-flex;
    min-height: 40px;
    align-items: center;
    gap: 7px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 600;
}

.class-admin-actions :deep(.ant-btn svg),
.form-save-row :deep(.ant-btn svg),
.operation-heading :deep(.ant-btn svg) {
    width: 16px;
    height: 16px;
}

.class-status {
    margin: 0;
    white-space: nowrap;
}

.class-loading-card :deep(.ant-card-body) {
    padding: 24px;
}

.edit-info-card,
.class-operations-card {
    overflow: hidden;
    border: 1px solid #dbe3ee;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 8px 24px rgba(15, 23, 42, .035);
}

.edit-info-card :deep(.ant-card-body) {
    padding: 0;
}

.section-heading,
.operations-heading {
    padding: 20px 24px 16px;
}

.section-heading {
    border-bottom: 1px solid #e6ecf3;
}

.section-heading h2,
.section-heading p,
.operations-heading h2,
.operations-heading p,
.operation-heading h3,
.operation-heading p {
    margin: 0;
}

.section-heading h2,
.operations-heading h2 {
    color: #0b214d;
    font-size: 17px;
    font-weight: 760;
    letter-spacing: -.012em;
}

.section-heading p,
.operations-heading p {
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.edit-info-card :deep(.ant-form) {
    padding: 20px 24px 18px;
}

.edit-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 20px;
}

.edit-room-field {
    grid-column: 1 / -1;
}

.edit-info-card :deep(.ant-form-item) {
    margin-bottom: 15px;
}

.edit-info-card :deep(.ant-form-item-label) {
    padding-bottom: 6px;
}

.edit-info-card :deep(.ant-form-item-label > label) {
    height: auto;
    color: #334155;
    font-size: 12px;
    font-weight: 700;
}

.edit-info-card :deep(.ant-input-affix-wrapper),
.edit-info-card :deep(.ant-select-selector) {
    min-height: 42px;
    border-color: #d7dee9 !important;
    border-radius: 9px !important;
    box-shadow: none !important;
}

.edit-info-card :deep(.ant-input-affix-wrapper) {
    gap: 9px;
    padding-inline: 11px;
}

.edit-info-card :deep(.ant-input),
.edit-info-card :deep(.ant-select-selection-item),
.edit-info-card :deep(.ant-select-selection-placeholder) {
    color: #1e293b;
    font-size: 13px;
}

.edit-info-card :deep(.ant-select-selection-item),
.edit-info-card :deep(.ant-select-selection-placeholder) {
    line-height: 40px !important;
}

.edit-info-card :deep(.ant-input-affix-wrapper:hover),
.edit-info-card :deep(.ant-select-selector:hover) {
    border-color: #91bdf7 !important;
}

.edit-info-card :deep(.ant-input-affix-wrapper-focused),
.edit-info-card :deep(.ant-select-focused .ant-select-selector) {
    border-color: #1677ff !important;
    box-shadow: 0 0 0 2px rgba(22, 119, 255, .12) !important;
}

.control-icon,
.select-with-icon > svg {
    width: 16px;
    height: 16px;
    color: #2563eb;
    stroke-width: 1.9;
}

.select-with-icon {
    position: relative;
}

.select-with-icon > svg {
    position: absolute;
    z-index: 2;
    top: 50%;
    left: 12px;
    pointer-events: none;
    transform: translateY(-50%);
}

.select-with-icon :deep(.ant-select) {
    width: 100%;
}

.select-with-icon :deep(.ant-select-selector) {
    padding-left: 39px !important;
}

.edit-info-card :deep(.ant-input-affix-wrapper-disabled .control-icon) {
    color: #94a3b8;
}

.class-change-note {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-top: 2px;
    border: 1px solid #a9ccff;
    border-radius: 10px;
    background: #f4f8ff;
    padding: 12px 14px;
    color: #245da8;
}

.class-change-note > svg {
    width: 17px;
    height: 17px;
    flex: none;
    margin-top: 1px;
}

.class-change-note span,
.class-change-note b {
    display: block;
}

.class-change-note span {
    font-size: 11px;
    line-height: 1.5;
}

.class-change-note b {
    margin-bottom: 2px;
    font-size: 12px;
}

.form-save-row {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid #e6ecf3;
}

.form-save-row :deep(.ant-btn) {
    display: inline-flex;
    min-height: 40px;
    align-items: center;
    gap: 7px;
    border-radius: 9px;
    font-size: 13px;
    font-weight: 650;
}

.class-operations-card :deep(.ant-card-body) {
    padding: 0;
}

.operations-heading {
    border-bottom: 1px solid #e6ecf3;
}

.operations-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.2fr) minmax(340px, .8fr);
}

.roster-section {
    min-width: 0;
    padding: 18px 20px 20px;
    border-right: 1px solid #e6ecf3;
}

.operations-aside {
    min-width: 0;
}

.operation-panel {
    padding: 18px 20px;
}

.operation-panel + .operation-panel {
    border-top: 1px solid #e6ecf3;
}

.operation-heading {
    display: flex;
    min-width: 0;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.operation-heading > div {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 11px;
}

.operation-heading > div > span {
    display: grid;
    width: 38px;
    height: 38px;
    flex: none;
    place-items: center;
    border-radius: 10px;
    background: #eff5ff;
    color: #2563eb;
}

.operation-heading > div > span svg {
    width: 18px;
    height: 18px;
}

.operation-heading h3 {
    color: #0b214d;
    font-size: 14px;
    font-weight: 750;
}

.operation-heading p {
    margin-top: 3px;
    color: #64748b;
    font-size: 11px;
    line-height: 1.45;
}

.operation-heading :deep(.ant-btn) {
    display: inline-flex;
    min-height: 36px;
    flex: none;
    align-items: center;
    gap: 6px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
}

.record-list,
.schedule-records {
    max-height: 260px;
    overflow-y: auto;
    border-top: 1px solid #e6ecf3;
}

.record-list > div {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 11px;
    min-height: 62px;
    border-bottom: 1px solid #edf1f6;
    transition: background-color 150ms ease;
}

.record-list > div:hover,
.schedule-records > div:hover {
    background: #fbfdff;
}

.record-list i {
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border-radius: 50%;
    background: #eff5ff;
    color: #1d4ed8;
    font-size: 12px;
    font-style: normal;
    font-weight: 750;
}

.record-list span {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

.record-list b,
.record-list small,
.schedule-records b,
.schedule-records small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.record-list b,
.schedule-records b {
    color: #1e293b;
    font-size: 13px;
    font-weight: 700;
}

.record-list small,
.schedule-records small {
    margin-top: 3px;
    color: #64748b;
    font-size: 11px;
}

.compact-record-list {
    max-height: 190px;
}

.schedule-records > div {
    display: flex;
    align-items: center;
    gap: 11px;
    min-height: 58px;
    border-bottom: 1px solid #edf1f6;
    transition: background-color 150ms ease;
}

.schedule-records > div > span {
    display: grid;
    width: 34px;
    height: 34px;
    flex: none;
    place-items: center;
    border-radius: 9px;
    background: #f1f5f9;
    color: #64748b;
}

.schedule-records svg {
    width: 16px;
    height: 16px;
}

.schedule-records > div > div {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

@container class-edit (max-width: 980px) {
    .class-admin-hero {
        grid-template-columns: auto minmax(0, 1fr);
    }

    .class-admin-actions {
        grid-column: 1 / -1;
        justify-content: flex-end;
        padding-top: 2px;
    }

    .operations-layout {
        grid-template-columns: 1fr;
    }

    .roster-section {
        border-right: 0;
        border-bottom: 1px solid #e6ecf3;
    }

    .operations-aside {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .operation-panel + .operation-panel {
        border-top: 0;
        border-left: 1px solid #e6ecf3;
    }
}

@container class-edit (max-width: 700px) {
    .class-edit-page {
        gap: 14px;
    }

    .class-admin-hero {
        grid-template-columns: auto minmax(0, 1fr);
        gap: 16px;
        padding: 18px;
    }

    .class-hero-icon {
        width: 54px;
        height: 54px;
        border-radius: 14px;
    }

    .class-hero-icon > svg {
        width: 26px;
        height: 26px;
    }

    .class-admin-identity h2 {
        font-size: 21px;
    }

    .class-admin-actions {
        justify-content: space-between;
    }

    .class-admin-actions .class-back-button {
        margin-left: auto;
    }

    .section-heading,
    .operations-heading {
        padding: 17px 18px 14px;
    }

    .edit-info-card :deep(.ant-form) {
        padding: 18px;
    }

    .edit-form-grid {
        grid-template-columns: 1fr;
    }

    .edit-room-field {
        grid-column: auto;
    }

    .form-save-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .form-save-row :deep(.ant-btn) {
        justify-content: center;
    }

    .operations-aside {
        grid-template-columns: 1fr;
    }

    .operation-panel + .operation-panel {
        border-top: 1px solid #e6ecf3;
        border-left: 0;
    }

    .roster-section,
    .operation-panel {
        padding: 16px 18px;
    }
}

@container class-edit (max-width: 460px) {
    .class-admin-hero {
        grid-template-columns: 1fr;
    }

    .class-hero-icon {
        display: none;
    }

    .class-admin-actions {
        display: grid;
        grid-template-columns: 1fr;
        justify-items: stretch;
    }

    .class-admin-actions .class-status {
        justify-self: start;
    }

    .class-admin-actions .class-back-button {
        width: 100%;
        margin-left: 0;
        justify-content: center;
    }

    .form-save-row {
        grid-template-columns: 1fr;
    }

    .operation-heading {
        align-items: stretch;
        flex-direction: column;
    }

    .operation-heading :deep(.ant-btn) {
        justify-content: center;
    }

    .record-list > div {
        grid-template-columns: auto minmax(0, 1fr);
        padding-block: 9px;
    }

    .record-list > div > .ant-tag {
        grid-column: 2;
        justify-self: start;
    }
}

@media (prefers-reduced-motion: reduce) {
    .record-list > div,
    .schedule-records > div {
        transition: none;
    }
}
</style>

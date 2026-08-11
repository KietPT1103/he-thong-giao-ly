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
    ArrowLeft, CalendarDays, Clock3, Pencil, RotateCcw, Save,
    UserRoundCheck, UsersRound,
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
const activeSection = ref("info");
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
function goToSection(section:string) {
    activeSection.value = section;
    document.getElementById(`class-section-${section}`)?.scrollIntoView({ block:"start" });
}
onMounted(load);
</script>

<template>
    <section class="class-edit-page mx-auto w-full max-w-[1500px]">
        <header class="class-admin-header">
            <div class="class-admin-header-row">
                <div class="class-admin-identity">
                    <div class="min-w-0">
                        <p>Đang quản lý</p>
                        <h1>{{ model?.name || "Đang tải lớp học" }}</h1>
                        <small v-if="model">{{ model.code }} · {{ model.parish?.name || "Chưa xác định giáo xứ" }} · {{ model.academic_year?.name || "Chưa có niên khóa" }}</small>
                    </div>
                </div>
                <div class="class-admin-actions">
                    <ATag v-if="model" :color="archived ? 'default' : model.status === 'active' ? 'success' : 'warning'">{{ archived ? "Đã lưu trữ" : model.status === "active" ? "Đang hoạt động" : "Tạm ngưng" }}</ATag>
                    <AButton v-if="model && archived" :loading="saving" @click="action='restore'"><template #icon><RotateCcw class="size-4" /></template>Khôi phục</AButton>
                    <AButton class="class-back-button" aria-label="Quay lại danh sách lớp học" @click="router.push('/admin/classes')">
                        <template #icon><ArrowLeft class="size-4" /></template>
                        Quay lại danh sách
                    </AButton>
                </div>
            </div>
            <nav v-if="model" class="class-section-nav" aria-label="Các phần thông tin lớp học">
                <button :class="{active:activeSection==='info'}" type="button" @click="goToSection('info')"><span>1</span>Thông tin chung</button>
                <button :class="{active:activeSection==='children'}" type="button" @click="goToSection('children')"><span>2</span>Danh sách thiếu nhi</button>
                <button :class="{active:activeSection==='teachers'}" type="button" @click="goToSection('teachers')"><span>3</span>Giáo lý viên</button>
                <button :class="{active:activeSection==='schedule'}" type="button" @click="goToSection('schedule')"><span>4</span>Lịch học định kỳ</button>
            </nav>
        </header>

        <AAlert v-if="pageError" type="error" show-icon :message="pageError" class="mb-4"><template #action><AButton size="small" @click="load">Thử lại</AButton></template></AAlert>
        <ACard v-if="loading" :bordered="false" class="admin-card class-loading-card"><ASkeleton active :paragraph="{rows:10}" /></ACard>

        <template v-else-if="model">
            <AAlert v-if="archived" type="warning" show-icon message="Lớp đã được lưu trữ. Khôi phục lớp trước khi thay đổi thông tin, thành viên hoặc lịch học." class="mb-4" />
            <ACard id="class-section-info" :bordered="false" class="admin-card edit-info-card class-scroll-section">
                    <div class="section-heading">
                        <span><Pencil /></span>
                        <div><h2>Thông tin chung</h2><p>Cập nhật tên, tổ chức, niên khóa và phòng học của lớp.</p></div>
                    </div>
                    <AForm ref="formRef" :model="form" :disabled="saving || archived" layout="vertical" @finish="saveInfo">
                        <div class="edit-form-grid">
                            <AFormItem label="Tên lớp" name="name" :rules="[{required:true,message:'Hãy nhập tên lớp.'}]" :help="formErrors.name" :validate-status="formErrors.name?'error':undefined"><AInput v-model:value="form.name" size="large" placeholder="Ví dụ: Thiếu Nhi 1A" /></AFormItem>
                            <AFormItem label="Mã lớp" name="code" :rules="[{required:true,message:'Hãy nhập mã lớp.'}]" :help="formErrors.code" :validate-status="formErrors.code?'error':undefined"><AInput v-model:value="form.code" size="large" placeholder="Ví dụ: TN-1A" /></AFormItem>
                            <AFormItem label="Giáo xứ"><ASelect v-model:value="form.parish_id" size="large" disabled :options="optionList(options.parishes)" /></AFormItem>
                            <AFormItem label="Trạng thái" name="status" required><ASelect v-model:value="form.status" size="large" :options="[{value:'active',label:'Đang hoạt động'},{value:'inactive',label:'Tạm ngưng'}]" /></AFormItem>
                            <AFormItem label="Niên khóa" name="academic_year_id" :rules="[{required:true,message:'Hãy chọn niên khóa.'}]" :help="formErrors.academic_year_id" :validate-status="formErrors.academic_year_id?'error':undefined"><ASelect v-model:value="form.academic_year_id" size="large" :options="optionList(options.academic_years)" /></AFormItem>
                            <AFormItem label="Khối giáo lý" name="catechism_level_id" :rules="[{required:true,message:'Hãy chọn khối giáo lý.'}]" :help="formErrors.catechism_level_id" :validate-status="formErrors.catechism_level_id?'error':undefined"><ASelect v-model:value="form.catechism_level_id" size="large" :options="optionList(options.levels)" /></AFormItem>
                            <AFormItem class="edit-room-field" label="Phòng học" name="classroom_id" :help="formErrors.classroom_id" :validate-status="formErrors.classroom_id?'error':undefined"><ASelect v-model:value="form.classroom_id" allow-clear size="large" placeholder="Chưa xếp phòng" :options="optionList(options.classrooms)" /></AFormItem>
                        </div>
                        <div class="form-save-row"><AButton type="primary" size="large" html-type="submit" :loading="saving" :disabled="archived"><template #icon><Save class="size-4" /></template>Cập nhật lớp học</AButton></div>
                    </AForm>
            </ACard>

            <ACard :bordered="false" class="admin-card class-operations-card">
                <div class="operations-heading">
                    <div><h2>Tổ chức lớp học</h2><p>Quản lý thiếu nhi, giáo lý viên phụ trách và lịch học trong một nơi.</p></div>
                </div>
                <div class="operations-layout">
                    <section id="class-section-children" class="roster-section class-scroll-section">
                        <div class="operation-heading">
                            <div><span><UsersRound /></span><div><h3>Danh sách thiếu nhi</h3><p>{{ model.enrollments_count }} em đang được xếp vào lớp</p></div></div>
                            <AButton :disabled="archived" @click="enrollmentOpen=true"><template #icon><Pencil class="size-4" /></template>Cập nhật danh sách</AButton>
                        </div>
                        <div v-if="model.enrollments?.length" class="record-list roster-list">
                            <div v-for="enrollment in model.enrollments" :key="enrollment.id"><i>{{ enrollment.child.full_name.slice(0,1) }}</i><span><b>{{ enrollment.child.full_name }}</b><small>{{ enrollment.child.code }}</small></span><ATag :color="enrollment.status==='active'?'success':'default'">{{ enrollment.status==='active'?'Đang học':'Đã rút' }}</ATag></div>
                        </div>
                        <AEmpty v-else description="Chưa có thiếu nhi trong lớp." class="py-10" />
                    </section>

                    <aside class="operations-aside">
                        <section id="class-section-teachers" class="operation-panel class-scroll-section">
                            <div class="operation-heading">
                                <div><span><UserRoundCheck /></span><div><h3>Giáo lý viên</h3><p>{{ model.teachers_count }} người phụ trách</p></div></div>
                                <AButton type="text" :disabled="archived" @click="teacherOpen=true">Phân công</AButton>
                            </div>
                            <div v-if="model.teachers?.length" class="record-list compact-record-list"><div v-for="teacher in model.teachers" :key="teacher.id"><i>{{ teacher.name.slice(0,1) }}</i><span><b>{{ teacher.name }}</b><small>{{ teacher.email }}</small></span><ATag>{{ teacher.role === 'primary' ? 'Phụ trách chính' : 'Phụ tá' }}</ATag></div></div>
                            <AEmpty v-else description="Chưa phân công giáo lý viên." class="py-6" />
                        </section>
                        <section id="class-section-schedule" class="operation-panel class-scroll-section">
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
.class-edit-page{display:grid;gap:1rem}.class-admin-header{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:1rem;padding:.25rem 0}.class-back-button{align-self:start}.class-admin-identity{display:flex;min-width:0;align-items:center;gap:.8rem}.class-admin-identity>span,.section-heading>span,.overview-heading>span,.operation-heading>div>span{display:grid;width:2.5rem;height:2.5rem;flex:none;place-items:center;border-radius:.7rem;background:#edf4ff;color:#185fce}.class-admin-identity>span{width:3rem;height:3rem;border-radius:.875rem}.class-admin-identity svg,.section-heading svg,.overview-heading svg,.operation-heading svg{width:1.1rem;height:1.1rem}.class-admin-identity p,.class-admin-identity h1,.class-admin-identity small{margin:0}.class-admin-identity p{color:#64748b;font-size:.68rem;font-weight:650}.class-admin-identity h1{overflow:hidden;margin-top:.08rem;color:#0b214d;font-size:1.35rem;font-weight:760;letter-spacing:-.025em;text-overflow:ellipsis;white-space:nowrap}.class-admin-identity small{display:block;overflow:hidden;margin-top:.12rem;color:#64748b;font-size:.7rem;text-overflow:ellipsis;white-space:nowrap}.class-admin-actions{display:flex;align-items:center;justify-content:flex-end;gap:.5rem}.class-loading-card{padding:.5rem}.class-admin-layout{display:grid;grid-template-columns:minmax(0,1fr) minmax(16rem,20rem);align-items:start;gap:1rem}.edit-info-card :deep(.ant-card-body){padding:1.25rem}.section-heading,.overview-heading{display:flex;align-items:center;gap:.75rem;margin-bottom:1.25rem}.section-heading h2,.section-heading p,.overview-heading h2,.overview-heading p,.operations-heading h2,.operations-heading p,.operation-heading h3,.operation-heading p{margin:0}.section-heading h2,.overview-heading h2,.operations-heading h2,.operation-heading h3{color:#0b214d;font-size:.9rem;font-weight:750}.section-heading p,.overview-heading p,.operations-heading p,.operation-heading p{margin-top:.15rem;color:#64748b;font-size:.7rem;line-height:1.5}.edit-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 1rem}.edit-room-field{grid-column:1/-1}.edit-info-card :deep(.ant-form-item){margin-bottom:1rem}.edit-info-card :deep(.ant-form-item-label>label){color:#334155;font-size:.72rem;font-weight:650}.edit-info-card :deep(.ant-input),.edit-info-card :deep(.ant-select-selector){border-radius:.625rem!important;box-shadow:none!important}.mobile-save-row{display:none}.class-overview-card{position:sticky;top:5.5rem;overflow:hidden;border:1px solid #dbe3ee;border-radius:.875rem;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.03)}.overview-heading{margin:0;padding:1rem;border-bottom:1px solid #e2e8f0}.class-overview-card dl{margin:0;padding:0 1rem}.class-overview-card dl>div{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.8rem 0;border-bottom:1px solid #eef2f7}.class-overview-card dl>div:last-child{border-bottom:0}.class-overview-card dt{display:flex;align-items:center;gap:.45rem;color:#64748b;font-size:.68rem}.class-overview-card dt svg{width:.9rem;height:.9rem;color:#7c8da6}.class-overview-card dd{overflow:hidden;margin:0;color:#0b214d;font-size:.72rem;font-weight:700;text-align:right;text-overflow:ellipsis;white-space:nowrap}.class-operations-card :deep(.ant-card-body){padding:0}.operations-heading{padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0}.operations-layout{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(20rem,.8fr)}.roster-section{min-width:0;padding:1.25rem;border-right:1px solid #e2e8f0}.operations-aside{min-width:0}.operation-panel{padding:1.25rem}.operation-panel+.operation-panel{border-top:1px solid #e2e8f0}.operation-heading{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem}.operation-heading>div{display:flex;min-width:0;align-items:center;gap:.65rem}.operation-heading>div>span{width:2.25rem;height:2.25rem}.record-list,.schedule-records{max-height:24rem;overflow-y:auto;border-top:1px solid #e2e8f0}.record-list>div{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.65rem;min-height:3.75rem;border-bottom:1px solid #eef2f7}.record-list i{display:grid;width:2rem;height:2rem;place-items:center;border-radius:.5rem;background:#f1f5f9;color:#475569;font-size:.7rem;font-style:normal;font-weight:700}.record-list span{display:flex;min-width:0;flex-direction:column}.record-list b,.record-list small,.schedule-records b,.schedule-records small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.record-list b,.schedule-records b{color:#1e293b;font-size:.75rem}.record-list small,.schedule-records small{margin-top:.12rem;color:#64748b;font-size:.65rem}.compact-record-list{max-height:13rem}.schedule-records>div{display:flex;align-items:center;gap:.65rem;min-height:3.5rem;border-bottom:1px solid #eef2f7}.schedule-records>div>span{display:grid;width:2rem;height:2rem;flex:none;place-items:center;border-radius:.5rem;background:#f1f5f9;color:#64748b}.schedule-records svg{width:.95rem;height:.95rem}.schedule-records>div>div{display:flex;min-width:0;flex-direction:column}@media(max-width:1399px){.class-admin-header{grid-template-columns:auto minmax(0,1fr)}.class-admin-actions{grid-column:1/-1}}@media(max-width:1199px){.class-admin-layout{grid-template-columns:minmax(0,1fr) 17rem}.operations-layout{grid-template-columns:1fr}.roster-section{border-right:0;border-bottom:1px solid #e2e8f0}.operations-aside{display:grid;grid-template-columns:1fr 1fr}.operation-panel+.operation-panel{border-top:0;border-left:1px solid #e2e8f0}}@media(max-width:767px){.class-admin-header{grid-template-columns:1fr}.class-back-button{justify-self:start}.class-admin-actions{grid-column:auto;flex-wrap:wrap;justify-content:flex-start}.class-admin-actions>.ant-btn-primary{display:none}.class-admin-layout{grid-template-columns:1fr}.class-overview-card{position:static}.edit-form-grid{grid-template-columns:1fr}.edit-room-field{grid-column:auto}.mobile-save-row{display:flex;justify-content:flex-end;padding-top:.25rem}.mobile-save-row .ant-btn{width:100%}.operations-aside{grid-template-columns:1fr}.operation-panel+.operation-panel{border-top:1px solid #e2e8f0;border-left:0}.operation-heading{align-items:flex-start}.roster-section,.operation-panel{padding:1rem}.operations-heading{padding:1rem}}@media(max-width:479px){.class-admin-identity h1{font-size:1.15rem}.class-admin-actions>.ant-tag{width:100%;margin:0;text-align:center}.class-admin-actions>.ant-btn{flex:1}.record-list>div{grid-template-columns:auto minmax(0,1fr)}.record-list>div>.ant-tag{grid-column:2;justify-self:start}.operation-heading{flex-direction:column}.operation-heading>.ant-btn{width:100%}}
.class-admin-header{display:flex;min-width:0;align-items:center;justify-content:space-between;gap:1.5rem;padding:1rem 1.125rem;border:1px solid #dbe3ee;border-radius:.875rem;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.03)}.class-admin-header-main{display:flex;min-width:0;align-items:center;gap:1rem}.class-back-button{align-self:auto;flex:none}.class-header-divider{width:1px;height:2.75rem;flex:none;background:#e2e8f0}.class-admin-identity>span{width:2.75rem;height:2.75rem}.class-admin-identity h1{font-size:1.2rem}.class-admin-actions{flex:none}.edit-info-card :deep(.ant-card-body){padding:1.5rem}.edit-form-grid{grid-template-columns:minmax(0,1.25fr) minmax(15rem,.75fr);gap:0 1.25rem}@media(max-width:1023px){.class-admin-header{align-items:flex-start}.class-admin-header-main{align-items:flex-start}.class-admin-actions{flex-wrap:wrap}.edit-form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:767px){.class-admin-header{align-items:stretch;flex-direction:column;padding:1rem}.class-admin-header-main{display:grid;grid-template-columns:1fr}.class-header-divider{display:none}.class-admin-actions{justify-content:space-between}.class-admin-actions>.ant-tag{display:inline-flex;align-items:center}.class-admin-actions>.ant-btn-primary{display:none}.edit-info-card :deep(.ant-card-body){padding:1rem}.edit-form-grid{grid-template-columns:1fr}}@media(max-width:479px){.class-admin-actions>.ant-tag{width:auto}.class-admin-actions>.ant-btn{flex:0 0 auto}}
.class-admin-header{display:block;padding:0}.class-admin-header-row{display:flex;min-width:0;align-items:center;justify-content:space-between;gap:1.5rem;padding:1rem 1.125rem}.class-admin-identity{display:block}.class-admin-identity p{text-transform:uppercase;letter-spacing:.06em}.class-admin-actions{display:flex;align-items:center;gap:.65rem}.class-section-nav{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.5rem;padding:.75rem 1.125rem;border-top:1px solid #e2e8f0;background:#f8fafc}.class-section-nav button{display:flex;min-width:0;min-height:2.5rem;align-items:center;gap:.5rem;padding:.5rem .75rem;border:1px solid #e2e8f0;border-radius:.625rem;background:#fff;color:#475569;font-size:.7rem;font-weight:650;text-align:left;transition:border-color 160ms ease,background-color 160ms ease,color 160ms ease,box-shadow 160ms ease}.class-section-nav button:hover{border-color:#93c5fd;background:#f8fbff;color:#185fce}.class-section-nav button:focus-visible{outline:2px solid #1677ff;outline-offset:2px}.class-section-nav button span{display:grid;width:1.25rem;height:1.25rem;flex:none;place-items:center;border-radius:999px;background:#f1f5f9;color:#64748b;font-size:.62rem}.class-section-nav button.active{border-color:#93c5fd;background:#eff6ff;color:#185fce;box-shadow:inset 0 0 0 1px rgba(59,130,246,.08)}.class-section-nav button.active span{background:#185fce;color:#fff}.class-scroll-section{scroll-margin-top:5.5rem}.form-save-row{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding-top:1rem;border-top:1px solid #e2e8f0}.form-save-row span{color:#64748b;font-size:.68rem}.form-save-row .ant-btn{flex:none}@media(max-width:1023px){.class-admin-header-row{align-items:flex-start}.class-admin-actions{flex-wrap:wrap;justify-content:flex-end}}@media(max-width:767px){.class-admin-header-row{align-items:stretch;flex-direction:column;padding:1rem}.class-admin-actions{justify-content:space-between}.class-section-nav{grid-template-columns:1fr 1fr;padding:.75rem 1rem}.form-save-row{align-items:stretch;flex-direction:column}.form-save-row .ant-btn{width:100%}}@media(max-width:479px){.class-section-nav{grid-template-columns:1fr}.class-admin-actions>.ant-tag{width:auto}.class-back-button{flex:0 0 auto}}
</style>

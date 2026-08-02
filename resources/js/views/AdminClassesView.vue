<script setup lang="ts">
import { onMounted, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import ADrawer from "ant-design-vue/es/drawer";
import AEmpty from "ant-design-vue/es/empty";
import AInput from "ant-design-vue/es/input";
import { default as AModal } from "ant-design-vue/es/modal";
import APagination from "ant-design-vue/es/pagination";
import ASelect from "ant-design-vue/es/select";
import ASpin from "ant-design-vue/es/spin";
import ATable from "ant-design-vue/es/table";
import ATag from "ant-design-vue/es/tag";
import ATooltip from "ant-design-vue/es/tooltip";
import type { ColumnsType } from "ant-design-vue/es/table/interface";
import { Archive, BookOpen, CalendarDays, Eye, GraduationCap, Pencil, Plus, RefreshCw, RotateCcw, Search, UserRoundCheck, UsersRound } from "lucide-vue-next";
import { toast } from "vue-sonner";
import {
    archiveClass, assignClassTeachers, createClass, getClass, getClassOptions, listClasses, restoreClass,
    updateClass, updateClassEnrollments, updateClassSchedules,
    type AdminClass, type AdminListMeta, type BusinessApiError, type ClassInput, type ClassOptions, type ClassScheduleInput,
} from "../api/admin";
import AdminActionConfirmModal from "../components/AdminActionConfirmModal.vue";
import AdminClassEnrollmentModal from "../components/AdminClassEnrollmentModal.vue";
import AdminClassFormModal from "../components/AdminClassFormModal.vue";
import AdminClassScheduleModal from "../components/AdminClassScheduleModal.vue";
import AdminClassTeacherModal from "../components/AdminClassTeacherModal.vue";

const emptyOptions = ():ClassOptions => ({parishes:[],academic_years:[],levels:[],classrooms:[],teachers:[],children:[]});
const classes = ref<AdminClass[]>([]);
const selected = ref<AdminClass|null>(null);
const options = ref<ClassOptions>(emptyOptions());
const meta = ref<AdminListMeta>({current_page:1,last_page:1,per_page:15,total:0});
const search = ref("");
const parishId = ref<number|undefined>();
const yearId = ref<number|undefined>();
const levelId = ref<number|undefined>();
const status = ref<"active"|"inactive"|"archived"|undefined>();
const loading = ref(true);
const detailLoading = ref(false);
const listError = ref("");
const detailError = ref("");
const formOpen = ref(false);
const editing = ref<AdminClass|null>(null);
const formErrors = ref<Record<string,string>>({});
const teacherOpen = ref(false);
const enrollmentOpen = ref(false);
const scheduleOpen = ref(false);
const saving = ref(false);
const action = ref<"archive"|"restore"|null>(null);
const actionError = ref("");
const columns:ColumnsType<AdminClass> = [
    {title:"Lớp học",key:"class",width:245},{title:"Tổ chức",key:"organization",width:260,responsive:["md"]},
    {title:"Phụ trách",key:"people",width:165,responsive:["lg"]},{title:"Lịch học",key:"schedule",width:185,responsive:["lg"]},
    {title:"Trạng thái",key:"status",width:125},{title:"",key:"action",width:60,fixed:"right",align:"center"},
];
const weekday = ["","Thứ hai","Thứ ba","Thứ tư","Thứ năm","Thứ sáu","Thứ bảy","Chủ nhật"];
const apiData = (error:unknown) => (error as BusinessApiError).response?.data;
const apiMessage = (error:unknown, fallback:string) => apiData(error)?.message ?? fallback;
const optionList = (items:Array<{id:number;name:string;code?:string}>) => items.map(item => ({value:item.id,label:item.code ? `${item.name} (${item.code})` : item.name}));

async function load(page = 1) {
    loading.value = true; listError.value = "";
    try {
        const response = await listClasses({search:search.value.trim() || undefined,parish_id:parishId.value,academic_year_id:yearId.value,catechism_level_id:levelId.value,status:status.value,page});
        classes.value = response.data.data;
        meta.value = response.data.meta as unknown as AdminListMeta;
    } catch (error) { listError.value = apiMessage(error,"Không thể tải danh sách lớp học."); }
    finally { loading.value = false; }
}

async function loadOptions(id?:number, query?:string) {
    try { options.value = (await getClassOptions(id,query)).data.data; }
    catch { toast.error("Không thể tải danh mục lớp học."); }
}

async function changeParish(id?:number) {
    parishId.value = id; yearId.value = undefined; levelId.value = undefined;
    await loadOptions(id); await load(1);
}

function selectParish(value:unknown) {
    void changeParish(value === undefined ? undefined : Number(value));
}

async function openDetails(item:AdminClass) {
    selected.value = item; detailLoading.value = true; detailError.value = "";
    try {
        selected.value = (await getClass(item.id,item.is_archived)).data.data;
        await loadOptions(selected.value.parish?.id);
    } catch (error) { detailError.value = apiMessage(error,"Không thể tải chi tiết lớp học."); }
    finally { detailLoading.value = false; }
}

async function openCreate() { editing.value = null; formErrors.value = {}; await loadOptions(); formOpen.value = true; }
async function openEdit() { if (!selected.value) return; editing.value = selected.value; formErrors.value = {}; await loadOptions(selected.value.parish?.id); formOpen.value = true; }
function closeForm() { formOpen.value = false; editing.value = null; formErrors.value = {}; }
function validationErrors(error:unknown) { return Object.fromEntries(Object.entries(apiData(error)?.errors ?? {}).map(([key,value]) => [key,value[0]])); }

async function saveClass(payload:ClassInput) {
    saving.value = true; formErrors.value = {};
    try {
        const wasEditing = Boolean(editing.value);
        const response = editing.value ? await updateClass(editing.value.id,payload) : await createClass(payload);
        toast.success(wasEditing ? "Đã cập nhật lớp học." : "Đã tạo lớp học.");
        closeForm(); await load(wasEditing ? meta.value.current_page : 1);
        selected.value = response.data.data;
    } catch (error) { formErrors.value = validationErrors(error); toast.error(apiMessage(error,"Không thể lưu lớp học.")); }
    finally { saving.value = false; }
}

async function saveTeachers(rows:Array<{teacher_id:number;role:"primary"|"assistant"}>, allow = false) {
    if (!selected.value) return; saving.value = true;
    try {
        selected.value = (await assignClassTeachers(selected.value.id,rows,allow)).data.data;
        teacherOpen.value = false; toast.success("Đã cập nhật giáo lý viên phụ trách."); await load(meta.value.current_page);
    } catch (error) {
        const data = apiData(error);
        if (data?.code === "TEACHER_SCHEDULE_CONFLICT" && !allow) {
            AModal.confirm({title:"Giáo lý viên bị trùng lịch",content:`${data.data?.conflicts?.map(item => `${item.teacher_name}: ${item.class_name}`).join("; ")}. Vẫn lưu phân công?`,okText:"Vẫn phân công",cancelText:"Kiểm tra lại",onOk:() => saveTeachers(rows,true)});
        } else toast.error(data?.message ?? "Không thể cập nhật phân công.");
    } finally { saving.value = false; }
}

async function saveEnrollments(rows:Array<{child_id:number;status:"active"|"inactive"}>) {
    if (!selected.value) return; saving.value = true;
    try { selected.value = (await updateClassEnrollments(selected.value.id,rows)).data.data; enrollmentOpen.value = false; toast.success("Đã cập nhật ghi danh."); await load(meta.value.current_page); }
    catch (error) { toast.error(apiMessage(error,"Không thể cập nhật ghi danh.")); }
    finally { saving.value = false; }
}

async function saveSchedules(rows:ClassScheduleInput[], allow = false) {
    if (!selected.value) return; saving.value = true;
    try { selected.value = (await updateClassSchedules(selected.value.id,rows,allow)).data.data; scheduleOpen.value = false; toast.success("Đã cập nhật lịch học."); await load(meta.value.current_page); }
    catch (error) {
        const data = apiData(error);
        if (data?.code === "TEACHER_SCHEDULE_CONFLICT" && !allow) {
            AModal.confirm({title:"Giáo lý viên bị trùng lịch",content:`${data.data?.conflicts?.map(item => `${item.teacher_name}: ${item.class_name}`).join("; ")}. Vẫn lưu lịch này?`,okText:"Vẫn lưu lịch",cancelText:"Chỉnh lại",onOk:() => saveSchedules(rows,true)});
        } else toast.error(data?.message ?? "Không thể cập nhật lịch học.");
    } finally { saving.value = false; }
}

async function confirmAction() {
    if (!selected.value || !action.value) return; saving.value = true; actionError.value = "";
    try {
        if (action.value === "archive") { await archiveClass(selected.value.id); selected.value = null; toast.success("Đã lưu trữ lớp học."); }
        else { selected.value = (await restoreClass(selected.value.id)).data.data; toast.success("Đã khôi phục lớp học."); }
        action.value = null; await load(meta.value.current_page);
    } catch (error) { actionError.value = apiMessage(error,"Không thể thực hiện thao tác."); }
    finally { saving.value = false; }
}

function rowInteractions(item:AdminClass) { return {class:"class-row",tabindex:0,"aria-label":`Xem lớp ${item.name}`,onClick:() => openDetails(item),onKeydown:(event:KeyboardEvent) => {if (["Enter"," "].includes(event.key)){event.preventDefault();void openDetails(item);}}}; }
onMounted(async () => { await Promise.all([load(),loadOptions()]); });
</script>

<template>
    <section class="class-management-page">
        <AAlert v-if="listError" type="error" show-icon :message="listError" class="mb-4"><template #action><AButton size="small" @click="load(meta.current_page)">Thử lại</AButton></template></AAlert>
        <ACard :bordered="false" class="admin-card admin-table-card">
            <div class="class-toolbar"><div class="min-w-0"><h2>Danh sách lớp học</h2><p>{{ meta.total.toLocaleString("vi-VN") }} lớp phù hợp</p></div><AButton type="primary" size="large" @click="openCreate"><template #icon><Plus class="size-4" /></template>Tạo lớp học</AButton></div>
            <div class="class-filters">
                <AInput v-model:value="search" allow-clear size="large" placeholder="Tìm tên hoặc mã lớp" @press-enter="load(1)"><template #prefix><Search class="size-4 text-slate-400" /></template></AInput>
                <ASelect v-model:value="parishId" allow-clear show-search option-filter-prop="label" size="large" placeholder="Tất cả giáo xứ" :options="optionList(options.parishes)" @change="selectParish" />
                <ASelect v-model:value="yearId" allow-clear size="large" placeholder="Tất cả niên khóa" :options="optionList(options.academic_years)" @change="load(1)" />
                <ASelect v-model:value="levelId" allow-clear size="large" placeholder="Tất cả khối" :options="optionList(options.levels)" @change="load(1)" />
                <ASelect v-model:value="status" allow-clear size="large" placeholder="Đang sử dụng" :options="[{value:'active',label:'Đang hoạt động'},{value:'inactive',label:'Tạm ngưng'},{value:'archived',label:'Đã lưu trữ'}]" @change="load(1)" />
                <AButton type="primary" size="large" :loading="loading" @click="load(1)"><template #icon><Search class="size-4" /></template>Tìm</AButton>
                <ATooltip title="Tải lại"><AButton class="class-refresh" size="large" :loading="loading" aria-label="Tải lại dữ liệu" @click="load(meta.current_page)"><template #icon><RefreshCw class="size-4" /></template></AButton></ATooltip>
            </div>
            <ATable :columns="columns" :custom-row="rowInteractions" :data-source="classes" :loading="loading" :pagination="false" :scroll="{x:1040}" row-key="id">
                <template #emptyText><AEmpty description="Không có lớp học phù hợp." /></template>
                <template #bodyCell="{column,record}">
                    <template v-if="column.key === 'class'"><div class="class-name-cell"><span><BookOpen class="size-5" /></span><div><b>{{ record.name }}</b><small>{{ record.code }}</small></div></div></template>
                    <template v-else-if="column.key === 'organization'"><div class="cell-stack"><b>{{ record.parish?.name }}</b><small>{{ record.academic_year?.name }} · {{ record.level?.name }}</small><small>{{ record.classroom?.name || "Chưa xếp phòng" }}</small></div></template>
                    <template v-else-if="column.key === 'people'"><div class="metric-lines"><span><UsersRound />{{ record.enrollments_count }} thiếu nhi</span><span><GraduationCap />{{ record.teachers_count }} GLV</span></div></template>
                    <template v-else-if="column.key === 'schedule'"><span v-if="record.schedules?.length" class="schedule-preview"><CalendarDays />{{ weekday[record.schedules[0].weekday] }}, {{ record.schedules[0].starts_at }}–{{ record.schedules[0].ends_at }}</span><span v-else class="muted-label">Chưa có lịch</span></template>
                    <template v-else-if="column.key === 'status'"><ATag :color="record.is_archived ? 'default' : record.status === 'active' ? 'success' : 'warning'">{{ record.is_archived ? 'Đã lưu trữ' : record.status === 'active' ? 'Đang hoạt động' : 'Tạm ngưng' }}</ATag></template>
                    <template v-else-if="column.key === 'action'"><ATooltip title="Xem chi tiết"><AButton type="text" class="icon-action-button" aria-label="Xem chi tiết lớp" @click.stop="openDetails(record as AdminClass)"><template #icon><Eye class="size-4" /></template></AButton></ATooltip></template>
                </template>
            </ATable>
            <div v-if="meta.total > meta.per_page" class="class-pagination"><APagination :current="meta.current_page" :page-size="meta.per_page" :total="meta.total" :show-size-changer="false" responsive @change="load" /></div>
        </ACard>
    </section>

    <ADrawer :open="Boolean(selected)" width="min(700px, 100vw)" title="Chi tiết lớp học" @close="selected = null">
        <template #extra><div v-if="selected" class="drawer-actions"><AButton v-if="selected.is_archived" @click="action='restore'"><template #icon><RotateCcw class="size-4" /></template>Khôi phục</AButton><template v-else><ATooltip title="Lưu trữ lớp"><AButton danger aria-label="Lưu trữ lớp" @click="action='archive'"><template #icon><Archive class="size-4" /></template></AButton></ATooltip><AButton @click="openEdit"><template #icon><Pencil class="size-4" /></template>Chỉnh sửa</AButton></template></div></template>
        <ASpin :spinning="detailLoading"><AAlert v-if="detailError" type="error" show-icon :message="detailError" class="mb-4" /><template v-if="selected">
            <div class="class-detail-head"><span><BookOpen /></span><div><h2>{{ selected.name }}</h2><p>{{ selected.code }} · {{ selected.parish?.name }}</p></div><ATag :color="selected.is_archived ? 'default' : selected.status === 'active' ? 'success' : 'warning'">{{ selected.is_archived ? 'Đã lưu trữ' : selected.status === 'active' ? 'Đang hoạt động' : 'Tạm ngưng' }}</ATag></div>
            <div class="detail-facts"><div><small>Niên khóa</small><b>{{ selected.academic_year?.name }}</b></div><div><small>Khối</small><b>{{ selected.level?.name }}</b></div><div><small>Phòng</small><b>{{ selected.classroom?.name || 'Chưa xếp' }}</b></div><div><small>Điểm danh</small><b>{{ selected.attendance_sessions_count }} buổi</b></div></div>
            <div class="detail-section"><div class="section-title"><div><h3>Giáo lý viên</h3><span>{{ selected.teachers_count }} người</span></div><AButton size="small" :disabled="selected.is_archived" @click="teacherOpen=true"><template #icon><UserRoundCheck class="size-4" /></template>Phân công</AButton></div><div v-if="selected.teachers?.length" class="detail-list"><div v-for="teacher in selected.teachers" :key="teacher.id"><span class="list-mark">{{ teacher.name.slice(0,1) }}</span><span><b>{{ teacher.name }}</b><small>{{ teacher.email }}</small></span><ATag>{{ teacher.role === 'primary' ? 'Phụ trách chính' : 'Phụ tá' }}</ATag></div></div><AEmpty v-else description="Chưa phân công giáo lý viên." /></div>
            <div class="detail-section"><div class="section-title"><div><h3>Thiếu nhi</h3><span>{{ selected.enrollments_count }} đang học</span></div><AButton size="small" :disabled="selected.is_archived" @click="enrollmentOpen=true"><template #icon><UsersRound class="size-4" /></template>Ghi danh</AButton></div><div v-if="selected.enrollments?.length" class="detail-list"><div v-for="enrollment in selected.enrollments" :key="enrollment.id"><span class="list-mark list-mark--neutral">{{ enrollment.child.full_name.slice(0,1) }}</span><span><b>{{ enrollment.child.full_name }}</b><small>{{ enrollment.child.code }}</small></span><ATag :color="enrollment.status === 'active' ? 'success' : 'default'">{{ enrollment.status === 'active' ? 'Đang học' : 'Đã rút' }}</ATag></div></div><AEmpty v-else description="Chưa có thiếu nhi trong lớp." /></div>
            <div class="detail-section"><div class="section-title"><div><h3>Lịch học</h3><span>{{ selected.schedules.length }} lịch</span></div><AButton size="small" :disabled="selected.is_archived" @click="scheduleOpen=true"><template #icon><CalendarDays class="size-4" /></template>Thiết lập</AButton></div><div v-if="selected.schedules.length" class="schedule-list"><div v-for="schedule in selected.schedules" :key="schedule.id"><CalendarDays /><span><b>{{ weekday[schedule.weekday] }}</b><small>{{ schedule.starts_at }}–{{ schedule.ends_at }}<template v-if="schedule.starts_on || schedule.ends_on"> · {{ schedule.starts_on || 'Đầu niên khóa' }} đến {{ schedule.ends_on || 'Cuối niên khóa' }}</template></small></span></div></div><AEmpty v-else description="Chưa thiết lập lịch học." /></div>
        </template></ASpin>
    </ADrawer>

    <AdminClassFormModal :open="formOpen" :model="editing" :options="options" :saving="saving" :errors="formErrors" @parish-change="loadOptions" @close="dirty => dirty ? AModal.confirm({title:'Bỏ thay đổi chưa lưu?',content:'Thông tin vừa nhập sẽ không được lưu.',okText:'Bỏ thay đổi',okType:'danger',cancelText:'Tiếp tục chỉnh sửa',onOk:closeForm}) : closeForm()" @submit="saveClass" />
    <AdminClassTeacherModal :open="teacherOpen" :model="selected" :teachers="options.teachers" :saving="saving" @close="teacherOpen=false" @search="query => loadOptions(selected?.parish?.id, query)" @submit="saveTeachers" />
    <AdminClassEnrollmentModal :open="enrollmentOpen" :model="selected" :children="options.children" :saving="saving" @close="enrollmentOpen=false" @search="query => loadOptions(selected?.parish?.id, query)" @submit="saveEnrollments" />
    <AdminClassScheduleModal :open="scheduleOpen" :model="selected" :saving="saving" @close="scheduleOpen=false" @submit="saveSchedules" />
    <AdminActionConfirmModal :open="action === 'archive'" title="Lưu trữ lớp học này?" description="Lớp sẽ ẩn khỏi danh sách đang sử dụng; toàn bộ phân công, ghi danh, lịch và điểm danh vẫn được giữ nguyên." confirm-text="Lưu trữ lớp" :target-name="selected?.name" danger :loading="saving" :error-message="actionError" @close="action=null" @confirm="confirmAction" />
    <AdminActionConfirmModal :open="action === 'restore'" title="Khôi phục lớp học này?" description="Lớp sẽ trở lại đúng trạng thái trước khi lưu trữ cùng toàn bộ dữ liệu liên quan." confirm-text="Khôi phục lớp" :target-name="selected?.name" :loading="saving" :error-message="actionError" @close="action=null" @confirm="confirmAction" />
</template>

<style scoped>
.class-management-page{width:100%}.class-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 1.25rem .9rem}.class-toolbar h2{margin:0;color:#0b214d;font-size:1rem;font-weight:700}.class-toolbar p{margin:.25rem 0 0;color:#64748b;font-size:.75rem}.class-filters{display:grid;grid-template-columns:minmax(12rem,1fr) repeat(4,minmax(8rem,.55fr)) auto auto;gap:.625rem;padding:0 1.25rem 1rem}.class-name-cell{display:flex;min-width:0;align-items:center;gap:.75rem}.class-name-cell>span,.class-detail-head>span{display:grid;width:2.5rem;height:2.5rem;flex:none;place-items:center;border-radius:.625rem;background:#edf4ff;color:#185fce}.class-name-cell div,.cell-stack{display:flex;min-width:0;flex-direction:column}.class-name-cell b,.cell-stack b{overflow:hidden;color:#0b214d;font-size:.78rem;text-overflow:ellipsis;white-space:nowrap}.class-name-cell small,.cell-stack small,.muted-label{margin-top:.15rem;color:#64748b;font-size:.68rem}.metric-lines{display:grid;gap:.35rem;color:#475569;font-size:.72rem}.metric-lines span,.schedule-preview{display:flex;align-items:center;gap:.4rem}.metric-lines svg,.schedule-preview svg{width:.9rem;height:.9rem;color:#64748b}.schedule-preview{color:#334155;font-size:.72rem;white-space:nowrap}.class-pagination{display:flex;justify-content:flex-end;padding:1rem 1.25rem;border-top:1px solid #e2e8f0}.drawer-actions{display:flex;gap:.5rem}.class-detail-head{display:grid;grid-template-columns:auto minmax(0,1fr) max-content;align-items:center;gap:.75rem;padding-bottom:1.25rem}.class-detail-head>.ant-tag{white-space:nowrap}.class-detail-head h2{margin:0;color:#0b214d;font-size:1.1rem;font-weight:750}.class-detail-head p{margin:.2rem 0 0;color:#64748b;font-size:.72rem}.detail-facts{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));overflow:hidden;border-block:1px solid #e2e8f0}.detail-facts div{display:flex;min-width:0;flex-direction:column;padding:.9rem;border-left:1px solid #e2e8f0}.detail-facts div:first-child{border-left:0}.detail-facts small{color:#64748b;font-size:.65rem}.detail-facts b{margin-top:.25rem;overflow:hidden;color:#1e293b;font-size:.75rem;text-overflow:ellipsis;white-space:nowrap}.detail-section{margin-top:1.5rem}.section-title{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:.6rem}.section-title>div{display:flex;align-items:baseline;gap:.5rem}.section-title h3{margin:0;color:#0b214d;font-size:.85rem;font-weight:700}.section-title span{color:#64748b;font-size:.68rem}.detail-list{border-top:1px solid #e2e8f0}.detail-list>div{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.7rem;min-height:3.6rem;border-bottom:1px solid #e2e8f0}.list-mark{display:grid;width:2rem;height:2rem;place-items:center;border-radius:.45rem;background:#edf4ff;color:#185fce;font-size:.7rem;font-weight:700}.list-mark--neutral{background:#f1f5f9;color:#475569}.detail-list>div>span:nth-child(2),.schedule-list span{display:flex;min-width:0;flex-direction:column}.detail-list b,.schedule-list b{overflow:hidden;color:#1e293b;font-size:.75rem;text-overflow:ellipsis;white-space:nowrap}.detail-list small,.schedule-list small{margin-top:.15rem;color:#64748b;font-size:.65rem}.schedule-list{border-top:1px solid #e2e8f0}.schedule-list>div{display:flex;align-items:center;gap:.75rem;min-height:3.4rem;border-bottom:1px solid #e2e8f0}.schedule-list svg{width:1rem;height:1rem;color:#64748b}.class-row{cursor:pointer}.class-row:focus-visible{outline:none}.class-row:focus-visible>td:first-child{box-shadow:inset 3px 0 #2563eb}@media(max-width:1279px){.class-filters{grid-template-columns:minmax(12rem,1fr) repeat(3,minmax(8rem,.6fr));}.class-filters>:nth-last-child(-n+3){grid-row:2}}@media(max-width:767px){.class-toolbar{align-items:stretch;flex-direction:column;padding:1rem}.class-toolbar .ant-btn{width:100%}.class-filters{grid-template-columns:1fr 1fr;padding:0 1rem 1rem}.class-filters>:first-child{grid-column:1/-1}.class-filters>:nth-last-child(-n+3){grid-row:auto}.class-filters>:nth-last-child(2){grid-column:1/-1}.class-filters>:last-child{display:none}.detail-facts{grid-template-columns:1fr 1fr}.detail-facts div:nth-child(3){border-left:0}.detail-facts div:nth-child(n+3){border-top:1px solid #e2e8f0}}@media(max-width:479px){.class-filters{grid-template-columns:1fr}.class-filters>*{grid-column:auto!important}.class-detail-head{grid-template-columns:auto minmax(0,1fr)}.class-detail-head>.ant-tag{grid-column:2}.section-title{align-items:flex-start}.detail-facts{grid-template-columns:1fr}.detail-facts div{border-top:1px solid #e2e8f0;border-left:0}.detail-facts div:first-child{border-top:0}}
.class-detail-head>.ant-tag{width:max-content;min-width:max-content}@media(max-width:767px){.class-refresh{display:none!important}}
</style>

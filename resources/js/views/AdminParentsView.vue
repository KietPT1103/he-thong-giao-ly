<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AEmpty from "ant-design-vue/es/empty";
import AInput from "ant-design-vue/es/input";
import APagination from "ant-design-vue/es/pagination";
import ASelect from "ant-design-vue/es/select";
import ATable from "ant-design-vue/es/table";
import ATag from "ant-design-vue/es/tag";
import ATooltip from "ant-design-vue/es/tooltip";
import type { ColumnsType } from "ant-design-vue/es/table/interface";
import { Archive, Mail, Pencil, Phone, Plus, RefreshCw, RotateCcw, Search, UserRound } from "lucide-vue-next";
import { toast } from "vue-sonner";
import { archiveParent, createParent, getParent, getParentOptions, listParents, restoreParent, updateParent, type AdminListMeta, type AdminParent, type ParentCreateInput, type ParentOptions, type ParentUpdateInput } from "../api/admin";
import AdminActionConfirmModal from "../components/AdminActionConfirmModal.vue";
import AdminParentFormModal, { type ParentFormErrors } from "../components/AdminParentFormModal.vue";
import { useAuthStore } from "../stores/authStore";

const auth = useAuthStore();
const rows = ref<AdminParent[]>([]);
const options = ref<ParentOptions|null>(null);
const meta = ref<AdminListMeta>({current_page:1,last_page:1,per_page:15,total:0});
const search = ref("");
const parishId = ref<number>();
const status = ref<"active"|"blocked"|"archived">();
const loading = ref(true);
const errorMessage = ref("");
const formOpen = ref(false);
const editing = ref<AdminParent|null>(null);
const saving = ref(false);
const formErrors = ref<ParentFormErrors>({});
const discardOpen = ref(false);
const action = ref<{type:"archive"|"restore";target:AdminParent}|null>(null);
const actionSaving = ref(false);
const actionError = ref("");
const confirmedUntil = ref(0);
const canCreate = computed(() => auth.hasPermission("create-parents"));
const canEdit = computed(() => auth.hasPermission("update-parents"));
const canLink = computed(() => auth.hasPermission("link-parent-child"));
const canArchive = computed(() => auth.hasPermission("manage-users"));
const needsPassword = computed(() => action.value?.type === "archive" && Date.now() >= confirmedUntil.value);
const columns:ColumnsType<AdminParent> = [
    {title:"Phụ huynh",key:"person",width:300},{title:"Liên hệ",key:"contact",responsive:["md"]},
    {title:"Giáo xứ & liên kết",key:"relations",width:250,responsive:["lg"]},{title:"Trạng thái",key:"status",width:140},
    {title:"",key:"action",width:104,fixed:"right",align:"center"},
];
const apiMessage = (error:unknown, fallback:string) => (error as {response?:{data?:{message?:string}}}).response?.data?.message ?? fallback;
function validationErrors(error:unknown):ParentFormErrors { const source=(error as {response?:{data?:{errors?:Record<string,string[]>}}}).response?.data?.errors??{}; return Object.fromEntries(Object.entries(source).map(([key,value])=>[key,value[0]])) as ParentFormErrors; }
function tag(row:AdminParent) { return row.is_archived ? {color:"default",label:"Đã lưu trữ"} : row.account_status === "blocked" ? {color:"error",label:"Đã khóa"} : {color:"success",label:"Hoạt động"}; }
async function load(page=1) { loading.value=true;errorMessage.value="";try { const response=await listParents({search:search.value.trim()||undefined,parish_id:parishId.value,status:status.value,page});rows.value=response.data.data;meta.value=response.data.meta as unknown as AdminListMeta; } catch(error) { errorMessage.value=apiMessage(error,"Không thể tải danh sách phụ huynh."); } finally { loading.value=false; } }
async function loadOptions() { try { options.value=(await getParentOptions()).data.data; } catch { toast.error("Không thể tải dữ liệu lựa chọn."); } }
function openCreate() { editing.value=null;formErrors.value={};formOpen.value=true; }
async function openEdit(row:AdminParent) { if (!canEdit.value || row.is_archived) return; loading.value=true;try { editing.value=(await getParent(row.id)).data.data;formErrors.value={};formOpen.value=true; } catch(error) { toast.error(apiMessage(error,"Không thể tải hồ sơ phụ huynh.")); } finally { loading.value=false; } }
function closeForm() { formOpen.value=false;editing.value=null;formErrors.value={};discardOpen.value=false; }
function requestClose(dirty:boolean) { if (saving.value) return; dirty ? discardOpen.value=true : closeForm(); }
async function save(payload:ParentCreateInput|ParentUpdateInput) { if(saving.value)return;saving.value=true;formErrors.value={};try { const current=editing.value;current ? await updateParent(current.id,payload as ParentUpdateInput) : await createParent(payload as ParentCreateInput);toast.success(current?"Đã cập nhật phụ huynh.":"Đã tạo phụ huynh.");closeForm();await Promise.all([load(current?meta.value.current_page:1),loadOptions()]); } catch(error) { formErrors.value=validationErrors(error);toast.error(apiMessage(error,"Không thể lưu hồ sơ phụ huynh.")); } finally { saving.value=false; } }
function requestAction(type:"archive"|"restore",target:AdminParent) { action.value={type,target};actionError.value=""; }
async function confirmAction(password:string) { if (!action.value||actionSaving.value) return;actionSaving.value=true;actionError.value="";try { if (needsPassword.value) { await auth.confirmPassword(password);confirmedUntil.value=Date.now()+15*60*1000; } const pending=action.value;pending.type==="archive"?await archiveParent(pending.target.id):await restoreParent(pending.target.id);toast.success(pending.type==="archive"?"Đã lưu trữ phụ huynh.":"Đã khôi phục phụ huynh.");action.value=null;await Promise.all([load(meta.value.current_page),loadOptions()]); } catch(error) { actionError.value=apiMessage(error,"Không thể thực hiện thao tác.");toast.error(actionError.value); } finally { actionSaving.value=false; } }
function rowInteractions(row:AdminParent) { return {class:canEdit.value&&!row.is_archived?"family-row":"",tabindex:canEdit.value&&!row.is_archived?0:-1,"aria-label":`Chỉnh sửa phụ huynh ${row.name}`,onClick:()=>void openEdit(row),onKeydown:(event:KeyboardEvent)=>{if((event.key==="Enter"||event.key===" ")&&canEdit.value&&!row.is_archived){event.preventDefault();void openEdit(row);}}}; }
onMounted(async()=>{await Promise.all([load(),loadOptions()]);});
</script>

<template>
    <section class="family-page">
        <AAlert v-if="errorMessage" type="error" show-icon closable :message="errorMessage" class="mb-4" @close="errorMessage=''" />
        <ACard :bordered="false" class="admin-card admin-table-card">
            <header class="family-toolbar"><div><h2>Danh sách phụ huynh</h2><p>{{ meta.total.toLocaleString('vi-VN') }} hồ sơ phù hợp</p></div><AButton v-if="canCreate" type="primary" size="large" @click="openCreate"><template #icon><Plus class="size-4" /></template>Tạo phụ huynh</AButton></header>
            <div class="family-filters">
                <AInput v-model:value="search" allow-clear size="large" placeholder="Tìm theo tên, email hoặc số điện thoại" @press-enter="load(1)"><template #prefix><Search class="size-4 text-slate-400" /></template></AInput>
                <ASelect v-model:value="parishId" allow-clear show-search option-filter-prop="label" size="large" placeholder="Tất cả giáo xứ" :options="options?.parishes.map(item=>({value:item.id,label:item.name}))??[]" @change="load(1)" />
                <ASelect v-model:value="status" allow-clear size="large" placeholder="Mọi trạng thái" :options="[{value:'active',label:'Hoạt động'},{value:'blocked',label:'Đã khóa'},{value:'archived',label:'Đã lưu trữ'}]" @change="load(1)" />
                <AButton type="primary" size="large" :loading="loading" @click="load(1)"><template #icon><Search class="size-4" /></template>Lọc</AButton>
                <ATooltip title="Tải lại"><AButton size="large" aria-label="Tải lại danh sách" :loading="loading" @click="load(meta.current_page)"><template #icon><RefreshCw class="size-4" /></template></AButton></ATooltip>
            </div>
            <ATable :columns="columns" :data-source="rows" :custom-row="rowInteractions" :loading="loading" :pagination="false" :scroll="{x:900}" row-key="id">
                <template #emptyText><AEmpty description="Không có phụ huynh phù hợp." /></template>
                <template #bodyCell="{column,record}">
                    <template v-if="column.key==='person'"><div class="flex min-w-0 items-center gap-3"><span class="family-avatar"><UserRound class="size-5" /></span><div class="min-w-0"><b class="block truncate text-[13px] text-blue-950">{{ record.name }}</b><span class="mt-0.5 block truncate text-xs text-slate-500">{{ record.email }}</span></div></div></template>
                    <template v-else-if="column.key==='contact'"><div class="space-y-1 text-xs text-slate-600"><div class="flex items-center gap-2"><Mail class="size-3.5 text-slate-400" />{{ record.email }}</div><div class="flex items-center gap-2"><Phone class="size-3.5 text-slate-400" />{{ record.phone||'Chưa cập nhật' }}</div></div></template>
                    <template v-else-if="column.key==='relations'"><div class="text-xs text-slate-700"><b class="block truncate font-medium">{{ record.parish.name }}</b><span class="mt-1 block text-slate-500">{{ record.children_count }} thiếu nhi liên kết</span></div></template>
                    <template v-else-if="column.key==='status'"><ATag :color="tag(record as AdminParent).color">{{ tag(record as AdminParent).label }}</ATag></template>
                    <template v-else-if="column.key==='action'"><div class="flex justify-center gap-1"><ATooltip v-if="canEdit&&!record.is_archived" title="Chỉnh sửa"><AButton type="text" class="icon-action-button" aria-label="Chỉnh sửa phụ huynh" @click.stop="openEdit(record as AdminParent)"><template #icon><Pencil class="size-4" /></template></AButton></ATooltip><ATooltip v-if="canArchive" :title="record.is_archived?'Khôi phục':'Lưu trữ'"><AButton type="text" class="icon-action-button" :class="record.is_archived?'is-success':'is-danger'" :aria-label="record.is_archived?'Khôi phục phụ huynh':'Lưu trữ phụ huynh'" @click.stop="requestAction(record.is_archived?'restore':'archive',record as AdminParent)"><template #icon><RotateCcw v-if="record.is_archived" class="size-4" /><Archive v-else class="size-4" /></template></AButton></ATooltip></div></template>
                </template>
            </ATable>
            <div v-if="meta.total>meta.per_page" class="flex justify-end border-t border-slate-100 px-4 py-3"><APagination :current="meta.current_page" :page-size="meta.per_page" :total="meta.total" :show-size-changer="false" responsive @change="load" /></div>
        </ACard>
    </section>
    <AdminParentFormModal :open="formOpen" :parent="editing" :options="options" :saving="saving" :errors="formErrors" :can-link="canLink" @close="requestClose" @submit="save" />
    <AdminActionConfirmModal :open="Boolean(action)" :title="action?.type==='archive'?'Lưu trữ phụ huynh này?':'Khôi phục phụ huynh này?'" :description="action?.type==='archive'?'Tài khoản sẽ bị đăng xuất và không thể truy cập hệ thống. Liên kết với thiếu nhi vẫn được bảo toàn.':'Tài khoản sẽ được mở lại và các liên kết trước đây được giữ nguyên.'" :confirm-text="action?.type==='archive'?'Lưu trữ':'Khôi phục'" :target-name="action?.target.name" :target-email="action?.target.email" :danger="action?.type==='archive'" :require-password="needsPassword" :loading="actionSaving" :error-message="actionError" @close="action=null" @confirm="confirmAction" />
    <AdminActionConfirmModal :open="discardOpen" title="Bỏ thay đổi chưa lưu?" description="Thông tin vừa nhập sẽ không được lưu lại." confirm-text="Bỏ thay đổi" danger @close="discardOpen=false" @confirm="closeForm" />
</template>

<style scoped>
.family-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 1.25rem .9rem}.family-toolbar h2{margin:0;color:#0b214d;font-size:1rem;font-weight:750}.family-toolbar p{margin:.25rem 0 0;color:#64748b;font-size:.75rem}.family-filters{display:grid;grid-template-columns:minmax(15rem,1fr) minmax(10rem,.5fr) minmax(9rem,.42fr) auto auto;gap:.625rem;padding:0 1.25rem 1rem}.family-avatar{display:grid;width:2.5rem;height:2.5rem;flex:none;place-items:center;border-radius:.75rem;background:#edf4ff;color:#185fce}.family-page :deep(.family-row){cursor:pointer}.family-page :deep(.family-row:hover>td){background:#f8fbff!important}@media(max-width:1023px){.family-filters{grid-template-columns:minmax(0,1fr) minmax(10rem,.6fr) auto}.family-filters>:nth-child(3){grid-column:1/2}}@media(max-width:639px){.family-toolbar{align-items:flex-start;padding:1rem}.family-filters{grid-template-columns:minmax(0,1fr) auto;padding:0 1rem 1rem}.family-filters>:nth-child(2),.family-filters>:nth-child(3),.family-filters>:nth-child(4){grid-column:1/3}}
</style>

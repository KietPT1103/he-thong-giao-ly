<!--
THESIS: Biến phần đầu trang từ một thẻ cấu hình nhỏ thành bảng điều khiển giáo xứ có điểm nhận diện rõ ràng.
OWN-WORLD: Nền trắng xanh, minh họa 3D mềm, chữ xanh mực và các control viền mảnh theo nhận diện hiện có.
STORY: Quản trị viên nhận biết đúng khu vực quản lý, chọn giáo xứ rồi thao tác với các danh mục dùng chung.
FIRST VIEWPORT: Minh họa và bộ chọn giáo xứ ở trái; nhãn ngữ cảnh, tiêu đề và mô tả lớn ở phải.
FORM: Hero vận hành hai cột theo ảnh tham chiếu của người dùng, thu gọn thành một cột trên màn hình nhỏ.
-->
<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AEmpty from "ant-design-vue/es/empty";
import AInput from "ant-design-vue/es/input";
import ASelect from "ant-design-vue/es/select";
import ASkeleton from "ant-design-vue/es/skeleton";
import ATag from "ant-design-vue/es/tag";
import ATooltip from "ant-design-vue/es/tooltip";
import {
    BookOpen, CalendarRange, Church, DoorOpen, Layers3, Pencil, Plus,
    Power, RefreshCw, Search, Settings2, Trash2,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import {
    createAcademicYear, createCatechismLevel, createClassroom,
    deleteAcademicYear, deleteCatechismLevel, deleteClassroom,
    getClassCatalogs, listParishes,
    updateAcademicYear, updateCatechismLevel, updateClassroom,
    type AcademicYearCatalog, type AcademicYearCatalogInput,
    type CatechismLevelCatalog, type CatechismLevelCatalogInput,
    type ClassCatalogs, type ClassroomCatalog, type ClassroomCatalogInput,
    type Parish,
} from "../api/admin";
import AdminActionConfirmModal from "../components/AdminActionConfirmModal.vue";
import ClassCatalogFormModal, {
    type ClassCatalogItem, type ClassCatalogPayload, type ClassCatalogType,
} from "../components/ClassCatalogFormModal.vue";
import { useAuthStore } from "../stores/authStore";

type CatalogStatus = "all" | "active" | "inactive";
const emptyCatalogs = ():ClassCatalogs => ({ academic_years:[], levels:[], classrooms:[] });
const catalogTabs:ClassCatalogType[] = ["academic_year", "level", "classroom"];
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const parishes = ref<Parish[]>([]);
const parishId = ref<number>();
const catalogs = ref<ClassCatalogs>(emptyCatalogs());
const requestedTab = route.query.tab as ClassCatalogType;
const activeTab = ref<ClassCatalogType>(catalogTabs.includes(requestedTab) ? requestedTab : "academic_year");
const search = ref("");
const status = ref<CatalogStatus>("all");
const loading = ref(true);
const listError = ref("");
const formOpen = ref(false);
const formType = ref<ClassCatalogType>("academic_year");
const editing = ref<ClassCatalogItem|null>(null);
const saving = ref(false);
const formErrors = ref<Record<string,string>>({});
const discardOpen = ref(false);
const pendingAction = ref<{kind:"delete"|"toggle";type:ClassCatalogType;item:ClassCatalogItem}|null>(null);
const actionError = ref("");
let initializing = true;

const selectedParish = computed(() => parishes.value.find(item => item.id === parishId.value));
const permission = {
    academic_year:{create:"create-academic-years",update:"update-academic-years",delete:"delete-academic-years"},
    level:{create:"create-levels",update:"update-levels",delete:"delete-levels"},
    classroom:{create:"create-classrooms",update:"update-classrooms",delete:"delete-classrooms"},
} as const;
const canCreate = computed(() => auth.hasPermission(permission[activeTab.value].create));
const currentItems = computed<ClassCatalogItem[]>(() => {
    const source:ClassCatalogItem[] = activeTab.value === "academic_year"
        ? catalogs.value.academic_years
        : activeTab.value === "level" ? catalogs.value.levels : catalogs.value.classrooms;
    const query = search.value.trim().toLocaleLowerCase("vi");
    return source.filter(item => {
        const searchable = activeTab.value === "level"
            ? `${item.name} ${(item as CatechismLevelCatalog).code}`
            : item.name;
        const matchesSearch = !query || searchable.toLocaleLowerCase("vi").includes(query);
        const matchesStatus = status.value === "all" || item.is_active === (status.value === "active");
        return matchesSearch && matchesStatus;
    });
});
const activeCount = computed(() => ({
    academic_year:catalogs.value.academic_years.filter(item => item.is_active).length,
    level:catalogs.value.levels.filter(item => item.is_active).length,
    classroom:catalogs.value.classrooms.filter(item => item.is_active).length,
}));

const apiMessage = (error:unknown, fallback:string) =>
    (error as {response?:{data?:{message?:string}}}).response?.data?.message ?? fallback;
const validationErrors = (error:unknown) => Object.fromEntries(Object.entries(
    (error as {response?:{data?:{errors?:Record<string,string[]>}}}).response?.data?.errors ?? {},
).map(([key, messages]) => [key, messages[0]]));
const typeLabel = (type:ClassCatalogType) => ({academic_year:"niên khóa",level:"khối giáo lý",classroom:"phòng học"}[type]);

async function loadParishes() {
    const response = await listParishes({ per_page:50 });
    parishes.value = response.data.data;
    const queryParish = Number(route.query.parish);
    parishId.value = parishes.value.some(item => item.id === queryParish) ? queryParish : parishes.value[0]?.id;
}

async function loadCatalogs() {
    if (!parishId.value) {
        catalogs.value = emptyCatalogs();
        loading.value = false;
        return;
    }
    loading.value = true;
    listError.value = "";
    try {
        catalogs.value = (await getClassCatalogs(parishId.value)).data.data;
    } catch (error) {
        listError.value = apiMessage(error, "Không thể tải danh mục lớp học.");
    } finally {
        loading.value = false;
    }
}

function changeTab(type:ClassCatalogType) {
    activeTab.value = type;
    search.value = "";
    status.value = "all";
    void router.replace({query:{...route.query,tab:type}});
}

function openCreate() {
    editing.value = null;
    formType.value = activeTab.value;
    formErrors.value = {};
    formOpen.value = true;
}

function openEdit(item:ClassCatalogItem) {
    editing.value = item;
    formType.value = activeTab.value;
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

async function saveCatalog(type:ClassCatalogType, payload:ClassCatalogPayload) {
    if (saving.value) return;
    saving.value = true;
    formErrors.value = {};
    try {
        if (type === "academic_year") {
            if (editing.value) await updateAcademicYear(editing.value.id, payload as AcademicYearCatalogInput);
            else await createAcademicYear(payload as AcademicYearCatalogInput);
        } else if (type === "level") {
            if (editing.value) await updateCatechismLevel(editing.value.id, payload as CatechismLevelCatalogInput);
            else await createCatechismLevel(payload as CatechismLevelCatalogInput);
        } else {
            if (editing.value) await updateClassroom(editing.value.id, payload as ClassroomCatalogInput);
            else await createClassroom(payload as ClassroomCatalogInput);
        }
        toast.success(`Đã ${editing.value ? "cập nhật" : "tạo"} ${typeLabel(type)}.`);
        closeForm();
        await loadCatalogs();
    } catch (error) {
        formErrors.value = validationErrors(error);
        toast.error(apiMessage(error, `Không thể lưu ${typeLabel(type)}.`));
    } finally {
        saving.value = false;
    }
}

function requestDelete(item:ClassCatalogItem) {
    pendingAction.value = {kind:"delete",type:activeTab.value,item};
    actionError.value = "";
}

function requestToggle(item:ClassCatalogItem) {
    pendingAction.value = {kind:"toggle",type:activeTab.value,item};
    actionError.value = "";
}

async function confirmAction() {
    if (!pendingAction.value || saving.value) return;
    saving.value = true;
    actionError.value = "";
    const {kind,type,item} = pendingAction.value;
    try {
        if (kind === "delete") {
            if (type === "academic_year") await deleteAcademicYear(item.id);
            else if (type === "level") await deleteCatechismLevel(item.id);
            else await deleteClassroom(item.id);
            toast.success(`Đã xóa ${typeLabel(type)}.`);
        } else if (type === "academic_year") {
            const year = item as AcademicYearCatalog;
            await updateAcademicYear(year.id, {
                name:year.name, starts_on:year.starts_on, ends_on:year.ends_on,
                is_current:year.is_active ? false : year.is_current, is_active:!year.is_active,
            });
            toast.success(year.is_active ? "Đã ngừng sử dụng niên khóa." : "Đã sử dụng lại niên khóa.");
        } else if (type === "level") {
            const level = item as CatechismLevelCatalog;
            await updateCatechismLevel(level.id, {
                name:level.name, code:level.code, sort_order:level.sort_order, is_active:!level.is_active,
            });
            toast.success(level.is_active ? "Đã ngừng sử dụng khối giáo lý." : "Đã sử dụng lại khối giáo lý.");
        } else {
            const classroom = item as ClassroomCatalog;
            await updateClassroom(classroom.id, {
                name:classroom.name, capacity:classroom.capacity, is_active:!classroom.is_active,
            });
            toast.success(classroom.is_active ? "Đã ngừng sử dụng phòng học." : "Đã sử dụng lại phòng học.");
        }
        pendingAction.value = null;
        await loadCatalogs();
    } catch (error) {
        actionError.value = apiMessage(error, `Không thể cập nhật ${typeLabel(type)}.`);
    } finally {
        saving.value = false;
    }
}

function itemDetail(item:ClassCatalogItem) {
    if (activeTab.value === "academic_year") {
        const year = item as AcademicYearCatalog;
        return `${year.starts_on.split("-").reverse().join("/")} – ${year.ends_on.split("-").reverse().join("/")}`;
    }
    if (activeTab.value === "level") {
        const level = item as CatechismLevelCatalog;
        return `Mã ${level.code} · Thứ tự ${level.sort_order}`;
    }
    const classroom = item as ClassroomCatalog;
    return classroom.capacity ? `Sức chứa ${classroom.capacity} người` : "Chưa đặt sức chứa";
}

watch(parishId, async (value, previous) => {
    if (initializing || !value || value === previous) return;
    await router.replace({query:{...route.query,parish:String(value)}});
    await loadCatalogs();
});

onMounted(async () => {
    try {
        await loadParishes();
        initializing = false;
        if (parishId.value) {
            await router.replace({query:{...route.query,parish:String(parishId.value)}});
        }
        await loadCatalogs();
    } catch (error) {
        initializing = false;
        listError.value = apiMessage(error, "Không thể tải dữ liệu quản trị.");
        loading.value = false;
    }
});
</script>

<template>
    <section class="catalog-page mx-auto w-full max-w-[1500px]">
        <ACard :bordered="false" class="catalog-hero">
            <div class="catalog-hero-visual">
                <img
                    class="catalog-hero-art"
                    :src="'/images/02_individual_assets/bg-quan-ly-danh-muc.png'"
                    alt=""
                    aria-hidden="true"
                    draggable="false"
                >
            </div>
            <div class="catalog-hero-copy">
                <span class="catalog-hero-kicker"><Settings2 aria-hidden="true" />Cấu hình theo giáo xứ</span>
                <h2>Danh mục lớp học</h2>
                <p>Quản lý niên khóa, chương trình giáo lý và cơ sở phòng học dùng chung cho các lớp.</p>
                <div class="catalog-parish-select">
                    <label for="catalog-parish">
                        <span class="catalog-parish-icon" aria-hidden="true"><Church /></span>
                        Giáo xứ đang quản lý
                    </label>
                    <ASelect
                        id="catalog-parish"
                        v-model:value="parishId"
                        size="large"
                        aria-label="Giáo xứ đang quản lý"
                        :options="parishes.map(item=>({value:item.id,label:`${item.name} (${item.code})`}))"
                    />
                </div>
            </div>
        </ACard>

        <AAlert v-if="listError" type="error" show-icon :message="listError" closable @close="listError=''">
            <template #action><AButton size="small" @click="loadCatalogs">Thử lại</AButton></template>
        </AAlert>

        <ACard :bordered="false" class="catalog-card">
            <div class="catalog-tabs" role="tablist" aria-label="Loại danh mục lớp học">
                <button type="button" role="tab" :aria-selected="activeTab==='academic_year'" :class="{active:activeTab==='academic_year'}" @click="changeTab('academic_year')">
                    <CalendarRange /><span><b>Niên khóa</b><small>{{ activeCount.academic_year }} đang dùng</small></span>
                </button>
                <button type="button" role="tab" :aria-selected="activeTab==='level'" :class="{active:activeTab==='level'}" @click="changeTab('level')">
                    <Layers3 /><span><b>Khối giáo lý</b><small>{{ activeCount.level }} đang dùng</small></span>
                </button>
                <button type="button" role="tab" :aria-selected="activeTab==='classroom'" :class="{active:activeTab==='classroom'}" @click="changeTab('classroom')">
                    <DoorOpen /><span><b>Phòng học</b><small>{{ activeCount.classroom }} đang dùng</small></span>
                </button>
            </div>

            <div class="catalog-toolbar">
                <AInput v-model:value="search" allow-clear size="large" :placeholder="`Tìm ${typeLabel(activeTab)} theo tên${activeTab==='level'?' hoặc mã':''}`">
                    <template #prefix><Search aria-hidden="true" /></template>
                </AInput>
                <ASelect v-model:value="status" size="large" :options="[{value:'all',label:'Tất cả trạng thái'},{value:'active',label:'Đang sử dụng'},{value:'inactive',label:'Ngừng sử dụng'}]" />
                <ATooltip title="Tải lại dữ liệu"><AButton size="large" :loading="loading" aria-label="Tải lại dữ liệu" @click="loadCatalogs"><template #icon><RefreshCw /></template></AButton></ATooltip>
                <AButton v-if="canCreate" type="primary" size="large" @click="openCreate"><template #icon><Plus /></template>Tạo {{ typeLabel(activeTab) }}</AButton>
            </div>

            <ASkeleton v-if="loading" active :paragraph="{rows:6}" class="catalog-skeleton" />
            <template v-else>
                <div class="catalog-table-head" aria-hidden="true"><span>Danh mục</span><span>Thông tin</span><span>Lớp sử dụng</span><span>Trạng thái</span><span>Thao tác</span></div>
                <div v-if="currentItems.length" class="catalog-list">
                    <article v-for="item in currentItems" :key="item.id" class="catalog-row">
                        <div class="catalog-identity">
                            <span class="catalog-item-icon" aria-hidden="true"><CalendarRange v-if="activeTab==='academic_year'" /><Layers3 v-else-if="activeTab==='level'" /><DoorOpen v-else /></span>
                            <div><b>{{ item.name }}</b><small v-if="activeTab==='academic_year' && (item as AcademicYearCatalog).is_current">Niên khóa hiện tại</small><small v-else>{{ activeTab==='level' ? (item as CatechismLevelCatalog).code : `#${item.id}` }}</small></div>
                        </div>
                        <div class="catalog-detail"><span class="catalog-field-label">Thông tin</span><b>{{ itemDetail(item) }}</b></div>
                        <div class="catalog-usage"><span class="catalog-field-label">Lớp sử dụng</span><b><BookOpen />{{ item.classes_count }} lớp</b></div>
                        <div class="catalog-state"><span class="catalog-field-label">Trạng thái</span><ATag :color="item.is_active?'success':'default'">{{ item.is_active?'Đang sử dụng':'Ngừng sử dụng' }}</ATag></div>
                        <div class="catalog-actions">
                            <ATooltip title="Chỉnh sửa"><AButton v-if="auth.hasPermission(permission[activeTab].update)" aria-label="Chỉnh sửa danh mục" @click="openEdit(item)"><template #icon><Pencil /></template></AButton></ATooltip>
                            <ATooltip :title="item.is_active?'Ngừng sử dụng':'Sử dụng lại'"><AButton v-if="auth.hasPermission(permission[activeTab].update)" :aria-label="item.is_active?'Ngừng sử dụng danh mục':'Sử dụng lại danh mục'" @click="requestToggle(item)"><template #icon><Power /></template></AButton></ATooltip>
                            <ATooltip :title="item.classes_count?'Không thể xóa vì đang có lớp sử dụng':'Xóa danh mục'">
                                <span><AButton v-if="auth.hasPermission(permission[activeTab].delete)" danger :disabled="item.classes_count>0" aria-label="Xóa danh mục" @click="requestDelete(item)"><template #icon><Trash2 /></template></AButton></span>
                            </ATooltip>
                        </div>
                    </article>
                </div>
                <AEmpty v-else :description="search || status!=='all' ? 'Không có danh mục phù hợp bộ lọc.' : `Chưa có ${typeLabel(activeTab)} cho giáo xứ này.`" class="catalog-empty">
                    <AButton v-if="canCreate && !search && status==='all'" type="primary" @click="openCreate"><template #icon><Plus /></template>Tạo đầu tiên</AButton>
                </AEmpty>
            </template>
        </ACard>
    </section>

    <ClassCatalogFormModal :open="formOpen" :type="formType" :item="editing" :parish-id="parishId ?? 0" :parish-name="selectedParish?.name ?? ''" :saving="saving" :errors="formErrors" @close="requestFormClose" @submit="saveCatalog" />
    <AdminActionConfirmModal :open="discardOpen" title="Bỏ thay đổi chưa lưu?" description="Thông tin vừa nhập sẽ không được lưu lại." confirm-text="Bỏ thay đổi" danger @close="discardOpen=false" @confirm="closeForm" />
    <AdminActionConfirmModal
        :open="Boolean(pendingAction)"
        :title="pendingAction?.kind==='delete' ? `Xóa ${typeLabel(pendingAction.type)} này?` : pendingAction?.item.is_active ? `Ngừng sử dụng ${typeLabel(pendingAction.type)} này?` : `Sử dụng lại ${typeLabel(pendingAction?.type ?? 'academic_year')} này?`"
        :description="pendingAction?.kind==='delete' ? 'Danh mục sẽ bị xóa vĩnh viễn vì chưa có lớp sử dụng.' : pendingAction?.item.is_active ? 'Danh mục sẽ không còn xuất hiện khi tạo lớp mới; các lớp hiện tại vẫn giữ nguyên dữ liệu.' : 'Danh mục sẽ xuất hiện trở lại trong danh sách lựa chọn khi tạo lớp.'"
        :confirm-text="pendingAction?.kind==='delete' ? 'Xóa danh mục' : pendingAction?.item.is_active ? 'Ngừng sử dụng' : 'Sử dụng lại'"
        :target-name="pendingAction?.item.name"
        :danger="pendingAction?.kind==='delete' || Boolean(pendingAction?.item.is_active)"
        :loading="saving"
        :error-message="actionError"
        @close="pendingAction=null"
        @confirm="confirmAction"
    />
</template>

<style scoped>
.catalog-page {
    display: grid;
    gap: 1rem;
}

.catalog-hero {
    position: relative;
    overflow: hidden;
    width: 100%;
    border-radius: 1rem;
    background: #fff;
    box-shadow:
        0 0 0 1px rgba(37, 99, 235, 0.14),
        0 12px 32px -22px rgba(15, 23, 42, 0.28);
}

.catalog-hero::before {
    position: absolute;
    z-index: 0;
    top: 0;
    right: 0;
    width: 17rem;
    height: 11rem;
    background-image: radial-gradient(circle, rgba(59, 130, 246, 0.22) 1.2px, transparent 1.2px);
    background-size: 1rem 1rem;
    content: "";
    opacity: 0.48;
    pointer-events: none;
}

.catalog-hero::after {
    position: absolute;
    z-index: 0;
    right: -10rem;
    bottom: -14rem;
    width: 34rem;
    height: 28rem;
    border-radius: 50%;
    background: #f4f8ff;
    content: "";
    pointer-events: none;
}

.catalog-hero :deep(.ant-card-body) {
    position: relative;
    z-index: 1;
    display: grid;
    min-height: 11.75rem;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: center;
    gap: 2rem;
    padding: 0.75rem 1.5rem 1rem;
}

.catalog-hero :deep(.ant-card-body)::before,
.catalog-hero :deep(.ant-card-body)::after {
    display: none;
    content: none;
}

.catalog-hero-visual {
    display: grid;
    min-width: 0;
    align-self: stretch;
    grid-column: 1;
    grid-row: 1;
    place-items: center;
}

.catalog-hero-art {
    width: 100%;
    height: 11.25rem;
    align-self: center;
    border-radius: 1.75rem;
    object-fit: contain;
    -webkit-mask-image: radial-gradient(ellipse 94% 92% at center, #000 66%, rgba(0, 0, 0, 0.92) 78%, transparent 100%);
    mask-image: radial-gradient(ellipse 94% 92% at center, #000 66%, rgba(0, 0, 0, 0.92) 78%, transparent 100%);
    object-position: center;
    pointer-events: none;
    user-select: none;
}

.catalog-hero-copy {
    position: relative;
    z-index: 1;
    max-width: 28rem;
    align-self: center;
    grid-column: 2;
    grid-row: 1;
}

.catalog-hero-kicker {
    display: inline-flex;
    min-height: 1.875rem;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.625rem;
    border-radius: 999px;
    background: var(--color-primary-50);
    color: var(--color-primary-600);
    font-size: 0.6875rem;
    font-weight: 700;
    letter-spacing: 0.035em;
    text-transform: uppercase;
}

.catalog-hero-kicker svg {
    width: 0.875rem;
    height: 0.875rem;
    stroke-width: 2;
}

.catalog-hero-copy h2 {
    max-width: 18ch;
    margin: 0.625rem 0 0.375rem;
    color: #081f49;
    font-size: clamp(1.45rem, 1.8vw, 1.9rem);
    font-weight: 700;
    letter-spacing: -0.035em;
    line-height: 1.08;
    text-wrap: balance;
}

.catalog-hero-copy p {
    max-width: 43ch;
    margin: 0;
    color: #52637c;
    font-size: clamp(0.75rem, 0.85vw, 0.875rem);
    line-height: 1.55;
    text-wrap: pretty;
}

.catalog-parish-select {
    display: grid;
    width: min(100%, 18rem);
    margin-top: 0.625rem;
    gap: 0.375rem;
}

.catalog-parish-select label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    color: #243b64;
    font-size: 0.6875rem;
    font-weight: 650;
}

.catalog-parish-icon {
    display: inline-grid;
    width: 1.5rem;
    height: 1.5rem;
    flex: none;
    place-items: center;
    border-radius: 0.5rem;
    background: var(--color-primary-50);
    color: var(--color-primary-600);
}

.catalog-parish-icon svg {
    width: 0.875rem;
    height: 0.875rem;
    stroke-width: 2;
}

.catalog-parish-select :deep(.ant-select) {
    width: 100%;
}

.catalog-parish-select :deep(.ant-select-selector) {
    min-height: 2.5rem !important;
    align-items: center;
    padding-inline: 0.75rem !important;
    border-color: #cbd5e1 !important;
    border-radius: 0.75rem !important;
    background: rgba(255, 255, 255, 0.92) !important;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.035) !important;
    transition-property: border-color, box-shadow;
    transition-duration: 150ms;
    transition-timing-function: ease-out;
}

.catalog-parish-select :deep(.ant-select-selection-item) {
    display: flex;
    align-items: center;
    color: #102a56;
    font-size: 0.8125rem;
    font-weight: 500;
}

.catalog-parish-select :deep(.ant-select-focused .ant-select-selector) {
    border-color: var(--color-primary-500) !important;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.14) !important;
}

.catalog-card {
    overflow: hidden;
    border: 1px solid #dbe3ee;
    border-radius: 0.875rem;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.035);
}

.catalog-card :deep(.ant-card-body) { padding: 0; }
.catalog-tabs { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border-bottom: 1px solid #e2e8f0; background: #fbfdff; }
.catalog-tabs button { display: flex; min-height: 4.5rem; align-items: center; justify-content: center; gap: 0.75rem; border: 0; border-bottom: 2px solid transparent; background: transparent; color: #64748b; cursor: pointer; transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease; }
.catalog-tabs button:hover { background: #f3f7fd; color: #1d4f91; }
.catalog-tabs button.active { border-bottom-color: #2563eb; background: #fff; color: #185fce; }
.catalog-tabs svg { width: 1.2rem; height: 1.2rem; }
.catalog-tabs span { display: flex; min-width: 0; flex-direction: column; text-align: left; }
.catalog-tabs b { font-size: 0.8rem; }
.catalog-tabs small { margin-top: 0.15rem; color: #75859c; font-size: 0.65rem; }
.catalog-toolbar { display: grid; grid-template-columns: minmax(14rem, 1fr) 12rem auto auto; gap: 0.625rem; padding: 1rem 1.25rem; border-bottom: 1px solid #e8edf4; }
.catalog-toolbar :deep(.ant-input-prefix svg), .catalog-toolbar :deep(.ant-btn svg) { width: 1rem; height: 1rem; }
.catalog-table-head, .catalog-row { display: grid; grid-template-columns: minmax(14rem, 1.35fr) minmax(13rem, 1fr) 7.5rem 8.5rem 8.5rem; align-items: center; gap: 1rem; }
.catalog-table-head { min-height: 2.75rem; padding: 0 1.25rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc; color: #64748b; font-size: 0.66rem; font-weight: 700; text-transform: uppercase; }
.catalog-list { display: grid; }
.catalog-row { min-height: 5rem; padding: 0.8rem 1.25rem; border-bottom: 1px solid #edf1f6; background: #fff; transition: background-color 0.15s ease; }
.catalog-row:last-child { border-bottom: 0; }
.catalog-row:hover { background: #fbfdff; }
.catalog-identity { display: flex; min-width: 0; align-items: center; gap: 0.75rem; }
.catalog-item-icon { display: grid; width: 2.5rem; height: 2.5rem; flex: none; place-items: center; border-radius: 0.625rem; background: #edf4ff; color: #2364d7; }
.catalog-item-icon svg { width: 1.1rem; height: 1.1rem; }
.catalog-identity > div { display: flex; min-width: 0; flex-direction: column; }
.catalog-identity b, .catalog-detail b { overflow: hidden; color: #17345f; font-size: 0.78rem; text-overflow: ellipsis; white-space: nowrap; }
.catalog-identity small { margin-top: 0.2rem; color: #64748b; font-size: 0.67rem; }
.catalog-detail, .catalog-usage, .catalog-state { display: flex; min-width: 0; flex-direction: column; }
.catalog-field-label { display: none; color: #64748b; font-size: 0.62rem; }
.catalog-detail b { color: #475569; font-weight: 550; }
.catalog-usage b { display: flex; align-items: center; gap: 0.35rem; color: #334155; font-size: 0.75rem; font-weight: 650; font-variant-numeric: tabular-nums; }
.catalog-usage svg { width: 0.9rem; height: 0.9rem; color: #64748b; }
.catalog-state :deep(.ant-tag) { width: max-content; margin: 0; }
.catalog-actions { display: flex; justify-content: flex-end; gap: 0.35rem; }
.catalog-actions :deep(.ant-btn) { display: inline-grid; width: 2.25rem; height: 2.25rem; place-items: center; padding: 0; border-radius: 0.5rem; }
.catalog-actions :deep(.ant-btn svg) { width: 0.95rem; height: 0.95rem; }
.catalog-empty { padding: 4rem 1rem; }
.catalog-skeleton { padding: 1.5rem; }

@media (min-width: 1600px) {
    .catalog-page { gap: 1.25rem; }
    .catalog-hero :deep(.ant-card-body) { min-height: 12rem; gap: 2.5rem; padding: 0.875rem 1.75rem 1rem; }
    .catalog-hero-art { height: 12rem; }
    .catalog-toolbar, .catalog-row, .catalog-table-head { padding-inline: 1.5rem; }
    .catalog-row { min-height: 5.5rem; }
}

@media (max-width: 1100px) {
    .catalog-hero :deep(.ant-card-body) { min-height: 12rem; gap: 1.5rem; padding: 0.875rem 1.25rem; }
    .catalog-hero-art { height: 10rem; }
    .catalog-table-head, .catalog-row { grid-template-columns: minmax(12rem, 1.25fr) minmax(11rem, 1fr) 6rem 7.5rem 7.5rem; }
    .catalog-toolbar { grid-template-columns: minmax(12rem, 1fr) 11rem auto; }
    .catalog-toolbar > :last-child { grid-column: 1 / -1; justify-self: end; }
}

@media (max-width: 900px) {
    .catalog-hero :deep(.ant-card-body) { grid-template-columns: 1fr; gap: 1.5rem; padding: 2rem; }
    .catalog-hero-copy { max-width: 42rem; grid-column: 1; grid-row: 1; }
    .catalog-hero-visual { grid-column: 1; grid-row: 2; }
    .catalog-hero-copy h2 { max-width: none; margin-top: 1rem; }
    .catalog-hero-copy p { max-width: 52ch; }
    .catalog-parish-select { width: min(100%, 36rem); }
    .catalog-hero-art { height: clamp(11rem, 31vw, 15rem); }
    .catalog-parish-select { width: 100%; }
}

@media (max-width: 760px) {
    .catalog-hero::before { width: 10rem; height: 8rem; opacity: 0.32; }
    .catalog-hero::after { right: -17rem; bottom: -18rem; }
    .catalog-hero :deep(.ant-card-body) { padding: 1.25rem; }
    .catalog-hero-kicker { min-height: 2.5rem; padding: 0.5rem 0.75rem; font-size: 0.75rem; }
    .catalog-hero-copy h2 { margin-bottom: 0.75rem; font-size: clamp(2rem, 10vw, 2.75rem); }
    .catalog-hero-copy p { font-size: 0.9375rem; line-height: 1.65; }
    .catalog-hero-art { height: clamp(9.5rem, 43vw, 12rem); }
    .catalog-tabs { overflow-x: auto; grid-template-columns: repeat(3, minmax(10rem, 1fr)); }
    .catalog-tabs button { min-height: 4rem; }
    .catalog-toolbar { grid-template-columns: minmax(0, 1fr) auto; padding: 0.875rem; }
    .catalog-toolbar > :nth-child(2) { grid-row: 2; grid-column: 1 / -1; }
    .catalog-toolbar > :last-child { grid-column: 1 / -1; width: 100%; justify-self: stretch; }
    .catalog-table-head { display: none; }
    .catalog-list { gap: 0.75rem; padding: 0.875rem; }
    .catalog-row { min-height: 0; grid-template-columns: minmax(0, 1fr) auto; gap: 0.75rem; padding: 1rem; border: 1px solid #e2e8f0; border-radius: 0.75rem; }
    .catalog-identity { grid-column: 1; }
    .catalog-detail, .catalog-usage, .catalog-state { grid-column: 1; }
    .catalog-field-label { display: block; margin-bottom: 0.2rem; }
    .catalog-actions { grid-row: 1 / 5; grid-column: 2; align-content: start; flex-direction: column; }
    .catalog-empty { padding: 3rem 1rem; }
}

@media (max-width: 520px) {
    .catalog-hero-art { display: none; }
    .catalog-hero-visual { display: none; }
    .catalog-parish-select label { font-size: 0.875rem; }
    .catalog-parish-select :deep(.ant-select-selector) { min-height: 3.25rem !important; }
}

@media (max-width: 420px) {
    .catalog-tabs { grid-template-columns: repeat(3, minmax(8.75rem, 1fr)); }
    .catalog-row { padding: 0.875rem; }
    .catalog-item-icon { width: 2.25rem; height: 2.25rem; }
}
</style>

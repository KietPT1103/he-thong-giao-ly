<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AEmpty from "ant-design-vue/es/empty";
import AInput from "ant-design-vue/es/input";
import APagination from "ant-design-vue/es/pagination";
import ASelect from "ant-design-vue/es/select";
import ASkeleton from "ant-design-vue/es/skeleton";
import ATag from "ant-design-vue/es/tag";
import ATooltip from "ant-design-vue/es/tooltip";
import {
    Archive, ArrowRight, BookOpen, CalendarDays, ChevronRight, ClipboardCheck,
    Clock3, DoorOpen, GraduationCap, Mail, Pencil, Phone, Plus, Printer,
    Search, SlidersHorizontal, UserPlus, Users,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import {
    archiveTeacherClass, createTeacherClass, enrollTeacherClassChild, getClassChildren,
    getTeacherClass, getTeacherClassOptions, getTeacherClasses, getTeacherClassWorkspace, getTeacherEnrollmentOptions,
    updateTeacherClass, updateTeacherClassEnrollment, type TeacherClassInput,
    type TeacherClassOptions, type TeacherEnrollmentAction, type TeacherEnrollmentOptions,
} from "../api/teacher";
import AdminActionConfirmModal from "../components/AdminActionConfirmModal.vue";
import TeacherClassEnrollmentModal from "../components/TeacherClassEnrollmentModal.vue";
import TeacherClassFormModal from "../components/TeacherClassFormModal.vue";
import TeacherEnrollmentActionModal from "../components/TeacherEnrollmentActionModal.vue";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";
import UserAvatar from "../components/UserAvatar.vue";
import type { CatechismClass, Child } from "../types/api";

const route = useRoute();
const router = useRouter();
const classes = ref<CatechismClass[]>([]);
const selectedClass = ref<CatechismClass | null>(null);
const children = ref<Child[]>([]);
const query = ref("");
const childSearch = ref("");
const childStatus = ref<"studying" | "inactive" | undefined>();
const childLoading = ref(false);
const childMeta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const loading = ref(true);
const optionsLoading = ref(false);
const error = ref("");
const options = ref<TeacherClassOptions>({ parishes: [], academic_years: [], levels: [], classrooms: [] });
const formOpen = ref(false);
const editing = ref<CatechismClass | null>(null);
const saving = ref(false);
const formErrors = ref<Record<string, string>>({});
const discardOpen = ref(false);
const archiveTarget = ref<CatechismClass | null>(null);
const actionError = ref("");
const enrollmentOpen = ref(false);
const enrollmentOptions = ref<TeacherEnrollmentOptions>({ children: [], transfer_classes: [] });
const enrollmentOptionsLoading = ref(false);
const enrollingChildId = ref<number | null>(null);
const enrollmentChild = ref<Child | null>(null);
const enrollmentActionOpen = ref(false);
const enrollmentActionSaving = ref(false);
const enrollmentActionError = ref("");
const classId = computed(() => {
    const value = Number(route.params.id);
    return Number.isInteger(value) && value > 0 ? value : null;
});
const isDetail = computed(() => classId.value !== null);
const primaryTeachers = computed(() => selectedClass.value?.teachers?.filter((teacher) => teacher.role === "primary") ?? []);
const studentOffset = computed(() => (childMeta.value.current_page - 1) * childMeta.value.per_page);
let childSearchTimer: ReturnType<typeof setTimeout> | undefined;
let enrollmentOptionsRequest = 0;
const filtered = computed(() =>
    classes.value.filter((item) =>
        `${item.name} ${item.code} ${item.level?.name}`
            .toLowerCase()
            .includes(query.value.trim().toLowerCase()),
    ),
);
const weekdays = ["Chủ nhật", "Thứ hai", "Thứ ba", "Thứ tư", "Thứ năm", "Thứ sáu", "Thứ bảy"];
const apiData = (value: unknown) => (value as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response?.data;
const apiMessage = (value: unknown, fallback: string) => apiData(value)?.message ?? fallback;

async function load() {
    const detailId = classId.value;
    const cachedClass = detailId ? classes.value.find((item) => item.id === detailId) : null;
    loading.value = !cachedClass;
    if (cachedClass) selectedClass.value = cachedClass;
    error.value = "";
    try {
        if (detailId) {
            childLoading.value = true;
            const workspace = (await getTeacherClassWorkspace(detailId)).data.data;
            selectedClass.value = workspace.class;
            children.value = workspace.children;
            childMeta.value = workspace.children_meta;
            return;
        }
        classes.value = (await getTeacherClasses()).data.data;
        selectedClass.value = null;
        children.value = [];
    } catch {
        error.value = isDetail.value
            ? "Không thể tải thông tin lớp học."
            : "Không thể tải danh sách lớp.";
    } finally {
        loading.value = false;
        childLoading.value = false;
    }
}

async function loadChildren(page = 1) {
    if (!classId.value) return;
    childLoading.value = true;
    try {
        const response = await getClassChildren(classId.value, {
            page,
            search: childSearch.value.trim() || undefined,
            status: childStatus.value,
        });
        children.value = response.data.data;
        childMeta.value = response.data.meta as typeof childMeta.value;
    } catch (value) {
        toast.error(apiMessage(value, "Không thể tải danh sách thiếu nhi."));
    } finally {
        childLoading.value = false;
    }
}

async function refreshClassDetail() {
    if (!classId.value) return;
    const response = await getTeacherClass(classId.value);
    selectedClass.value = response.data.data;
}

async function loadEnrollmentOptions(search = "") {
    if (!classId.value) return;
    const requestId = ++enrollmentOptionsRequest;
    enrollmentOptionsLoading.value = true;
    try {
        const response = await getTeacherEnrollmentOptions(classId.value, search);
        if (requestId === enrollmentOptionsRequest) enrollmentOptions.value = response.data.data;
    } catch (value) {
        if (requestId === enrollmentOptionsRequest) {
            toast.error(apiMessage(value, "Không thể tải danh sách thiếu nhi trong giáo xứ."));
        }
    } finally {
        if (requestId === enrollmentOptionsRequest) enrollmentOptionsLoading.value = false;
    }
}

function openEnrollmentManager() {
    enrollmentOpen.value = true;
}

async function enrollChild(childId: number) {
    if (!classId.value || enrollingChildId.value !== null) return;
    enrollingChildId.value = childId;
    try {
        await enrollTeacherClassChild(classId.value, childId);
        toast.success("Đã thêm thiếu nhi vào lớp.");
        await Promise.all([refreshClassDetail(), loadChildren(1), loadEnrollmentOptions()]);
    } catch (value) {
        toast.error(apiMessage(value, "Không thể thêm thiếu nhi vào lớp."));
    } finally {
        enrollingChildId.value = null;
    }
}

function openEnrollmentAction(child: Child) {
    enrollmentChild.value = child;
    enrollmentActionError.value = "";
    enrollmentActionOpen.value = true;
    void loadEnrollmentOptions();
}

function closeEnrollmentAction() {
    if (enrollmentActionSaving.value) return;
    enrollmentActionOpen.value = false;
    enrollmentChild.value = null;
    enrollmentActionError.value = "";
}

async function submitEnrollmentAction(payload: TeacherEnrollmentAction) {
    if (!classId.value || !enrollmentChild.value || enrollmentActionSaving.value) return;
    enrollmentActionSaving.value = true;
    enrollmentActionError.value = "";
    try {
        const response = await updateTeacherClassEnrollment(
            classId.value,
            enrollmentChild.value.id,
            payload,
        );
        toast.success(response.data.message);
        enrollmentActionOpen.value = false;
        enrollmentChild.value = null;
        await Promise.all([refreshClassDetail(), loadChildren(1)]);
    } catch (value) {
        enrollmentActionError.value = apiMessage(value, "Không thể cập nhật xếp lớp.");
    } finally {
        enrollmentActionSaving.value = false;
    }
}

function submitChildSearch() {
    clearTimeout(childSearchTimer);
    void loadChildren(1);
}

function formatDate(value: string | null) {
    if (!value) return "Chưa cập nhật";
    return new Intl.DateTimeFormat("vi-VN").format(new Date(`${value}T00:00:00`));
}

function scheduleRange(startsOn: string | null, endsOn: string | null) {
    if (!startsOn && !endsOn) return "Áp dụng suốt niên khóa";
    return `Từ ${startsOn ? formatDate(startsOn) : "bắt đầu"} đến ${endsOn ? formatDate(endsOn) : "kết thúc"}`;
}

function weekdayLabel(value: number) {
    return value === 7 ? "Chủ nhật" : weekdays[value] ?? "Ngày học";
}

function formatTime(value: string) {
    return value.slice(0, 5);
}

function printClassList() {
    window.print();
}

async function loadOptions() {
    if (optionsLoading.value) return;
    optionsLoading.value = true;
    try {
        options.value = (await getTeacherClassOptions()).data.data;
    } catch {
        toast.error("Không thể tải danh mục để quản lý lớp học.");
    } finally {
        optionsLoading.value = false;
    }
}

function openCreate() {
    editing.value = null;
    formErrors.value = {};
    formOpen.value = true;
}

async function openEdit(item: CatechismClass) {
    if (!item.can_manage) return;
    if (!options.value.parishes.length) await loadOptions();
    editing.value = item;
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

function validationErrors(value: unknown) {
    return Object.fromEntries(
        Object.entries(apiData(value)?.errors ?? {}).map(([field, messages]) => [field, messages[0]]),
    );
}

async function saveClass(payload: TeacherClassInput) {
    if (saving.value) return;
    saving.value = true;
    formErrors.value = {};
    const current = editing.value;
    try {
        const saved = current
            ? (await updateTeacherClass(current.id, payload)).data.data
            : (await createTeacherClass(payload)).data.data;
        toast.success(current ? "Đã cập nhật lớp học." : "Đã tạo lớp học.");
        closeForm();
        if (selectedClass.value?.id === saved.id) selectedClass.value = saved;
        await load();
    } catch (value) {
        formErrors.value = validationErrors(value);
        toast.error(apiMessage(value, "Không thể lưu lớp học."));
    } finally {
        saving.value = false;
    }
}

function requestArchive(item: CatechismClass) {
    if (!item.can_manage) return;
    archiveTarget.value = item;
    actionError.value = "";
}

async function confirmArchive() {
    if (!archiveTarget.value || saving.value) return;
    const target = archiveTarget.value;
    saving.value = true;
    actionError.value = "";
    try {
        await archiveTeacherClass(target.id);
        archiveTarget.value = null;
        toast.success("Đã lưu trữ lớp học.");
        if (isDetail.value) await router.push("/teacher/classes");
        else await load();
    } catch (value) {
        actionError.value = apiMessage(value, "Không thể lưu trữ lớp học.");
    } finally {
        saving.value = false;
    }
}

function openClass(id: number) {
    router.push(`/teacher/classes/${id}`);
}

watch(() => route.fullPath, async () => {
    if (isDetail.value) {
        await load();
        return;
    }
    await Promise.all([load(), loadOptions()]);
}, { immediate: true });
watch(childSearch, () => {
    clearTimeout(childSearchTimer);
    childSearchTimer = setTimeout(() => void loadChildren(1), 350);
});
</script>

<template>
    <section class="teacher-page-stack class-information-page" :class="{ 'class-information-page--detail': isDetail }">
        <template v-if="!isDetail">
            <TeacherPageHeader title="Lớp của tôi" description="Theo dõi và quản lý các lớp được phân công trong niên khóa hiện tại." :count="`${filtered.length} lớp`">
                <template #actions><AButton type="primary" size="large" :disabled="!options.parishes.length" @click="openCreate"><template #icon><Plus class="size-4" /></template>Tạo lớp học</AButton></template>
            </TeacherPageHeader>
            <ACard :bordered="false" class="teacher-card">
                <div class="teacher-toolbar">
                    <div class="teacher-toolbar-main">
                        <AInput v-model:value="query" allow-clear size="large" aria-label="Tìm lớp" placeholder="Tìm theo tên, mã lớp hoặc khối giáo lý">
                            <template #prefix><Search class="size-4 text-slate-400" /></template>
                        </AInput>
                    </div>
                </div>
                <div v-if="loading" class="space-y-3 p-5" aria-busy="true" aria-label="Đang tải danh sách lớp">
                    <div v-for="i in 4" :key="i" class="h-16 animate-pulse rounded-xl bg-slate-100" />
                </div>
                <div v-else-if="error" class="p-4">
                    <AAlert type="error" show-icon :message="error"><template #action><AButton size="small" @click="load">Thử lại</AButton></template></AAlert>
                </div>
                <div v-else-if="!filtered.length" class="teacher-empty-state">
                    <GraduationCap class="size-10 text-slate-400" />
                    <h3>Không tìm thấy lớp phù hợp</h3>
                    <p>Thử thay đổi từ khóa hoặc tạo lớp học mới.</p>
                </div>
                <ul v-else class="teacher-list">
                    <li
                        v-for="item in filtered"
                        :key="item.id"
                        class="teacher-list-row class-list-row"
                        role="link"
                        tabindex="0"
                        @click="openClass(item.id)"
                        @keydown.enter="openClass(item.id)"
                    >
                        <span class="teacher-mark">{{ item.level?.name?.slice(0, 1) || "L" }}</span>
                        <div class="teacher-list-copy">
                            <b>{{ item.name }}</b>
                            <small>{{ item.code }} · {{ item.level?.name }} · {{ item.classroom?.name || "Chưa xếp phòng" }}</small>
                        </div>
                        <div class="teacher-row-actions" @click.stop>
                            <ATag color="blue"><span class="inline-flex items-center gap-1"><Users class="size-3.5" />{{ item.children_count || 0 }} thiếu nhi</span></ATag>
                            <template v-if="item.can_manage">
                                <ATooltip title="Chỉnh sửa lớp"><AButton type="text" class="icon-action-button" aria-label="Chỉnh sửa lớp" @click="openEdit(item)"><template #icon><Pencil class="size-4" /></template></AButton></ATooltip>
                                <ATooltip title="Lưu trữ lớp"><AButton type="text" danger class="icon-action-button" aria-label="Lưu trữ lớp" @click="requestArchive(item)"><template #icon><Archive class="size-4" /></template></AButton></ATooltip>
                            </template>
                            <ATooltip title="Mở thông tin lớp"><RouterLink :to="`/teacher/classes/${item.id}`" class="icon-action-button" aria-label="Mở thông tin lớp"><ArrowRight class="size-4" /></RouterLink></ATooltip>
                            <ATooltip title="Điểm danh"><RouterLink :to="`/teacher/attendance?class=${item.id}`" class="icon-action-button" aria-label="Điểm danh lớp"><ClipboardCheck class="size-4" /></RouterLink></ATooltip>
                        </div>
                    </li>
                </ul>
            </ACard>
        </template>

        <template v-else>
            <div v-if="loading" class="class-detail-loading teacher-card"><ASkeleton active :paragraph="{ rows: 9 }" /></div>
            <div v-else-if="error" class="teacher-card p-4">
                <AAlert type="error" show-icon :message="error"><template #action><AButton size="small" @click="load">Thử lại</AButton></template></AAlert>
            </div>
            <template v-else-if="selectedClass">
                <nav class="class-breadcrumb" aria-label="Đường dẫn trang">
                    <button type="button" @click="router.push('/teacher/classes')">Lớp học</button>
                    <ChevronRight aria-hidden="true" />
                    <span>Chi tiết lớp</span>
                </nav>

                <ACard :bordered="false" class="teacher-card class-hero-card">
                    <div class="class-hero">
                        <span class="class-hero-mark"><BookOpen aria-hidden="true" /></span>
                        <div class="class-hero-copy">
                            <h1>{{ selectedClass.name }}</h1>
                            <p>Lớp học: {{ selectedClass.code }} <i aria-hidden="true" /> Khối {{ selectedClass.level?.name || "chưa xếp" }}</p>
                        </div>
                        <div class="class-detail-actions">
                            <AButton class="class-print-action" size="large" @click="printClassList"><template #icon><Printer class="size-4" /></template>In danh sách</AButton>
                            <AButton v-if="selectedClass.can_manage" class="class-edit-action" size="large" @click="openEdit(selectedClass)"><template #icon><Pencil class="size-4" /></template>Chỉnh sửa</AButton>
                            <ATooltip v-if="selectedClass.can_manage" title="Lưu trữ lớp"><AButton class="class-archive-action" danger size="large" aria-label="Lưu trữ lớp" @click="requestArchive(selectedClass)"><template #icon><Archive class="size-4" /></template></AButton></ATooltip>
                            <AButton class="class-attendance-action" type="primary" size="large" @click="router.push(`/teacher/attendance?class=${selectedClass.id}`)"><template #icon><ClipboardCheck class="size-4" /></template>Điểm danh lớp</AButton>
                        </div>
                    </div>
                </ACard>

                <div class="class-facts" aria-label="Tóm tắt lớp học">
                    <div><span class="fact-icon"><CalendarDays /></span><span><small>Niên khóa</small><b>{{ selectedClass.academic_year?.name || "Chưa cập nhật" }}</b></span></div>
                    <div><span class="fact-icon"><GraduationCap /></span><span><small>Khối giáo lý</small><b>{{ selectedClass.level?.name || "Chưa cập nhật" }}</b></span></div>
                    <div><span class="fact-icon"><DoorOpen /></span><span><small>Phòng học</small><b>{{ selectedClass.classroom?.name || "Chưa xếp phòng" }}</b></span></div>
                    <div><span class="fact-icon"><Users /></span><span><small>Sĩ số</small><b>{{ selectedClass.children_count ?? childMeta.total }} thiếu nhi</b></span></div>
                    <div class="class-status-fact"><ATag :color="selectedClass.status === 'active' ? 'success' : 'warning'">{{ selectedClass.status === "active" ? "Đang hoạt động" : "Tạm ngưng" }}</ATag></div>
                </div>

                <div class="class-content-grid">
                    <ACard :bordered="false" class="teacher-card student-directory-card">
                        <header class="student-directory-header">
                            <div><h2>Danh sách thiếu nhi</h2><p>Thông tin các em đang học trong lớp.</p></div>
                            <div class="student-directory-tools">
                                <AButton v-if="selectedClass.can_manage_enrollments && selectedClass.status === 'active'" class="student-add-button" type="primary" @click="openEnrollmentManager"><template #icon><UserPlus class="size-4" /></template>Thêm thiếu nhi</AButton>
                                <div class="student-filters">
                                    <AInput v-model:value="childSearch" allow-clear aria-label="Tìm học viên" placeholder="Tìm tên hoặc mã học viên" @press-enter="submitChildSearch"><template #prefix><Search class="size-4 text-slate-400" /></template></AInput>
                                    <ASelect v-model:value="childStatus" allow-clear aria-label="Lọc trạng thái" placeholder="Tất cả trạng thái" :options="[{value:'studying',label:'Đang học'},{value:'inactive',label:'Tạm nghỉ'}]" @change="loadChildren(1)" />
                                </div>
                            </div>
                        </header>

                        <div class="student-table" :class="{ 'student-table--manageable': selectedClass.can_manage_enrollments }" role="table" aria-label="Danh sách thiếu nhi" :aria-busy="childLoading">
                            <div class="student-table-head" role="row">
                                <span role="columnheader">STT</span><span role="columnheader">Họ và tên</span><span role="columnheader">Mã học viên</span><span role="columnheader">Ngày sinh</span><span role="columnheader">Trạng thái</span><span v-if="selectedClass.can_manage_enrollments" role="columnheader">Thao tác</span>
                            </div>
                            <div v-if="childLoading" class="student-loading"><ASkeleton active :paragraph="{ rows: 5 }" /></div>
                            <div v-else-if="children.length" class="student-table-body">
                                <article v-for="(child, index) in children" :key="child.id" class="student-record" role="row">
                                    <span class="student-index" data-label="STT" role="cell">{{ studentOffset + index + 1 }}</span>
                                    <div class="student-person" data-label="Họ và tên" role="cell"><UserAvatar size="sm" :name="child.full_name" :avatar-url="child.avatar_url" /><div><b>{{ child.full_name }}</b><small v-if="child.saint_name">{{ child.saint_name }}</small></div></div>
                                    <span class="student-value" data-label="Mã học viên" role="cell">{{ child.code }}</span>
                                    <span class="student-value" data-label="Ngày sinh" role="cell">{{ formatDate(child.date_of_birth) }}</span>
                                    <span class="student-state" data-label="Trạng thái" role="cell"><ATag :color="child.status === 'studying' ? 'success' : 'default'">{{ child.status === "studying" ? "Đang học" : "Tạm nghỉ" }}</ATag></span>
                                    <span v-if="selectedClass.can_manage_enrollments" class="student-actions" role="cell"><AButton size="small" @click="openEnrollmentAction(child)"><template #icon><SlidersHorizontal class="size-3.5" /></template>Quản lý</AButton></span>
                                </article>
                            </div>
                            <AEmpty v-else description="Không có thiếu nhi phù hợp." class="py-10" />
                        </div>

                        <footer v-if="childMeta.total" class="student-directory-footer">
                            <span>Hiển thị {{ studentOffset + 1 }}–{{ Math.min(studentOffset + children.length, childMeta.total) }} trong {{ childMeta.total }} học viên</span>
                            <APagination v-if="childMeta.last_page > 1" :current="childMeta.current_page" :page-size="childMeta.per_page" :total="childMeta.total" :show-size-changer="false" responsive @change="loadChildren" />
                        </footer>
                    </ACard>

                    <aside class="class-detail-aside">
                        <ACard :bordered="false" class="teacher-card aside-card">
                            <header class="aside-heading"><div><h2>Lịch học định kỳ</h2><p>Thời gian học cố định của lớp.</p></div><CalendarDays /></header>
                            <div v-if="selectedClass.schedules.length" class="schedule-stack">
                                <div v-for="schedule in selectedClass.schedules" :key="schedule.id" class="schedule-item"><span><Clock3 /></span><div><b>{{ weekdayLabel(schedule.weekday) }} hàng tuần</b><strong>{{ formatTime(schedule.starts_at) }} – {{ formatTime(schedule.ends_at) }}</strong><small>{{ scheduleRange(schedule.starts_on, schedule.ends_on) }}</small></div></div>
                            </div>
                            <AEmpty v-else description="Chưa có lịch học định kỳ." class="py-6" />
                        </ACard>

                        <ACard :bordered="false" class="teacher-card aside-card">
                            <header class="aside-heading"><div><h2>Giáo lý viên phụ trách</h2><p>{{ primaryTeachers.length || selectedClass.teachers?.length || 0 }} người phụ trách chính.</p></div></header>
                            <div v-if="selectedClass.teachers?.length" class="teacher-stack">
                                <div v-for="teacher in selectedClass.teachers" :key="teacher.id" class="responsible-teacher"><span class="responsible-avatar">{{ teacher.name.slice(0, 1) }}</span><div><b>{{ teacher.name }}</b><small><Phone />{{ teacher.phone || "Chưa cập nhật số điện thoại" }}</small><small><Mail />{{ teacher.email }}</small></div><ATag v-if="teacher.role === 'primary'" color="blue">Phụ trách</ATag></div>
                            </div>
                            <AEmpty v-else description="Chưa phân công giáo lý viên." class="py-6" />
                        </ACard>

                        <section class="attendance-callout">
                            <div><ClipboardCheck /><span><b>Sẵn sàng điểm danh?</b><p>Tạo phiên mới hoặc tiếp tục phiên điểm danh của lớp.</p></span></div>
                            <AButton type="primary" size="large" @click="router.push(`/teacher/attendance?class=${selectedClass.id}`)"><template #icon><ClipboardCheck class="size-4" /></template>Đi đến điểm danh</AButton>
                        </section>
                    </aside>
                </div>
            </template>
        </template>
    </section>

    <TeacherClassFormModal :open="formOpen" :model="editing" :options="options" :saving="saving" :errors="formErrors" @close="requestFormClose" @submit="saveClass" />
    <TeacherClassEnrollmentModal v-if="selectedClass" :open="enrollmentOpen" :class-id="selectedClass.id" :class-name="selectedClass.name" :children="enrollmentOptions.children" :loading="enrollmentOptionsLoading" :saving-child-id="enrollingChildId" @close="enrollmentOpen=false" @search="loadEnrollmentOptions" @add="enrollChild" />
    <TeacherEnrollmentActionModal :open="enrollmentActionOpen" :child="enrollmentChild" :transfer-classes="enrollmentOptions.transfer_classes" :loading="enrollmentActionSaving || enrollmentOptionsLoading" :error-message="enrollmentActionError" @close="closeEnrollmentAction" @submit="submitEnrollmentAction" />
    <AdminActionConfirmModal :open="Boolean(archiveTarget)" title="Lưu trữ lớp học này?" description="Lớp sẽ ẩn khỏi danh sách của giáo viên; lịch học, ghi danh và lịch sử điểm danh vẫn được giữ nguyên." confirm-text="Lưu trữ lớp" :target-name="archiveTarget?.name" danger :loading="saving" :error-message="actionError" @close="archiveTarget=null" @confirm="confirmArchive" />
    <AdminActionConfirmModal :open="discardOpen" title="Bỏ thay đổi chưa lưu?" description="Thông tin vừa nhập sẽ không được lưu lại." confirm-text="Bỏ thay đổi" danger @close="discardOpen=false" @confirm="closeForm" />
</template>

<style scoped>
.class-information-page--detail {
    max-width: 1600px;
    margin-inline: auto;
    gap: 16px;
}

.class-list-row {
    cursor: pointer;
}

.class-list-row:focus-visible {
    outline: 2px solid #1677ff;
    outline-offset: -2px;
    background: #f8fbff;
}

.class-detail-loading {
    padding: 24px;
}

.class-breadcrumb {
    display: flex;
    min-height: 24px;
    align-items: center;
    gap: 8px;
    color: #64748b;
    font-size: 12px;
}

.class-breadcrumb button {
    border: 0;
    background: transparent;
    padding: 0;
    color: #64748b;
    cursor: pointer;
    font: inherit;
}

.class-breadcrumb button:hover,
.class-breadcrumb button:focus-visible {
    color: #155dcc;
}

.class-breadcrumb button:focus-visible {
    border-radius: 4px;
    outline: 2px solid #93c5fd;
    outline-offset: 3px;
}

.class-breadcrumb svg {
    width: 14px;
    height: 14px;
    color: #94a3b8;
}

.class-breadcrumb span {
    color: #334155;
    font-weight: 650;
}

.class-hero-card :deep(.ant-card-body) {
    padding: 20px 22px;
}

.class-hero {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 16px;
}

.class-hero-mark {
    display: grid;
    width: 56px;
    height: 56px;
    flex: none;
    place-items: center;
    border-radius: 14px;
    background: #e8efff;
    color: #235fd6;
    box-shadow: 0 8px 18px rgba(37, 99, 235, 0.14);
}

.class-hero-mark svg {
    width: 25px;
    height: 25px;
}

.class-hero-copy {
    min-width: 0;
}

.class-hero-copy h1,
.class-hero-copy p {
    margin: 0;
}

.class-hero-copy h1 {
    overflow: hidden;
    color: #0b214d;
    font-size: 22px;
    font-weight: 760;
    letter-spacing: 0;
    line-height: 1.3;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.class-hero-copy p {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.class-hero-copy i {
    width: 3px;
    height: 3px;
    border-radius: 50%;
    background: #94a3b8;
}

.class-detail-actions {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.class-detail-actions :deep(.ant-btn) {
    border-radius: 9px;
    font-size: 13px;
}

.class-facts {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr)) auto;
    overflow: hidden;
    border: 1px solid #e1e7f0;
    border-radius: 14px;
    background: #fff;
}

.class-facts > div {
    display: flex;
    min-width: 0;
    min-height: 76px;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-left: 1px solid #edf1f6;
}

.class-facts > div:first-child {
    border-left: 0;
}

.fact-icon {
    display: grid;
    width: 34px;
    height: 34px;
    flex: none;
    place-items: center;
    border-radius: 10px;
    background: #f0f5ff;
    color: #1f66dc;
}

.fact-icon svg {
    width: 17px;
    height: 17px;
}

.class-facts > div > span:last-child {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

.class-facts small {
    color: #64748b;
    font-size: 11px;
    line-height: 1.4;
}

.class-facts b {
    overflow: hidden;
    margin-top: 3px;
    color: #10234b;
    font-size: 13px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.class-facts .class-status-fact {
    min-width: 156px;
    justify-content: center;
}

.class-content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.7fr) minmax(320px, 0.72fr);
    align-items: start;
    gap: 16px;
}

.student-directory-card {
    min-width: 0;
}

.student-directory-header {
    display: flex;
    min-height: 82px;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    padding: 16px 18px;
    border-bottom: 1px solid #e8edf4;
}

.student-directory-header h2,
.student-directory-header p,
.aside-heading h2,
.aside-heading p {
    margin: 0;
}

.student-directory-tools {
    display: flex;
    min-width: 0;
    align-items: center;
    justify-content: flex-end;
    gap: 8px;
}

.student-add-button {
    flex: none;
}

.student-directory-header h2,
.aside-heading h2 {
    color: #10234b;
    font-size: 14px;
    font-weight: 750;
    line-height: 1.4;
}

.student-directory-header p,
.aside-heading p {
    margin-top: 4px;
    color: #64748b;
    font-size: 11px;
    line-height: 1.5;
}

.student-filters {
    display: grid;
    width: min(100%, 410px);
    grid-template-columns: minmax(180px, 1fr) 150px;
    gap: 8px;
}

.student-filters :deep(.ant-input-affix-wrapper),
.student-filters :deep(.ant-select-selector) {
    min-height: 40px;
    border-radius: 9px;
}

.student-filters :deep(.ant-select) {
    width: 100%;
}

.student-filters :deep(.ant-select-single) {
    height: 40px;
}

.student-filters :deep(.ant-select-single .ant-select-selector) {
    display: flex;
    height: 40px !important;
    align-items: center;
    padding-block: 0;
}

.student-filters :deep(.ant-select-single .ant-select-selection-search-input) {
    height: 38px;
}

.student-filters :deep(.ant-select-single .ant-select-selection-item),
.student-filters :deep(.ant-select-single .ant-select-selection-placeholder) {
    line-height: 38px !important;
}

.student-table {
    min-width: 0;
}

.student-table-head,
.student-record {
    display: grid;
    grid-template-columns: 56px minmax(190px, 1.45fr) minmax(110px, 0.8fr) minmax(105px, 0.72fr) 104px;
    align-items: center;
    column-gap: 12px;
}

.student-table--manageable .student-table-head,
.student-table--manageable .student-record {
    grid-template-columns: 42px minmax(145px, 1.35fr) minmax(86px, 0.7fr) minmax(86px, 0.68fr) 82px 90px;
    column-gap: 10px;
}

.student-table-head {
    min-height: 42px;
    padding: 0 18px;
    border-bottom: 1px solid #e8edf4;
    background: #f8fafc;
    color: #64748b;
    font-size: 10px;
    font-weight: 700;
}

.student-table-body {
    min-width: 0;
}

.student-record {
    min-height: 68px;
    padding: 10px 18px;
    border-bottom: 1px solid #edf1f6;
    transition: background-color 160ms ease;
}

.student-record:last-child {
    border-bottom: 0;
}

.student-record:hover {
    background: #fbfdff;
}

.student-index {
    display: grid;
    width: 28px;
    height: 28px;
    place-items: center;
    border-radius: 50%;
    background: #f1f5f9;
    color: #64748b;
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}

.student-person {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 10px;
}

.student-person > div {
    min-width: 0;
}

.student-person b,
.student-person small {
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.student-person b {
    color: #15284e;
    font-size: 12px;
    font-weight: 700;
}

.student-person small {
    margin-top: 2px;
    color: #64748b;
    font-size: 10px;
}

.student-value {
    overflow: hidden;
    color: #475569;
    font-size: 11px;
    font-variant-numeric: tabular-nums;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.student-state :deep(.ant-tag) {
    margin: 0;
}

.student-actions {
    display: flex;
    justify-content: flex-end;
}

.student-actions :deep(.ant-btn) {
    border-radius: 8px;
    font-size: 11px;
}

.student-loading {
    padding: 18px;
}

.student-directory-footer {
    display: flex;
    min-height: 58px;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 10px 18px;
    border-top: 1px solid #e8edf4;
    color: #64748b;
    font-size: 11px;
}

.class-detail-aside {
    display: grid;
    min-width: 0;
    gap: 16px;
}

.aside-heading {
    display: flex;
    min-height: 72px;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 18px;
    border-bottom: 1px solid #e8edf4;
}

.aside-heading > svg {
    width: 18px;
    height: 18px;
    flex: none;
    color: #1f66dc;
}

.schedule-stack {
    display: grid;
    gap: 10px;
    padding: 14px;
}

.schedule-item {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: start;
    gap: 12px;
    padding: 13px;
    border: 1px solid #dbe7f8;
    border-radius: 12px;
    background: #f8fbff;
}

.schedule-item > span {
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border-radius: 10px;
    background: #fff;
    color: #1e64d8;
}

.schedule-item svg {
    width: 17px;
    height: 17px;
}

.schedule-item div {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

.schedule-item b,
.schedule-item strong,
.schedule-item small {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.schedule-item b {
    color: #15284e;
    font-size: 12px;
    font-weight: 700;
}

.schedule-item strong {
    margin-top: 2px;
    color: #245bba;
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}

.schedule-item small {
    margin-top: 3px;
    color: #64748b;
    font-size: 10px;
}

.teacher-stack {
    display: grid;
}

.responsible-teacher {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    border-bottom: 1px solid #edf1f6;
}

.responsible-teacher:last-child {
    border-bottom: 0;
}

.responsible-avatar {
    display: grid;
    width: 38px;
    height: 38px;
    place-items: center;
    border-radius: 50%;
    background: #10234b;
    color: #fff;
    font-size: 12px;
    font-weight: 750;
}

.responsible-teacher > div {
    min-width: 0;
}

.responsible-teacher b,
.responsible-teacher small {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 6px;
}

.responsible-teacher b {
    overflow: hidden;
    color: #15284e;
    font-size: 12px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.responsible-teacher small {
    overflow: hidden;
    margin-top: 3px;
    color: #64748b;
    font-size: 10px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.responsible-teacher small svg {
    width: 12px;
    height: 12px;
    flex: none;
}

.responsible-teacher :deep(.ant-tag) {
    margin: 0;
}

.attendance-callout {
    display: grid;
    gap: 14px;
    padding: 18px;
    border: 1px solid #bfdbfe;
    border-radius: 14px;
    background: #f7fbff;
}

.attendance-callout > div {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.attendance-callout > div > svg {
    width: 18px;
    height: 18px;
    flex: none;
    margin-top: 1px;
    color: #1f66dc;
}

.attendance-callout b {
    color: #10234b;
    font-size: 13px;
    font-weight: 750;
}

.attendance-callout p {
    margin: 3px 0 0;
    color: #5f7190;
    font-size: 11px;
    line-height: 1.5;
}

.attendance-callout :deep(.ant-btn) {
    width: 100%;
    border-radius: 9px;
}

@media (max-width: 1279px) {
    .class-hero {
        grid-template-columns: auto minmax(0, 1fr);
    }

    .class-detail-actions {
        grid-column: 1 / -1;
    }

    .class-content-grid {
        grid-template-columns: minmax(0, 1fr) 320px;
    }

    .student-directory-header {
        align-items: stretch;
        flex-direction: column;
    }

    .student-directory-tools {
        width: 100%;
    }

    .student-filters {
        flex: 1;
        width: 100%;
    }
}

@media (max-width: 1099px) {
    .class-facts {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .class-facts > div {
        border-top: 1px solid #edf1f6;
    }

    .class-facts > div:nth-child(-n + 2) {
        border-top: 0;
    }

    .class-facts > div:nth-child(odd) {
        border-left: 0;
    }

    .class-facts .class-status-fact {
        grid-column: 1 / -1;
        min-height: 54px;
        border-top: 1px solid #edf1f6;
        border-left: 0;
    }

    .class-content-grid {
        grid-template-columns: 1fr;
    }

    .class-detail-aside {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .attendance-callout {
        grid-column: 1 / -1;
    }
}

@media (max-width: 767px) {
    .class-hero-card :deep(.ant-card-body) {
        padding: 18px;
    }

    .student-table-head,
    .student-record {
        grid-template-columns: 46px minmax(180px, 1.35fr) minmax(105px, 0.75fr) minmax(100px, 0.7fr) 100px;
    }

    .student-table {
        overflow-x: auto;
    }

    .student-table-head,
    .student-table-body {
        min-width: 720px;
    }

    .class-detail-aside {
        grid-template-columns: 1fr;
    }

    .attendance-callout {
        grid-column: auto;
    }
}

@media (max-width: 639px) {
    .class-information-page--detail {
        gap: 12px;
    }

    .class-hero {
        grid-template-columns: auto minmax(0, 1fr);
        gap: 12px;
    }

    .class-hero-mark {
        width: 44px;
        height: 44px;
        border-radius: 12px;
    }

    .class-hero-mark svg {
        width: 21px;
        height: 21px;
    }

    .class-hero-copy h1 {
        font-size: 19px;
    }

    .class-detail-actions {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 44px;
        justify-content: stretch;
    }

    .class-detail-actions :deep(.ant-btn) {
        width: 100%;
    }

    .class-attendance-action {
        grid-row: 1;
        grid-column: 1 / -1;
    }

    .class-facts > div {
        min-height: 70px;
        gap: 9px;
        padding: 12px;
    }

    .fact-icon {
        width: 30px;
        height: 30px;
        border-radius: 9px;
    }

    .student-directory-header {
        min-height: 0;
        gap: 14px;
        padding: 16px;
    }

    .student-directory-tools {
        align-items: stretch;
        flex-direction: column;
    }

    .student-add-button {
        width: 100%;
    }

    .student-filters {
        grid-template-columns: 1fr;
    }

    .student-table {
        overflow: visible;
        padding: 12px;
        background: #f8fafc;
    }

    .student-table-head {
        display: none;
    }

    .student-table-body {
        display: grid;
        min-width: 0;
        gap: 10px;
    }

    .student-record {
        position: relative;
        display: grid;
        min-height: 0;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px 14px;
        padding: 14px;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: #fff;
    }

    .student-table--manageable .student-record {
        grid-template-columns: minmax(0, 1fr) auto;
    }

    .student-record:last-child {
        border-bottom: 1px solid #e2e8f0;
    }

    .student-index {
        grid-row: 1;
        grid-column: 2;
    }

    .student-person {
        grid-row: 1;
        grid-column: 1;
    }

    .student-value,
    .student-state {
        display: grid;
        grid-column: 1 / -1;
        grid-template-columns: 90px minmax(0, 1fr);
        align-items: center;
        gap: 10px;
        overflow: visible;
        white-space: normal;
    }

    .student-value::before,
    .student-state::before {
        content: attr(data-label);
        color: #64748b;
        font-size: 10px;
        font-weight: 650;
    }

    .student-state :deep(.ant-tag) {
        width: max-content;
        justify-self: start;
    }

    .student-actions :deep(.ant-btn) {
        width: 100%;
    }

    .student-actions {
        display: block;
        grid-column: 1 / -1;
    }

    .student-directory-footer {
        align-items: flex-start;
        flex-direction: column;
        padding: 14px 16px;
    }

    .responsible-teacher {
        grid-template-columns: auto minmax(0, 1fr);
    }

    .responsible-teacher :deep(.ant-tag) {
        grid-column: 2;
        justify-self: start;
    }

    .teacher-list-row {
        grid-template-columns: auto minmax(0, 1fr);
    }

    .teacher-row-actions {
        grid-column: 2;
        justify-content: flex-start;
        padding-left: 0;
    }
}

@media (max-width: 359px) {
    .class-facts {
        grid-template-columns: 1fr;
    }

    .class-facts > div {
        border-top: 1px solid #edf1f6;
        border-left: 0;
    }

    .class-facts > div:first-child {
        border-top: 0;
    }

    .class-facts .class-status-fact {
        grid-column: auto;
    }
}

@media print {
    .class-breadcrumb,
    .class-detail-actions,
    .class-detail-aside,
    .student-filters {
        display: none !important;
    }

    .student-add-button,
    .student-actions {
        display: none !important;
    }

    .class-information-page--detail {
        max-width: none;
    }

    .class-content-grid {
        display: block;
    }

    .teacher-card,
    .class-facts {
        box-shadow: none !important;
    }
}
</style>

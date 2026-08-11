<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AInput from "ant-design-vue/es/input";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import { Check, Save, Plus, ClipboardCheck } from "lucide-vue-next";
import {
    createAttendanceSession,
    getAttendanceSession,
    getAttendanceSessions,
    getClassChildren,
    getTeacherClasses,
    saveAttendance,
} from "../api/teacher";
import type {
    AttendanceSession,
    AttendanceStatus,
    CatechismClass,
    Child,
} from "../types/api";
import { toast } from "vue-sonner";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";
type Row = Child & { attendanceStatus: AttendanceStatus };
const route = useRoute(),
    classes = ref<CatechismClass[]>([]),
    classId = ref<number | "">(""),
    sessions = ref<AttendanceSession[]>([]),
    sessionId = ref<number | "">(""),
    rows = ref<Row[]>([]);
const loading = ref(true),
    saving = ref(false),
    error = ref(""),
    success = ref(""),
    showCreate = ref(false),
    heldAt = ref(new Date().toISOString().slice(0, 16));
const selectedClass = computed(() =>
    classes.value.find((x) => x.id === Number(classId.value)),
);
const classOptions = computed(() =>
    classes.value.map((item) => ({ value: item.id, label: `${item.name} · ${item.code}` })),
);
const sessionOptions = computed(() =>
    sessions.value.map((item) => ({
        value: item.id,
        label: new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(item.held_at)),
    })),
);
async function loadClasses() {
    loading.value = true;
    try {
        classes.value = (await getTeacherClasses()).data.data;
        const requested = Number(route.query.class);
        classId.value = classes.value.some((x) => x.id === requested)
            ? requested
            : classes.value[0]?.id || "";
        if (classId.value) await loadSessions();
    } catch {
        error.value = "Không thể tải dữ liệu điểm danh.";
    } finally {
        loading.value = false;
    }
}
async function loadSessions() {
    sessionId.value = "";
    rows.value = [];
    if (!classId.value) return;
    const [sessionResponse, childrenResponse] = await Promise.all([
        getAttendanceSessions(Number(classId.value)),
        getClassChildren(Number(classId.value)),
    ]);
    sessions.value = sessionResponse.data.data.data ?? [];
    rows.value = childrenResponse.data.data.map((child) => ({
        ...child,
        attendanceStatus: "unknown",
    }));
}
async function selectSession() {
    if (!sessionId.value) return;
    const session = (await getAttendanceSession(Number(sessionId.value))).data
        .data;
    const byChild = new Map(
        session.attendances.map((x) => [x.child_id, x.status]),
    );
    rows.value = rows.value.map((row) => ({
        ...row,
        attendanceStatus: byChild.get(row.id) ?? "unknown",
    }));
}
async function createSession() {
    if (!classId.value || saving.value) return;
    saving.value = true;
    error.value = "";
    try {
        const created = (
            await createAttendanceSession(Number(classId.value), {
                held_at: new Date(heldAt.value).toISOString(),
            })
        ).data.data;
        sessions.value.unshift(created);
        sessionId.value = created.id;
        showCreate.value = false;
        success.value = "Đã mở phiên điểm danh.";
        toast.success(success.value);
    } catch (e) {
        error.value =
            (e as { response?: { data?: { message?: string } } }).response?.data
                ?.message || "Không thể tạo phiên điểm danh.";
        toast.error(error.value);
    } finally {
        saving.value = false;
    }
}
async function save() {
    if (!sessionId.value || saving.value) return;
    saving.value = true;
    error.value = "";
    success.value = "";
    try {
        await saveAttendance(
            Number(sessionId.value),
            rows.value.map((row) => ({
                child_id: row.id,
                status: row.attendanceStatus,
            })),
        );
        success.value = "Đã lưu điểm danh vào hệ thống.";
        toast.success(success.value);
    } catch {
        error.value = "Không thể lưu điểm danh. Vui lòng thử lại.";
        toast.error(error.value);
    } finally {
        saving.value = false;
    }
}
function closeCreateSession() { if (!saving.value) showCreate.value = false; }
function markAll() {
    rows.value = rows.value.map((row) => ({
        ...row,
        attendanceStatus: "present",
    }));
}
watch(classId, () => loadSessions());
watch(sessionId, () => selectSession());
onMounted(loadClasses);
const statuses: Array<{ value: AttendanceStatus; label: string }> = [
    { value: "unknown", label: "Chưa ghi nhận" },
    { value: "present", label: "Có mặt" },
    { value: "late", label: "Đi trễ" },
    { value: "excused_absence", label: "Nghỉ phép" },
    { value: "unexcused_absence", label: "Vắng" },
];
</script>
<template>
    <section class="teacher-page-stack">
        <TeacherPageHeader title="Điểm danh lớp" description="Chọn lớp và phiên để ghi nhận chuyên cần thực tế." :count="`${rows.length} thiếu nhi`">
            <template #actions><AButton type="primary" size="large" :disabled="!classId || saving" @click="showCreate=true"><template #icon><Plus class="size-4" /></template>Mở phiên mới</AButton></template>
        </TeacherPageHeader>

        <AAlert v-if="error" type="error" show-icon closable :message="error" @close="error=''" />
        <AAlert v-if="success" type="success" show-icon closable :message="success" @close="success=''" />

        <ACard :bordered="false" class="teacher-card">
            <div class="teacher-toolbar attendance-toolbar">
                <div class="teacher-toolbar-main attendance-filter-grid">
                    <label class="grid gap-1.5 text-xs font-semibold text-slate-700">Lớp được phân công<ASelect v-model:value="classId" size="large" show-search option-filter-prop="label" placeholder="Chọn lớp" :disabled="saving" :options="classOptions" /></label>
                    <label class="grid gap-1.5 text-xs font-semibold text-slate-700">Phiên điểm danh<ASelect v-model:value="sessionId" size="large" placeholder="Chọn ngày và giờ" :options="sessionOptions" :disabled="!classId || saving" /></label>
                </div>
                <AButton :disabled="!rows.length || saving" @click="markAll"><template #icon><Check class="size-4" /></template>Tất cả có mặt</AButton>
            </div>

            <div v-if="loading" class="space-y-3 p-5" aria-busy="true" aria-label="Đang tải dữ liệu điểm danh"><div v-for="i in 5" :key="i" class="h-16 animate-pulse rounded-xl bg-slate-100" /></div>
            <div v-else-if="!classes.length" class="teacher-empty-state"><ClipboardCheck class="size-10 text-slate-400" /><h3>Chưa có lớp phụ trách</h3><p>Liên hệ quản trị viên để được phân công trước khi điểm danh.</p></div>
            <div v-else-if="!sessionId" class="teacher-empty-state"><ClipboardCheck class="size-10 text-slate-400" /><h3>Chọn một phiên điểm danh</h3><p>Bạn có thể chọn phiên đã có hoặc mở phiên mới cho lớp {{ selectedClass?.name }}.</p></div>
            <template v-else>
                <ul class="teacher-list">
                    <li v-for="student in rows" :key="student.id" class="teacher-list-row">
                        <span class="teacher-mark">{{ student.full_name.split(" ").slice(-2).map((word) => word[0]).join("") }}</span>
                        <div class="teacher-list-copy"><b>{{ student.full_name }}</b><small>{{ student.code }}<template v-if="student.saint_name"> · {{ student.saint_name }}</template></small></div>
                        <div class="teacher-row-actions"><ASelect v-model:value="student.attendanceStatus" class="attendance-status-select" :disabled="saving" :options="statuses" aria-label="Trạng thái điểm danh" /></div>
                    </li>
                </ul>
                <footer class="flex justify-stretch border-t border-slate-200 bg-slate-50/70 p-3 sm:justify-end sm:p-4"><AButton type="primary" size="large" :disabled="!sessionId" :loading="saving" class="w-full sm:w-auto" @click="save"><template #icon><Save class="size-4" /></template>Lưu điểm danh</AButton></footer>
            </template>
        </ACard>

        <AModal :open="showCreate" centered title="Mở phiên điểm danh mới" :confirm-loading="saving" :closable="!saving" :keyboard="!saving" :mask-closable="false" :cancel-button-props="{ disabled: saving }" ok-text="Tạo phiên" cancel-text="Hủy" :ok-button-props="{ disabled: !heldAt }" @cancel="closeCreateSession" @ok="createSession">
            <label class="grid gap-1.5 pt-2 text-xs font-semibold text-slate-700">Ngày và giờ bắt đầu <span class="text-red-500">*</span><AInput v-model:value="heldAt" type="datetime-local" size="large" :disabled="saving" /></label>
        </AModal>
    </section>
</template>

<style scoped>
.attendance-filter-grid{display:grid;grid-template-columns:repeat(2,minmax(15rem,1fr));max-width:58rem}.attendance-status-select{width:11.5rem}@media(max-width:767px){.attendance-toolbar{align-items:stretch;flex-direction:column}.attendance-filter-grid{grid-template-columns:1fr;max-width:none}.attendance-toolbar>.ant-btn{width:100%}.attendance-status-select{width:100%}}
</style>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
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
    if (!classId.value) return;
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
    if (!sessionId.value) return;
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
    { value: "present", label: "Có mặt" },
    { value: "late", label: "Đi trễ" },
    { value: "excused_absence", label: "Nghỉ phép" },
    { value: "unexcused_absence", label: "Vắng" },
];
</script>
<template>
    <div class="space-y-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-ink">Điểm danh lớp</h2>
                <p class="mt-1 text-sm text-slate-500">
                    Chọn lớp và phiên để ghi nhận chuyên cần thực tế.
                </p>
            </div>
            <button
                v-if="classId"
                class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary-600 px-4 text-sm font-semibold text-white"
                @click="showCreate = !showCreate"
            >
                <Plus class="size-4" />Mở phiên mới
            </button>
        </header>
        <div
            v-if="error"
            role="alert"
            class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700"
        >
            {{ error }}
        </div>
        <div
            v-if="success"
            role="status"
            class="flex items-center gap-2 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700"
        >
            <Check class="size-4" />{{ success }}
        </div>
        <form
            v-if="showCreate"
            class="flex flex-wrap items-end gap-3 rounded-2xl border border-primary-100 bg-primary-50 p-4"
            @submit.prevent="createSession"
        >
            <label class="text-sm font-medium text-slate-700"
                >Ngày giờ<input
                    v-model="heldAt"
                    required
                    type="datetime-local"
                    class="mt-2 block min-h-11 rounded-xl border border-slate-300 bg-white px-3" /></label
            ><button
                :disabled="saving"
                class="min-h-11 rounded-xl bg-primary-600 px-4 text-sm font-semibold text-white disabled:opacity-50"
            >
                Tạo phiên</button
            ><button
                type="button"
                class="min-h-11 px-3 text-sm"
                @click="showCreate = false"
            >
                Hủy
            </button>
        </form>
        <section
            class="grid gap-4 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-2"
        >
            <label class="text-sm font-medium text-slate-700"
                >Lớp<select
                    v-model="classId"
                    class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3"
                >
                    <option value="" disabled>Chọn lớp</option>
                    <option
                        v-for="item in classes"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select></label
            ><label class="text-sm font-medium text-slate-700"
                >Phiên điểm danh<select
                    v-model="sessionId"
                    class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3"
                >
                    <option value="">Chọn phiên</option>
                    <option
                        v-for="item in sessions"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ new Date(item.held_at).toLocaleString("vi-VN") }}
                    </option>
                </select></label
            >
        </section>
        <div
            v-if="loading"
            class="rounded-2xl bg-white p-8 text-sm text-slate-500"
        >
            Đang tải…
        </div>
        <div
            v-else-if="!classes.length"
            class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
        >
            <ClipboardCheck class="mx-auto size-9 text-slate-400" />
            <p class="mt-3">Bạn chưa được phân công lớp.</p>
        </div>
        <section
            v-else
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4"
            >
                <div>
                    <h3 class="font-semibold text-ink">
                        {{ selectedClass?.name }}
                    </h3>
                    <p class="text-xs text-slate-500">
                        {{ rows.length }} thiếu nhi
                    </p>
                </div>
                <button
                    type="button"
                    class="min-h-11 text-sm font-medium text-primary-600"
                    @click="markAll"
                >
                    Đánh dấu tất cả có mặt
                </button>
            </div>
            <div class="divide-y divide-slate-100">
                <article
                    v-for="student in rows"
                    :key="student.id"
                    class="flex flex-wrap items-center gap-3 p-4"
                >
                    <span
                        class="grid size-10 place-items-center rounded-full bg-primary-100 text-sm font-semibold text-primary-700"
                        >{{ student.full_name.slice(-2) }}</span
                    >
                    <div class="min-w-40 flex-1">
                        <p class="font-medium text-ink">
                            {{ student.full_name }}
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ student.code }} · {{ student.saint_name }}
                        </p>
                    </div>
                    <div
                        class="flex flex-wrap rounded-lg bg-slate-100 p-1 text-xs"
                    >
                        <button
                            v-for="status in statuses"
                            :key="status.value"
                            type="button"
                            class="min-h-9 rounded-md px-2.5"
                            :class="
                                student.attendanceStatus === status.value
                                    ? 'bg-white text-primary-700 shadow-sm'
                                    : 'text-slate-500'
                            "
                            @click="student.attendanceStatus = status.value"
                        >
                            {{ status.label }}
                        </button>
                    </div>
                </article>
            </div>
            <footer
                class="sticky bottom-0 flex justify-stretch border-t border-slate-200 bg-white p-3 sm:justify-end sm:p-4"
            >
                <button
                    :disabled="!sessionId || saving"
                    class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary-600 px-5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                    :title="
                        !sessionId ? 'Hãy chọn hoặc tạo phiên điểm danh' : ''
                    "
                    @click="save"
                >
                    <Save class="size-4" />{{
                        saving ? "Đang lưu…" : "Lưu điểm danh"
                    }}
                </button>
            </footer>
        </section>
    </div>
</template>

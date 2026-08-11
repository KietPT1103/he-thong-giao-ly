<script setup>
import { computed, onMounted, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import ACol from "ant-design-vue/es/grid/Col";
import ARow from "ant-design-vue/es/grid/Row";
import AStatistic from "ant-design-vue/es/statistic";
import {
    ArrowUpRight,
    CalendarClock,
    CheckCircle2,
    ClipboardCheck,
    Users,
} from "lucide-vue-next";
import client from "../api/client";
const data = ref(null),
    loading = ref(true),
    error = ref("");
const stats = computed(() =>
    data.value
        ? [
              { label: "Thiếu nhi đang học", value: data.value.summary.child_count, icon: Users, tone: "bg-emerald-50 text-emerald-700" },
              { label: "Lớp phụ trách", value: data.value.summary.class_count, icon: ClipboardCheck, tone: "bg-blue-50 text-blue-700" },
              { label: "Đơn chờ xử lý", value: data.value.summary.pending_leave_requests, icon: CalendarClock, tone: "bg-amber-50 text-amber-700" },
              { label: "Phiên gần đây", value: data.value.recent_attendance_sessions.length, icon: CheckCircle2, tone: "bg-violet-50 text-violet-700" },
          ]
        : [],
);
async function load() {
    loading.value = true;
    error.value = "";
    try {
        const r = await client.get("/teacher/dashboard");
        data.value = r.data.data;
    } catch {
        error.value = "Không thể tải tổng quan. Vui lòng thử lại.";
    } finally {
        loading.value = false;
    }
}
onMounted(load);
</script>
<template>
    <div class="teacher-page-stack">
        <div v-if="loading" class="animate-pulse space-y-5">
            <div class="h-40 rounded-3xl bg-primary-100"></div>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div v-for="i in 4" :key="i" class="h-28 rounded-2xl bg-slate-200"></div>
            </div>
        </div>
        <div v-else-if="error" class="teacher-card p-4">
            <AAlert type="error" show-icon :message="error"><template #action><AButton size="small" @click="load">Thử lại</AButton></template></AAlert>
        </div>
        <template v-else-if="data">
            <section class="overflow-hidden rounded-3xl bg-gradient-to-r from-primary-700 to-primary-500 p-5 text-white sm:p-7 lg:p-8">
                <p class="text-sm font-medium text-blue-100">Không gian giáo lý viên</p>
                <div class="mt-2 flex flex-wrap items-end justify-between gap-5">
                    <div>
                        <h2 class="text-2xl font-bold sm:text-3xl">Chào {{ data.teacher.name }}!</h2>
                        <p class="mt-2 max-w-2xl text-sm text-blue-100">Theo dõi lớp học, lịch dạy và chuyên cần trong cùng một không gian làm việc.</p>
                    </div>
                    <RouterLink to="/teacher/attendance" class="inline-flex min-h-10 w-full items-center justify-center gap-2 rounded-xl bg-white px-5 text-sm font-semibold text-primary-700 shadow-sm transition hover:-translate-y-0.5 hover:bg-blue-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/80 sm:w-auto">Điểm danh nhanh<ArrowUpRight class="size-4" /></RouterLink>
                </div>
            </section>
            <ARow :gutter="[16, 16]">
                <ACol v-for="item in stats" :key="item.label" :xs="24" :sm="12" :xl="6"><ACard :bordered="false" class="admin-card h-full"><div class="flex items-center gap-3"><span :class="item.tone" class="grid size-11 shrink-0 place-items-center rounded-xl"><component :is="item.icon" class="size-5" /></span><AStatistic :title="item.label" :value="item.value" /></div></ACard></ACol>
            </ARow>
            <section class="grid gap-5 xl:grid-cols-3">
                <ACard :bordered="false" class="teacher-card xl:col-span-2">
                    <div class="teacher-toolbar"><div><h3 class="m-0 text-sm font-bold text-blue-950">Lớp phụ trách</h3><p class="mt-1 text-xs text-slate-500">Các lớp được phân công trong niên khóa hiện tại.</p></div><RouterLink to="/teacher/classes" class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600">Xem tất cả<ArrowUpRight class="size-4" /></RouterLink></div>
                    <ul v-if="data.classes.length" class="teacher-list"><li v-for="item in data.classes" :key="item.id" class="teacher-list-row"><span class="teacher-mark">{{ item.level?.name?.slice(0, 1) || "L" }}</span><div class="teacher-list-copy"><b>{{ item.name }}</b><small>{{ item.classroom?.name || "Chưa xếp phòng" }} · {{ item.children_count }} thiếu nhi</small></div><RouterLink :to="`/teacher/classes/${item.id}`" class="text-xs font-semibold text-primary-600">Xem lớp</RouterLink></li></ul>
                    <div v-else class="teacher-empty-state"><ClipboardCheck class="size-10 text-slate-400" /><h3>Chưa có lớp phụ trách</h3><p>Liên hệ quản trị viên để được phân công lớp.</p></div>
                </ACard>
                <aside class="grid gap-5 content-start">
                    <ACard :bordered="false" class="admin-card"><div class="flex items-center gap-3"><span class="grid size-10 place-items-center rounded-xl bg-amber-50 text-amber-700"><CalendarClock class="size-5" /></span><div><p class="m-0 text-2xl font-bold text-ink">{{ data.summary.pending_leave_requests }}</p><p class="m-0 text-xs text-amber-800">Đơn xin nghỉ chờ xử lý</p></div></div></ACard>
                    <ACard :bordered="false" class="admin-card"><h3 class="m-0 text-sm font-bold text-blue-950">Phiên điểm danh gần đây</h3><div v-if="data.recent_attendance_sessions.length" class="mt-4 space-y-4"><div v-for="session in data.recent_attendance_sessions.slice(0, 4)" :key="session.id" class="flex gap-3"><CheckCircle2 class="mt-0.5 size-4 shrink-0 text-primary-600" /><div class="min-w-0"><p class="m-0 truncate text-xs font-semibold text-blue-950">{{ session.catechism_class?.name || "Phiên điểm danh" }}</p><p class="mt-1 text-[11px] text-slate-500">{{ new Date(session.held_at).toLocaleString("vi-VN") }}</p></div></div></div><p v-else class="mt-4 text-xs text-slate-500">Chưa có phiên điểm danh.</p></ACard>
                </aside>
            </section>
        </template>
    </div>
</template>

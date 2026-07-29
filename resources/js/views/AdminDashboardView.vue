<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import {
    Building2,
    CalendarCheck2,
    ClipboardClock,
    GraduationCap,
    School,
    Users,
    ArrowRight,
    RefreshCw,
} from "lucide-vue-next";
import { getAdminDashboard, type AdminDashboard } from "../api/admin";
import ACard from "ant-design-vue/es/card";
import ACol from "ant-design-vue/es/grid/Col";
import ARow from "ant-design-vue/es/grid/Row";
import AStatistic from "ant-design-vue/es/statistic";

const data = ref<AdminDashboard | null>(null),
    loading = ref(true),
    error = ref("");
const stats = computed(() =>
    data.value
        ? [
              {
                  label: "Giáo xứ",
                  value: data.value.summary.parish_count,
                  icon: Building2,
                  tone: "bg-blue-50 text-blue-700",
              },
              {
                  label: "Giáo lý viên",
                  value: data.value.summary.teacher_count,
                  icon: GraduationCap,
                  tone: "bg-violet-50 text-violet-700",
              },
              {
                  label: "Thiếu nhi đang học",
                  value: data.value.summary.child_count,
                  icon: Users,
                  tone: "bg-emerald-50 text-emerald-700",
              },
              {
                  label: "Lớp đang hoạt động",
                  value: data.value.summary.active_class_count,
                  icon: School,
                  tone: "bg-amber-50 text-amber-700",
              },
          ]
        : [],
);
async function load() {
    loading.value = true;
    error.value = "";
    try {
        data.value = (await getAdminDashboard()).data.data;
    } catch {
        error.value = "Không thể tải tổng quan quản trị. Vui lòng thử lại.";
    } finally {
        loading.value = false;
    }
}
onMounted(load);
</script>
<template>
    <div class="space-y-6">
        <div v-if="loading" class="space-y-5" aria-label="Đang tải tổng quan">
            <div class="h-36 animate-pulse rounded-3xl bg-primary-100" />
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="i in 4"
                    :key="i"
                    class="h-32 animate-pulse rounded-2xl bg-slate-200"
                />
            </div>
        </div>
        <section
            v-else-if="error"
            class="rounded-2xl border border-rose-200 bg-white p-10 text-center"
        >
            <p class="text-rose-700">{{ error }}</p>
            <button
                class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-xl bg-primary-600 px-4 text-sm font-semibold text-white"
                @click="load"
            >
                <RefreshCw class="size-4" />Thử lại
            </button>
        </section>
        <template v-else-if="data">
            <section
                class="overflow-hidden rounded-3xl bg-gradient-to-r from-primary-700 to-primary-500 p-4 text-white sm:p-6 lg:p-8"
            >
                <p class="text-sm font-medium text-blue-100">
                    Giáo phận Cần Thơ
                </p>
                <div
                    class="mt-2 flex flex-wrap items-end justify-between gap-5"
                >
                    <div>
                        <h2 class="text-2xl font-bold sm:text-3xl">
                            Tổng quan hệ thống giáo lý
                        </h2>
                        <p class="mt-2 max-w-2xl text-sm text-blue-100">
                            Theo dõi tổ chức, nhân sự và chuyên cần từ dữ liệu
                            hiện tại.
                        </p>
                    </div>
                    <div
                        class="w-full rounded-2xl bg-white/15 px-4 py-3 sm:w-auto sm:px-5"
                    >
                        <p class="text-xs text-blue-100">Chuyên cần tuần này</p>
                        <p class="mt-1 text-2xl font-bold">
                            {{
                                data.attendance.rate_this_week === null
                                    ? "Chưa có dữ liệu"
                                    : `${data.attendance.rate_this_week}%`
                            }}
                        </p>
                    </div>
                </div>
            </section>

            <ARow :gutter="[16, 16]">
                <ACol
                    v-for="item in stats"
                    :key="item.label"
                    :xs="24"
                    :sm="12"
                    :xl="6"
                >
                    <ACard :bordered="false" class="admin-card h-full">
                        <div class="flex items-center gap-3">
                            <span :class="item.tone" class="grid size-11 shrink-0 place-items-center rounded-xl"><component :is="item.icon" class="size-5" /></span>
                            <AStatistic :title="item.label" :value="item.value" />
                        </div>
                    </ACard>
                </ACol>
            </ARow>

            <section class="grid gap-5 xl:grid-cols-3">
                <article
                    class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 xl:col-span-2"
                >
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-bold text-ink">
                                Giáo xứ trong hệ thống
                            </h3>
                            <p class="mt-1 text-sm text-slate-500">
                                Dữ liệu tổ chức đang được quản lý.
                            </p>
                        </div>
                        <RouterLink
                            to="/admin/parishes"
                            class="flex items-center gap-1 text-sm font-semibold text-primary-600"
                            >Quản lý <ArrowRight class="size-4"
                        /></RouterLink>
                    </div>
                    <div
                        v-if="data.parishes.length"
                        class="mt-5 divide-y divide-slate-100"
                    >
                        <div
                            v-for="parish in data.parishes"
                            :key="parish.id"
                            class="flex items-center gap-4 py-4"
                        >
                            <span
                                class="grid size-11 place-items-center rounded-xl bg-primary-50 font-bold text-primary-700"
                                >{{ parish.name.slice(0, 1) }}</span
                            >
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-semibold text-ink">
                                    {{ parish.name }}
                                </p>
                                <p class="text-xs text-slate-500">
                                    {{ parish.code }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-ink">
                                    {{ parish.children_count }}
                                </p>
                                <p class="text-xs text-slate-500">thiếu nhi</p>
                            </div>
                        </div>
                    </div>
                    <p
                        v-else
                        class="mt-6 rounded-xl bg-slate-50 p-6 text-center text-sm text-slate-500"
                    >
                        Chưa có giáo xứ.
                    </p>
                </article>

                <aside class="space-y-5">
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="grid size-10 place-items-center rounded-xl bg-amber-50 text-amber-700"
                                ><ClipboardClock class="size-5"
                            /></span>
                            <div>
                                <p class="text-2xl font-bold text-ink">
                                    {{
                                        data.summary.pending_leave_request_count
                                    }}
                                </p>
                                <p class="text-sm text-slate-500">
                                    Đơn xin nghỉ chờ xử lý
                                </p>
                            </div>
                        </div>
                    </article>
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="grid size-10 place-items-center rounded-xl bg-emerald-50 text-emerald-700"
                                ><CalendarCheck2 class="size-5"
                            /></span>
                            <div>
                                <p class="text-2xl font-bold text-ink">
                                    {{
                                        data.summary
                                            .class_session_count_this_week
                                    }}
                                </p>
                                <p class="text-sm text-slate-500">
                                    Phiên học trong tuần
                                </p>
                            </div>
                        </div>
                    </article>
                    <article
                        class="rounded-2xl border border-slate-200 bg-white p-5"
                    >
                        <h3 class="font-bold text-ink">
                            Phiên điểm danh gần đây
                        </h3>
                        <div
                            v-if="data.recent_sessions.length"
                            class="mt-4 space-y-4"
                        >
                            <div
                                v-for="session in data.recent_sessions"
                                :key="session.id"
                                class="flex gap-3"
                            >
                                <CalendarCheck2
                                    class="mt-0.5 size-4 shrink-0 text-primary-600"
                                />
                                <div>
                                    <p class="text-sm font-medium text-ink">
                                        {{ session.catechism_class.name }}
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        {{
                                            new Date(
                                                session.held_at,
                                            ).toLocaleString("vi-VN")
                                        }}
                                        · {{ session.attendances_count }} lượt
                                    </p>
                                </div>
                            </div>
                        </div>
                        <p v-else class="mt-4 text-sm text-slate-500">
                            Chưa có phiên điểm danh.
                        </p>
                    </article>
                </aside>
            </section>
        </template>
    </div>
</template>

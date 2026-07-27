<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { CalendarDays, Clock3, MapPin } from "lucide-vue-next";
import { getTeacherClasses } from "../api/teacher";
import type { CatechismClass } from "../types/api";
const classes = ref<CatechismClass[]>([]),
    loading = ref(true),
    error = ref("");
const weekdays = [
    "Chủ nhật",
    "Thứ hai",
    "Thứ ba",
    "Thứ tư",
    "Thứ năm",
    "Thứ sáu",
    "Thứ bảy",
];
const schedules = computed(() =>
    classes.value
        .flatMap((item) =>
            item.schedules.map((schedule) => ({ item, schedule })),
        )
        .sort((a, b) => a.schedule.weekday - b.schedule.weekday),
);
async function load() {
    loading.value = true;
    error.value = "";
    try {
        classes.value = (await getTeacherClasses()).data.data;
    } catch {
        error.value = "Không thể tải lịch dạy. Vui lòng thử lại.";
    } finally {
        loading.value = false;
    }
}
onMounted(load);
</script>
<template>
    <div class="space-y-6">
        <header>
            <h2 class="text-xl font-bold text-ink">Lịch dạy</h2>
            <p class="mt-1 text-sm text-slate-500">
                Lịch sinh hoạt định kỳ của các lớp bạn đang phụ trách.
            </p>
        </header>
        <div v-if="loading" class="grid gap-4 md:grid-cols-2">
            <div
                v-for="i in 4"
                :key="i"
                class="h-32 animate-pulse rounded-2xl bg-slate-200"
            />
        </div>
        <div
            v-else-if="error"
            class="rounded-2xl border border-rose-200 bg-white p-8 text-center"
        >
            <p class="text-rose-700">{{ error }}</p>
            <button
                class="mt-4 rounded-xl bg-primary-600 px-4 py-2 text-white"
                @click="load"
            >
                Thử lại
            </button>
        </div>
        <div
            v-else-if="!schedules.length"
            class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
        >
            <CalendarDays class="mx-auto size-9 text-slate-400" />
            <h3 class="mt-3 font-semibold text-ink">Chưa có lịch dạy</h3>
            <p class="mt-1 text-sm text-slate-500">
                Liên hệ quản trị viên để cập nhật lịch lớp.
            </p>
        </div>
        <div v-else class="grid gap-4 md:grid-cols-2">
            <article
                v-for="{ item, schedule } in schedules"
                :key="schedule.id"
                class="rounded-2xl border border-slate-200 bg-white p-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-primary-700">
                            {{ weekdays[schedule.weekday] }}
                        </p>
                        <h3 class="mt-1 text-lg font-bold text-ink">
                            {{ item.name }}
                        </h3>
                        <p class="text-sm text-slate-500">
                            {{ item.level?.name }} ·
                            {{ item.academic_year?.name }}
                        </p>
                    </div>
                    <CalendarDays class="size-6 text-primary-500" />
                </div>
                <div
                    class="mt-5 flex flex-wrap gap-4 border-t border-slate-100 pt-4 text-sm text-slate-600"
                >
                    <span class="flex items-center gap-2"
                        ><Clock3 class="size-4" />{{
                            schedule.starts_at.slice(0, 5)
                        }}–{{ schedule.ends_at.slice(0, 5) }}</span
                    ><span class="flex items-center gap-2"
                        ><MapPin class="size-4" />{{
                            item.classroom?.name || "Chưa xếp phòng"
                        }}</span
                    >
                </div>
            </article>
        </div>
    </div>
</template>

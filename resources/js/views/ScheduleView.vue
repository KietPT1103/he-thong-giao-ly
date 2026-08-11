<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import ATag from "ant-design-vue/es/tag";
import { CalendarDays, Clock3, MapPin } from "lucide-vue-next";
import { getTeacherClasses } from "../api/teacher";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";
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
    <section class="teacher-page-stack">
        <TeacherPageHeader title="Lịch dạy" description="Lịch sinh hoạt định kỳ của các lớp bạn đang phụ trách." :count="`${schedules.length} lịch`" />
        <div v-if="loading" class="teacher-card space-y-3 p-5" aria-busy="true" aria-label="Đang tải lịch dạy">
            <div v-for="i in 4" :key="i" class="h-16 animate-pulse rounded-xl bg-slate-100" />
        </div>
        <div v-else-if="error" class="teacher-card p-4">
            <AAlert type="error" show-icon :message="error"><template #action><AButton size="small" @click="load">Thử lại</AButton></template></AAlert>
        </div>
        <ACard v-else :bordered="false" class="teacher-card">
            <div v-if="!schedules.length" class="teacher-empty-state"><CalendarDays class="size-10 text-slate-400" /><h3>Chưa có lịch dạy</h3><p>Liên hệ quản trị viên để cập nhật lịch học của lớp.</p></div>
            <ul v-else class="teacher-list">
                <li v-for="{ item, schedule } in schedules" :key="schedule.id" class="teacher-list-row">
                    <span class="teacher-mark"><CalendarDays class="size-5" /></span>
                    <div class="teacher-list-copy"><b>{{ item.name }}</b><small>{{ item.level?.name }} · {{ item.academic_year?.name }}</small></div>
                    <div class="teacher-row-actions"><ATag color="blue">{{ weekdays[schedule.weekday] }}</ATag><span class="inline-flex items-center gap-1.5 text-xs text-slate-600"><Clock3 class="size-4 text-slate-400" />{{ schedule.starts_at.slice(0, 5) }}–{{ schedule.ends_at.slice(0, 5) }}</span><span class="inline-flex items-center gap-1.5 text-xs text-slate-600"><MapPin class="size-4 text-slate-400" />{{ item.classroom?.name || "Chưa xếp phòng" }}</span></div>
                </li>
            </ul>
        </ACard>
    </section>
</template>

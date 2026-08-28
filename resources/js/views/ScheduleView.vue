<!--
THESIS: A teaching operations board that makes day, class, time, and room readable in one scan; it refuses the old flat list.
OWN-WORLD: White calendar canvas, slate structure, restrained system blue, and pastel event blocks with dense but calm controls.
STORY: The teacher finds this week, locates the next class, opens its details, then moves directly into attendance or class work.
FIRST VIEWPORT: Compact context header, single toolbar, and a full-width 7-day calendar; event details open in a focused modal on demand.
FORM: Operate-mode weekly scheduler, derived directly from the user-pinned reference and existing dashboard identity.
-->
<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import AModal from "ant-design-vue/es/modal";
import { CalendarDays } from "lucide-vue-next";
import { getTeacherClasses } from "../api/teacher";
import ScheduleDetailPanel from "../components/schedule/ScheduleDetailPanel.vue";
import ScheduleMonthCalendar from "../components/schedule/ScheduleMonthCalendar.vue";
import ScheduleToolbar from "../components/schedule/ScheduleToolbar.vue";
import WeeklyCalendar from "../components/schedule/WeeklyCalendar.vue";
import ScrollableArea from "../components/ui/ScrollableArea.vue";
import {
    addDays,
    buildTeachingEvents,
    formatLongDate,
    formatMonth,
    formatWeekRange,
    monthGridDays,
    type ScheduleTab,
    type ScheduleViewMode,
    type TeachingCalendarEvent,
    weekDays,
} from "../components/schedule/scheduleCalendar";
import type { CatechismClass } from "../types/api";

const router = useRouter();
const classes = ref<CatechismClass[]>([]);
const loading = ref(true);
const error = ref("");
const tab = ref<ScheduleTab>("all");
const query = ref("");
const viewMode = ref<ScheduleViewMode>("week");
const anchorDate = ref(new Date());
const activeDate = ref(new Date());
const selectedClassIds = ref<number[]>([]);
const selectedEvent = ref<TeachingCalendarEvent | null>(null);
const detailModalOpen = ref(false);
const viewportWidth = ref(1440);

const isMobile = computed(() => viewportWidth.value < 768);
const calendarDays = computed(() => {
    if (viewMode.value === "day") return [anchorDate.value];
    if (viewMode.value === "month") return monthGridDays(anchorDate.value);
    return weekDays(anchorDate.value);
});
const filteredClasses = computed(() => {
    const term = query.value.trim().toLocaleLowerCase("vi");
    return classes.value.filter(item => {
        const matchesClass = !selectedClassIds.value.length || selectedClassIds.value.includes(item.id);
        const haystack = [item.name, item.code, item.level?.name, item.classroom?.name].filter(Boolean).join(" ").toLocaleLowerCase("vi");
        return matchesClass && (!term || haystack.includes(term));
    });
});
const teachingEvents = computed(() => buildTeachingEvents(filteredClasses.value, calendarDays.value));
const visibleEvents = computed(() => tab.value === "attendance" ? [] : teachingEvents.value);
const classOptions = computed(() => classes.value.map(item => ({ value: item.id, label: `${item.name} · ${item.code}` })));
const rangeLabel = computed(() => {
    if (viewMode.value === "month") return formatMonth(anchorDate.value);
    if (viewMode.value === "day") return formatLongDate(anchorDate.value).replace(/^./, character => character.toUpperCase());
    return formatWeekRange(calendarDays.value);
});
const pickerRange = computed<[Date, Date]>(() => {
    if (viewMode.value === "month") {
        return [
            new Date(anchorDate.value.getFullYear(), anchorDate.value.getMonth(), 1),
            new Date(anchorDate.value.getFullYear(), anchorDate.value.getMonth() + 1, 0),
        ];
    }
    return [calendarDays.value[0], calendarDays.value[calendarDays.value.length - 1]];
});

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

function updateViewport() {
    viewportWidth.value = window.innerWidth;
}

function setViewMode(mode: ScheduleViewMode) {
    viewMode.value = mode;
    if (mode === "day") activeDate.value = anchorDate.value;
}

function moveRange(direction: -1 | 1) {
    if (viewMode.value === "month") {
        anchorDate.value = new Date(anchorDate.value.getFullYear(), anchorDate.value.getMonth() + direction, 1);
    } else {
        anchorDate.value = addDays(anchorDate.value, direction * (viewMode.value === "week" ? 7 : 1));
    }
    activeDate.value = anchorDate.value;
}

function selectRange([start]: [Date, Date]) {
    anchorDate.value = start;
    activeDate.value = start;
}

function selectDay(day: Date) {
    activeDate.value = day;
    if (viewMode.value === "day") anchorDate.value = day;
    if (viewMode.value === "month" && isMobile.value) {
        anchorDate.value = day;
        viewMode.value = "day";
    }
}

function selectEvent(event: TeachingCalendarEvent) {
    selectedEvent.value = event;
    activeDate.value = event.date;
    detailModalOpen.value = true;
}

function goToAttendance() {
    if (!selectedEvent.value) return;
    const heldAt = new Date(selectedEvent.value.date);
    const [hours, minutes] = selectedEvent.value.startsAt.split(":").map(Number);
    heldAt.setHours(hours, minutes, 0, 0);
    router.push({ path: "/teacher/attendance", query: { class: selectedEvent.value.classItem.id, held_at: heldAt.toISOString() } });
}

function goToQr() {
    if (selectedEvent.value) router.push({ path: "/teacher/attendance", query: { class: selectedEvent.value.classItem.id, qr: "1" } });
}

function goToClass() {
    if (selectedEvent.value) router.push(`/teacher/classes/${selectedEvent.value.classItem.id}`);
}

function requestChange() {
    if (!selectedEvent.value) return;
    AModal.info({
        title: "Yêu cầu đổi lịch",
        content: `Lịch cố định của ${selectedEvent.value.classItem.name} do quản trị viên phụ trách. Vui lòng liên hệ quản trị viên và cung cấp ngày, giờ mong muốn để được hỗ trợ đổi lịch.`,
        okText: "Đã hiểu",
        centered: true,
    });
}

watch(visibleEvents, events => {
    if (selectedEvent.value && events.some(event => event.key === selectedEvent.value?.key)) return;
    selectedEvent.value = events[0] ?? null;
    if (!selectedEvent.value) detailModalOpen.value = false;
}, { immediate: true });

onMounted(async () => {
    updateViewport();
    if (isMobile.value) viewMode.value = "day";
    window.addEventListener("resize", updateViewport, { passive: true });
    await load();
});
onBeforeUnmount(() => window.removeEventListener("resize", updateViewport));
</script>

<template>
    <section class="schedule-page">
        <div v-if="loading" class="schedule-loading" aria-busy="true" aria-label="Đang tải lịch dạy">
            <div class="loading-toolbar" />
            <div class="loading-grid"><span v-for="item in 8" :key="item" /></div>
        </div>
        <div v-else-if="error" class="teacher-card p-4">
            <AAlert type="error" show-icon :message="error"><template #action><AButton size="small" @click="load">Thử lại</AButton></template></AAlert>
        </div>
        <div v-else-if="!classes.length" class="teacher-card teacher-empty-state">
            <CalendarDays class="teacher-empty-icon text-slate-400" />
            <h3>Chưa có lớp phụ trách</h3>
            <p>Lịch dạy sẽ xuất hiện khi bạn được phân công vào một lớp.</p>
        </div>
        <div v-else class="schedule-shell">
            <ScheduleToolbar
                :class-options="classOptions"
                :event-count="visibleEvents.length"
                :query="query"
                :range="pickerRange"
                :range-label="rangeLabel"
                :selected-class-ids="selectedClassIds"
                :tab="tab"
                :view-mode="viewMode"
                @next="moveRange(1)"
                @previous="moveRange(-1)"
                @update:query="query=$event"
                @update:selected-class-ids="selectedClassIds=$event"
                @update:range="selectRange"
                @update:tab="tab=$event"
                @update:view-mode="setViewMode"
            />
            <div class="schedule-body">
                <ScrollableArea as="main" class="calendar-pane" aria-label="Lịch dạy">
                    <ScheduleMonthCalendar
                        v-if="viewMode==='month'"
                        :anchor="anchorDate"
                        :days="calendarDays"
                        :events="visibleEvents"
                        :selected-key="selectedEvent?.key"
                        @select-day="selectDay"
                        @select-event="selectEvent"
                    />
                    <WeeklyCalendar
                        v-else
                        :active-date="activeDate"
                        :days="calendarDays"
                        :events="visibleEvents"
                        :selected-key="selectedEvent?.key"
                        @select-day="selectDay"
                        @select-event="selectEvent"
                        @swipe="moveRange($event==='next'?1:-1)"
                    />
                </ScrollableArea>
            </div>
        </div>

        <AModal
            :open="detailModalOpen"
            :footer="null"
            :closable="false"
            :width="760"
            centered
            :mask-closable="true"
            :body-style="{padding:'0'}"
            wrap-class-name="schedule-detail-modal"
            @cancel="detailModalOpen=false"
        >
            <ScheduleDetailPanel
                closable
                :event="selectedEvent"
                @close="detailModalOpen=false"
                @attendance="goToAttendance"
                @class-list="goToClass"
                @qr="goToQr"
                @request-change="requestChange"
            />
        </AModal>
    </section>
</template>

<style scoped>
.schedule-page{display:grid;width:100%;min-width:0;gap:14px}.schedule-shell{min-width:0;overflow:hidden;border-radius:12px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.05),0 12px 32px rgba(15,23,42,.045)}.schedule-body{display:grid;min-width:0;grid-template-columns:minmax(0,1fr) minmax(280px,23%)}.calendar-pane{min-width:0;max-height:calc(100dvh - 188px);overflow:auto;border-right:1px solid #e2e8f0}.schedule-filter-strip{display:flex;align-items:flex-end;justify-content:flex-end;gap:12px;padding:10px;border-bottom:1px solid #e7edf5;background:#f8fafc}.schedule-filter-strip label{display:grid;width:min(360px,100%);gap:5px;color:#475569;font-size:10px;font-weight:650}.schedule-filter-strip :deep(.ant-select){width:100%}.schedule-filter-strip :deep(.ant-select-selector){min-height:38px;border-radius:9px!important;background:#fff!important}.schedule-filter-strip button{min-height:38px;cursor:pointer;border:0;background:transparent;color:#2563eb;font-size:10px;font-weight:650}.schedule-loading{overflow:hidden;border-radius:12px;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.05)}.loading-toolbar{height:62px;border-bottom:1px solid #edf1f6;background:linear-gradient(90deg,#f8fafc 25%,#f1f5f9 50%,#f8fafc 75%);background-size:200% 100%;animation:loading 1.5s infinite}.loading-grid{display:grid;height:620px;grid-template-columns:64px repeat(7,1fr)}.loading-grid span{border-right:1px solid #edf1f6;background:linear-gradient(180deg,#fff,#f8fafc)}@keyframes loading{to{background-position:-200% 0}}@media(min-width:1600px){.schedule-body{grid-template-columns:minmax(0,1fr) minmax(300px,22%)}}@media(max-width:1279px){.schedule-body{grid-template-columns:minmax(0,1fr) 286px}.calendar-pane{max-height:calc(100dvh - 236px)}}@media(max-width:1023px){.schedule-body{display:block}.calendar-pane{max-height:none;border-right:0}.schedule-filter-strip{justify-content:flex-start}}@media(max-width:767px){.schedule-page{gap:12px}.schedule-shell{border-radius:10px}.schedule-filter-strip{align-items:stretch;flex-direction:column}.schedule-filter-strip label{width:100%}.schedule-filter-strip button{align-self:flex-start}.calendar-pane{overflow-x:auto}.loading-grid{grid-template-columns:60px 1fr}.loading-grid span:nth-child(n+3){display:none}}@media(prefers-reduced-motion:reduce){.loading-toolbar{animation:none}}
@media(max-width:1279px){.schedule-body{display:block}.calendar-pane{max-height:none;border-right:0}.schedule-filter-strip{justify-content:flex-start}}
.schedule-body{display:block}.calendar-pane{border-right:0}
</style>

<style>
.schedule-detail-modal .ant-modal{max-width:calc(100vw - 32px)}
.schedule-detail-modal .ant-modal-content{overflow:hidden;border-radius:14px;box-shadow:0 24px 72px rgba(15,23,42,.22)}
.schedule-detail-modal .ant-modal-body{max-height:calc(100dvh - 64px);overflow-y:auto;overscroll-behavior:contain}
@media(max-width:639px){.schedule-detail-modal .ant-modal{max-width:calc(100vw - 16px);margin:8px auto}.schedule-detail-modal .ant-modal-content{border-radius:12px}.schedule-detail-modal .ant-modal-body{max-height:calc(100dvh - 16px)}}
</style>

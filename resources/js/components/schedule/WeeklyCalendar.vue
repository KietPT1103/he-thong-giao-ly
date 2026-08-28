<script setup lang="ts">
import { computed, ref } from "vue";
import { CalendarDays } from "lucide-vue-next";
import ScheduleEventCard from "./ScheduleEventCard.vue";
import { localDateKey, sameDay, type TeachingCalendarEvent } from "./scheduleCalendar";

const props = defineProps<{
    days: Date[];
    events: TeachingCalendarEvent[];
    selectedKey?: string;
    activeDate: Date;
}>();
const emit = defineEmits<{
    selectEvent: [event: TeachingCalendarEvent];
    selectDay: [day: Date];
    swipe: [direction: "previous" | "next"];
}>();

const startHour = 7;
const endHour = 19;
const hours = Array.from({ length: endHour - startHour }, (_, index) => startHour + index);
const totalMinutes = (endHour - startHour) * 60;
const today = new Date();
const weekdayFormatter = new Intl.DateTimeFormat("vi-VN", { weekday: "short" });
const dayFormatter = new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit" });
const touchStartX = ref<number | null>(null);

const eventsByDay = computed(() => {
    const grouped = new Map<string, TeachingCalendarEvent[]>();
    props.days.forEach(day => grouped.set(localDateKey(day), []));
    props.events.forEach(event => grouped.get(event.dateKey)?.push(event));
    return grouped;
});

function eventStyle(event: TeachingCalendarEvent) {
    const clippedStart = Math.max(event.startMinutes, startHour * 60);
    const clippedEnd = Math.min(event.endMinutes, endHour * 60);
    return {
        top: `${((clippedStart - startHour * 60) / totalMinutes) * 100}%`,
        height: `${Math.max(((clippedEnd - clippedStart) / totalMinutes) * 100, 4.6)}%`,
    };
}

function onTouchEnd(event: TouchEvent) {
    if (touchStartX.value === null) return;
    const distance = event.changedTouches[0].clientX - touchStartX.value;
    touchStartX.value = null;
    if (Math.abs(distance) < 55) return;
    emit("swipe", distance > 0 ? "previous" : "next");
}
</script>

<template>
    <div class="weekly-calendar" @touchstart.passive="touchStartX=$event.touches[0].clientX" @touchend.passive="onTouchEnd">
        <div class="weekly-calendar-inner" :class="{'is-day-view':days.length===1}">
            <div class="calendar-corner"><span>Múi giờ</span><b>GMT+7</b></div>
            <button
                v-for="day in days"
                :key="localDateKey(day)"
                type="button"
                class="calendar-day-head"
                :class="{active:sameDay(day,activeDate),today:sameDay(day,today)}"
                @click="emit('selectDay',day)"
            >
                <span>{{ weekdayFormatter.format(day) }}</span>
                <strong>{{ dayFormatter.format(day) }}</strong>
            </button>

            <div class="calendar-time-column">
                <span v-for="hour in hours" :key="hour" :class="{'is-first-hour':hour===startHour}" :style="{top:`${((hour-startHour)/(endHour-startHour))*100}%`}">{{ hour > 12 ? hour-12 : hour }} {{ hour >= 12 ? 'PM' : 'AM' }}</span>
            </div>
            <div v-for="day in days" :key="`column-${localDateKey(day)}`" class="calendar-day-column" :class="{today:sameDay(day,today)}">
                <div v-if="!(eventsByDay.get(localDateKey(day))?.length)" class="day-empty" aria-hidden="true" />
                <div v-for="event in eventsByDay.get(localDateKey(day))" :key="event.key" class="event-position" :style="eventStyle(event)">
                    <ScheduleEventCard :event="event" :selected="event.key===selectedKey" @select="emit('selectEvent',$event)" />
                </div>
            </div>
        </div>
        <div v-if="!events.length" class="calendar-empty-overlay" role="status"><CalendarDays /><strong>Không có lịch trong khoảng này</strong><span>Thử chuyển tuần hoặc điều chỉnh tìm kiếm và bộ lọc.</span></div>
    </div>
</template>

<style scoped>
.weekly-calendar{position:relative;min-height:640px;background:#fff}.weekly-calendar-inner{display:grid;min-width:900px;grid-template-columns:64px repeat(7,minmax(112px,1fr));grid-template-rows:58px 760px}.weekly-calendar-inner.is-day-view{min-width:0;grid-template-columns:64px minmax(260px,1fr)}.calendar-corner,.calendar-day-head{position:sticky;z-index:5;top:0;border:0;border-bottom:1px solid #e7edf5;background:#fff}.calendar-corner{left:0;z-index:6;display:flex;flex-direction:column;justify-content:center;padding-left:12px;color:#94a3b8;font-size:8px}.calendar-corner b{margin-top:2px;color:#64748b;font-size:9px;font-weight:650}.calendar-day-head{cursor:pointer;padding:8px 4px 7px;color:#64748b;text-align:center;transition:background-color 140ms ease,color 140ms ease,box-shadow 140ms ease}.calendar-day-head:hover{background:#f8fbff;color:#1d4ed8}.calendar-day-head span,.calendar-day-head strong{display:block}.calendar-day-head span{font-size:9px;font-weight:700;text-transform:uppercase}.calendar-day-head strong{margin-top:2px;font-size:11px;font-weight:700;font-variant-numeric:tabular-nums}.calendar-day-head.active{color:#1d4ed8;box-shadow:inset 0 -3px #2563eb}.calendar-day-head.today span:after{content:" · Hôm nay";color:#2563eb}.calendar-time-column{position:sticky;z-index:3;left:0;border-right:1px solid #e7edf5;background:#fff}.calendar-time-column span{position:absolute;right:10px;transform:translateY(-50%);color:#94a3b8;font-size:9px;font-weight:600;font-variant-numeric:tabular-nums;white-space:nowrap}.calendar-time-column span.is-first-hour{transform:translateY(6px)}.calendar-day-column{position:relative;border-right:1px solid #edf1f6;background-color:#fff;background-image:repeating-linear-gradient(to bottom,transparent 0,transparent calc(8.333% - 1px),#edf1f6 calc(8.333% - 1px),#edf1f6 8.333%)}.calendar-day-column.today{background-color:#fbfdff}.event-position{position:absolute;z-index:2;left:4px;right:4px;min-height:44px}.event-position :deep(.schedule-event){height:100%}.calendar-empty-overlay{position:absolute;z-index:4;top:120px;left:50%;display:flex;max-width:320px;transform:translateX(-50%);align-items:center;flex-direction:column;border-radius:12px;background:rgba(255,255,255,.94);padding:20px;text-align:center;box-shadow:0 10px 30px rgba(15,23,42,.08)}.calendar-empty-overlay svg{width:28px;height:28px;color:#94a3b8}.calendar-empty-overlay strong{margin-top:8px;color:#334155;font-size:12px}.calendar-empty-overlay span{margin-top:4px;color:#64748b;font-size:10px;line-height:1.5}.day-empty{display:none}@media(max-width:1279px){.weekly-calendar-inner{min-width:840px;grid-template-columns:60px repeat(7,minmax(108px,1fr))}.calendar-time-column span{right:8px}}@media(max-width:767px){.weekly-calendar{min-height:620px}.weekly-calendar-inner{grid-template-rows:58px 720px}.weekly-calendar-inner:not(.is-day-view){min-width:860px}.calendar-day-head.today span:after{content:""}}@media(prefers-reduced-motion:reduce){.calendar-day-head{transition:none}}
@media(max-width:1399px){.weekly-calendar-inner{min-width:780px;grid-template-columns:60px repeat(7,minmax(100px,1fr))}}
</style>

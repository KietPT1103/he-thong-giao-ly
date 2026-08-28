<script setup lang="ts">
import { computed } from "vue";
import ScheduleEventCard from "./ScheduleEventCard.vue";
import { localDateKey, sameDay, type TeachingCalendarEvent } from "./scheduleCalendar";

const props = defineProps<{ days:Date[]; events:TeachingCalendarEvent[]; anchor:Date; selectedKey?:string }>();
const emit = defineEmits<{selectEvent:[event:TeachingCalendarEvent];selectDay:[day:Date]} >();
const today = new Date();
const eventsByDay = computed(() => {
    const grouped = new Map<string,TeachingCalendarEvent[]>();
    props.events.forEach(event => grouped.set(event.dateKey,[...(grouped.get(event.dateKey)??[]),event]));
    return grouped;
});
const headers = ["Thứ hai","Thứ ba","Thứ tư","Thứ năm","Thứ sáu","Thứ bảy","Chủ nhật"];
</script>

<template>
    <div class="month-calendar">
        <div class="month-head" aria-hidden="true"><span v-for="header in headers" :key="header">{{ header }}</span></div>
        <div class="month-grid">
            <section v-for="day in days" :key="localDateKey(day)" class="month-day" :class="{muted:day.getMonth()!==anchor.getMonth(),today:sameDay(day,today)}">
                <button type="button" class="month-day-number" :aria-label="`Xem ngày ${day.getDate()}`" @click="emit('selectDay',day)">{{ day.getDate() }}</button>
                <div class="month-events">
                    <ScheduleEventCard v-for="event in (eventsByDay.get(localDateKey(day))??[]).slice(0,3)" :key="event.key" compact :event="event" :selected="event.key===selectedKey" @select="emit('selectEvent',$event)" />
                    <span v-if="(eventsByDay.get(localDateKey(day))?.length??0)>3" class="month-more">+{{ (eventsByDay.get(localDateKey(day))?.length??0)-3 }} lịch khác</span>
                </div>
            </section>
        </div>
    </div>
</template>

<style scoped>
.month-calendar{min-width:760px;overflow:hidden;background:#fff}.month-head{display:grid;height:42px;grid-template-columns:repeat(7,1fr);border-bottom:1px solid #e7edf5;background:#f8fafc}.month-head span{display:grid;place-items:center;border-right:1px solid #edf1f6;color:#64748b;font-size:9px;font-weight:700;text-transform:uppercase}.month-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}.month-day{min-width:0;min-height:132px;border-right:1px solid #edf1f6;border-bottom:1px solid #edf1f6;padding:6px;background:#fff}.month-day.muted{background:#fafbfc}.month-day.muted .month-day-number,.month-day.muted .month-events{opacity:.48}.month-day.today{background:#f8fbff}.month-day-number{display:grid;width:26px;height:26px;cursor:pointer;place-items:center;border:0;border-radius:8px;background:transparent;color:#475569;font-size:10px;font-weight:700;font-variant-numeric:tabular-nums}.month-day-number:hover{background:#eff6ff;color:#1d4ed8}.month-day.today .month-day-number{background:#2563eb;color:#fff}.month-events{display:grid;gap:4px;margin-top:3px}.month-more{padding-left:5px;color:#64748b;font-size:9px;font-weight:600}@media(max-width:767px){.month-calendar{min-width:720px}.month-day{min-height:116px}}
</style>

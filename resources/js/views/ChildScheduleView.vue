<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import AButton from "ant-design-vue/es/button";
import {
    BookOpen, CalendarDays, ChevronLeft, ChevronRight, CircleAlert,
    Clock3, DoorOpen, GraduationCap, MapPin, RefreshCw, UserRound,
} from "lucide-vue-next";
import { getChildSchedule, type ChildScheduleData } from "../api/child";
import {
    addDays, buildTeachingEvents, formatWeekRange, localDateKey,
    sameDay, startOfWeek, type TeachingCalendarEvent, weekDays,
} from "../components/schedule/scheduleCalendar";

const scheduleData = ref<ChildScheduleData | null>(null);
const loading = ref(true);
const error = ref("");
const anchorDate = ref(startOfWeek(new Date()));
const today = new Date();
const weekdayFormatter = new Intl.DateTimeFormat("vi-VN", { weekday: "long" });
const dateFormatter = new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit" });
const longDateFormatter = new Intl.DateTimeFormat("vi-VN", { weekday: "long", day: "2-digit", month: "2-digit", year: "numeric" });

const currentClass = computed(() => scheduleData.value?.class ?? null);
const visibleDays = computed(() => weekDays(anchorDate.value));
const events = computed(() => currentClass.value
    ? buildTeachingEvents([currentClass.value], visibleDays.value)
    : []);
const eventsByDay = computed(() => {
    const grouped = new Map<string, TeachingCalendarEvent[]>();
    visibleDays.value.forEach(day => grouped.set(localDateKey(day), []));
    events.value.forEach(event => grouped.get(event.dateKey)?.push(event));
    return grouped;
});
const upcomingEvent = computed(() => {
    if (!currentClass.value) return null;
    const days = Array.from({ length: 21 }, (_, index) => addDays(today, index));
    const now = Date.now();
    return buildTeachingEvents([currentClass.value], days).find(event => {
        const startsAt = new Date(event.date);
        const [hours, minutes] = event.startsAt.split(":").map(Number);
        startsAt.setHours(hours, minutes, 0, 0);
        return startsAt.getTime() >= now;
    }) ?? null;
});
const primaryTeacher = computed(() => currentClass.value?.teachers?.find(teacher => teacher.role === "primary")
    ?? currentClass.value?.teachers?.[0]);

function apiMessage(value: unknown) {
    return (value as { response?: { data?: { message?: string } } }).response?.data?.message
        ?? "Không thể tải lịch học. Hãy thử lại.";
}
function capitalize(value: string) { return value.replace(/^./, character => character.toLocaleUpperCase("vi")); }
function moveWeek(direction: -1 | 1) { anchorDate.value = addDays(anchorDate.value, direction * 7); }
function goToday() { anchorDate.value = startOfWeek(new Date()); }

async function load() {
    loading.value = true;
    error.value = "";
    try { scheduleData.value = (await getChildSchedule()).data.data; }
    catch (exception) { error.value = apiMessage(exception); }
    finally { loading.value = false; }
}

onMounted(load);
</script>

<template>
    <section class="child-schedule-page">
        <div v-if="loading" class="schedule-loading" aria-busy="true" aria-label="Đang tải lịch học">
            <span /><span /><span />
        </div>

        <section v-else-if="error" class="schedule-state schedule-state--error" role="alert">
            <CircleAlert aria-hidden="true" />
            <div><h1>Chưa tải được lịch học</h1><p>{{ error }}</p></div>
            <AButton @click="load"><RefreshCw aria-hidden="true" />Thử lại</AButton>
        </section>

        <section v-else-if="!currentClass" class="schedule-state" role="status">
            <GraduationCap aria-hidden="true" />
            <div><h1>Em chưa được xếp lớp</h1><p>Lịch học sẽ xuất hiện sau khi quản trị viên xếp em vào một lớp giáo lý.</p></div>
        </section>

        <template v-else>
            <header class="schedule-heading">
                <div>
                    <span><CalendarDays aria-hidden="true" />Lịch cố định của lớp</span>
                    <h1>Lịch học của em</h1>
                    <p>Theo dõi ngày học, giờ học và phòng học của lớp {{ currentClass.name }}.</p>
                </div>
                <div v-if="upcomingEvent" class="next-lesson" aria-label="Buổi học tiếp theo">
                    <span>Buổi học tiếp theo</span>
                    <strong>{{ capitalize(longDateFormatter.format(upcomingEvent.date)) }}</strong>
                    <small><Clock3 aria-hidden="true" />{{ upcomingEvent.startsAt }}–{{ upcomingEvent.endsAt }}</small>
                </div>
            </header>

            <dl class="class-context" aria-label="Thông tin lớp học">
                <div><span><BookOpen aria-hidden="true" /></span><dt>Lớp học</dt><dd>{{ currentClass.name }} · {{ currentClass.code }}</dd></div>
                <div><span><DoorOpen aria-hidden="true" /></span><dt>Phòng học</dt><dd>{{ currentClass.classroom?.name || "Chưa phân phòng" }}</dd></div>
                <div><span><UserRound aria-hidden="true" /></span><dt>Giáo lý viên</dt><dd>{{ primaryTeacher?.name || "Chưa phân công" }}</dd></div>
            </dl>

            <section class="week-board" aria-labelledby="week-title">
                <header class="week-toolbar">
                    <div><h2 id="week-title">Tuần này</h2><p>{{ formatWeekRange(visibleDays) }}</p></div>
                    <div class="week-actions">
                        <AButton aria-label="Tuần trước" @click="moveWeek(-1)"><ChevronLeft aria-hidden="true" /></AButton>
                        <AButton @click="goToday">Hôm nay</AButton>
                        <AButton aria-label="Tuần sau" @click="moveWeek(1)"><ChevronRight aria-hidden="true" /></AButton>
                    </div>
                </header>

                <div class="week-grid">
                    <article
                        v-for="day in visibleDays"
                        :key="localDateKey(day)"
                        class="day-column"
                        :class="{ 'is-today': sameDay(day, today), 'has-lesson': eventsByDay.get(localDateKey(day))?.length }"
                    >
                        <header><span>{{ capitalize(weekdayFormatter.format(day)) }}</span><strong>{{ dateFormatter.format(day) }}</strong><small v-if="sameDay(day,today)">Hôm nay</small></header>
                        <div v-if="eventsByDay.get(localDateKey(day))?.length" class="day-lessons">
                            <div v-for="event in eventsByDay.get(localDateKey(day))" :key="event.key" class="lesson-item">
                                <span><Clock3 aria-hidden="true" />{{ event.startsAt }}–{{ event.endsAt }}</span>
                                <strong>{{ event.classItem.name }}</strong>
                                <small><MapPin aria-hidden="true" />{{ event.classItem.classroom?.name || "Chưa phân phòng" }}</small>
                            </div>
                        </div>
                        <p v-else class="day-empty">Không có buổi học</p>
                    </article>
                </div>
            </section>
        </template>
    </section>
</template>

<style scoped>
.child-schedule-page{display:grid;width:100%;max-width:1500px;margin-inline:auto;gap:1rem}.schedule-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:1.5rem;padding:.5rem .125rem}.schedule-heading>div:first-child{min-width:0}.schedule-heading>div:first-child>span{display:inline-flex;align-items:center;gap:.45rem;color:#2563eb;font-size:.7rem;font-weight:700}.schedule-heading>div:first-child>span svg{width:1rem;height:1rem}.schedule-heading h1{margin:.4rem 0 0;color:#172554;font-size:clamp(1.5rem,2vw,2rem);font-weight:780;letter-spacing:-.025em}.schedule-heading p{max-width:65ch;margin:.35rem 0 0;color:#64748b;font-size:.78rem;line-height:1.6}.next-lesson{display:flex;min-width:18rem;flex:none;flex-direction:column;border:1px solid #bfdbfe;border-radius:.75rem;background:#eff6ff;padding:.8rem 1rem;color:#1e3a8a}.next-lesson>span{font-size:.62rem;font-weight:650}.next-lesson strong{margin-top:.2rem;font-size:.78rem}.next-lesson small{display:flex;align-items:center;gap:.35rem;margin-top:.25rem;color:#315f9c;font-size:.68rem;font-variant-numeric:tabular-nums}.next-lesson svg{width:.85rem;height:.85rem}.class-context{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));overflow:hidden;margin:0;border:1px solid #dbe3ee;border-radius:.875rem;background:#fff}.class-context>div{display:grid;min-width:0;grid-template-columns:auto minmax(0,1fr);column-gap:.7rem;padding:1rem}.class-context>div+div{border-left:1px solid #e2e8f0}.class-context>div>span{display:grid;width:2.35rem;height:2.35rem;grid-row:1/3;place-items:center;border-radius:.625rem;background:#edf4ff;color:#2563eb}.class-context svg{width:1.05rem;height:1.05rem}.class-context dt{color:#64748b;font-size:.65rem}.class-context dd{overflow:hidden;margin:.2rem 0 0;color:#172554;font-size:.75rem;font-weight:700;text-overflow:ellipsis;white-space:nowrap}.week-board{overflow:hidden;border:1px solid #dbe3ee;border-radius:.875rem;background:#fff;box-shadow:0 .5rem 1.75rem rgba(15,23,42,.045)}.week-toolbar{display:flex;min-height:4.5rem;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 1rem;border-bottom:1px solid #e2e8f0}.week-toolbar h2,.week-toolbar p{margin:0}.week-toolbar h2{color:#172554;font-size:.9rem;font-weight:750}.week-toolbar p{margin-top:.2rem;color:#64748b;font-size:.68rem;font-variant-numeric:tabular-nums}.week-actions{display:flex;gap:.45rem}.week-actions :deep(.ant-btn){min-height:2.5rem;border-radius:.6rem}.week-actions :deep(.ant-btn:first-child),.week-actions :deep(.ant-btn:last-child){width:2.5rem;padding:0}.week-actions svg{width:1rem;height:1rem}.week-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}.day-column{min-width:0;min-height:13rem;padding:.85rem;border-left:1px solid #edf1f6;background:#fff}.day-column:first-child{border-left:0}.day-column>header{display:flex;min-height:3rem;flex-wrap:wrap;align-content:start;align-items:baseline;gap:.2rem .4rem;border-bottom:1px solid #edf1f6;padding-bottom:.6rem}.day-column>header span{color:#475569;font-size:.68rem;font-weight:700}.day-column>header strong{margin-left:auto;color:#64748b;font-size:.66rem;font-variant-numeric:tabular-nums}.day-column>header small{width:100%;color:#2563eb;font-size:.58rem;font-weight:650}.day-column.is-today{background:#fbfdff}.day-column.is-today>header{border-bottom-color:#bfdbfe}.day-lessons{display:grid;gap:.5rem;margin-top:.7rem}.lesson-item{display:flex;min-width:0;flex-direction:column;border-radius:.625rem;background:#eff6ff;padding:.65rem;color:#1e3a8a}.lesson-item>span,.lesson-item>small{display:flex;align-items:center;gap:.3rem}.lesson-item>span{font-size:.6rem;font-weight:650;font-variant-numeric:tabular-nums}.lesson-item strong{overflow:hidden;margin-top:.3rem;font-size:.7rem;text-overflow:ellipsis;white-space:nowrap}.lesson-item>small{margin-top:.3rem;color:#526f9c;font-size:.58rem}.lesson-item svg{width:.75rem;height:.75rem;flex:none}.day-empty{margin:1rem 0 0;color:#94a3b8;font-size:.62rem;line-height:1.5}.schedule-state{display:flex;min-height:20rem;align-items:center;justify-content:center;gap:1rem;border:1px solid #dbe3ee;border-radius:.875rem;background:#fff;padding:2rem}.schedule-state>svg{width:2.5rem;height:2.5rem;color:#2563eb}.schedule-state h1,.schedule-state p{margin:0}.schedule-state h1{color:#172554;font-size:1rem;font-weight:750}.schedule-state p{max-width:55ch;margin-top:.35rem;color:#64748b;font-size:.75rem;line-height:1.6}.schedule-state>.ant-btn{display:flex;min-height:2.75rem;align-items:center;gap:.4rem;margin-left:1rem}.schedule-state>.ant-btn svg{width:1rem}.schedule-state--error>svg{color:#dc2626}.schedule-loading{display:grid;gap:.75rem}.schedule-loading span{height:5rem;border-radius:.875rem;background:linear-gradient(90deg,#eef2f7 20%,#fff 50%,#eef2f7 80%);background-size:200% 100%;animation:schedule-pulse 1.4s infinite}.schedule-loading span:last-child{height:20rem}@keyframes schedule-pulse{to{background-position:-200% 0}}
.class-context>div{min-height:4.75rem;grid-template-columns:2.5rem minmax(0,1fr);grid-template-rows:auto auto;align-content:center;column-gap:.8rem;row-gap:.15rem;padding:.9rem 1rem}
.class-context>div>span{width:2.5rem;height:2.5rem;grid-row:1/-1}
.class-context svg{width:1.1rem;height:1.1rem}
.class-context dt{align-self:end;font-size:.8125rem;font-weight:500;line-height:1.35}
.class-context dd{min-width:0;align-self:start;overflow:visible;margin:0;font-size:.9375rem;line-height:1.4;text-overflow:clip;white-space:normal;overflow-wrap:anywhere}
@media(min-width:1600px){.child-schedule-page{max-width:1600px;gap:1.25rem}.week-toolbar{padding-inline:1.25rem}.day-column{min-height:15rem;padding:1rem}.lesson-item{padding:.75rem}.lesson-item strong{font-size:.75rem}}
@media(max-width:1199px){.week-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.day-column{min-height:9rem;border-top:1px solid #edf1f6}.day-column:nth-child(odd){border-left:0}.day-column:nth-child(-n+2){border-top:0}.day-column:last-child{grid-column:1/-1}.day-column>header{min-height:2.25rem}.day-empty{margin:.75rem 0 0}}
@media(max-width:767px){.child-schedule-page{gap:.75rem}.schedule-heading{align-items:stretch;flex-direction:column;padding:.25rem 0}.schedule-heading h1{font-size:1.45rem}.schedule-heading p{font-size:.75rem}.next-lesson{width:100%;min-width:0}.class-context{grid-template-columns:1fr}.class-context>div+div{border-top:1px solid #e2e8f0;border-left:0}.week-toolbar{align-items:flex-start;flex-direction:column}.week-actions{width:100%;display:grid;grid-template-columns:2.75rem minmax(0,1fr) 2.75rem}.week-actions :deep(.ant-btn){width:100%!important;min-height:2.75rem}.week-grid{grid-template-columns:1fr}.day-column,.day-column:last-child{min-height:0;grid-column:auto;border-top:1px solid #edf1f6;border-left:0}.day-column:first-child{border-top:0}.day-column:nth-child(2){border-top:1px solid #edf1f6}.day-column>header{min-height:0}.day-column:not(.has-lesson):not(.is-today){display:grid;grid-template-columns:minmax(0,1fr) max-content;align-items:center;column-gap:.75rem}.day-column:not(.has-lesson):not(.is-today)>header{min-width:0;border:0;padding:0}.day-column:not(.has-lesson):not(.is-today) .day-empty{margin:0;text-align:right;white-space:nowrap}.schedule-state{min-height:18rem;align-items:flex-start;flex-direction:column;padding:1.5rem}.schedule-state>.ant-btn{width:100%;justify-content:center;margin-left:0}}
@media(max-width:359px){.class-context>div{padding:.875rem}.day-column{padding:.75rem}.week-toolbar{padding:.75rem}.lesson-item{padding:.6rem}}
@media(pointer:coarse){.week-actions :deep(.ant-btn){min-height:2.75rem}.lesson-item{min-height:4rem}}
.schedule-heading>div:first-child>span{font-size:.75rem}.schedule-heading p{font-size:.875rem}.next-lesson>span{font-size:.7rem}.next-lesson strong{font-size:.875rem}.next-lesson small{font-size:.75rem}.week-toolbar h2{font-size:1rem}.week-toolbar p{font-size:.75rem}.day-column>header span,.day-column>header strong{font-size:.75rem}.day-column>header small{font-size:.65rem}.lesson-item>span{font-size:.7rem}.lesson-item strong{font-size:.8125rem}.lesson-item>small{font-size:.68rem}.day-empty{font-size:.7rem}.schedule-state p{font-size:.8125rem}
@media(prefers-reduced-motion:reduce){.schedule-loading span{animation:none}}
</style>

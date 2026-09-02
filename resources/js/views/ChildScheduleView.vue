<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import AButton from "ant-design-vue/es/button";
import {
    CalendarDays, CalendarX2, ChevronLeft, ChevronRight, CircleAlert,
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
const longDateFormatter = new Intl.DateTimeFormat("vi-VN", {
    weekday: "long",
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
});
const scheduleHeroImage = "/images/02_individual_assets/clock-bg.png";

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

function capitalize(value: string) {
    return value.replace(/^./, character => character.toLocaleUpperCase("vi"));
}

function moveWeek(direction: -1 | 1) {
    anchorDate.value = addDays(anchorDate.value, direction * 7);
}

function goToday() {
    anchorDate.value = startOfWeek(new Date());
}

async function load() {
    loading.value = true;
    error.value = "";
    try {
        scheduleData.value = (await getChildSchedule()).data.data;
    } catch (exception) {
        error.value = apiMessage(exception);
    } finally {
        loading.value = false;
    }
}

onMounted(load);
</script>

<!--
THESIS: Lịch tuần là trung tâm; buổi học kế tiếp nổi bật nhưng không lấn át khả năng quét lịch.
OWN-WORLD: Nền xanh trắng sáng, thẻ viền mảnh, minh họa học tập 3D và chữ xanh mực của Hành Trang Đức Tin.
STORY: Thiếu nhi nhận ra lớp, phòng, Giáo lý viên và buổi học gần nhất trước khi xem chi tiết từng ngày.
FIRST VIEWPORT: Hero ngang gồm tiêu đề, minh họa và thẻ buổi kế tiếp; ba thẻ ngữ cảnh nằm ngay trước bảng tuần.
FORM: Bảng lịch bảy cột trên desktop, chuyển hai cột ở tablet và danh sách ngày trên mobile.
-->
<template>
    <section class="mx-auto grid w-full max-w-[1600px] gap-5 max-sm:gap-3.5">
        <div
            v-if="loading"
            class="grid gap-4"
            aria-busy="true"
            aria-label="Đang tải lịch học"
        >
            <span class="h-56 animate-pulse rounded-2xl bg-slate-200/70" />
            <span class="h-24 animate-pulse rounded-2xl bg-slate-200/70" />
            <span class="h-80 animate-pulse rounded-2xl bg-slate-200/70" />
        </div>

        <section
            v-else-if="error"
            class="flex min-h-88 items-center justify-center gap-4 rounded-2xl border border-slate-200 bg-white p-8 max-sm:min-h-72 max-sm:flex-col max-sm:items-start max-sm:p-6"
            role="alert"
        >
            <CircleAlert class="size-11 shrink-0 text-red-600" aria-hidden="true" />
            <div>
                <h1 class="m-0 text-lg font-bold text-slate-900">Chưa tải được lịch học</h1>
                <p class="mt-1.5 max-w-[55ch] text-sm leading-6 text-slate-500">{{ error }}</p>
            </div>
            <AButton class="!ml-4 !flex !h-11 !items-center !gap-2 !rounded-xl max-sm:!ml-0 max-sm:!w-full max-sm:!justify-center" @click="load">
                <RefreshCw class="size-4" aria-hidden="true" />
                Thử lại
            </AButton>
        </section>

        <section
            v-else-if="!currentClass"
            class="flex min-h-88 items-center justify-center gap-4 rounded-2xl border border-slate-200 bg-white p-8 max-sm:min-h-72 max-sm:flex-col max-sm:items-start max-sm:p-6"
            role="status"
        >
            <GraduationCap class="size-11 shrink-0 text-blue-600" aria-hidden="true" />
            <div>
                <h1 class="m-0 text-lg font-bold text-slate-900">Em chưa được xếp lớp</h1>
                <p class="mt-1.5 max-w-[55ch] text-sm leading-6 text-slate-500">
                    Lịch học sẽ xuất hiện sau khi quản trị viên xếp em vào một lớp giáo lý.
                </p>
            </div>
        </section>

        <template v-else>
            <section
                class="relative isolate grid min-h-42 grid-cols-[minmax(18rem,1.2fr)_minmax(9rem,.55fr)_minmax(20rem,1fr)] items-center gap-5 overflow-hidden rounded-2xl border border-[#d6e4f6] bg-[#f3f8ff] px-6 py-5 shadow-[0_12px_32px_rgba(37,99,235,0.045)] max-[900px]:grid-cols-1 max-[900px]:gap-6 max-[900px]:px-6 max-[900px]:py-6 max-sm:gap-5 max-sm:px-5 max-sm:py-5"
                aria-labelledby="schedule-title"
            >
                <img
                    class="pointer-events-none relative z-[5] col-start-2 row-start-1 h-36 w-full max-w-none -translate-x-1/4 scale-[2.15] object-cover object-center opacity-90 mix-blend-multiply select-none [mask-image:linear-gradient(to_right,transparent_0%,black_22%,black_78%,transparent_100%)] [-webkit-mask-image:linear-gradient(to_right,transparent_0%,black_22%,black_78%,transparent_100%)] max-[900px]:hidden"
                    :src="scheduleHeroImage"
                    alt=""
                    width="2172"
                    height="724"
                    aria-hidden="true"
                />

                <div class="relative z-10 min-w-0">
                    <span class="inline-flex items-center gap-2 text-sm font-bold text-blue-600">
                        <CalendarDays class="size-[1.1rem]" aria-hidden="true" />
                        Lịch cố định của lớp
                    </span>
                    <h1
                        id="schedule-title"
                        class="mt-2.5 text-[clamp(2rem,2.6vw,2.5rem)] leading-[1.05] font-extrabold tracking-[-0.035em] text-[#10214a] max-sm:mt-2.5 max-sm:text-[2rem]"
                    >
                        Lịch học của em
                    </h1>
                    <p class="mt-2 max-w-[55ch] text-[0.9375rem] leading-6 text-slate-500 max-sm:text-sm">
                        Theo dõi ngày học, giờ học và phòng học của lớp {{ currentClass.name }}.
                    </p>
                </div>

                <aside
                    class="relative z-10 col-start-3 grid w-full max-w-[22rem] min-w-0 justify-self-end grid-cols-[auto_minmax(0,1fr)] items-center gap-4 overflow-hidden rounded-2xl border border-[#dbe6f5] bg-white/95 px-5 py-4 shadow-[0_13px_32px_rgba(15,23,42,0.07)] before:absolute before:inset-y-0 before:left-0 before:w-0.5 before:bg-gradient-to-b before:from-blue-500 before:via-blue-600 before:to-transparent max-[900px]:col-start-1 max-[900px]:justify-self-start max-[900px]:max-w-md max-sm:gap-3 max-sm:px-4 max-sm:py-4"
                    :class="{ 'shadow-[0_13px_32px_rgba(15,23,42,0.05)]': !upcomingEvent }"
                    aria-label="Buổi học tiếp theo"
                >
                    <span class="grid size-14 shrink-0 place-items-center rounded-full bg-[#edf4ff] text-blue-600 max-sm:size-13">
                        <CalendarDays class="size-6" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <small class="block text-[0.8125rem] text-slate-500">Buổi học tiếp theo</small>
                        <template v-if="upcomingEvent">
                            <strong class="mt-1 block text-base leading-snug font-bold text-blue-700">
                                {{ capitalize(longDateFormatter.format(upcomingEvent.date)) }}
                            </strong>
                            <p class="mt-2 flex items-center gap-1.5 text-sm text-slate-600 tabular-nums">
                                <Clock3 class="size-4" aria-hidden="true" />
                                {{ upcomingEvent.startsAt }}–{{ upcomingEvent.endsAt }}
                            </p>
                        </template>
                        <template v-else>
                            <strong class="mt-1 block text-base leading-snug font-bold text-slate-700">
                                Chưa có lịch sắp tới
                            </strong>
                            <p class="mt-2 text-sm text-slate-600">Lịch mới sẽ hiển thị tại đây.</p>
                        </template>
                    </div>
                </aside>
            </section>

            <dl class="grid grid-cols-3 gap-4 max-[900px]:grid-cols-2 max-sm:grid-cols-1 max-sm:gap-3" aria-label="Thông tin lớp học">
                <div class="grid min-h-24 min-w-0 grid-cols-[3rem_minmax(0,1fr)] items-center gap-4 rounded-[0.875rem] border border-[#dbe3ee] bg-white px-5 py-4 shadow-[0_7px_20px_rgba(15,23,42,0.04)] max-sm:min-h-21 max-sm:px-4 max-sm:py-3.5">
                    <span class="grid size-12 place-items-center rounded-xl bg-[#eef4ff] text-blue-600 max-sm:size-10.5">
                        <GraduationCap class="size-[1.45rem]" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <dt class="text-sm font-medium leading-5 text-slate-500">Lớp học</dt>
                        <dd class="mt-1 break-words text-base leading-5.5 font-bold text-[#172554]">
                            {{ currentClass.name }} · {{ currentClass.code }}
                        </dd>
                    </div>
                </div>
                <div class="grid min-h-24 min-w-0 grid-cols-[3rem_minmax(0,1fr)] items-center gap-4 rounded-[0.875rem] border border-[#dbe3ee] bg-white px-5 py-4 shadow-[0_7px_20px_rgba(15,23,42,0.04)] max-sm:min-h-21 max-sm:px-4 max-sm:py-3.5">
                    <span class="grid size-12 place-items-center rounded-xl bg-[#eef4ff] text-blue-600 max-sm:size-10.5">
                        <DoorOpen class="size-[1.45rem]" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <dt class="text-sm font-medium leading-5 text-slate-500">Phòng học</dt>
                        <dd class="mt-1 break-words text-base leading-5.5 font-bold text-[#172554]">
                            {{ currentClass.classroom?.name || "Chưa phân phòng" }}
                        </dd>
                    </div>
                </div>
                <div class="grid min-h-24 min-w-0 grid-cols-[3rem_minmax(0,1fr)] items-center gap-4 rounded-[0.875rem] border border-[#dbe3ee] bg-white px-5 py-4 shadow-[0_7px_20px_rgba(15,23,42,0.04)] max-[900px]:col-span-2 max-sm:col-span-1 max-sm:min-h-21 max-sm:px-4 max-sm:py-3.5">
                    <span class="grid size-12 place-items-center rounded-xl bg-[#eef4ff] text-blue-600 max-sm:size-10.5">
                        <UserRound class="size-[1.45rem]" aria-hidden="true" />
                    </span>
                    <div class="min-w-0">
                        <dt class="text-sm font-medium leading-5 text-slate-500">Giáo lý viên</dt>
                        <dd class="mt-1 break-words text-base leading-5.5 font-bold text-[#172554]">
                            {{ primaryTeacher?.name || "Chưa phân công" }}
                        </dd>
                    </div>
                </div>
            </dl>

            <section class="overflow-hidden rounded-2xl border border-[#dbe3ee] bg-white shadow-[0_12px_32px_rgba(15,23,42,0.045)]" aria-labelledby="week-title">
                <header class="flex min-h-23 items-center justify-between gap-4 border-b border-slate-200 px-6 py-4 max-sm:flex-col max-sm:items-stretch max-sm:px-4">
                    <div class="flex min-w-0 items-center gap-3.5">
                        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-[#eef4ff] text-blue-600">
                            <CalendarDays class="size-[1.35rem]" aria-hidden="true" />
                        </span>
                        <div>
                            <h2 id="week-title" class="text-[1.0625rem] font-bold text-[#172554]">Tuần này</h2>
                            <p class="mt-1 text-[0.8125rem] text-slate-500 tabular-nums">{{ formatWeekRange(visibleDays) }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2.5 max-sm:grid max-sm:w-full max-sm:grid-cols-[2.75rem_minmax(0,1fr)_2.75rem]">
                        <AButton class="!flex !size-11 !items-center !justify-center !rounded-xl !p-0 max-sm:!w-full" aria-label="Tuần trước" @click="moveWeek(-1)">
                            <ChevronLeft class="size-4" aria-hidden="true" />
                        </AButton>
                        <AButton class="!flex !h-11 !items-center !justify-center !rounded-xl !border-blue-200 !font-semibold !text-blue-600 max-sm:!w-full" @click="goToday">
                            Hôm nay
                        </AButton>
                        <AButton class="!flex !size-11 !items-center !justify-center !rounded-xl !p-0 max-sm:!w-full" aria-label="Tuần sau" @click="moveWeek(1)">
                            <ChevronRight class="size-4" aria-hidden="true" />
                        </AButton>
                    </div>
                </header>

                <div class="grid grid-cols-7 gap-px bg-slate-200 max-[1440px]:grid-cols-2 max-sm:grid-cols-1">
                    <article
                        v-for="(day, dayIndex) in visibleDays"
                        :key="localDateKey(day)"
                        class="flex min-h-68 min-w-0 flex-col bg-white max-[1440px]:min-h-40 max-sm:min-h-0"
                        :class="{
                            'bg-slate-50/70': sameDay(day, today),
                            'max-[1440px]:col-span-2 max-sm:col-span-1': dayIndex === visibleDays.length - 1,
                        }"
                    >
                        <header
                            class="grid min-h-22 grid-cols-[minmax(0,1fr)_auto] content-center gap-x-2 gap-y-1 border-b border-slate-200 px-4 py-3.5 max-sm:min-h-0 max-sm:px-4 max-sm:py-3"
                            :class="{ '!border-blue-200': sameDay(day, today) }"
                        >
                            <span class="text-[0.8125rem] leading-4 font-bold text-slate-600" :class="{ '!text-blue-600': eventsByDay.get(localDateKey(day))?.length }">
                                {{ capitalize(weekdayFormatter.format(day)) }}
                            </span>
                            <strong class="text-[0.8125rem] leading-4 font-medium text-slate-500 tabular-nums">
                                {{ dateFormatter.format(day) }}
                            </strong>
                            <small v-if="sameDay(day, today)" class="col-span-2 w-max rounded-full bg-blue-50 px-1.5 py-0.5 text-[0.6875rem] leading-4 font-bold text-blue-600">
                                Hôm nay
                            </small>
                        </header>

                        <div v-if="eventsByDay.get(localDateKey(day))?.length" class="grid gap-2.5 p-4 max-sm:px-4 max-sm:pt-0 max-sm:pb-4">
                            <div
                                v-for="event in eventsByDay.get(localDateKey(day))"
                                :key="event.key"
                                class="flex min-w-0 flex-col rounded-xl border border-[#cfe0ff] bg-[#f2f7ff] p-3.5 text-blue-900"
                            >
                                <span class="flex items-center gap-1.5 whitespace-nowrap text-[0.8125rem] font-bold tabular-nums">
                                    <Clock3 class="size-3.5 shrink-0" aria-hidden="true" />
                                    {{ event.startsAt }}–{{ event.endsAt }}
                                </span>
                                <strong class="mt-1.5 overflow-hidden text-[0.9375rem] leading-5 font-bold text-ellipsis whitespace-nowrap">
                                    {{ event.classItem.name }}
                                </strong>
                                <small class="mt-1.5 flex items-center gap-1.5 text-xs text-[#526f9c]">
                                    <MapPin class="size-3.5 shrink-0" aria-hidden="true" />
                                    {{ event.classItem.classroom?.name || "Chưa phân phòng" }}
                                </small>
                            </div>
                        </div>

                        <div v-else class="grid flex-1 place-content-center place-items-center gap-3 px-3 py-6 text-center text-slate-400 max-sm:flex max-sm:justify-end max-sm:gap-2 max-sm:px-4 max-sm:py-3 max-sm:text-right">
                            <CalendarX2 class="size-9 text-slate-300 max-sm:size-4" aria-hidden="true" />
                            <span class="text-[0.8125rem] leading-5">Không có buổi học</span>
                        </div>
                    </article>
                </div>
            </section>
        </template>
    </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from "vue";
import { CalendarDays, ChevronLeft, ChevronRight, Clock3 } from "lucide-vue-next";
import { monthGridDays, sameDay } from "../schedule/scheduleCalendar";
import ScrollableArea from "./ScrollableArea.vue";

const props = withDefaults(defineProps<{
    modelValue: string;
    disabled?: boolean;
}>(), { disabled: false });

const emit = defineEmits<{ "update:modelValue": [value: string] }>();
const weekdays = ["T2", "T3", "T4", "T5", "T6", "T7", "CN"];
const hours = Array.from({ length: 12 }, (_, index) => index + 1);
const minutes = Array.from({ length: 60 }, (_, index) => index);
const periods = ["SA", "CH"] as const;
const dayFormatter = new Intl.DateTimeFormat("vi-VN", { dateStyle: "full" });
const hourList = ref<HTMLElement | null>(null);
const minuteList = ref<HTMLElement | null>(null);

function parseDate(value: string) {
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? new Date() : date;
}

function localDateTime(value: Date) {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, "0");
    const day = String(value.getDate()).padStart(2, "0");
    const hour = String(value.getHours()).padStart(2, "0");
    const minute = String(value.getMinutes()).padStart(2, "0");
    return `${year}-${month}-${day}T${hour}:${minute}`;
}

const selected = computed(() => parseDate(props.modelValue));
const visibleMonth = ref(new Date(selected.value.getFullYear(), selected.value.getMonth(), 1));
const days = computed(() => monthGridDays(visibleMonth.value));
const monthLabel = computed(() => new Intl.DateTimeFormat("vi-VN", { month: "long", year: "numeric" }).format(visibleMonth.value));
const selectedHour = computed(() => (selected.value.getHours() % 12) || 12);
const selectedMinute = computed(() => selected.value.getMinutes());
const selectedPeriod = computed<"SA" | "CH">(() => selected.value.getHours() < 12 ? "SA" : "CH");
const selectedLabel = computed(() => {
    const date = selected.value;
    const datePart = new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit", year: "numeric" }).format(date);
    return `${datePart} ${String(selectedHour.value).padStart(2, "0")}:${String(selectedMinute.value).padStart(2, "0")} ${selectedPeriod.value}`;
});

function update(mutator: (value: Date) => void) {
    const value = new Date(selected.value);
    mutator(value);
    value.setSeconds(0, 0);
    emit("update:modelValue", localDateTime(value));
}

function selectDay(day: Date) {
    update(value => value.setFullYear(day.getFullYear(), day.getMonth(), day.getDate()));
    visibleMonth.value = new Date(day.getFullYear(), day.getMonth(), 1);
}

function selectHour(hour: number) {
    update(value => value.setHours((hour % 12) + (selectedPeriod.value === "CH" ? 12 : 0)));
}

function selectMinute(minute: number) {
    update(value => value.setMinutes(minute));
}

function selectPeriod(period: "SA" | "CH") {
    update(value => value.setHours((selectedHour.value % 12) + (period === "CH" ? 12 : 0)));
}

function selectToday() {
    const today = new Date();
    selectDay(today);
}

function centerOption(host: HTMLElement | null, value: string | number) {
    const container = host?.querySelector<HTMLElement>(".time-option-list");
    const option = container?.querySelector<HTMLElement>(`[data-value="${value}"]`);
    if (container && option) container.scrollTop = option.offsetTop - (container.clientHeight - option.clientHeight) / 2;
}

function centerSelectedTime() {
    void nextTick(() => {
        centerOption(hourList.value, selectedHour.value);
        centerOption(minuteList.value, selectedMinute.value);
    });
}

watch(() => props.modelValue, centerSelectedTime);
onMounted(centerSelectedTime);
</script>

<template>
    <div class="date-time-picker">
        <section class="date-picker-calendar" aria-label="Chọn ngày bắt đầu">
            <header class="date-picker-navigation">
                <button type="button" :disabled="disabled" aria-label="Tháng trước" @click="visibleMonth=new Date(visibleMonth.getFullYear(),visibleMonth.getMonth()-1,1)"><ChevronLeft /></button>
                <strong>{{ monthLabel }}</strong>
                <button type="button" :disabled="disabled" aria-label="Tháng sau" @click="visibleMonth=new Date(visibleMonth.getFullYear(),visibleMonth.getMonth()+1,1)"><ChevronRight /></button>
            </header>
            <div class="date-picker-weekdays" aria-hidden="true"><span v-for="weekday in weekdays" :key="weekday">{{ weekday }}</span></div>
            <div class="date-picker-days">
                <button
                    v-for="day in days"
                    :key="day.toISOString()"
                    type="button"
                    :disabled="disabled"
                    :class="{ muted:day.getMonth()!==visibleMonth.getMonth(), selected:sameDay(day,selected), today:sameDay(day,new Date()) }"
                    :aria-label="dayFormatter.format(day)"
                    :aria-pressed="sameDay(day,selected)"
                    @click="selectDay(day)"
                >{{ day.getDate() }}</button>
            </div>
            <button type="button" class="today-button" :disabled="disabled" @click="selectToday"><CalendarDays />Hôm nay</button>
        </section>

        <section class="time-picker-panel" aria-label="Chọn giờ bắt đầu">
            <h3>Thời gian</h3>
            <div class="time-picker-columns">
                <div class="time-column"><span>Giờ</span><div ref="hourList" class="time-list-shell"><ScrollableArea class="time-option-list" role="listbox" aria-label="Giờ"><button v-for="hour in hours" :key="hour" type="button" role="option" :data-value="hour" :aria-selected="hour===selectedHour" :class="{ selected:hour===selectedHour }" :disabled="disabled" @click="selectHour(hour)">{{ String(hour).padStart(2,"0") }}</button></ScrollableArea></div></div>
                <i aria-hidden="true">:</i>
                <div class="time-column"><span>Phút</span><div ref="minuteList" class="time-list-shell"><ScrollableArea class="time-option-list" role="listbox" aria-label="Phút"><button v-for="minute in minutes" :key="minute" type="button" role="option" :data-value="minute" :aria-selected="minute===selectedMinute" :class="{ selected:minute===selectedMinute }" :disabled="disabled" @click="selectMinute(minute)">{{ String(minute).padStart(2,"0") }}</button></ScrollableArea></div></div>
                <i aria-hidden="true">:</i>
                <div class="time-column"><span>SA/CH</span><div class="time-option-list time-period-list" role="listbox" aria-label="Buổi"><button v-for="period in periods" :key="period" type="button" role="option" :aria-selected="period===selectedPeriod" :class="{ selected:period===selectedPeriod }" :disabled="disabled" @click="selectPeriod(period)">{{ period }}</button></div></div>
            </div>
            <div class="selected-time-summary" aria-live="polite"><Clock3 /><span><small>Thời gian đã chọn</small><strong>{{ selectedLabel }}</strong></span></div>
        </section>
    </div>
</template>

<style scoped>
.date-time-picker{display:grid;grid-template-columns:minmax(0,1.08fr) minmax(0,1fr);gap:16px;color:#334155;font-variant-numeric:tabular-nums}.date-picker-calendar,.time-picker-panel{min-width:0;border:1px solid #dbe3ee;border-radius:12px;background:#fff;padding:16px}.date-picker-navigation{display:grid;grid-template-columns:40px minmax(0,1fr) 40px;align-items:center;gap:8px}.date-picker-navigation strong{text-align:center;color:#172554;font-size:14px;font-weight:750;text-transform:capitalize}.date-picker-navigation button,.today-button{cursor:pointer;border:0;background:transparent;color:#475569}.date-picker-navigation button{display:grid;width:40px;height:40px;place-items:center;border-radius:9px;transition:background-color 140ms ease,color 140ms ease}.date-picker-navigation button:hover:not(:disabled){background:#eff6ff;color:#1d4ed8}.date-picker-navigation svg{width:17px;height:17px}.date-picker-weekdays,.date-picker-days{display:grid;grid-template-columns:repeat(7,minmax(0,1fr))}.date-picker-weekdays{margin-top:8px}.date-picker-weekdays span{padding-block:8px;color:#64748b;font-size:10px;font-weight:700;text-align:center}.date-picker-days button{position:relative;display:grid;min-width:0;height:38px;cursor:pointer;place-items:center;border:0;border-radius:9px;background:transparent;color:#334155;font-size:11px;font-weight:650;transition:background-color 140ms ease,color 140ms ease,box-shadow 140ms ease}.date-picker-days button:hover:not(:disabled){background:#eff6ff;color:#1d4ed8}.date-picker-days button.muted{color:#a8b3c4}.date-picker-days button.selected{background:#1677ff;color:#fff;box-shadow:0 4px 10px rgba(22,119,255,.24)}.date-picker-days button.today:not(.selected)::after{position:absolute;bottom:3px;left:50%;width:3px;height:3px;transform:translateX(-50%);border-radius:50%;background:#1677ff;content:""}.today-button{display:flex;min-height:40px;align-items:center;gap:7px;margin-top:5px;border-radius:9px;padding-inline:8px;color:#1677ff;font-size:11px;font-weight:700;transition:background-color 140ms ease}.today-button:hover:not(:disabled){background:#eff6ff}.today-button svg{width:15px;height:15px}.time-picker-panel{display:flex;flex-direction:column}.time-picker-panel h3{margin:2px 0 13px;color:#64748b;font-size:11px;font-weight:650}.time-picker-columns{display:grid;grid-template-columns:minmax(0,1fr) 10px minmax(0,1fr) 10px minmax(0,1fr);align-items:end;gap:4px}.time-picker-columns>i{align-self:center;margin-top:22px;color:#64748b;font-size:15px;font-style:normal;font-weight:750;text-align:center}.time-column{display:grid;min-width:0;gap:7px}.time-column>span{color:#475569;font-size:10px;font-weight:700;text-align:center}.time-option-list{height:172px;overflow-y:auto;overscroll-behavior:contain;border:1px solid #dbe3ee;border-radius:10px;background:#fbfdff;padding:67px 5px;scrollbar-color:#93c5fd transparent;scrollbar-width:thin}.time-option-list button{display:block;width:100%;height:36px;cursor:pointer;border:0;border-radius:7px;background:transparent;color:#52627c;font:inherit;font-size:11px;font-weight:650;scroll-snap-align:center;transition:background-color 120ms ease,color 120ms ease,box-shadow 120ms ease}.time-option-list button:hover:not(:disabled){background:#eff6ff;color:#1d4ed8}.time-option-list button.selected{background:#1677ff;color:#fff;box-shadow:0 3px 8px rgba(22,119,255,.22)}.time-period-list{display:flex;flex-direction:column;justify-content:center;gap:4px;overflow:hidden;padding:8px 5px}.selected-time-summary{display:flex;align-items:flex-start;gap:8px;margin-top:14px;border-top:1px solid #e2e8f0;padding-top:13px}.selected-time-summary>svg{width:16px;height:16px;flex:none;margin-top:1px;color:#64748b;stroke-width:1.8}.selected-time-summary span{display:grid;gap:5px}.selected-time-summary small{color:#64748b;font-size:10px}.selected-time-summary strong{color:#1677ff;font-size:13px;font-weight:750}.date-time-picker button:disabled{cursor:not-allowed;opacity:.55}@media(max-width:639px){.date-time-picker{grid-template-columns:1fr}.date-picker-calendar,.time-picker-panel{padding:12px}.time-option-list{height:152px;padding-block:57px}}@media(prefers-reduced-motion:reduce){.date-picker-navigation button,.date-picker-days button,.today-button,.time-option-list button{transition:none}}
.time-option-list{position:relative;scrollbar-color:#60a5fa transparent}.time-option-list::-webkit-scrollbar{width:5px}.time-option-list::-webkit-scrollbar-track{background:transparent}.time-option-list::-webkit-scrollbar-thumb{border-radius:999px;background:#60a5fa}.time-option-list::-webkit-scrollbar-button{display:none;width:0;height:0}
.time-list-shell{height:172px;min-width:0}.time-list-shell :deep(.ui-scroll-shell){height:172px}.time-list-shell :deep(.time-option-list){position:relative;height:172px;overflow-y:auto;overscroll-behavior:contain;border:1px solid #dbe3ee;border-radius:10px;background:#fbfdff;padding:67px 5px;scrollbar-width:none}.time-list-shell :deep(.time-option-list::-webkit-scrollbar){display:none;width:0;height:0}@media(max-width:639px){.time-list-shell,.time-list-shell :deep(.ui-scroll-shell),.time-list-shell :deep(.time-option-list){height:152px}.time-list-shell :deep(.time-option-list){padding-block:57px}}
</style>

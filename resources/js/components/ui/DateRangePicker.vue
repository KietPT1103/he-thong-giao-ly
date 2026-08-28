<script setup lang="ts">
import { computed, ref, watch } from "vue";
import APopover from "ant-design-vue/es/popover";
import { ArrowRight, CalendarDays, ChevronDown, ChevronLeft, ChevronRight } from "lucide-vue-next";
import { addDays, monthGridDays, sameDay, startOfWeek } from "../schedule/scheduleCalendar";

const props = withDefaults(defineProps<{
    modelValue: [Date, Date];
    label: string;
    weekSelection?: boolean;
}>(), { weekSelection: false });
const emit = defineEmits<{ "update:modelValue": [value: [Date, Date]] }>();

const open = ref(false);
const visibleMonth = ref(new Date(props.modelValue[0].getFullYear(), props.modelValue[0].getMonth(), 1));
const days = computed(() => monthGridDays(visibleMonth.value));
const monthLabel = computed(() => new Intl.DateTimeFormat("vi-VN", { month: "long", year: "numeric" }).format(visibleMonth.value));
const dateFormatter = new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit", year: "numeric" });
const weekdays = ["T2", "T3", "T4", "T5", "T6", "T7", "CN"];

watch(() => props.modelValue, ([start]) => {
    visibleMonth.value = new Date(start.getFullYear(), start.getMonth(), 1);
}, { deep: true });

function isInRange(day: Date) {
    const time = day.setHours(0, 0, 0, 0);
    return time >= new Date(props.modelValue[0]).setHours(0, 0, 0, 0) && time <= new Date(props.modelValue[1]).setHours(0, 0, 0, 0);
}

function selectDay(day: Date) {
    const start = props.weekSelection ? startOfWeek(day) : new Date(day);
    const end = props.weekSelection ? addDays(start, 6) : new Date(day);
    emit("update:modelValue", [start, end]);
    open.value = false;
}

function applyPreset(kind: "last-week" | "last-month" | "this-week" | "this-month") {
    const today = new Date();
    if (kind === "last-week") {
        const start = addDays(startOfWeek(today), -7);
        emit("update:modelValue", [start, addDays(start, 6)]);
    } else if (kind === "this-week") {
        const start = startOfWeek(today);
        emit("update:modelValue", [start, addDays(start, 6)]);
    } else {
        const offset = kind === "last-month" ? -1 : 0;
        const start = new Date(today.getFullYear(), today.getMonth() + offset, 1);
        const end = new Date(start.getFullYear(), start.getMonth() + 1, 0);
        emit("update:modelValue", [start, end]);
    }
    open.value = false;
}
</script>

<template>
    <APopover v-model:open="open" trigger="click" placement="bottomRight" overlay-class-name="date-range-popover">
        <button type="button" class="date-range-trigger" :aria-expanded="open" aria-label="Chọn khoảng thời gian">
            <CalendarDays aria-hidden="true" />
            <span>{{ label }}</span>
            <ChevronDown class="trigger-chevron" :class="{ rotated: open }" aria-hidden="true" />
        </button>
        <template #content>
            <section class="date-range-panel" aria-label="Chọn khoảng ngày">
                <div class="range-summary">
                    <div><small>Từ ngày</small><b><CalendarDays />{{ dateFormatter.format(modelValue[0]) }}</b></div>
                    <ArrowRight class="range-arrow" aria-hidden="true" />
                    <div><small>Đến ngày</small><b><CalendarDays />{{ dateFormatter.format(modelValue[1]) }}</b></div>
                </div>
                <div class="range-presets" aria-label="Khoảng thời gian nhanh">
                    <button type="button" @click="applyPreset('last-week')">Tuần trước</button>
                    <button type="button" @click="applyPreset('last-month')">Tháng trước</button>
                    <button type="button" @click="applyPreset('this-week')">Tuần này</button>
                    <button type="button" @click="applyPreset('this-month')">Tháng này</button>
                </div>
                <header class="calendar-navigation">
                    <strong>{{ monthLabel }}</strong>
                    <div>
                        <button type="button" aria-label="Tháng trước" @click="visibleMonth=new Date(visibleMonth.getFullYear(),visibleMonth.getMonth()-1,1)"><ChevronLeft /></button>
                        <button type="button" aria-label="Tháng sau" @click="visibleMonth=new Date(visibleMonth.getFullYear(),visibleMonth.getMonth()+1,1)"><ChevronRight /></button>
                    </div>
                </header>
                <div class="calendar-weekdays"><span v-for="weekday in weekdays" :key="weekday">{{ weekday }}</span></div>
                <div class="calendar-days">
                    <button
                        v-for="day in days"
                        :key="day.toISOString()"
                        type="button"
                        :class="{ muted:day.getMonth()!==visibleMonth.getMonth(),range:isInRange(day),start:sameDay(day,modelValue[0]),end:sameDay(day,modelValue[1]),today:sameDay(day,new Date()) }"
                        :aria-label="dateFormatter.format(day)"
                        @click="selectDay(day)"
                    >{{ day.getDate() }}</button>
                </div>
                <p v-if="weekSelection" class="range-hint">Chọn một ngày để hiển thị trọn tuần chứa ngày đó.</p>
            </section>
        </template>
    </APopover>
</template>

<style scoped>
.date-range-trigger{display:flex;min-width:174px;height:34px;cursor:pointer;align-items:center;justify-content:center;gap:7px;border:0;border-radius:8px;background:transparent;padding:0 8px;color:#334155;font-size:10px;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap;transition:background-color 140ms ease,color 140ms ease}.date-range-trigger:hover,.date-range-trigger[aria-expanded=true]{background:#eff6ff;color:#1d4ed8}.date-range-trigger>svg{width:14px;height:14px;color:#2563eb}.date-range-trigger .trigger-chevron{width:12px;height:12px;color:#64748b;transition:transform 140ms ease}.trigger-chevron.rotated{transform:rotate(180deg)}
</style>

<style>
.date-range-popover .ant-popover-inner{border-radius:14px;padding:0;box-shadow:0 18px 50px rgba(15,23,42,.16)}.date-range-panel{width:360px;padding:14px;color:#1e293b}.range-summary{display:grid;grid-template-columns:1fr auto 1fr;align-items:end;gap:9px;border:1px solid #dbe5f2;border-radius:11px;background:#f8fafc;padding:10px}.range-summary>div{display:grid;gap:5px}.range-summary small{color:#64748b;font-size:10px;font-weight:650}.range-summary b{display:flex;height:38px;align-items:center;gap:7px;border:1px solid #cbd5e1;border-radius:9px;background:#fff;padding:0 10px;color:#172554;font-size:11px;font-weight:700;font-variant-numeric:tabular-nums}.range-summary b svg{width:15px;height:15px;color:#2563eb}.range-summary>div:last-child b{border-color:#60a5fa;box-shadow:0 0 0 2px rgba(37,99,235,.12)}.range-arrow{width:16px;height:16px;margin-bottom:11px;color:#64748b}.range-presets{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:8px}.range-presets button{min-height:30px;cursor:pointer;border:0;border-radius:7px;background:#f1f5f9;color:#475569;font-size:9px;font-weight:650;transition:background-color 140ms ease,color 140ms ease}.range-presets button:hover{background:#dbeafe;color:#1d4ed8}.calendar-navigation{display:flex;align-items:center;justify-content:space-between;padding:16px 2px 10px}.calendar-navigation strong{color:#172554;font-size:15px;font-weight:750;text-transform:capitalize}.calendar-navigation>div{display:flex;gap:4px}.calendar-navigation button{display:grid;width:34px;height:34px;cursor:pointer;place-items:center;border:0;border-radius:8px;background:transparent;color:#475569}.calendar-navigation button:hover{background:#eff6ff;color:#1d4ed8}.calendar-navigation svg{width:16px;height:16px}.calendar-weekdays,.calendar-days{display:grid;grid-template-columns:repeat(7,1fr)}.calendar-weekdays span{padding:5px 0 7px;color:#64748b;font-size:9px;font-weight:700;text-align:center}.calendar-days button{position:relative;z-index:1;height:38px;cursor:pointer;border:0;background:transparent;color:#334155;font-size:11px;font-weight:650;font-variant-numeric:tabular-nums}.calendar-days button:hover:before,.calendar-days button.start:before,.calendar-days button.end:before{position:absolute;z-index:-1;inset:3px;content:"";border-radius:9px;background:#dbeafe}.calendar-days button.range{background:#eff6ff;color:#1d4ed8}.calendar-days button.start,.calendar-days button.end{color:#fff}.calendar-days button.start:before,.calendar-days button.end:before{inset:2px;background:#2563eb;box-shadow:0 3px 8px rgba(37,99,235,.25)}.calendar-days button.start:before{border-radius:11px 2px 2px 11px;clip-path:polygon(0 0,calc(100% - 8px) 0,100% 50%,calc(100% - 8px) 100%,0 100%)}.calendar-days button.end:before{border-radius:2px 11px 11px 2px;clip-path:polygon(8px 0,100% 0,100% 100%,8px 100%,0 50%)}.calendar-days button.start.end:before{border-radius:11px;clip-path:none}.calendar-days button.muted{color:#a8b3c4}.calendar-days button.today:after{position:absolute;bottom:3px;left:50%;width:3px;height:3px;transform:translateX(-50%);border-radius:50%;background:#2563eb;content:""}.calendar-days button.start.today:after,.calendar-days button.end.today:after{background:#fff}.range-hint{margin:9px 2px 0;color:#64748b;font-size:9px;line-height:1.45}@media(max-width:479px){.date-range-panel{width:min(340px,calc(100vw - 32px));padding:12px}.range-summary{grid-template-columns:1fr}.range-arrow{display:none}.range-presets{grid-template-columns:repeat(2,1fr)}}@media(prefers-reduced-motion:reduce){.date-range-trigger,.date-range-trigger .trigger-chevron,.range-presets button{transition:none}}
</style>

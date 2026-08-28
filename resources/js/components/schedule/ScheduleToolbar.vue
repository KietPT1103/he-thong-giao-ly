<script setup lang="ts">
import AButton from "ant-design-vue/es/button";
import AInput from "ant-design-vue/es/input";
import { ChevronLeft, ChevronRight, Search } from "lucide-vue-next";
import DateRangePicker from "../ui/DateRangePicker.vue";
import ScheduleFilterDropdown from "./ScheduleFilterDropdown.vue";
import type { ScheduleTab, ScheduleViewMode } from "./scheduleCalendar";

defineProps<{
    tab: ScheduleTab;
    query: string;
    viewMode: ScheduleViewMode;
    rangeLabel: string;
    range: [Date, Date];
    eventCount: number;
    classOptions: Array<{ value:number; label:string }>;
    selectedClassIds: number[];
}>();
const emit = defineEmits<{
    "update:tab": [value: ScheduleTab];
    "update:query": [value: string];
    "update:viewMode": [value: ScheduleViewMode];
    "update:selectedClassIds": [value: number[]];
    "update:range": [value: [Date, Date]];
    previous: [];
    next: [];
}>();

const tabs: Array<{ value: ScheduleTab; label: string }> = [
    { value: "all", label: "Tất cả lịch" },
    { value: "teaching", label: "Buổi dạy" },
    { value: "attendance", label: "Điểm danh" },
];
const modes: Array<{ value: ScheduleViewMode; label: string }> = [
    { value: "day", label: "Ngày" },
    { value: "week", label: "Tuần" },
    { value: "month", label: "Tháng" },
];
</script>

<template>
    <div class="schedule-toolbar" aria-label="Điều khiển lịch dạy">
        <div class="schedule-tabs-group">
            <div class="schedule-tabs" role="tablist" aria-label="Loại lịch">
                <button v-for="item in tabs" :key="item.value" type="button" role="tab" :aria-selected="tab===item.value" :class="{active:tab===item.value}" @click="emit('update:tab',item.value)">{{ item.label }}</button>
            </div>
            <span class="schedule-result-count" aria-live="polite">{{ eventCount }} buổi</span>
        </div>
        <AInput :value="query" allow-clear class="schedule-search" placeholder="Tìm kiếm lớp, buổi dạy..." aria-label="Tìm kiếm lịch dạy" @update:value="emit('update:query',String($event ?? ''))">
            <template #prefix><Search aria-hidden="true" /></template>
        </AInput>
        <ScheduleFilterDropdown :model-value="selectedClassIds" :options="classOptions" @update:model-value="emit('update:selectedClassIds',$event)" />
        <div class="schedule-view-switch" aria-label="Chế độ xem">
            <button v-for="mode in modes" :key="mode.value" type="button" :aria-pressed="viewMode===mode.value" :class="{active:viewMode===mode.value}" @click="emit('update:viewMode',mode.value)">{{ mode.label }}</button>
        </div>
        <div class="schedule-range-control">
            <AButton type="text" aria-label="Khoảng thời gian trước" @click="emit('previous')"><ChevronLeft /></AButton>
            <DateRangePicker :label="rangeLabel" :model-value="range" :week-selection="viewMode==='week'" @update:model-value="emit('update:range',$event)" />
            <AButton type="text" aria-label="Khoảng thời gian tiếp theo" @click="emit('next')"><ChevronRight /></AButton>
        </div>
    </div>
</template>

<style scoped>
.schedule-toolbar{display:grid;grid-template-columns:auto minmax(190px,1fr) auto auto auto;align-items:center;gap:10px;padding:10px;border-bottom:1px solid #e7edf5;background:#fff}.schedule-tabs-group,.schedule-tabs,.schedule-view-switch,.schedule-range-control{display:flex;align-items:center}.schedule-tabs-group{gap:8px}.schedule-result-count{display:inline-flex;min-height:24px;align-items:center;border-radius:7px;background:#eff6ff;padding:4px 8px;color:#1d4ed8;font-size:10px;font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap}.schedule-tabs,.schedule-view-switch{height:40px;overflow:hidden;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc}.schedule-tabs button,.schedule-view-switch button{height:100%;cursor:pointer;border:0;border-right:1px solid #e2e8f0;background:transparent;padding:0 12px;color:#64748b;font-size:11px;font-weight:650;transition:background-color 140ms ease,color 140ms ease,box-shadow 140ms ease}.schedule-tabs button:last-child,.schedule-view-switch button:last-child{border-right:0}.schedule-tabs button:hover,.schedule-view-switch button:hover{background:#fff;color:#1d4ed8}.schedule-tabs button.active,.schedule-view-switch button.active{background:#fff;color:#1d4ed8;box-shadow:inset 0 -2px #2563eb}.schedule-search{min-height:40px;border-radius:10px}.schedule-search :deep(svg){width:15px;height:15px;color:#94a3b8}.schedule-filter-button{position:relative;min-height:40px;border-radius:10px;color:#475569;font-size:11px;font-weight:650}.schedule-filter-button :deep(svg){width:15px;height:15px}.schedule-filter-button.active{border-color:#93c5fd;background:#eff6ff;color:#1d4ed8}.filter-dot{position:absolute;top:7px;right:7px;width:6px;height:6px;border:1px solid #fff;border-radius:50%;background:#2563eb}.schedule-range-control{min-width:210px;height:40px;justify-content:space-between;border:1px solid #e2e8f0;border-radius:10px;background:#fff;padding:0 3px}.schedule-range-control span{min-width:116px;color:#334155;font-size:10px;font-weight:650;text-align:center;font-variant-numeric:tabular-nums;white-space:nowrap}.schedule-range-control :deep(.ant-btn){width:34px;min-width:34px;height:34px;padding:0}.schedule-range-control :deep(svg){width:15px;height:15px}.today-button{color:#2563eb}@media(max-width:1399px){.schedule-toolbar{grid-template-columns:auto minmax(180px,1fr) auto auto}.schedule-range-control{grid-column:1/-1;justify-self:end}}@media(max-width:1023px){.schedule-toolbar{grid-template-columns:minmax(0,1fr) auto auto}.schedule-tabs-group{grid-column:1/-1;justify-self:start}.schedule-search{min-width:0}.schedule-range-control{grid-column:auto;min-width:190px}}@media(max-width:767px){.schedule-toolbar{display:flex;align-items:stretch;overflow-x:auto;padding:10px;scrollbar-width:none}.schedule-toolbar::-webkit-scrollbar{display:none}.schedule-tabs-group,.schedule-tabs,.schedule-view-switch,.schedule-range-control,.schedule-search,.schedule-filter-button{flex:none}.schedule-tabs button,.schedule-view-switch button{padding-inline:11px}.schedule-search{width:220px}.schedule-range-control{min-width:200px}}
</style>

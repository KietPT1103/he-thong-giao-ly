<script setup lang="ts">
import { ref, watch } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import AInput from "ant-design-vue/es/input";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import ATooltip from "ant-design-vue/es/tooltip";
import { Plus, Trash2 } from "lucide-vue-next";
import type { AdminClass, ClassScheduleInput } from "../api/admin";

const props = withDefaults(defineProps<{open:boolean;model:AdminClass|null;saving?:boolean}>(), {saving:false});
const emit = defineEmits<{close:[];submit:[rows:ClassScheduleInput[]]} >();
const rows = ref<ClassScheduleInput[]>([]);
const error = ref("");
const weekdays = [
    {value:1,label:"Thứ hai"},{value:2,label:"Thứ ba"},{value:3,label:"Thứ tư"},{value:4,label:"Thứ năm"},
    {value:5,label:"Thứ sáu"},{value:6,label:"Thứ bảy"},{value:7,label:"Chủ nhật"},
];
watch(() => props.open, (open) => {
    if (!open) return;
    error.value = "";
    rows.value = (props.model?.schedules ?? []).map(({weekday,starts_at,ends_at,starts_on,ends_on}) => ({weekday,starts_at,ends_at,starts_on,ends_on}));
});
function add() { rows.value.push({weekday:7,starts_at:"08:00",ends_at:"09:30",starts_on:null,ends_on:null}); }
function changeDate(row:ClassScheduleInput, field:"starts_on"|"ends_on", value:string|number|undefined) {
    row[field] = value ? String(value) : null;
}
function submit() {
    const invalid = rows.value.find(row => !row.weekday || !row.starts_at || !row.ends_at || row.starts_at >= row.ends_at || (row.starts_on && row.ends_on && row.starts_on > row.ends_on));
    if (invalid) { error.value = "Kiểm tra lại thứ, giờ bắt đầu/kết thúc và khoảng ngày của từng lịch."; return; }
    error.value = "";
    emit("submit", rows.value);
}
</script>

<template>
    <AModal :open="open" title="Thiết lập lịch học" width="820px" centered :mask-closable="false" :closable="!saving" :confirm-loading="saving" ok-text="Lưu lịch học" cancel-text="Hủy" @cancel="emit('close')" @ok="submit">
        <div class="schedule-heading"><p>{{ model?.name }} · {{ rows.length }} lịch định kỳ</p><AButton @click="add"><template #icon><Plus class="size-4" /></template>Thêm lịch</AButton></div>
        <AAlert v-if="error" type="error" show-icon :message="error" class="mb-3" />
        <div v-if="rows.length" class="schedule-grid">
            <div class="schedule-labels" aria-hidden="true"><span>Ngày học</span><span>Bắt đầu</span><span>Kết thúc</span><span>Từ ngày</span><span>Đến ngày</span><span></span></div>
            <div v-for="(row,index) in rows" :key="index" class="schedule-row">
                <ASelect v-model:value="row.weekday" aria-label="Ngày học" :options="weekdays" />
                <AInput v-model:value="row.starts_at" aria-label="Giờ bắt đầu" type="time" />
                <AInput v-model:value="row.ends_at" aria-label="Giờ kết thúc" type="time" />
                <AInput :value="row.starts_on ?? ''" aria-label="Ngày bắt đầu áp dụng" type="date" @update:value="changeDate(row,'starts_on',$event)" />
                <AInput :value="row.ends_on ?? ''" aria-label="Ngày kết thúc áp dụng" type="date" @update:value="changeDate(row,'ends_on',$event)" />
                <ATooltip title="Xóa lịch"><AButton danger type="text" aria-label="Xóa lịch" @click="rows.splice(index,1)"><template #icon><Trash2 class="size-4" /></template></AButton></ATooltip>
            </div>
        </div>
        <div v-else class="schedule-empty"><p>Chưa có lịch học định kỳ.</p><AButton type="primary" @click="add"><template #icon><Plus class="size-4" /></template>Thêm lịch đầu tiên</AButton></div>
    </AModal>
</template>

<style scoped>
.schedule-heading{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.75rem}.schedule-heading p{margin:0;color:#64748b;font-size:.75rem}.schedule-grid{overflow-x:auto;border-block:1px solid #e2e8f0}.schedule-labels,.schedule-row{display:grid;grid-template-columns:8rem 6.5rem 6.5rem 9.5rem 9.5rem 2.5rem;gap:.5rem;min-width:48rem}.schedule-labels{padding:.6rem .25rem;color:#64748b;font-size:.68rem;font-weight:650}.schedule-row{align-items:center;min-height:3.75rem;padding:.5rem .25rem;border-top:1px solid #e2e8f0}.schedule-row :deep(.ant-select),.schedule-row :deep(.ant-input){width:100%}.schedule-empty{display:grid;min-height:12rem;place-items:center;align-content:center;gap:.75rem;border-block:1px solid #e2e8f0}.schedule-empty p{margin:0;color:#64748b;font-size:.8rem}@media(max-width:639px){.schedule-heading{align-items:stretch;flex-direction:column}.schedule-heading .ant-btn{align-self:flex-start}}
.schedule-labels,.schedule-row{grid-template-columns:8rem 7.5rem 7.5rem 9.5rem 9.5rem 2.5rem}
</style>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import AButton from "ant-design-vue/es/button";
import AInput from "ant-design-vue/es/input";
import APopover from "ant-design-vue/es/popover";
import { Check, ChevronDown, ListFilter, Search } from "lucide-vue-next";

const props = defineProps<{
    options: Array<{ value:number; label:string }>;
    modelValue: number[];
}>();
const emit = defineEmits<{"update:modelValue":[value:number[]]}>();
const open = ref(false);
const query = ref("");
const draft = ref<number[]>([]);
const filteredOptions = computed(() => {
    const term = query.value.trim().toLocaleLowerCase("vi");
    return props.options.filter(option => !term || option.label.toLocaleLowerCase("vi").includes(term));
});

watch(open, value => {
    if (!value) return;
    draft.value = [...props.modelValue];
    query.value = "";
});

function toggle(value:number) {
    draft.value = draft.value.includes(value)
        ? draft.value.filter(item => item !== value)
        : [...draft.value, value];
}

function apply() {
    emit("update:modelValue", [...draft.value]);
    open.value = false;
}
</script>

<template>
    <APopover v-model:open="open" trigger="click" placement="bottomRight" overlay-class-name="schedule-filter-popover">
        <template #content>
            <section class="filter-select-panel" aria-label="Lọc lịch theo lớp">
                <header><div><strong>Lọc theo lớp</strong><span>Chọn một hoặc nhiều lớp phụ trách</span></div><b v-if="draft.length">{{ draft.length }}</b></header>
                <AInput v-model:value="query" allow-clear placeholder="Tìm tên hoặc mã lớp" aria-label="Tìm lớp trong bộ lọc"><template #prefix><Search /></template></AInput>
                <div class="filter-options" role="listbox" aria-multiselectable="true">
                    <button v-for="option in filteredOptions" :key="option.value" type="button" role="option" :aria-selected="draft.includes(option.value)" :class="{selected:draft.includes(option.value)}" @click="toggle(option.value)">
                        <span class="filter-check" aria-hidden="true"><Check v-if="draft.includes(option.value)" /></span>
                        <span>{{ option.label }}</span>
                        <Check v-if="draft.includes(option.value)" />
                    </button>
                    <div v-if="!filteredOptions.length" class="filter-empty">Không tìm thấy lớp phù hợp.</div>
                </div>
                <footer><button type="button" :disabled="!draft.length" @click="draft=[]">Xóa tất cả</button><div><AButton @click="open=false">Hủy</AButton><AButton type="primary" @click="apply">Áp dụng</AButton></div></footer>
            </section>
        </template>
        <AButton class="schedule-filter-button" :class="{active:open||modelValue.length}" :aria-expanded="open" aria-haspopup="listbox">
            <template #icon><ListFilter /></template>
            Bộ lọc
            <span v-if="modelValue.length" class="filter-count">{{ modelValue.length }}</span>
            <ChevronDown class="filter-chevron" :class="{rotated:open}" />
        </AButton>
    </APopover>
</template>

<style scoped>
.schedule-filter-button{min-height:40px;border-radius:10px;color:#475569;font-size:11px;font-weight:650}.schedule-filter-button :deep(svg){width:15px;height:15px;stroke-width:2}.schedule-filter-button.active{border-color:#93c5fd;background:#eff6ff;color:#1d4ed8}.filter-count{display:grid;min-width:18px;height:18px;place-items:center;border-radius:6px;background:#2563eb;padding-inline:4px;color:#fff;font-size:9px;font-variant-numeric:tabular-nums}.filter-chevron{transition:transform 140ms ease}.filter-chevron.rotated{transform:rotate(180deg)}.filter-select-panel{width:320px}.filter-select-panel header{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}.filter-select-panel header strong,.filter-select-panel header span{display:block}.filter-select-panel header strong{color:#172554;font-size:13px;font-weight:750}.filter-select-panel header span{margin-top:3px;color:#64748b;font-size:10px}.filter-select-panel header b{display:grid;min-width:24px;height:24px;place-items:center;border-radius:7px;background:#eff6ff;color:#1d4ed8;font-size:10px;font-variant-numeric:tabular-nums}.filter-select-panel :deep(.ant-input-affix-wrapper){min-height:40px;border-radius:9px}.filter-select-panel :deep(.ant-input-prefix svg){width:15px;height:15px;color:#94a3b8}.filter-options{max-height:248px;margin-top:10px;overflow-y:auto;border-block:1px solid #edf1f6;padding-block:4px;overscroll-behavior:contain}.filter-options>button{display:grid;width:100%;min-height:42px;cursor:pointer;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:9px;border:0;border-radius:8px;background:transparent;padding:7px 8px;color:#475569;text-align:left;transition:background-color 120ms ease,color 120ms ease}.filter-options>button:hover{background:#f8fafc;color:#1d4ed8}.filter-options>button.selected{background:#eff6ff;color:#1d4ed8}.filter-options>button>span{overflow:hidden;font-size:11px;font-weight:600;text-overflow:ellipsis;white-space:nowrap}.filter-options>button>svg{width:14px;height:14px;stroke-width:2}.filter-empty{padding:24px 12px;color:#64748b;font-size:11px;text-align:center}.filter-select-panel footer{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-top:12px}.filter-select-panel footer>button{min-height:36px;cursor:pointer;border:0;background:transparent;color:#2563eb;font-size:10px;font-weight:650}.filter-select-panel footer>button:disabled{cursor:not-allowed;color:#94a3b8}.filter-select-panel footer>div{display:flex;gap:8px}.filter-select-panel footer :deep(.ant-btn){min-height:36px;border-radius:9px;font-size:10px;font-weight:650}@media(max-width:479px){.filter-select-panel{width:min(300px,calc(100vw - 48px))}}@media(prefers-reduced-motion:reduce){.filter-chevron,.filter-options>button{transition:none}}
.filter-options>button>span:not(.filter-check){overflow:hidden;font-size:11px;font-weight:600;text-overflow:ellipsis;white-space:nowrap}.filter-check{display:grid;width:17px;height:17px;place-items:center;border:1px solid #cbd5e1;border-radius:5px;background:#fff;color:#fff}.filter-options>button.selected .filter-check{border-color:#2563eb;background:#2563eb}.filter-check svg{width:12px;height:12px;stroke-width:2.5}
</style>

<style>
.schedule-filter-popover .ant-popover-inner{border-radius:12px;padding:14px;box-shadow:0 16px 42px rgba(15,23,42,.16)}
</style>

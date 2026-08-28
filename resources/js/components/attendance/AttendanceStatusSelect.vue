<script setup lang="ts">
import { computed, ref } from "vue";
import APopover from "ant-design-vue/es/popover";
import { Check, ChevronDown, CircleHelp, Clock3, LogOut, UserMinus, UserRoundX } from "lucide-vue-next";
import type { AttendanceStatus } from "../../types/api";

const props = defineProps<{ modelValue: AttendanceStatus; disabled?: boolean; studentName: string }>();
const emit = defineEmits<{ "update:modelValue": [value: AttendanceStatus] }>();
const open = ref(false);
const options: Array<{ value: AttendanceStatus; label: string; icon: typeof Check; tone: string }> = [
    { value: "present", label: "Có mặt", icon: Check, tone: "present" },
    { value: "late", label: "Đi trễ", icon: Clock3, tone: "late" },
    { value: "excused_absence", label: "Nghỉ phép", icon: UserMinus, tone: "excused" },
    { value: "unexcused_absence", label: "Vắng", icon: UserRoundX, tone: "absent" },
    { value: "left_early", label: "Về sớm", icon: LogOut, tone: "early" },
    { value: "unknown", label: "Chưa ghi nhận", icon: CircleHelp, tone: "unknown" },
];
const selected = computed(() => options.find(option => option.value === props.modelValue) ?? options[5]);

function select(value: AttendanceStatus) {
    emit("update:modelValue", value);
    open.value = false;
}
</script>

<template>
    <APopover v-model:open="open" trigger="click" placement="bottomRight" overlay-class-name="attendance-status-popover">
        <button type="button" class="status-trigger" :class="`status-${selected.tone}`" :disabled="disabled" :aria-expanded="open" :aria-label="`Trạng thái của ${studentName}: ${selected.label}`">
            <component :is="selected.icon" aria-hidden="true" />
            <span>{{ selected.label }}</span>
            <ChevronDown class="status-chevron" :class="{rotated:open}" aria-hidden="true" />
        </button>
        <template #content>
            <div class="status-menu" role="listbox" :aria-label="`Chọn trạng thái cho ${studentName}`">
                <button v-for="option in options" :key="option.value" type="button" role="option" :aria-selected="modelValue===option.value" :class="[`status-${option.tone}`,{active:modelValue===option.value}]" @click="select(option.value)">
                    <component :is="option.icon" aria-hidden="true" />
                    <span>{{ option.label }}</span>
                    <Check v-if="modelValue===option.value" class="option-check" aria-hidden="true" />
                </button>
            </div>
        </template>
    </APopover>
</template>

<style scoped>
.status-trigger{display:grid;width:100%;min-width:174px;min-height:40px;cursor:pointer;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:8px;border:1px solid currentColor;border-radius:9px;background:#fff;padding:0 11px;font-size:11px;font-weight:700;text-align:left;transition:background-color 140ms ease,box-shadow 140ms ease}.status-trigger:hover:not(:disabled),.status-trigger[aria-expanded=true]{box-shadow:0 4px 12px rgba(15,23,42,.08)}.status-trigger:disabled{cursor:not-allowed;opacity:.58}.status-trigger svg{width:15px;height:15px;stroke-width:2}.status-chevron{transition:transform 140ms ease}.status-chevron.rotated{transform:rotate(180deg)}.status-present{color:#169c4b}.status-late{color:#d97706}.status-excused{color:#2563eb}.status-absent{color:#dc2626}.status-early{color:#7c3aed}.status-unknown{color:#64748b}@media(max-width:767px){.status-trigger{min-width:0;min-height:44px}}@media(prefers-reduced-motion:reduce){.status-trigger,.status-chevron{transition:none}}
.status-trigger{font-size:13px}
</style>

<style>
.attendance-status-popover .ant-popover-inner{border-radius:11px;padding:5px;box-shadow:0 14px 36px rgba(15,23,42,.16)}.status-menu{display:grid;width:190px;gap:2px}.status-menu button{display:grid;min-height:38px;cursor:pointer;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:8px;border:0;border-radius:8px;background:transparent;padding:0 9px;font-size:11px;font-weight:650;text-align:left}.status-menu button:hover,.status-menu button.active{background:#f8fafc}.status-menu svg{width:15px;height:15px}.status-menu .option-check{width:13px;height:13px}
.status-menu{width:210px}.status-menu button{min-height:40px;font-size:13px}
</style>

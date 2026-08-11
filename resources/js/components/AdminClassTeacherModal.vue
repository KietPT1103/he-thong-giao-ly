<script setup lang="ts">
import { computed, ref, watch } from "vue";
import ACheckbox from "ant-design-vue/es/checkbox";
import AEmpty from "ant-design-vue/es/empty";
import AInputSearch from "ant-design-vue/es/input/Search";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import type { AdminClass, ClassPersonOption } from "../api/admin";

const props = withDefaults(defineProps<{open:boolean;model:AdminClass|null;teachers:ClassPersonOption[];saving?:boolean}>(), {saving:false});
const emit = defineEmits<{close:[];search:[query:string];submit:[rows:Array<{teacher_id:number;role:"primary"|"assistant"}>]} >();
const search = ref("");
const selected = ref<Record<number,"primary"|"assistant">>({});
const filtered = computed(() => {
    const merged = new Map(props.teachers.map(item => [item.id,item]));
    (props.model?.teachers ?? []).forEach(item => merged.set(item.id, {
        id:item.id, name:item.name, email:item.email, code:item.code,
    }));
    const term = search.value.trim().toLocaleLowerCase("vi");
    const items = [...merged.values()];
    return term ? items.filter(item => `${item.name} ${item.email} ${item.code}`.toLocaleLowerCase("vi").includes(term)) : items;
});
watch(() => props.open, (open) => {
    if (!open) return;
    search.value = "";
    selected.value = Object.fromEntries((props.model?.teachers ?? []).map(item => [item.id,item.role]));
});
function toggle(id:number, checked:boolean) {
    if (props.saving) return;
    const next = {...selected.value};
    if (checked) next[id] = "assistant"; else delete next[id];
    selected.value = next;
}
function submit() {
    if (props.saving) return;
    emit("submit", Object.entries(selected.value).map(([teacher_id,role]) => ({teacher_id:Number(teacher_id),role})));
}
</script>

<template>
    <AModal :open="open" title="Phân công giáo lý viên" width="700px" centered :mask-closable="false" :closable="!saving" :keyboard="!saving" :confirm-loading="saving" :cancel-button-props="{ disabled: saving }" ok-text="Lưu phân công" cancel-text="Hủy" @cancel="emit('close')" @ok="submit">
        <p class="picker-context">{{ model?.name }} · {{ Object.keys(selected).length }} người được chọn</p>
        <AInputSearch v-model:value="search" allow-clear size="large" :disabled="saving" placeholder="Tìm theo tên, email hoặc mã" @search="emit('search', search.trim())" />
        <div v-if="filtered.length" class="person-picker" role="list">
            <div v-for="teacher in filtered" :key="teacher.id" class="person-row">
                <ACheckbox :checked="Boolean(selected[teacher.id])" :disabled="saving" :aria-label="`Chọn ${teacher.name}`" @change="toggle(teacher.id, $event.target.checked)" />
                <span class="person-avatar">{{ teacher.name?.slice(0,1).toUpperCase() }}</span>
                <span class="person-copy"><b>{{ teacher.name }}</b><small>{{ teacher.email }} · {{ teacher.code }}</small></span>
                <ASelect v-if="selected[teacher.id]" v-model:value="selected[teacher.id]" class="role-select" :disabled="saving" :options="[{value:'primary',label:'Phụ trách chính'},{value:'assistant',label:'Phụ tá'}]" />
                <span v-else class="person-unselected">Chưa chọn</span>
            </div>
        </div>
        <AEmpty v-else description="Không có giáo lý viên phù hợp." class="py-8" />
    </AModal>
</template>

<style scoped>
.picker-context{margin:0 0 .75rem;color:#64748b;font-size:.75rem}.person-picker{max-height:min(52vh,28rem);margin-top:.75rem;overflow-y:auto;border-block:1px solid #e2e8f0}.person-row{display:grid;grid-template-columns:auto auto minmax(0,1fr) 9.5rem;align-items:center;gap:.75rem;min-height:4.25rem;padding:.65rem .25rem;border-bottom:1px solid #e2e8f0}.person-row:last-child{border-bottom:0}.person-avatar{display:grid;width:2.25rem;height:2.25rem;place-items:center;border-radius:.5rem;background:#edf4ff;color:#185fce;font-size:.75rem;font-weight:700}.person-copy{min-width:0}.person-copy b,.person-copy small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.person-copy b{color:#1e293b;font-size:.78rem}.person-copy small,.person-unselected{margin-top:.15rem;color:#64748b;font-size:.68rem}.role-select{width:100%}@media(max-width:639px){.person-row{grid-template-columns:auto auto minmax(0,1fr)}.role-select,.person-unselected{grid-column:2/4;width:100%}}
</style>

<script setup lang="ts">
import { computed, ref, watch } from "vue";
import AEmpty from "ant-design-vue/es/empty";
import AInputSearch from "ant-design-vue/es/input/Search";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import ATag from "ant-design-vue/es/tag";
import type { AdminClass, ClassPersonOption } from "../api/admin";

const props = withDefaults(defineProps<{open:boolean;model:AdminClass|null;children:ClassPersonOption[];saving?:boolean}>(), {saving:false});
const emit = defineEmits<{close:[];search:[query:string];submit:[rows:Array<{child_id:number;status:"active"|"inactive"}>]} >();
const search = ref("");
const statuses = ref<Record<number,"active"|"inactive">>({});
const people = computed(() => {
    const current = (props.model?.enrollments ?? []).map(item => ({id:item.child.id,full_name:item.child.full_name,code:item.child.code,status:item.status}));
    const merged = new Map<number,ClassPersonOption>(current.map(item => [item.id,item]));
    props.children.forEach(item => merged.set(item.id,{...merged.get(item.id),...item}));
    const term = search.value.trim().toLocaleLowerCase("vi");
    return [...merged.values()].filter(item => !term || `${item.full_name} ${item.code}`.toLocaleLowerCase("vi").includes(term));
});
const activeCount = computed(() => Object.values(statuses.value).filter(value => value === "active").length);
const capacity = computed(() => props.model?.classroom?.capacity ?? null);
watch(() => props.open, (open) => {
    if (!open) return;
    search.value = "";
    statuses.value = Object.fromEntries((props.model?.enrollments ?? []).map(item => [item.child.id,item.status]));
});
function add(id:number) { statuses.value = {...statuses.value,[id]:"active"}; }
</script>

<template>
    <AModal :open="open" title="Ghi danh thiếu nhi" width="720px" centered :mask-closable="false" :closable="!saving" :confirm-loading="saving" ok-text="Lưu danh sách" cancel-text="Hủy" @cancel="emit('close')" @ok="emit('submit', Object.entries(statuses).map(([child_id,status]) => ({child_id:Number(child_id),status})))">
        <div class="enrollment-summary"><span>{{ model?.name }}</span><strong>{{ activeCount }}<template v-if="capacity !== null"> / {{ capacity }}</template> đang học</strong></div>
        <AInputSearch v-model:value="search" allow-clear size="large" placeholder="Tìm theo tên hoặc mã thiếu nhi" @search="emit('search', search.trim())" />
        <div v-if="people.length" class="enrollment-list">
            <div v-for="child in people" :key="child.id" class="enrollment-row">
                <span class="child-mark">{{ child.full_name?.slice(0,1).toUpperCase() }}</span>
                <span class="child-copy"><b>{{ child.full_name }}</b><small>{{ child.code }}</small></span>
                <template v-if="statuses[child.id]">
                    <ATag :color="statuses[child.id] === 'active' ? 'success' : 'default'">{{ statuses[child.id] === 'active' ? 'Đang học' : 'Đã rút' }}</ATag>
                    <ASelect v-model:value="statuses[child.id]" class="status-select" :options="[{value:'active',label:'Đang học'},{value:'inactive',label:'Rút khỏi lớp'}]" />
                </template>
                <button v-else type="button" class="enroll-button" @click="add(child.id)">Ghi danh</button>
            </div>
        </div>
        <AEmpty v-else description="Không có thiếu nhi phù hợp." class="py-8" />
    </AModal>
</template>

<style scoped>
.enrollment-summary{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.75rem;color:#64748b;font-size:.75rem}.enrollment-summary strong{color:#0b214d;font-variant-numeric:tabular-nums}.enrollment-list{max-height:min(52vh,29rem);margin-top:.75rem;overflow-y:auto;border-block:1px solid #e2e8f0}.enrollment-row{display:grid;grid-template-columns:auto minmax(0,1fr) auto 8rem;align-items:center;gap:.75rem;min-height:4rem;padding:.6rem .25rem;border-bottom:1px solid #e2e8f0}.enrollment-row:last-child{border-bottom:0}.child-mark{display:grid;width:2.25rem;height:2.25rem;place-items:center;border-radius:.5rem;background:#f1f5f9;color:#475569;font-size:.75rem;font-weight:700}.child-copy{min-width:0}.child-copy b,.child-copy small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.child-copy b{color:#1e293b;font-size:.78rem}.child-copy small{color:#64748b;font-size:.68rem}.status-select{width:100%}.enroll-button{min-height:2.25rem;border:1px solid #bfdbfe;border-radius:.5rem;background:#eff6ff;color:#1d4ed8;font-size:.75rem;font-weight:650;transition:background-color .15s ease,color .15s ease,transform .15s ease}.enroll-button:hover{background:#dbeafe}.enroll-button:active{transform:scale(.96)}@media(max-width:639px){.enrollment-row{grid-template-columns:auto minmax(0,1fr) auto}.status-select,.enroll-button{grid-column:2/4;width:100%}.enrollment-row>.ant-tag{margin:0}}
</style>

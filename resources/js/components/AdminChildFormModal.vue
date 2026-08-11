<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import AForm, { type FormInstance } from "ant-design-vue/es/form";
import AFormItem from "ant-design-vue/es/form/FormItem";
import type { RuleObject } from "ant-design-vue/es/form/interface";
import AInput from "ant-design-vue/es/input";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import type { AdminChild, ChildCreateInput, ChildInput, ChildOptions, ChildUpdateInput } from "../api/admin";

export type ChildFormErrors = Partial<Record<keyof ChildInput, string>>;
const props = withDefaults(defineProps<{
    open:boolean;child?:AdminChild|null;options?:ChildOptions|null;saving?:boolean;errors?:ChildFormErrors;canLink?:boolean;canEnroll?:boolean;
}>(), { child:null, options:null, saving:false, errors:() => ({}), canLink:true, canEnroll:true });
const emit = defineEmits<{ close:[dirty:boolean];submit:[payload:ChildCreateInput|ChildUpdateInput] }>();

const formRef = ref<FormInstance>();
const form = reactive({
    full_name:"", code:"", saint_name:"", date_of_birth:"", parish_id:undefined as number|undefined,
    status:"studying" as ChildInput["status"], parent_ids:[] as number[], class_id:undefined as number|undefined,
});
const initialSnapshot = ref("");
const editing = computed(() => Boolean(props.child));
const dirty = computed(() => JSON.stringify(form) !== initialSnapshot.value);
const classOptions = computed(() => (props.options?.classes ?? [])
    .filter(item => !form.parish_id || item.parish_id === form.parish_id)
    .map(item => ({value:item.id,label:`${item.name} · ${item.code} · ${item.academic_year}`})));
const parentOptions = computed(() => (props.options?.parents ?? [])
    .filter(item => !form.parish_id || item.parish_id === form.parish_id)
    .map(item => ({value:item.id,label:`${item.name} · ${item.email}`})));
const rules:Record<string,RuleObject[]> = {
    full_name:[{required:true,message:"Hãy nhập họ và tên."}],
    code:[{required:true,message:"Hãy nhập mã thiếu nhi."}],
    parish_id:[{required:true,message:"Hãy chọn giáo xứ."}],
    status:[{required:true,message:"Hãy chọn trạng thái."}],
};

watch(() => props.open, (open) => {
    if (!open) return;
    Object.assign(form, {
        full_name:props.child?.full_name ?? "", code:props.child?.code ?? "", saint_name:props.child?.saint_name ?? "",
        date_of_birth:props.child?.date_of_birth ?? "", parish_id:props.child?.parish_id,
        status:props.child?.status ?? "studying", parent_ids:props.child?.parents?.map(parent => parent.id) ?? [],
        class_id:props.child?.current_class?.id,
    });
    initialSnapshot.value = JSON.stringify(form);
    formRef.value?.clearValidate();
});

watch(() => form.parish_id, () => {
    if (form.class_id && !classOptions.value.some(item => item.value === form.class_id)) form.class_id = undefined;
    const allowedParents = new Set(parentOptions.value.map(item => item.value));
    form.parent_ids = form.parent_ids.filter(id => allowedParents.has(id));
});

async function submit() {
    if (props.saving) return;
    try {
        await formRef.value?.validate();
        const shared = {
            full_name:form.full_name.trim(), code:form.code.trim(), saint_name:form.saint_name.trim() || null,
            date_of_birth:form.date_of_birth || null, parish_id:form.parish_id as number, status:form.status,
        };
        emit("submit", editing.value
            ? {...shared,...(props.canLink ? {parent_ids:[...form.parent_ids]} : {}),...(props.canEnroll ? {class_id:form.class_id ?? null} : {})}
            : {...shared,parent_ids:props.canLink ? [...form.parent_ids] : [],class_id:props.canEnroll ? form.class_id ?? null : null});
    } catch { /* Ant Form displays field errors. */ }
}
</script>

<template>
    <AModal :open="open" :title="editing ? 'Chỉnh sửa thiếu nhi' : 'Tạo hồ sơ thiếu nhi'" :confirm-loading="saving" :closable="!saving" :keyboard="!saving" :mask-closable="false" :cancel-button-props="{ disabled: saving }" :ok-text="editing ? 'Lưu thay đổi' : 'Tạo thiếu nhi'" cancel-text="Hủy" width="760px" centered @cancel="emit('close', dirty)" @ok="submit">
        <AForm ref="formRef" :model="form" :rules="rules" :disabled="saving" layout="vertical" class="family-form">
            <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                <AFormItem class="sm:col-span-2" label="Họ và tên" name="full_name" required :help="errors.full_name" :validate-status="errors.full_name ? 'error' : undefined"><AInput v-model:value="form.full_name" size="large" autocomplete="name" placeholder="Ví dụ: Nguyễn Minh An" /></AFormItem>
                <AFormItem label="Mã thiếu nhi" name="code" required :help="errors.code" :validate-status="errors.code ? 'error' : undefined"><AInput v-model:value="form.code" size="large" autocomplete="off" placeholder="TN-001" /></AFormItem>
                <AFormItem label="Tên thánh" name="saint_name" :help="errors.saint_name" :validate-status="errors.saint_name ? 'error' : undefined"><AInput v-model:value="form.saint_name" size="large" placeholder="Ví dụ: Maria" /></AFormItem>
                <AFormItem label="Ngày sinh" name="date_of_birth" :help="errors.date_of_birth" :validate-status="errors.date_of_birth ? 'error' : undefined"><AInput v-model:value="form.date_of_birth" size="large" type="date" /></AFormItem>
                <AFormItem label="Trạng thái" name="status" required :help="errors.status" :validate-status="errors.status ? 'error' : undefined"><ASelect v-model:value="form.status" size="large" :options="[{value:'studying',label:'Đang học'},{value:'paused',label:'Tạm nghỉ'},{value:'graduated',label:'Đã hoàn thành'}]" /></AFormItem>
                <AFormItem label="Giáo xứ" name="parish_id" required :help="errors.parish_id" :validate-status="errors.parish_id ? 'error' : undefined"><ASelect v-model:value="form.parish_id" size="large" show-search option-filter-prop="label" placeholder="Chọn giáo xứ" :options="options?.parishes.map(item => ({value:item.id,label:item.name})) ?? []" /></AFormItem>
                <AFormItem v-if="canEnroll" label="Lớp hiện tại" name="class_id" :help="errors.class_id" :validate-status="errors.class_id ? 'error' : undefined"><ASelect v-model:value="form.class_id" allow-clear size="large" show-search option-filter-prop="label" placeholder="Chưa xếp lớp" :options="classOptions" /></AFormItem>
                <AFormItem v-if="canLink" class="sm:col-span-2" label="Phụ huynh liên kết" name="parent_ids" :help="errors.parent_ids" :validate-status="errors.parent_ids ? 'error' : undefined"><ASelect v-model:value="form.parent_ids" mode="multiple" size="large" show-search option-filter-prop="label" placeholder="Chọn một hoặc nhiều phụ huynh" :max-tag-count="3" :options="parentOptions" /></AFormItem>
            </div>
        </AForm>
    </AModal>
</template>

<style scoped>
.family-form{margin-top:1.25rem}.family-form :deep(.ant-form-item){margin-bottom:1rem}.family-form :deep(.ant-form-item-label>label){color:#334155;font-size:.75rem;font-weight:650}.family-form :deep(.ant-input),.family-form :deep(.ant-select-selector){border-radius:.625rem!important;box-shadow:none!important}
</style>

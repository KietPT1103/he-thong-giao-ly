<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import AForm, { type FormInstance } from "ant-design-vue/es/form";
import AFormItem from "ant-design-vue/es/form/FormItem";
import AInput from "ant-design-vue/es/input";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import type { AdminClass, ClassInput, ClassOptions } from "../api/admin";

const props = withDefaults(defineProps<{open:boolean;model?:AdminClass|null;options:ClassOptions;saving?:boolean;errors?:Record<string,string>}>(), {
    model:null, saving:false, errors:() => ({}),
});
const emit = defineEmits<{close:[dirty:boolean];submit:[ClassInput];parishChange:[id:number]} >();
const formRef = ref<FormInstance>();
const form = reactive<ClassInput & {parish_id?:number}>({name:"",code:"",parish_id:undefined,academic_year_id:0,catechism_level_id:0,classroom_id:null,status:"active"});
const initial = ref("");
const editing = computed(() => Boolean(props.model));
const dirty = computed(() => JSON.stringify(form) !== initial.value);
const option = (items:Array<{id:number;name:string;code?:string}>) => items.map(item => ({value:item.id,label:item.code ? `${item.name} (${item.code})` : item.name}));

watch(() => props.open, (open) => {
    if (!open) return;
    Object.assign(form, {
        name:props.model?.name ?? "",
        code:props.model?.code ?? "",
        parish_id:props.model?.parish?.id,
        academic_year_id:props.model?.academic_year_id ?? 0,
        catechism_level_id:props.model?.catechism_level_id ?? 0,
        classroom_id:props.model?.classroom_id ?? null,
        status:props.model?.status ?? "active",
    });
    initial.value = JSON.stringify(form);
    formRef.value?.clearValidate();
});

function changeParish(value:unknown) {
    const id = Number(value);
    form.academic_year_id = 0;
    form.catechism_level_id = 0;
    form.classroom_id = null;
    emit("parishChange", id);
}

function changeRoom(value:unknown) {
    form.classroom_id = value === undefined ? null : Number(value);
}

async function submit() {
    try {
        await formRef.value?.validate();
        emit("submit", {
            name:form.name.trim(), code:form.code.trim(), academic_year_id:form.academic_year_id,
            catechism_level_id:form.catechism_level_id, classroom_id:form.classroom_id, status:form.status,
        });
    } catch { /* Ant Form renders errors. */ }
}
</script>

<template>
    <AModal :open="open" :title="editing ? 'Chỉnh sửa lớp học' : 'Tạo lớp học'" width="680px" centered :mask-closable="false" :closable="!saving" :confirm-loading="saving" :ok-text="editing ? 'Lưu thay đổi' : 'Tạo lớp học'" cancel-text="Hủy" @cancel="emit('close', dirty)" @ok="submit">
        <AForm ref="formRef" :model="form" layout="vertical" class="class-form">
            <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                <AFormItem class="sm:col-span-2" label="Tên lớp" name="name" :rules="[{required:true,message:'Hãy nhập tên lớp.'},{max:255,message:'Tên lớp tối đa 255 ký tự.'}]" :help="errors.name" :validate-status="errors.name ? 'error' : undefined"><AInput v-model:value="form.name" size="large" placeholder="Ví dụ: Thiếu Nhi 1A" /></AFormItem>
                <AFormItem label="Mã lớp" name="code" :rules="[{required:true,message:'Hãy nhập mã lớp.'},{max:50,message:'Mã lớp tối đa 50 ký tự.'}]" :help="errors.code" :validate-status="errors.code ? 'error' : undefined"><AInput v-model:value="form.code" size="large" placeholder="TN-1A" /></AFormItem>
                <AFormItem label="Trạng thái" name="status" required><ASelect v-model:value="form.status" size="large" :options="[{value:'active',label:'Đang hoạt động'},{value:'inactive',label:'Tạm ngưng'}]" /></AFormItem>
                <AFormItem label="Giáo xứ" name="parish_id" :rules="[{required:true,message:'Hãy chọn giáo xứ.'}]"><ASelect v-model:value="form.parish_id" show-search option-filter-prop="label" size="large" placeholder="Chọn giáo xứ" :disabled="editing" :options="option(options.parishes)" @change="changeParish" /></AFormItem>
                <AFormItem label="Niên khóa" name="academic_year_id" :rules="[{required:true,type:'number',min:1,message:'Hãy chọn niên khóa.'}]" :help="errors.academic_year_id" :validate-status="errors.academic_year_id ? 'error' : undefined"><ASelect v-model:value="form.academic_year_id" size="large" placeholder="Chọn niên khóa" :options="option(options.academic_years)" /></AFormItem>
                <AFormItem label="Khối giáo lý" name="catechism_level_id" :rules="[{required:true,type:'number',min:1,message:'Hãy chọn khối.'}]" :help="errors.catechism_level_id" :validate-status="errors.catechism_level_id ? 'error' : undefined"><ASelect v-model:value="form.catechism_level_id" size="large" placeholder="Chọn khối" :options="option(options.levels)" /></AFormItem>
                <AFormItem label="Phòng học" name="classroom_id" :help="errors.classroom_id" :validate-status="errors.classroom_id ? 'error' : undefined"><ASelect :value="form.classroom_id ?? undefined" allow-clear size="large" placeholder="Chưa xếp phòng" :options="option(options.classrooms)" @change="changeRoom" /></AFormItem>
            </div>
        </AForm>
    </AModal>
</template>

<style scoped>
.class-form{margin-top:1.25rem}.class-form :deep(.ant-form-item){margin-bottom:1rem}.class-form :deep(.ant-form-item-label>label){color:#334155;font-size:.75rem;font-weight:650}.class-form :deep(.ant-input),.class-form :deep(.ant-select-selector){border-radius:.625rem!important;box-shadow:none!important}
</style>

<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import AForm from "ant-design-vue/es/form";
import type { FormInstance } from "ant-design-vue/es/form";
import AFormItem from "ant-design-vue/es/form/FormItem";
import type { RuleObject } from "ant-design-vue/es/form/interface";
import AInput from "ant-design-vue/es/input";
import AModal from "ant-design-vue/es/modal";
import type { Parish, ParishInput } from "../api/admin";
import { vietnamesePhoneRule } from "../utils/phoneValidation";

const props = withDefaults(defineProps<{
    open:boolean;
    parish?:Parish|null;
    saving?:boolean;
    errors?:Partial<Record<keyof ParishInput, string>>;
}>(), { parish:null, saving:false, errors:() => ({}) });

const emit = defineEmits<{
    close:[dirty:boolean];
    submit:[payload:ParishInput];
}>();

interface ParishFormState { name:string;code:string;phone:string;email:string }
const formRef = ref<FormInstance>();
const form = reactive<ParishFormState>({ name:"", code:"", phone:"", email:"" });
const initialSnapshot = ref("");
const editing = computed(() => Boolean(props.parish));
const dirty = computed(() => JSON.stringify(form) !== initialSnapshot.value);
const rules:Record<string, RuleObject[]> = {
    name:[{ required:true, message:"Hãy nhập tên giáo xứ." }, { max:255, message:"Tên không được vượt quá 255 ký tự." }],
    code:[{ required:true, message:"Hãy nhập mã giáo xứ." }, { max:50, message:"Mã không được vượt quá 50 ký tự." }],
    phone:[vietnamesePhoneRule(), { max:30, message:"Số điện thoại không được vượt quá 30 ký tự." }],
    email:[{ type:"email", message:"Email không hợp lệ." }, { max:255, message:"Email không được vượt quá 255 ký tự." }],
};

watch(() => props.open, (open) => {
    if (!open) return;
    Object.assign(form, {
        name:props.parish?.name ?? "",
        code:props.parish?.code ?? "",
        phone:props.parish?.phone ?? "",
        email:props.parish?.email ?? "",
    });
    initialSnapshot.value = JSON.stringify(form);
    formRef.value?.clearValidate();
});

async function submit() {
    try {
        await formRef.value?.validate();
        emit("submit", {
            name:form.name.trim(),
            code:form.code.trim(),
            phone:form.phone?.trim() || null,
            email:form.email?.trim() || null,
        });
    } catch {
        // Ant Form renders field-level validation errors.
    }
}
</script>

<template>
    <AModal
        :open="open"
        :title="editing ? 'Chỉnh sửa giáo xứ' : 'Tạo giáo xứ'"
        :confirm-loading="saving"
        :closable="!saving"
        :mask-closable="false"
        :keyboard="!saving"
        :ok-text="editing ? 'Lưu thay đổi' : 'Tạo giáo xứ'"
        cancel-text="Hủy"
        width="620px"
        centered
        @cancel="emit('close', dirty)"
        @ok="submit"
    >
        <AForm ref="formRef" :model="form" :rules="rules" layout="vertical" class="parish-form">
            <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                <AFormItem class="sm:col-span-2" label="Tên giáo xứ" name="name" required :help="errors.name" :validate-status="errors.name ? 'error' : undefined">
                    <AInput v-model:value="form.name" size="large" autocomplete="organization" placeholder="Ví dụ: Giáo xứ An Bình" />
                </AFormItem>
                <AFormItem label="Mã giáo xứ" name="code" required :help="errors.code" :validate-status="errors.code ? 'error' : undefined">
                    <AInput v-model:value="form.code" size="large" autocomplete="off" placeholder="AN-BINH" />
                </AFormItem>
                <AFormItem label="Số điện thoại" name="phone" :help="errors.phone" :validate-status="errors.phone ? 'error' : undefined">
                    <AInput v-model:value="form.phone" size="large" inputmode="tel" autocomplete="tel" placeholder="0292 388 8888" />
                </AFormItem>
                <AFormItem class="sm:col-span-2" label="Email" name="email" :help="errors.email" :validate-status="errors.email ? 'error' : undefined">
                    <AInput v-model:value="form.email" size="large" type="email" autocomplete="email" placeholder="giaoxu@example.org" />
                </AFormItem>
            </div>
        </AForm>
    </AModal>
</template>

<style scoped>
.parish-form{margin-top:1.25rem}.parish-form :deep(.ant-form-item){margin-bottom:1rem}.parish-form :deep(.ant-form-item-label>label){color:#334155;font-size:.75rem;font-weight:650}.parish-form :deep(.ant-input){border-radius:.625rem;box-shadow:none}
</style>

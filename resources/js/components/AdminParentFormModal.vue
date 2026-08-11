<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import AForm, { type FormInstance } from "ant-design-vue/es/form";
import AFormItem from "ant-design-vue/es/form/FormItem";
import type { RuleObject } from "ant-design-vue/es/form/interface";
import AInput from "ant-design-vue/es/input";
import AInputPassword from "ant-design-vue/es/input/Password";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import type { AdminParent, ParentCreateInput, ParentOptions, ParentUpdateInput } from "../api/admin";
import { vietnamesePhoneRule } from "../utils/phoneValidation";

export type ParentFormErrors = Partial<Record<keyof ParentCreateInput | keyof ParentUpdateInput, string>>;
const props = withDefaults(defineProps<{
    open:boolean;parent?:AdminParent|null;options?:ParentOptions|null;saving?:boolean;errors?:ParentFormErrors;canLink?:boolean;
}>(), { parent:null, options:null, saving:false, errors:() => ({}), canLink:true });
const emit = defineEmits<{ close:[dirty:boolean];submit:[payload:ParentCreateInput|ParentUpdateInput] }>();

const formRef = ref<FormInstance>();
const form = reactive({
    name:"", email:"", phone:"", parish_id:undefined as number|undefined,
    password:"", password_confirmation:"", child_ids:[] as number[],
});
const initialSnapshot = ref("");
const editing = computed(() => Boolean(props.parent));
const dirty = computed(() => JSON.stringify(form) !== initialSnapshot.value);
const childOptions = computed(() => (props.options?.children ?? [])
    .filter(item => !form.parish_id || item.parish_id === form.parish_id)
    .map(item => ({value:item.id,label:`${item.full_name} · ${item.code}`})));
const rules = computed<Record<string,RuleObject[]>>(() => ({
    name:[{required:true,message:"Hãy nhập họ và tên."}],
    email:[{required:true,message:"Hãy nhập email."},{type:"email",message:"Email không hợp lệ."}],
    phone:[vietnamesePhoneRule()],
    parish_id:[{required:true,message:"Hãy chọn giáo xứ."}],
    password:editing.value ? [] : [{required:true,message:"Hãy nhập mật khẩu ban đầu."},{min:8,message:"Mật khẩu phải có ít nhất 8 ký tự."}],
    password_confirmation:editing.value ? [] : [
        {required:true,message:"Hãy xác nhận mật khẩu."},
        {validator:async (_rule,value) => { if (value !== form.password) throw new Error("Mật khẩu xác nhận không khớp."); }},
    ],
}));

watch(() => props.open, (open) => {
    if (!open) return;
    Object.assign(form, {
        name:props.parent?.name ?? "", email:props.parent?.email ?? "", phone:props.parent?.phone ?? "",
        parish_id:props.parent?.parish_id,
        password:"", password_confirmation:"", child_ids:props.parent?.children?.map(child => child.id) ?? [],
    });
    initialSnapshot.value = JSON.stringify(form);
    formRef.value?.clearValidate();
});

watch(() => form.parish_id, () => {
    const allowed = new Set(childOptions.value.map(item => item.value));
    form.child_ids = form.child_ids.filter(id => allowed.has(id));
});

async function submit() {
    if (props.saving) return;
    try {
        await formRef.value?.validate();
        const shared = {
            name:form.name.trim(), email:form.email.trim(), phone:form.phone.trim() || null,
            parish_id:form.parish_id as number,
        };
        emit("submit", editing.value
            ? {...shared,...(props.canLink ? {child_ids:[...form.child_ids]} : {})}
            : {...shared,password:form.password,password_confirmation:form.password_confirmation,child_ids:props.canLink ? [...form.child_ids] : []});
    } catch { /* Ant Form displays field errors. */ }
}
</script>

<template>
    <AModal :open="open" :title="editing ? 'Chỉnh sửa phụ huynh' : 'Tạo phụ huynh'" :confirm-loading="saving" :closable="!saving" :keyboard="!saving" :mask-closable="false" :cancel-button-props="{ disabled: saving }" :ok-text="editing ? 'Lưu thay đổi' : 'Tạo phụ huynh'" cancel-text="Hủy" width="720px" centered @cancel="emit('close', dirty)" @ok="submit">
        <AForm ref="formRef" :model="form" :rules="rules" :disabled="saving" layout="vertical" class="family-form">
            <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                <AFormItem class="sm:col-span-2" label="Họ và tên" name="name" required :help="errors.name" :validate-status="errors.name ? 'error' : undefined"><AInput v-model:value="form.name" size="large" autocomplete="name" placeholder="Ví dụ: Nguyễn Văn An" /></AFormItem>
                <AFormItem label="Email" name="email" required :help="errors.email" :validate-status="errors.email ? 'error' : undefined"><AInput v-model:value="form.email" size="large" type="email" autocomplete="email" placeholder="phuhuynh@example.org" /></AFormItem>
                <AFormItem label="Số điện thoại" name="phone" :help="errors.phone" :validate-status="errors.phone ? 'error' : undefined"><AInput v-model:value="form.phone" size="large" inputmode="tel" autocomplete="tel" placeholder="0901 234 567" /></AFormItem>
                <AFormItem label="Giáo xứ" name="parish_id" required :help="errors.parish_id" :validate-status="errors.parish_id ? 'error' : undefined"><ASelect v-model:value="form.parish_id" size="large" show-search option-filter-prop="label" placeholder="Chọn giáo xứ" :options="options?.parishes.map(item => ({value:item.id,label:item.name})) ?? []" /></AFormItem>
                <template v-if="!editing">
                    <AFormItem label="Mật khẩu ban đầu" name="password" required :help="errors.password" :validate-status="errors.password ? 'error' : undefined"><AInputPassword v-model:value="form.password" size="large" autocomplete="new-password" placeholder="Tối thiểu 8 ký tự" /></AFormItem>
                    <AFormItem label="Xác nhận mật khẩu" name="password_confirmation" required :help="errors.password_confirmation" :validate-status="errors.password_confirmation ? 'error' : undefined"><AInputPassword v-model:value="form.password_confirmation" size="large" autocomplete="new-password" placeholder="Nhập lại mật khẩu" /></AFormItem>
                </template>
                <AFormItem v-if="canLink" class="sm:col-span-2" label="Thiếu nhi liên kết" name="child_ids" :help="errors.child_ids" :validate-status="errors.child_ids ? 'error' : undefined">
                    <ASelect v-model:value="form.child_ids" mode="multiple" size="large" show-search option-filter-prop="label" placeholder="Chọn một hoặc nhiều thiếu nhi" :max-tag-count="3" :options="childOptions" />
                </AFormItem>
            </div>
        </AForm>
    </AModal>
</template>

<style scoped>
.family-form{margin-top:1.25rem}.family-form :deep(.ant-form-item){margin-bottom:1rem}.family-form :deep(.ant-form-item-label>label){color:#334155;font-size:.75rem;font-weight:650}.family-form :deep(.ant-input),.family-form :deep(.ant-input-affix-wrapper),.family-form :deep(.ant-select-selector){border-radius:.625rem!important;box-shadow:none!important}
</style>

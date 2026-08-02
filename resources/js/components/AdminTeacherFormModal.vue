<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AForm from "ant-design-vue/es/form";
import type { FormInstance } from "ant-design-vue/es/form";
import AFormItem from "ant-design-vue/es/form/FormItem";
import type { RuleObject } from "ant-design-vue/es/form/interface";
import AInput from "ant-design-vue/es/input";
import AInputPassword from "ant-design-vue/es/input/Password";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import type { Parish, Teacher, TeacherCreateInput, TeacherUpdateInput } from "../api/admin";
import { vietnamesePhoneRule } from "../utils/phoneValidation";

export type TeacherFormErrors = Partial<Record<keyof TeacherCreateInput | keyof TeacherUpdateInput, string>>;

const props = withDefaults(defineProps<{
    open:boolean;
    teacher?:Teacher|null;
    parishes?:Parish[];
    saving?:boolean;
    errors?:TeacherFormErrors;
}>(), { teacher:null, parishes:() => [], saving:false, errors:() => ({}) });

const emit = defineEmits<{
    close:[dirty:boolean];
    submit:[payload:TeacherCreateInput|TeacherUpdateInput];
}>();

interface TeacherFormState {
    name:string;
    email:string;
    phone:string;
    code:string;
    parish_id:number|undefined;
    status:"active"|"blocked";
    password:string;
    password_confirmation:string;
}

const formRef = ref<FormInstance>();
const form = reactive<TeacherFormState>({
    name:"", email:"", phone:"", code:"", parish_id:undefined,
    status:"active", password:"", password_confirmation:"",
});
const initialSnapshot = ref("");
const editing = computed(() => Boolean(props.teacher));
const dirty = computed(() => JSON.stringify(form) !== initialSnapshot.value);
const parishChangedWithClasses = computed(() =>
    editing.value
    && Boolean(props.teacher?.classes_count)
    && form.parish_id !== props.teacher?.parish_id,
);

const rules = computed<Record<string, RuleObject[]>>(() => ({
    name:[{ required:true, message:"Hãy nhập họ và tên." }, { max:255, message:"Họ và tên không được vượt quá 255 ký tự." }],
    email:[{ required:true, message:"Hãy nhập email." }, { type:"email", message:"Email không hợp lệ." }, { max:255, message:"Email không được vượt quá 255 ký tự." }],
    phone:[vietnamesePhoneRule(), { max:30, message:"Số điện thoại không được vượt quá 30 ký tự." }],
    code:[{ required:true, message:"Hãy nhập mã giáo lý viên." }, { max:50, message:"Mã không được vượt quá 50 ký tự." }],
    parish_id:[{ required:true, message:"Hãy chọn giáo xứ." }],
    password:editing.value ? [] : [{ required:true, message:"Hãy nhập mật khẩu ban đầu." }, { min:8, message:"Mật khẩu phải có ít nhất 8 ký tự." }],
    password_confirmation:editing.value ? [] : [
        { required:true, message:"Hãy xác nhận mật khẩu." },
        { validator:async (_rule, value) => { if (value !== form.password) throw new Error("Mật khẩu xác nhận không khớp."); } },
    ],
}));

watch(() => props.open, (open) => {
    if (!open) return;
    Object.assign(form, {
        name:props.teacher?.name ?? "",
        email:props.teacher?.email ?? "",
        phone:props.teacher?.phone ?? "",
        code:props.teacher?.code ?? "",
        parish_id:props.teacher?.parish_id,
        status:props.teacher?.account_status === "blocked" ? "blocked" : "active",
        password:"",
        password_confirmation:"",
    });
    initialSnapshot.value = JSON.stringify(form);
    formRef.value?.clearValidate();
});

async function submit() {
    try {
        await formRef.value?.validate();
        const shared = {
            name:form.name.trim(),
            email:form.email.trim(),
            phone:form.phone.trim() || null,
            code:form.code.trim(),
            parish_id:form.parish_id as number,
        };
        emit("submit", editing.value
            ? { ...shared, status:form.status }
            : { ...shared, password:form.password, password_confirmation:form.password_confirmation });
    } catch {
        // Ant Form renders field-level validation errors.
    }
}
</script>

<template>
    <AModal
        :open="open"
        :title="editing ? 'Chỉnh sửa giáo lý viên' : 'Tạo giáo lý viên'"
        :confirm-loading="saving"
        :closable="!saving"
        :mask-closable="false"
        :keyboard="!saving"
        :ok-text="editing ? 'Lưu thay đổi' : 'Tạo giáo lý viên'"
        cancel-text="Hủy"
        width="680px"
        centered
        @cancel="emit('close', dirty)"
        @ok="submit"
    >
        <AForm ref="formRef" :model="form" :rules="rules" layout="vertical" class="teacher-form">
            <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                <AFormItem class="sm:col-span-2" label="Họ và tên" name="name" required :help="errors.name" :validate-status="errors.name ? 'error' : undefined">
                    <AInput v-model:value="form.name" size="large" autocomplete="name" placeholder="Ví dụ: Nguyễn Văn An" />
                </AFormItem>
                <AFormItem label="Email" name="email" required :help="errors.email" :validate-status="errors.email ? 'error' : undefined">
                    <AInput v-model:value="form.email" size="large" type="email" autocomplete="email" placeholder="giaolyvien@example.org" />
                </AFormItem>
                <AFormItem label="Số điện thoại" name="phone" :help="errors.phone" :validate-status="errors.phone ? 'error' : undefined">
                    <AInput v-model:value="form.phone" size="large" inputmode="tel" autocomplete="tel" placeholder="0901 234 567" />
                </AFormItem>
                <AFormItem label="Mã giáo lý viên" name="code" required :help="errors.code" :validate-status="errors.code ? 'error' : undefined">
                    <AInput v-model:value="form.code" size="large" autocomplete="off" placeholder="GLV-001" />
                </AFormItem>
                <AFormItem label="Giáo xứ" name="parish_id" required :help="errors.parish_id" :validate-status="errors.parish_id ? 'error' : undefined">
                    <ASelect v-model:value="form.parish_id" size="large" show-search option-filter-prop="label" :disabled="parishes.length === 0" :placeholder="parishes.length ? 'Chọn giáo xứ' : 'Chưa tải được danh sách giáo xứ'" :options="parishes.map(parish => ({ value:parish.id, label:parish.name }))" />
                </AFormItem>

                <AAlert v-if="parishChangedWithClasses" class="mb-4 sm:col-span-2" type="warning" show-icon message="Giáo lý viên đang phụ trách lớp. Việc đổi giáo xứ không làm thay đổi các lớp đã phân công." />

                <template v-if="editing">
                    <AFormItem class="sm:col-span-2" label="Trạng thái tài khoản" name="status" :help="errors.status" :validate-status="errors.status ? 'error' : undefined">
                        <ASelect v-model:value="form.status" size="large" :options="[{value:'active',label:'Đang hoạt động'},{value:'blocked',label:'Đã khóa'}]" />
                    </AFormItem>
                </template>
                <template v-else>
                    <AFormItem label="Mật khẩu ban đầu" name="password" required :help="errors.password" :validate-status="errors.password ? 'error' : undefined">
                        <AInputPassword v-model:value="form.password" size="large" autocomplete="new-password" placeholder="Tối thiểu 8 ký tự" />
                    </AFormItem>
                    <AFormItem label="Xác nhận mật khẩu" name="password_confirmation" required :help="errors.password_confirmation" :validate-status="errors.password_confirmation ? 'error' : undefined">
                        <AInputPassword v-model:value="form.password_confirmation" size="large" autocomplete="new-password" placeholder="Nhập lại mật khẩu" />
                    </AFormItem>
                </template>
            </div>
        </AForm>
    </AModal>
</template>

<style scoped>
.teacher-form{margin-top:1.25rem}.teacher-form :deep(.ant-form-item){margin-bottom:1rem}.teacher-form :deep(.ant-form-item-label>label){color:#334155;font-size:.75rem;font-weight:650}.teacher-form :deep(.ant-input),.teacher-form :deep(.ant-input-affix-wrapper),.teacher-form :deep(.ant-select-selector){border-radius:.625rem!important;box-shadow:none!important}
</style>

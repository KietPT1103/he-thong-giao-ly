<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import AForm, { type FormInstance } from "ant-design-vue/es/form";
import AFormItem from "ant-design-vue/es/form/FormItem";
import AInput from "ant-design-vue/es/input";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import { BookOpen, Church, School } from "lucide-vue-next";
import type { TeacherClassInput, TeacherClassOptions } from "../api/teacher";
import type { CatechismClass } from "../types/api";

const props = withDefaults(defineProps<{
    open: boolean;
    model?: CatechismClass | null;
    options: TeacherClassOptions;
    saving?: boolean;
    errors?: Record<string, string>;
}>(), {
    model: null,
    saving: false,
    errors: () => ({}),
});
const emit = defineEmits<{
    close: [dirty: boolean];
    submit: [payload: TeacherClassInput];
}>();

const formRef = ref<FormInstance>();
const form = reactive<Omit<TeacherClassInput, "academic_year_id" | "catechism_level_id"> & {
    academic_year_id?: number;
    catechism_level_id?: number;
}>({
    name: "",
    code: "",
    academic_year_id: undefined,
    catechism_level_id: undefined,
    classroom_id: null,
    status: "active",
});
const initial = ref("");
const editing = computed(() => Boolean(props.model));
const dirty = computed(() => JSON.stringify(form) !== initial.value);
const parish = computed(() => props.options.parishes[0]);
const option = (items: Array<{ id: number; name: string; code?: string }>) =>
    items.map((item) => ({
        value: item.id,
        label: item.code ? `${item.name} (${item.code})` : item.name,
    }));

watch(() => props.open, (open) => {
    if (!open) return;
    const currentYear = props.options.academic_years.find((item) => item.is_current);
    Object.assign(form, {
        name: props.model?.name ?? "",
        code: props.model?.code ?? "",
        academic_year_id: props.model?.academic_year_id ?? currentYear?.id,
        catechism_level_id: props.model?.catechism_level_id,
        classroom_id: props.model?.classroom_id ?? null,
        status: props.model?.status === "inactive" ? "inactive" : "active",
    });
    initial.value = JSON.stringify(form);
    formRef.value?.clearValidate();
});

function changeRoom(value: unknown) {
    form.classroom_id = value === undefined ? null : Number(value);
}

async function submit() {
    if (props.saving) return;
    try {
        await formRef.value?.validate();
        emit("submit", {
            ...form,
            name: form.name.trim(),
            code: form.code.trim(),
            academic_year_id: form.academic_year_id as number,
            catechism_level_id: form.catechism_level_id as number,
        });
    } catch {
        // Ant Form renders validation details next to each field.
    }
}
</script>

<template>
    <AModal
        :open="open"
        :title="editing ? 'Chỉnh sửa lớp học' : 'Tạo lớp học'"
        width="min(680px, calc(100vw - 24px))"
        centered
        :mask-closable="false"
        :closable="!saving"
        :keyboard="!saving"
        :confirm-loading="saving"
        :cancel-button-props="{ disabled: saving }"
        :ok-text="editing ? 'Lưu thay đổi' : 'Tạo lớp học'"
        cancel-text="Hủy"
        @cancel="emit('close', dirty)"
        @ok="submit"
    >
        <div class="teacher-class-intro">
            <span><BookOpen aria-hidden="true" /></span>
            <div>
                <b>{{ editing ? "Cập nhật thông tin lớp" : "Khởi tạo lớp mới" }}</b>
                <p>Bạn sẽ là giáo lý viên phụ trách chính của lớp này.</p>
            </div>
        </div>

        <div class="teacher-parish-band">
            <Church aria-hidden="true" />
            <span><small>Giáo xứ</small><b>{{ parish?.name ?? "Chưa có giáo xứ" }}</b></span>
        </div>

        <AForm ref="formRef" :model="form" :disabled="saving" layout="vertical" class="teacher-class-form">
            <div class="teacher-class-form-grid">
                <div class="teacher-form-section"><BookOpen aria-hidden="true" /><b>Thông tin nhận diện</b></div>
                <AFormItem class="field-wide" label="Tên lớp" name="name" :rules="[{required:true,message:'Hãy nhập tên lớp.'},{max:255,message:'Tên lớp tối đa 255 ký tự.'}]" :help="errors.name" :validate-status="errors.name ? 'error' : undefined">
                    <AInput v-model:value="form.name" size="large" placeholder="Ví dụ: Thiếu Nhi 1A" />
                </AFormItem>
                <AFormItem label="Mã lớp" name="code" :rules="[{required:true,message:'Hãy nhập mã lớp.'},{max:50,message:'Mã lớp tối đa 50 ký tự.'}]" :help="errors.code" :validate-status="errors.code ? 'error' : undefined">
                    <AInput v-model:value="form.code" size="large" placeholder="TN-1A" />
                </AFormItem>
                <AFormItem label="Trạng thái" name="status" required>
                    <ASelect v-model:value="form.status" size="large" :options="[{value:'active',label:'Đang hoạt động'},{value:'inactive',label:'Tạm ngưng'}]" />
                </AFormItem>

                <div class="teacher-form-section"><School aria-hidden="true" /><b>Tổ chức giảng dạy</b></div>
                <AFormItem label="Niên khóa" name="academic_year_id" :rules="[{required:true,type:'number',min:1,message:'Hãy chọn niên khóa.'}]" :help="errors.academic_year_id" :validate-status="errors.academic_year_id ? 'error' : undefined">
                    <ASelect v-model:value="form.academic_year_id" size="large" placeholder="Chọn niên khóa" :options="option(options.academic_years)" />
                </AFormItem>
                <AFormItem label="Khối giáo lý" name="catechism_level_id" :rules="[{required:true,type:'number',min:1,message:'Hãy chọn khối.'}]" :help="errors.catechism_level_id" :validate-status="errors.catechism_level_id ? 'error' : undefined">
                    <ASelect v-model:value="form.catechism_level_id" size="large" placeholder="Chọn khối" :options="option(options.levels)" />
                </AFormItem>
                <AFormItem class="field-wide" label="Phòng học" name="classroom_id" :help="errors.classroom_id" :validate-status="errors.classroom_id ? 'error' : undefined">
                    <ASelect :value="form.classroom_id ?? undefined" allow-clear size="large" placeholder="Chưa xếp phòng" :options="option(options.classrooms)" @change="changeRoom" />
                </AFormItem>
            </div>
        </AForm>
    </AModal>
</template>

<style scoped>
.teacher-class-intro,.teacher-parish-band{display:flex;align-items:center;gap:.75rem;border-radius:.75rem}.teacher-class-intro{margin-top:1rem;border:1px solid #dbeafe;background:#f8fbff;padding:.875rem}.teacher-class-intro>span{display:grid;width:2.5rem;height:2.5rem;flex:none;place-items:center;border-radius:.625rem;background:#eaf2ff;color:#185fce}.teacher-class-intro svg,.teacher-parish-band>svg{width:1.05rem;height:1.05rem}.teacher-class-intro div{min-width:0}.teacher-class-intro b,.teacher-parish-band b{display:block;color:#0b214d;font-size:.78rem}.teacher-class-intro p{margin:.15rem 0 0;color:#64748b;font-size:.68rem;line-height:1.5}.teacher-parish-band{margin-top:.75rem;background:#f1f5f9;padding:.75rem;color:#475569}.teacher-parish-band>svg{flex:none}.teacher-parish-band span{display:flex;min-width:0;flex-direction:column}.teacher-parish-band small{color:#64748b;font-size:.65rem}.teacher-parish-band b{margin-top:.1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.teacher-class-form{margin-top:1rem}.teacher-class-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:1rem}.teacher-form-section{grid-column:1/-1;display:flex;align-items:center;gap:.45rem;margin:.15rem 0 .75rem;border-bottom:1px solid #eef2f7;padding-bottom:.55rem;color:#0b214d;font-size:.72rem}.teacher-form-section:not(:first-child){margin-top:.25rem}.teacher-form-section svg{width:.95rem;height:.95rem;color:#185fce}.field-wide{grid-column:1/-1}.teacher-class-form :deep(.ant-form-item){margin-bottom:1rem}.teacher-class-form :deep(.ant-form-item-label>label){color:#334155;font-size:.75rem;font-weight:650}.teacher-class-form :deep(.ant-input),.teacher-class-form :deep(.ant-select-selector){border-radius:.625rem!important;box-shadow:none!important}@media(max-width:639px){.teacher-class-intro{align-items:flex-start}.teacher-class-form-grid{grid-template-columns:1fr}.teacher-form-section,.field-wide{grid-column:auto}}
</style>

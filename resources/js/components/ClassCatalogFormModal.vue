<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AForm, { type FormInstance } from "ant-design-vue/es/form";
import AFormItem from "ant-design-vue/es/form/FormItem";
import AInput from "ant-design-vue/es/input";
import AInputNumber from "ant-design-vue/es/input-number";
import AModal from "ant-design-vue/es/modal";
import ASwitch from "ant-design-vue/es/switch";
import type {
    AcademicYearCatalog, AcademicYearCatalogInput,
    CatechismLevelCatalog, CatechismLevelCatalogInput,
    ClassroomCatalog, ClassroomCatalogInput,
} from "../api/admin";

export type ClassCatalogType = "academic_year" | "level" | "classroom";
export type ClassCatalogItem = AcademicYearCatalog | CatechismLevelCatalog | ClassroomCatalog;
export type ClassCatalogPayload = AcademicYearCatalogInput | CatechismLevelCatalogInput | ClassroomCatalogInput;

const props = withDefaults(defineProps<{
    open:boolean;
    type:ClassCatalogType;
    item?:ClassCatalogItem|null;
    parishId:number;
    parishName:string;
    saving?:boolean;
    errors?:Record<string,string>;
}>(), { item:null, saving:false, errors:() => ({}) });

const emit = defineEmits<{
    close:[dirty:boolean];
    submit:[type:ClassCatalogType, payload:ClassCatalogPayload];
}>();

interface FormState {
    name:string;
    starts_on:string;
    ends_on:string;
    is_current:boolean;
    code:string;
    sort_order:number;
    capacity:number|undefined;
    is_active:boolean;
}

const formRef = ref<FormInstance>();
const form = reactive<FormState>({
    name:"", starts_on:"", ends_on:"", is_current:false,
    code:"", sort_order:0, capacity:undefined, is_active:true,
});
const initialSnapshot = ref("");
const editing = computed(() => Boolean(props.item));
const dirty = computed(() => JSON.stringify(form) !== initialSnapshot.value);
const label = computed(() => ({
    academic_year:"niên khóa",
    level:"khối giáo lý",
    classroom:"phòng học",
}[props.type]));

watch(() => props.open, (open) => {
    if (!open) return;
    const item = props.item;
    Object.assign(form, {
        name:item?.name ?? "",
        starts_on:props.type === "academic_year" ? (item as AcademicYearCatalog | null)?.starts_on ?? "" : "",
        ends_on:props.type === "academic_year" ? (item as AcademicYearCatalog | null)?.ends_on ?? "" : "",
        is_current:props.type === "academic_year" ? (item as AcademicYearCatalog | null)?.is_current ?? false : false,
        code:props.type === "level" ? (item as CatechismLevelCatalog | null)?.code ?? "" : "",
        sort_order:props.type === "level" ? (item as CatechismLevelCatalog | null)?.sort_order ?? 0 : 0,
        capacity:props.type === "classroom" ? (item as ClassroomCatalog | null)?.capacity ?? undefined : undefined,
        is_active:item?.is_active ?? true,
    });
    initialSnapshot.value = JSON.stringify(form);
    formRef.value?.clearValidate();
});

watch(() => form.is_current, (current) => {
    if (current) form.is_active = true;
});
watch(() => form.is_active, (active) => {
    if (!active) form.is_current = false;
});

async function submit() {
    if (props.saving) return;
    try {
        await formRef.value?.validate();
        const parish = editing.value ? {} : { parish_id:props.parishId };
        if (props.type === "academic_year") {
            emit("submit", props.type, {
                ...parish,
                name:form.name.trim(), starts_on:form.starts_on, ends_on:form.ends_on,
                is_current:form.is_current, is_active:form.is_active,
            });
        } else if (props.type === "level") {
            emit("submit", props.type, {
                ...parish,
                name:form.name.trim(), code:form.code.trim().toLocaleUpperCase("vi"),
                sort_order:Number(form.sort_order), is_active:form.is_active,
            });
        } else {
            emit("submit", props.type, {
                ...parish,
                name:form.name.trim(), capacity:form.capacity === undefined ? null : Number(form.capacity),
                is_active:form.is_active,
            });
        }
    } catch {
        // Ant Form renders field-level validation errors.
    }
}
</script>

<template>
    <AModal
        :open="open"
        :title="`${editing ? 'Chỉnh sửa' : 'Tạo'} ${label}`"
        :confirm-loading="saving"
        :closable="!saving"
        :keyboard="!saving"
        :mask-closable="false"
        :cancel-button-props="{ disabled:saving }"
        :ok-text="editing ? 'Lưu thay đổi' : `Tạo ${label}`"
        cancel-text="Hủy"
        width="640px"
        centered
        @cancel="emit('close', dirty)"
        @ok="submit"
    >
        <div class="catalog-modal-context">
            <span>Thuộc giáo xứ</span>
            <strong>{{ parishName }}</strong>
        </div>
        <AAlert
            v-if="item?.classes_count"
            type="info"
            show-icon
            :message="`${item.classes_count} lớp đang sử dụng danh mục này. Việc đổi tên sẽ được cập nhật trên các lớp đó.`"
        />
        <AForm ref="formRef" :model="form" :disabled="saving" layout="vertical" class="catalog-form">
            <AFormItem :label="`Tên ${label}`" name="name" :rules="[{required:true,message:`Hãy nhập tên ${label}.`},{max:100,message:'Tên không vượt quá 100 ký tự.'}]" :help="errors.name" :validate-status="errors.name?'error':undefined">
                <AInput v-model:value="form.name" size="large" :placeholder="type==='academic_year'?'Ví dụ: 2026–2027':type==='level'?'Ví dụ: Thiếu Nhi':'Ví dụ: Phòng A1'" />
            </AFormItem>

            <div v-if="type==='academic_year'" class="catalog-form-grid">
                <AFormItem label="Ngày bắt đầu" name="starts_on" :rules="[{required:true,message:'Hãy chọn ngày bắt đầu.'}]" :help="errors.starts_on" :validate-status="errors.starts_on?'error':undefined">
                    <AInput v-model:value="form.starts_on" type="date" size="large" />
                </AFormItem>
                <AFormItem label="Ngày kết thúc" name="ends_on" :rules="[{required:true,message:'Hãy chọn ngày kết thúc.'}]" :help="errors.ends_on" :validate-status="errors.ends_on?'error':undefined">
                    <AInput v-model:value="form.ends_on" type="date" size="large" />
                </AFormItem>
            </div>

            <div v-if="type==='level'" class="catalog-form-grid">
                <AFormItem label="Mã khối" name="code" :rules="[{required:true,message:'Hãy nhập mã khối.'},{pattern:/^[\p{L}\p{N}_-]+$/u,message:'Chỉ dùng chữ, số, gạch ngang hoặc gạch dưới.'}]" :help="errors.code" :validate-status="errors.code?'error':undefined">
                    <AInput v-model:value="form.code" size="large" placeholder="Ví dụ: L2" />
                </AFormItem>
                <AFormItem label="Thứ tự hiển thị" name="sort_order" :rules="[{required:true,message:'Hãy nhập thứ tự.'}]" :help="errors.sort_order" :validate-status="errors.sort_order?'error':undefined">
                    <AInputNumber v-model:value="form.sort_order" :min="0" :max="65535" size="large" class="w-full" />
                </AFormItem>
            </div>

            <AFormItem v-if="type==='classroom'" label="Sức chứa" name="capacity" :help="errors.capacity" :validate-status="errors.capacity?'error':undefined">
                <AInputNumber v-model:value="form.capacity" :min="1" :max="65535" size="large" class="w-full" placeholder="Không bắt buộc" />
            </AFormItem>

            <div class="catalog-switch-list">
                <label v-if="type==='academic_year'">
                    <span><b>Niên khóa hiện tại</b><small>Tự bỏ đánh dấu ở niên khóa hiện tại trước đó.</small></span>
                    <ASwitch v-model:checked="form.is_current" />
                </label>
                <label>
                    <span><b>Đang sử dụng</b><small>Danh mục ngừng sử dụng sẽ không xuất hiện khi tạo lớp mới.</small></span>
                    <ASwitch v-model:checked="form.is_active" />
                </label>
            </div>
        </AForm>
    </AModal>
</template>

<style scoped>
.catalog-modal-context{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:1rem 0;padding:.75rem 1rem;border:1px solid #e2e8f0;border-radius:.625rem;background:#f8fafc;color:#64748b;font-size:.75rem}.catalog-modal-context strong{overflow:hidden;color:#17345f;text-overflow:ellipsis;white-space:nowrap}.catalog-form{margin-top:1rem}.catalog-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}.catalog-form :deep(.ant-form-item){margin-bottom:1rem}.catalog-form :deep(.ant-form-item-label>label){color:#334155;font-size:.75rem;font-weight:650}.catalog-form :deep(.ant-input),.catalog-form :deep(.ant-input-number){border-radius:.625rem;box-shadow:none}.catalog-switch-list{overflow:hidden;border:1px solid #e2e8f0;border-radius:.75rem}.catalog-switch-list label{display:flex;min-height:4.25rem;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 1rem}.catalog-switch-list label+label{border-top:1px solid #e2e8f0}.catalog-switch-list span{display:flex;min-width:0;flex-direction:column}.catalog-switch-list b{color:#1e293b;font-size:.78rem}.catalog-switch-list small{margin-top:.2rem;color:#64748b;font-size:.68rem;line-height:1.45}@media(max-width:520px){.catalog-form-grid{grid-template-columns:1fr}}
</style>

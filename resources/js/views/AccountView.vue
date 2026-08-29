<script setup lang="ts">
import { onMounted, reactive, ref, watch } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AForm from "ant-design-vue/es/form";
import AFormItem from "ant-design-vue/es/form/FormItem";
import AInput from "ant-design-vue/es/input";
import ATag from "ant-design-vue/es/tag";
import { Camera, LoaderCircle, Mail, Phone, Save, ShieldCheck, UserRound } from "lucide-vue-next";
import { toast } from "vue-sonner";
import { getProfile, updateProfile, uploadAvatar } from "../api/accounts";
import { useAuthStore } from "../stores/authStore";
import { vietnamesePhoneRule } from "../utils/phoneValidation";

const auth = useAuthStore();
const form = reactive({ name: "", email: "", phone: "" });
const loading = ref(true), saving = ref(false), avatarUploading = ref(false), error = ref("");
const avatarFailed = ref(false);
const roleLabels: Record<string, string> = { admin: "Quản trị viên", teacher: "Giáo lý viên", parent: "Phụ huynh", child: "Thiếu nhi" };
const profileRules = { phone: [vietnamesePhoneRule()] };
const displayRole = (role: string) => roleLabels[role] ?? role;
const apiMessage = (e: unknown, fallback: string) => (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;
watch(() => auth.user?.avatar_url, () => { avatarFailed.value = false; });

async function load() {
    loading.value = true;
    error.value = "";
    try {
        const { data } = await getProfile();
        Object.assign(form, { name: data.data.name, email: data.data.email, phone: data.data.phone ?? "" });
    } catch (e) {
        error.value = apiMessage(e, "Không thể tải thông tin tài khoản.");
    } finally {
        loading.value = false;
    }
}
async function save() { if(saving.value)return;saving.value = true; error.value = ""; try { const { data } = await updateProfile({ ...form, phone: form.phone || null }); auth.user = data.data; toast.success("Thông tin tài khoản đã được cập nhật."); } catch (e) { error.value = apiMessage(e, "Không thể cập nhật tài khoản."); toast.error(error.value); } finally { saving.value = false; } }
async function selectAvatar(event: Event) { const file = (event.target as HTMLInputElement).files?.[0]; if (!file||avatarUploading.value) return; avatarUploading.value = true; error.value = ""; try { const { data } = await uploadAvatar(file); auth.user = data.data; toast.success("Ảnh đại diện đã được cập nhật."); } catch (e) { error.value = apiMessage(e, "Ảnh không hợp lệ."); toast.error(error.value); } finally { avatarUploading.value = false; } }
onMounted(load);
</script>

<template>
    <div class="account-page mx-auto w-full max-w-6xl space-y-5">
        <div v-if="loading" class="account-loading-grid" aria-busy="true" aria-label="Đang tải thông tin tài khoản">
            <div class="h-72 animate-pulse rounded-2xl bg-slate-200" />
            <div class="h-72 animate-pulse rounded-2xl bg-slate-200" />
        </div>

        <template v-else>
            <AAlert v-if="error" type="error" show-icon closable :message="error" @close="error = ''" />

            <div class="account-overview-grid">
                <ACard :bordered="false" class="account-card profile-summary-card">
                    <div class="profile-summary">
                        <div class="profile-avatar-wrap">
                            <img v-if="auth.user?.avatar_url && !avatarFailed" :src="auth.user.avatar_url" alt="Ảnh đại diện" class="profile-avatar object-cover" @error="avatarFailed = true" />
                            <div v-else class="profile-avatar grid place-items-center bg-blue-50 text-primary-700"><UserRound class="size-10" /></div>
                            <label class="avatar-upload-control" :class="avatarUploading ? 'pointer-events-none opacity-70' : ''" title="Đổi ảnh đại diện">
                                <LoaderCircle v-if="avatarUploading" class="size-4 animate-spin" />
                                <Camera v-else class="size-4" />
                                <span class="sr-only">Tải ảnh đại diện mới</span>
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" :disabled="avatarUploading" @change="selectAvatar">
                            </label>
                        </div>
                        <div class="profile-copy min-w-0">
                            <h3 class="truncate text-base font-bold text-ink">{{ auth.user?.name }}</h3>
                            <p class="truncate text-xs text-slate-500">{{ auth.user?.email }}</p>
                            <div class="profile-roles"><ATag v-for="role in auth.roles" :key="role" color="blue">{{ displayRole(role) }}</ATag></div>
                        </div>
                    </div>
                </ACard>

                <ACard :bordered="false" class="account-card profile-form-card">
                    <div class="account-section-heading">
                        <span class="section-icon"><ShieldCheck class="size-5" /></span>
                        <div><h3>Thông tin cá nhân</h3><p>Vai trò và quyền chỉ quản trị viên được thay đổi.</p></div>
                    </div>
                    <AForm :model="form" :rules="profileRules" :disabled="saving" layout="vertical" class="mt-5" @finish="save">
                        <div class="account-form-grid">
                            <AFormItem label="Họ và tên" required><AInput v-model:value="form.name" size="large" :maxlength="255"><template #prefix><UserRound class="account-input-icon" /></template></AInput></AFormItem>
                            <AFormItem label="Email" required><AInput v-model:value="form.email" size="large" type="email"><template #prefix><Mail class="account-input-icon" /></template></AInput></AFormItem>
                            <AFormItem class="account-phone-field" label="Số điện thoại" name="phone"><AInput v-model:value="form.phone" size="large" :maxlength="20" inputmode="tel" autocomplete="tel" placeholder="0901 234 567"><template #prefix><Phone class="account-input-icon" /></template></AInput></AFormItem>
                        </div>
                        <div class="account-form-actions"><AButton type="primary" size="large" html-type="submit" :loading="saving"><template #icon><Save class="size-4" /></template>Lưu thay đổi</AButton></div>
                    </AForm>
                </ACard>
            </div>

        </template>
    </div>
</template>

<style scoped>
.account-loading-grid,.account-overview-grid{display:grid;grid-template-columns:17.5rem minmax(0,1fr);gap:1.25rem}.account-card{overflow:hidden;border:1px solid #e2e8f0;border-radius:1rem;box-shadow:0 1px 2px rgba(15,23,42,.03)}.account-card :deep(.ant-card-body){padding:1.5rem}.profile-summary-card :deep(.ant-card-body){height:100%}.profile-summary{display:flex;height:100%;flex-direction:column;align-items:center;justify-content:center;text-align:center}.profile-avatar-wrap{position:relative;width:7rem;height:7rem}.profile-avatar{width:7rem;height:7rem;border-radius:999px;box-shadow:0 0 0 6px #eff6ff}.avatar-upload-control{position:absolute;right:-.125rem;bottom:.125rem;display:grid;width:2.5rem;height:2.5rem;cursor:pointer;place-items:center;border:3px solid #fff;border-radius:999px;background:#2563eb;color:#fff;box-shadow:0 6px 14px rgba(37,99,235,.28);transition:transform .2s ease,background-color .2s ease,box-shadow .2s ease}.avatar-upload-control:hover{transform:translateY(-2px) scale(1.04);background:#1d4ed8;box-shadow:0 8px 18px rgba(37,99,235,.34)}.avatar-upload-control:focus-within{outline:2px solid #2563eb;outline-offset:3px}.profile-copy{margin-top:1rem;width:100%}.profile-roles{display:flex;flex-wrap:wrap;justify-content:center;gap:.25rem;margin-top:.875rem}.account-section-heading{display:flex;min-width:0;align-items:flex-start;gap:.75rem}.account-section-heading h3{margin:0;color:#172554;font-size:1rem;font-weight:700;line-height:1.4}.account-section-heading p{margin:.125rem 0 0;color:#64748b;font-size:.75rem;line-height:1.5}.section-icon{display:grid;width:2.5rem;height:2.5rem;flex:none;place-items:center;border-radius:.75rem;background:#eff6ff;color:#1d4ed8}.account-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:1rem}.account-phone-field{grid-column:1/-1}.account-input-icon{width:1rem;height:1rem;color:#94a3b8}.account-form-actions{display:flex;justify-content:flex-end}.account-form-actions :deep(.ant-btn){min-width:9.5rem}.account-page :deep(.ant-form-item-label>label){font-weight:600;color:#334155}.account-page :deep(.ant-input-affix-wrapper),.account-page :deep(.ant-input){border-radius:.75rem}.account-page :deep(.ant-btn){border-radius:.75rem;font-weight:600}
@media(max-width:767px){.account-loading-grid,.account-overview-grid{grid-template-columns:1fr}.profile-summary{display:grid;grid-template-columns:5.5rem minmax(0,1fr);gap:1rem;text-align:left}.profile-avatar-wrap,.profile-avatar{width:5.5rem;height:5.5rem}.profile-copy{margin-top:0}.profile-roles{justify-content:flex-start;margin-top:.625rem}.account-card :deep(.ant-card-body){padding:1.125rem}.account-form-grid{grid-template-columns:1fr}.account-phone-field{grid-column:auto}.account-form-actions :deep(.ant-btn){width:100%}}
@media(max-width:419px){.profile-summary{grid-template-columns:4.5rem minmax(0,1fr)}.profile-avatar-wrap,.profile-avatar{width:4.5rem;height:4.5rem}.avatar-upload-control{width:2.125rem;height:2.125rem}.account-section-heading p{font-size:.6875rem}}
@media(prefers-reduced-motion:reduce){.avatar-upload-control{transition:none}.avatar-upload-control:hover{transform:none}}
</style>

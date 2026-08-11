<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AForm from "ant-design-vue/es/form";
import AFormItem from "ant-design-vue/es/form/FormItem";
import AInput from "ant-design-vue/es/input";
import AInputPassword from "ant-design-vue/es/input/Password";
import ATag from "ant-design-vue/es/tag";
import { Camera, CheckCircle2, KeyRound, LoaderCircle, Mail, Phone, RefreshCw, Save, ShieldCheck, ShieldOff, UserRound } from "lucide-vue-next";
import QrcodeVue from "qrcode.vue";
import { toast } from "vue-sonner";
import { confirmMfa, disableMfa, getMfaStatus, getProfile, regenerateMfaRecoveryCodes, setupMfa, updateProfile, uploadAvatar } from "../api/accounts";
import { useAuthStore } from "../stores/authStore";
import { vietnamesePhoneRule } from "../utils/phoneValidation";

const auth = useAuthStore();
const form = reactive({ name: "", email: "", phone: "" });
const loading = ref(true), saving = ref(false), avatarUploading = ref(false), error = ref("");
const mfaEnabled = ref(false), mfaPassword = ref(""), mfaCode = ref(""), mfaUri = ref(""), mfaSecret = ref(""), recoveryCodes = ref<string[]>([]);
const roleLabels: Record<string, string> = { admin: "Quản trị viên", teacher: "Giáo lý viên", parent: "Phụ huynh", child: "Thiếu nhi" };
const profileRules = { phone: [vietnamesePhoneRule()] };
const displayRole = (role: string) => roleLabels[role] ?? role;
const apiMessage = (e: unknown, fallback: string) => (e as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;

async function load() {
    loading.value = true;
    error.value = "";
    try {
        const { data } = await getProfile();
        Object.assign(form, { name: data.data.name, email: data.data.email, phone: data.data.phone ?? "" });
        if (auth.hasRole("admin")) mfaEnabled.value = (await getMfaStatus()).data.data.enabled;
    } catch (e) {
        error.value = apiMessage(e, "Không thể tải thông tin tài khoản.");
    } finally {
        loading.value = false;
    }
}
async function save() { if(saving.value)return;saving.value = true; error.value = ""; try { const { data } = await updateProfile({ ...form, phone: form.phone || null }); auth.user = data.data; toast.success("Thông tin tài khoản đã được cập nhật."); } catch (e) { error.value = apiMessage(e, "Không thể cập nhật tài khoản."); toast.error(error.value); } finally { saving.value = false; } }
async function selectAvatar(event: Event) { const file = (event.target as HTMLInputElement).files?.[0]; if (!file||avatarUploading.value) return; avatarUploading.value = true; error.value = ""; try { const { data } = await uploadAvatar(file); auth.user = data.data; toast.success("Ảnh đại diện đã được cập nhật."); } catch (e) { error.value = apiMessage(e, "Ảnh không hợp lệ."); toast.error(error.value); } finally { avatarUploading.value = false; } }
async function beginMfa() { if(saving.value)return;if (!mfaPassword.value) { toast.error("Hãy nhập mật khẩu để xác nhận."); return; } saving.value = true; try { await auth.confirmPassword(mfaPassword.value); const { data } = await setupMfa(); mfaUri.value = data.data.otpauth_uri; mfaSecret.value = data.data.secret; mfaPassword.value = ""; toast.info("Quét QR rồi nhập mã 6 chữ số để hoàn tất."); } catch (e) { toast.error(apiMessage(e, "Không thể bắt đầu thiết lập MFA.")); } finally { saving.value = false; } }
async function enableMfa() { if(saving.value)return;saving.value = true; try { const { data } = await confirmMfa(mfaCode.value); recoveryCodes.value = data.data.recovery_codes; mfaEnabled.value = true; mfaUri.value = ""; mfaSecret.value = ""; mfaCode.value = ""; toast.success("Đã bật xác thực hai lớp cho tài khoản admin."); } catch (e) { toast.error(apiMessage(e, "Mã xác thực không hợp lệ.")); } finally { saving.value = false; } }
async function turnOffMfa() { if(saving.value)return;if (!mfaPassword.value) { toast.error("Hãy nhập mật khẩu để xác nhận."); return; } saving.value = true; try { await auth.confirmPassword(mfaPassword.value); await disableMfa(); mfaEnabled.value = false; recoveryCodes.value = []; mfaPassword.value = ""; toast.success("Đã tắt xác thực hai lớp."); } catch (e) { toast.error(apiMessage(e, "Không thể tắt MFA.")); } finally { saving.value = false; } }
async function regenerateCodes() { if(saving.value)return;if (!mfaPassword.value) { toast.error("Hãy nhập mật khẩu để xác nhận."); return; } saving.value = true; try { await auth.confirmPassword(mfaPassword.value); recoveryCodes.value = (await regenerateMfaRecoveryCodes()).data.data.recovery_codes; mfaPassword.value = ""; toast.success("Đã tạo lại mã khôi phục."); } catch (e) { toast.error(apiMessage(e, "Không thể tạo lại mã khôi phục.")); } finally { saving.value = false; } }
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
                            <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="Ảnh đại diện" class="profile-avatar object-cover" />
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

            <ACard v-if="auth.hasRole('admin')" :bordered="false" class="account-card mfa-card">
                <div class="mfa-heading">
                    <div class="account-section-heading">
                        <span class="section-icon"><KeyRound class="size-5" /></span>
                        <div><h3>Xác thực hai lớp cho admin</h3><p>Dùng ứng dụng Authenticator. Mọi thay đổi MFA đều yêu cầu nhập lại mật khẩu.</p></div>
                    </div>
                    <ATag :color="mfaEnabled ? 'success' : 'warning'">{{ mfaEnabled ? 'Đang bảo vệ' : 'Chưa thiết lập' }}</ATag>
                </div>

                <div class="mfa-content">
                    <template v-if="mfaUri">
                        <div class="mfa-setup-grid">
                            <div class="mfa-qr"><QrcodeVue :value="mfaUri" :size="180" level="M" /></div>
                            <div class="min-w-0 space-y-3">
                                <p class="text-sm font-semibold text-ink">Quét mã QR bằng ứng dụng Authenticator</p>
                                <p class="text-xs leading-5 text-slate-500">Sau khi quét, nhập mã 6 chữ số để hoàn tất kích hoạt.</p>
                                <code class="mfa-secret">{{ mfaSecret }}</code>
                                <div class="mfa-control-row">
                                    <AInput v-model:value="mfaCode" size="large" inputmode="numeric" :maxlength="6" placeholder="Mã 6 chữ số" />
                                    <AButton type="primary" size="large" :loading="saving" @click="enableMfa"><template #icon><CheckCircle2 class="size-4" /></template>Xác nhận bật MFA</AButton>
                                </div>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="mfa-control-row">
                            <AInputPassword v-model:value="mfaPassword" size="large" autocomplete="current-password" placeholder="Nhập lại mật khẩu" />
                            <AButton v-if="!mfaEnabled" type="primary" size="large" :loading="saving" @click="beginMfa"><template #icon><ShieldCheck class="size-4" /></template>Thiết lập MFA</AButton>
                        </div>
                        <div v-if="mfaEnabled" class="mfa-enabled-actions">
                            <AButton size="large" :loading="saving" @click="regenerateCodes"><template #icon><RefreshCw class="size-4" /></template>Tạo lại mã khôi phục</AButton>
                            <AButton danger size="large" :loading="saving" @click="turnOffMfa"><template #icon><ShieldOff class="size-4" /></template>Tắt MFA</AButton>
                        </div>
                    </template>

                    <div v-if="recoveryCodes.length" role="status" class="recovery-codes">
                        <p class="text-sm font-bold text-amber-900">Lưu các mã khôi phục ngay — chúng chỉ hiển thị một lần.</p>
                        <div class="mt-3 grid gap-2 font-mono text-sm sm:grid-cols-2"><code v-for="code in recoveryCodes" :key="code">{{ code }}</code></div>
                    </div>
                </div>
            </ACard>
        </template>
    </div>
</template>

<style scoped>
.account-loading-grid,.account-overview-grid{display:grid;grid-template-columns:17.5rem minmax(0,1fr);gap:1.25rem}.account-card{overflow:hidden;border:1px solid #e2e8f0;border-radius:1rem;box-shadow:0 1px 2px rgba(15,23,42,.03)}.account-card :deep(.ant-card-body){padding:1.5rem}.profile-summary-card :deep(.ant-card-body){height:100%}.profile-summary{display:flex;height:100%;flex-direction:column;align-items:center;justify-content:center;text-align:center}.profile-avatar-wrap{position:relative;width:7rem;height:7rem}.profile-avatar{width:7rem;height:7rem;border-radius:999px;box-shadow:0 0 0 6px #eff6ff}.avatar-upload-control{position:absolute;right:-.125rem;bottom:.125rem;display:grid;width:2.5rem;height:2.5rem;cursor:pointer;place-items:center;border:3px solid #fff;border-radius:999px;background:#2563eb;color:#fff;box-shadow:0 6px 14px rgba(37,99,235,.28);transition:transform .2s ease,background-color .2s ease,box-shadow .2s ease}.avatar-upload-control:hover{transform:translateY(-2px) scale(1.04);background:#1d4ed8;box-shadow:0 8px 18px rgba(37,99,235,.34)}.avatar-upload-control:focus-within{outline:2px solid #2563eb;outline-offset:3px}.profile-copy{margin-top:1rem;width:100%}.profile-roles{display:flex;flex-wrap:wrap;justify-content:center;gap:.25rem;margin-top:.875rem}.account-section-heading{display:flex;min-width:0;align-items:flex-start;gap:.75rem}.account-section-heading h3{margin:0;color:#172554;font-size:1rem;font-weight:700;line-height:1.4}.account-section-heading p{margin:.125rem 0 0;color:#64748b;font-size:.75rem;line-height:1.5}.section-icon{display:grid;width:2.5rem;height:2.5rem;flex:none;place-items:center;border-radius:.75rem;background:#eff6ff;color:#1d4ed8}.account-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));column-gap:1rem}.account-phone-field{grid-column:1/-1}.account-input-icon{width:1rem;height:1rem;color:#94a3b8}.account-form-actions{display:flex;justify-content:flex-end}.account-form-actions :deep(.ant-btn){min-width:9.5rem}.mfa-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.mfa-content{margin-top:1.5rem;max-width:50rem}.mfa-control-row{display:grid;grid-template-columns:minmax(15rem,24rem) max-content;align-items:center;justify-content:start;gap:.75rem}.mfa-enabled-actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1rem}.mfa-setup-grid{display:grid;grid-template-columns:13rem minmax(0,1fr);align-items:start;gap:1.5rem}.mfa-qr{width:fit-content;border:1px solid #e2e8f0;border-radius:.875rem;background:#fff;padding:.75rem}.mfa-secret{display:block;max-width:100%;overflow-wrap:anywhere;border:1px solid #e2e8f0;border-radius:.75rem;background:#f8fafc;padding:.75rem;color:#334155;font-size:.75rem}.recovery-codes{margin-top:1.25rem;border:1px solid #fde68a;border-radius:.875rem;background:#fffbeb;padding:1rem}.account-page :deep(.ant-form-item-label>label){font-weight:600;color:#334155}.account-page :deep(.ant-input-affix-wrapper),.account-page :deep(.ant-input){border-radius:.75rem}.account-page :deep(.ant-btn){border-radius:.75rem;font-weight:600}
@media(max-width:767px){.account-loading-grid,.account-overview-grid{grid-template-columns:1fr}.profile-summary{display:grid;grid-template-columns:5.5rem minmax(0,1fr);gap:1rem;text-align:left}.profile-avatar-wrap,.profile-avatar{width:5.5rem;height:5.5rem}.profile-copy{margin-top:0}.profile-roles{justify-content:flex-start;margin-top:.625rem}.account-card :deep(.ant-card-body){padding:1.125rem}.account-form-grid{grid-template-columns:1fr}.account-phone-field{grid-column:auto}.account-form-actions :deep(.ant-btn){width:100%}.mfa-heading{align-items:flex-start}.mfa-control-row{grid-template-columns:1fr}.mfa-control-row :deep(.ant-btn),.mfa-enabled-actions :deep(.ant-btn){width:100%}.mfa-enabled-actions{display:grid;grid-template-columns:1fr}.mfa-setup-grid{grid-template-columns:1fr}.mfa-qr{margin-inline:auto}}
@media(max-width:419px){.profile-summary{grid-template-columns:4.5rem minmax(0,1fr)}.profile-avatar-wrap,.profile-avatar{width:4.5rem;height:4.5rem}.avatar-upload-control{width:2.125rem;height:2.125rem}.mfa-heading{flex-direction:column}.account-section-heading p{font-size:.6875rem}}
@media(prefers-reduced-motion:reduce){.avatar-upload-control{transition:none}.avatar-upload-control:hover{transform:none}}
</style>

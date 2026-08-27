<script setup lang="ts">
import { computed, onMounted, reactive, ref, shallowRef } from "vue";
import { useRouter } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AForm from "ant-design-vue/es/form";
import AFormItem from "ant-design-vue/es/form/FormItem";
import type { RuleObject } from "ant-design-vue/es/form/interface";
import AInput from "ant-design-vue/es/input";
import AInputPassword from "ant-design-vue/es/input/Password";
import AModal from "ant-design-vue/es/modal";
import APagination from "ant-design-vue/es/pagination";
import ASelect from "ant-design-vue/es/select";
import ATable from "ant-design-vue/es/table";
import ATag from "ant-design-vue/es/tag";
import ATabs, { TabPane as ATabPane } from "ant-design-vue/es/tabs";
import ATooltip from "ant-design-vue/es/tooltip";
import type { ColumnsType } from "ant-design-vue/es/table/interface";
import { Check, GraduationCap, KeyRound, LockKeyhole, LockKeyholeOpen, Pencil, Plus, Save, Search, ShieldCheck } from "lucide-vue-next";
import { toast } from "vue-sonner";
import { createAccount, getAccountOptions, listAccounts, resetAccountPassword, updateAccount, updateAccountAccess, updateAccountStatus, type AccountMeta, type AccountOptions } from "../api/accounts";
import AccountPermissionEditorModal from "../components/AccountPermissionEditorModal.vue";
import AdminActionConfirmModal from "../components/AdminActionConfirmModal.vue";
import UserAvatar from "../components/UserAvatar.vue";
import { displayPermission, displayRole, permissionGroups, roleDescriptions } from "../constants/permissionCatalog";
import { useAuthStore } from "../stores/authStore";
import type { User } from "../types/api";
import { vietnamesePhoneRule } from "../utils/phoneValidation";

interface SensitiveAction {
    title: string;
    description: string;
    confirmText: string;
    success: string;
    danger?: boolean;
    target?: User | null;
    run: () => Promise<User | null>;
    after?: (result: User | null) => void;
}

interface AccessPayload {
    role: string;
    granted_permissions: string[];
    denied_permissions: string[];
}

const auth = useAuthStore();
const router = useRouter();
const accounts = ref<User[]>([]), selected = ref<User | null>(null), options = ref<AccountOptions>({ roles: [], permissions: [] });
const meta = ref<AccountMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const loading = ref(true), saving = ref(false), listError = ref(""), editorError = ref("");
const search = ref(""), roleFilter = ref(""), statusFilter = ref("");
const createOpen = ref(false), permissionEditorOpen = ref(false), activeTab = ref("info"), resetPassword = ref(""), resetPasswordConfirmation = ref("");
const confirmedUntil = ref(0), confirmOpen = ref(false), confirmNeedsPassword = ref(false), confirmError = ref("");
const discardOpen = ref(false), editSnapshot = ref("");
const pendingAction = shallowRef<SensitiveAction | null>(null);
const createForm = reactive({ name: "", email: "", phone: "", password: "", role: "" });
const editForm = reactive({ name: "", email: "", phone: "", role: "", granted: [] as string[], denied: [] as string[] });
const infoRules: Record<string, RuleObject[]> = {
    name: [{ required: true, message: "Hãy nhập họ và tên." }],
    email: [{ required: true, message: "Hãy nhập email." }, { type: "email", message: "Email không hợp lệ." }],
    phone: [vietnamesePhoneRule()],
};
const createRules: Record<string, RuleObject[]> = {
    ...infoRules,
    password: [{ required: true, message: "Hãy nhập mật khẩu." }, { min: 8, message: "Mật khẩu phải có ít nhất 8 ký tự." }],
    role: [{ required: true, message: "Hãy chọn vai trò." }],
};
const roleOptions = computed(() => options.value.roles.map((role) => ({ label: displayRole(role.name), value: role.name })));
const nonAdminRoleOptions = computed(() => roleOptions.value.filter((role) => role.value !== "admin"));
const accountCreationRoleOptions = computed(() => nonAdminRoleOptions.value.filter((role) => role.value !== "teacher"));
const accountEditorRoleOptions = computed(() => {
    if (selected.value?.roles.includes("admin")) return roleOptions.value.filter((role) => role.value === "admin");
    if (selected.value?.roles.includes("teacher")) return nonAdminRoleOptions.value;
    return accountCreationRoleOptions.value;
});
const selectedRole = computed(() => selected.value?.roles[0] ?? "");
const selectedPermissionGroups = computed(() => permissionGroups
    .map((group) => ({
        ...group,
        permissions: group.permissions.filter((permission) => selected.value?.permissions.includes(permission)),
    }))
    .filter((group) => group.permissions.length > 0));
const customPermissionCount = computed(() => (
    (selected.value?.granted_permissions?.length ?? 0) + (selected.value?.denied_permissions?.length ?? 0)
));
const columns: ColumnsType<User> = [
    { title: "Tài khoản", key: "account", width: 300 },
    { title: "Vai trò", key: "roles", width: 170, responsive: ["md"] },
    { title: "Trạng thái", key: "status", width: 150, responsive: ["sm"] },
    { title: "Thao tác", key: "action", width: 116, fixed: "right", align: "center" },
];

const displayStatus = (account: User) => account.deleted_at ? "Đã lưu trữ" : account.status === "active" ? "Hoạt động" : "Đã chặn";
const isSelf = (account: User) => account.id === auth.user?.id;
const apiMessage = (error: unknown, fallback: string) => (error as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;
const serializedEditor = () => JSON.stringify({ name: editForm.name, email: editForm.email, phone: editForm.phone, resetPassword: resetPassword.value, resetPasswordConfirmation: resetPasswordConfirmation.value });
const editorDirty = computed(() => Boolean(selected.value) && serializedEditor() !== editSnapshot.value);

async function load(page = 1) {
    loading.value = true;
    listError.value = "";
    try {
        const response = await listAccounts({ search: search.value || undefined, role: roleFilter.value || undefined, status: statusFilter.value || undefined, page });
        accounts.value = response.data.data;
        meta.value = response.data.meta as unknown as AccountMeta;
    } catch (error) {
        listError.value = apiMessage(error, "Không thể tải danh sách tài khoản.");
    } finally {
        loading.value = false;
    }
}

function syncEditor(account: User, resetTab = false) {
    selected.value = account;
    Object.assign(editForm, { name: account.name, email: account.email, phone: account.phone ?? "", role: account.roles[0] ?? "", granted: [...(account.granted_permissions ?? [])], denied: [...(account.denied_permissions ?? [])] });
    resetPassword.value = "";
    resetPasswordConfirmation.value = "";
    editorError.value = "";
    if (resetTab) activeTab.value = "info";
    editSnapshot.value = serializedEditor();
}

function openEditor(account: User) {
    if (account.deleted_at) return;
    syncEditor(account, true);
}

function closeEditor() {
    permissionEditorOpen.value = false;
    selected.value = null;
    resetPassword.value = "";
    resetPasswordConfirmation.value = "";
    activeTab.value = "info";
    editorError.value = "";
    discardOpen.value = false;
}

function requestEditorClose() {
    if (saving.value) return;
    if (editorDirty.value) discardOpen.value = true;
    else closeEditor();
}

function rowInteractions(account: User) {
    const disabled = Boolean(account.deleted_at);
    return {
        class: disabled ? "account-row is-disabled" : "account-row",
        tabindex: disabled ? -1 : 0,
        "aria-label": disabled ? `${account.name}, đã lưu trữ` : `Chỉnh sửa tài khoản ${account.name}`,
        onClick: () => openEditor(account),
        onKeydown: (event: KeyboardEvent) => {
            if (!disabled && (event.key === "Enter" || event.key === " ")) {
                event.preventDefault();
                openEditor(account);
            }
        },
    };
}

async function saveInfo() {
    if (!selected.value || saving.value) return;
    saving.value = true;
    editorError.value = "";
    try {
        const response = await updateAccount(selected.value.id, { name: editForm.name, email: editForm.email, phone: editForm.phone || null });
        syncEditor(response.data.data);
        toast.success("Đã cập nhật thông tin tài khoản.");
        await load(meta.value.current_page);
    } catch (error) {
        editorError.value = apiMessage(error, "Không thể cập nhật thông tin tài khoản.");
        toast.error(editorError.value);
    } finally {
        saving.value = false;
    }
}

function openSensitiveAction(action: SensitiveAction, alwaysConfirm = false) {
    if (saving.value) return;
    pendingAction.value = action;
    confirmNeedsPassword.value = Date.now() >= confirmedUntil.value;
    confirmError.value = "";
    if (alwaysConfirm || confirmNeedsPassword.value) confirmOpen.value = true;
    else void executeSensitiveAction("");
}

async function executeSensitiveAction(password: string) {
    const action = pendingAction.value;
    if (!action || saving.value) return;
    saving.value = true;
    confirmError.value = "";
    try {
        if (confirmNeedsPassword.value) {
            await auth.confirmPassword(password);
            confirmedUntil.value = Date.now() + 15 * 60 * 1000;
        }
        const result = await action.run();
        action.after?.(result);
        toast.success(action.success);
        confirmOpen.value = false;
        pendingAction.value = null;
        await load(meta.value.current_page);
    } catch (error) {
        confirmError.value = apiMessage(error, "Không thể thực hiện thao tác.");
        toast.error(confirmError.value);
    } finally {
        saving.value = false;
    }
}

function closeSensitiveConfirm() {
    if (saving.value) return;
    confirmOpen.value = false;
    confirmError.value = "";
    pendingAction.value = null;
}

function requestStatusChange(account: User) {
    if (isSelf(account) || account.deleted_at) return;
    const willBlock = account.status === "active";
    openSensitiveAction({
        title: willBlock ? "Chặn tài khoản này?" : "Mở khóa tài khoản này?",
        description: willBlock ? "Tài khoản sẽ bị đăng xuất khỏi tất cả thiết bị và không thể truy cập hệ thống." : "Tài khoản sẽ có thể đăng nhập và sử dụng lại các quyền được cấp.",
        confirmText: willBlock ? "Chặn tài khoản" : "Mở khóa",
        success: willBlock ? "Đã chặn tài khoản." : "Đã mở khóa tài khoản.",
        danger: willBlock,
        target: account,
        run: async () => (await updateAccountStatus(account.id, willBlock ? "blocked" : "active")).data.data,
        after: (updated) => { if (updated && selected.value?.id === updated.id) syncEditor(updated); },
    }, true);
}

function requestSaveAccess(payload: AccessPayload) {
    if (!selected.value) return;
    const account = selected.value;
    openSensitiveAction({
        title: "Lưu thay đổi phân quyền?",
        description: "Vai trò và quyền truy cập của tài khoản sẽ được cập nhật ngay.",
        confirmText: "Lưu phân quyền",
        success: "Đã cập nhật vai trò và quyền.",
        target: account,
        run: async () => (await updateAccountAccess(account.id, payload)).data.data,
        after: (updated) => {
            if (updated) syncEditor(updated);
            permissionEditorOpen.value = false;
        },
    });
}

function requestPasswordReset() {
    if (!selected.value || resetPassword.value.length < 8) {
        editorError.value = "Mật khẩu mới phải có ít nhất 8 ký tự.";
        return;
    }
    if (resetPassword.value !== resetPasswordConfirmation.value) {
        editorError.value = "Mật khẩu xác nhận không khớp.";
        return;
    }
    const account = selected.value;
    openSensitiveAction({
        title: "Đặt lại mật khẩu?",
        description: "Mật khẩu hiện tại sẽ bị thay thế và tất cả phiên đăng nhập của tài khoản sẽ bị thu hồi.",
        confirmText: "Đặt mật khẩu mới",
        success: "Đã đặt lại mật khẩu và thu hồi các phiên cũ.",
        target: account,
        run: async () => { await resetAccountPassword(account.id, resetPassword.value, resetPasswordConfirmation.value); return null; },
        after: () => { resetPassword.value = ""; resetPasswordConfirmation.value = ""; editSnapshot.value = serializedEditor(); },
    });
}

function requestCreate() {
    if (saving.value) return;
    if (!createForm.name || !createForm.email || createForm.password.length < 8 || !createForm.role) {
        toast.error("Hãy nhập đủ họ tên, email, vai trò và mật khẩu tối thiểu 8 ký tự.");
        return;
    }
    openSensitiveAction({
        title: "Tạo tài khoản mới?",
        description: "Kiểm tra thông tin trước khi cấp quyền truy cập hệ thống.",
        confirmText: "Tạo tài khoản",
        success: "Đã tạo tài khoản.",
        run: async () => (await createAccount({ ...createForm })).data.data,
        after: () => { createOpen.value = false; Object.assign(createForm, { name: "", email: "", phone: "", password: "", role: "" }); },
    });
}

function openTeacherCreation() {
    if (saving.value) return;
    createOpen.value = false;
    void router.push({ path: "/admin/teachers", query: { create: "1" } });
}

function closeCreateModal() {
    if (!saving.value) createOpen.value = false;
}

onMounted(async () => {
    try {
        options.value = (await getAccountOptions()).data.data;
    } catch (error) {
        listError.value = apiMessage(error, "Không thể tải danh mục vai trò và quyền.");
    }
    await load();
});
</script>

<template>
    <section class="account-management-page">
        <AAlert v-if="listError" type="error" show-icon closable :message="listError" class="mb-4" @close="listError = ''" />

        <ACard :bordered="false" class="admin-card admin-table-card account-management-card">
            <div class="account-management-toolbar">
                <div class="min-w-0">
                    <p>Vai trò mặc định, quyền tùy chỉnh và trạng thái truy cập toàn hệ thống.</p>
                    <span>{{ meta.total.toLocaleString("vi-VN") }} tài khoản</span>
                </div>
                <div class="flex flex-wrap justify-end gap-2.5">
                    <AButton size="large" @click="openTeacherCreation"><template #icon><GraduationCap class="size-4" /></template>Tạo giáo lý viên</AButton>
                    <AButton type="primary" size="large" class="page-primary-action" @click="createOpen = true"><template #icon><Plus class="size-4" /></template>Tạo tài khoản</AButton>
                </div>
            </div>
            <div class="account-filters">
                <AInput v-model:value="search" allow-clear size="large" placeholder="Tìm theo tên hoặc email" @press-enter="load(1)"><template #prefix><Search class="size-4 text-slate-400" /></template></AInput>
                <ASelect v-model:value="roleFilter" size="large" :options="[{ label: 'Mọi vai trò', value: '' }, ...roleOptions]" />
                <ASelect v-model:value="statusFilter" size="large" :options="[{ label: 'Mọi trạng thái', value: '' }, { label: 'Đang hoạt động', value: 'active' }, { label: 'Đã chặn', value: 'blocked' }]" />
                <AButton type="primary" size="large" class="account-filter-submit" :loading="loading" @click="load(1)"><template #icon><Search class="size-4" /></template>Lọc</AButton>
            </div>

            <ATable class="accounts-table" :columns="columns" :custom-row="rowInteractions" :data-source="accounts" :loading="loading" :locale="{ emptyText: 'Không có tài khoản phù hợp' }" :pagination="false" :scroll="{ x: 736 }" row-key="id">
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'account'">
                        <div class="flex min-w-0 items-center gap-3">
                            <UserAvatar :name="record.name" :avatar-url="record.avatar_url" />
                            <div class="flex min-w-0 flex-col items-start">
                                <b class="block truncate text-[13px] font-semibold text-blue-950">{{ record.name }}</b>
                                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ record.email }}</span>
                            </div>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'roles'"><div class="flex flex-wrap gap-1"><ATag v-for="role in record.roles" :key="role" color="blue">{{ displayRole(role) }}</ATag></div></template>
                    <template v-else-if="column.key === 'status'"><ATag :color="record.deleted_at ? 'default' : record.status === 'active' ? 'success' : 'error'">{{ displayStatus(record as User) }}</ATag></template>
                    <template v-else-if="column.key === 'action'">
                        <div class="table-icon-actions">
                            <ATooltip title="Chỉnh sửa tài khoản"><AButton type="text" class="icon-action-button" aria-label="Chỉnh sửa tài khoản" :disabled="Boolean(record.deleted_at)" @click.stop="openEditor(record as User)"><template #icon><Pencil class="size-4" /></template></AButton></ATooltip>
                            <ATooltip :title="isSelf(record as User) ? 'Không thể tự chặn tài khoản đang đăng nhập' : record.status === 'active' ? 'Chặn tài khoản' : 'Mở khóa tài khoản'"><AButton type="text" class="icon-action-button" :class="record.status === 'active' ? 'is-danger' : 'is-success'" :aria-label="record.status === 'active' ? 'Chặn tài khoản' : 'Mở khóa tài khoản'" :disabled="isSelf(record as User) || Boolean(record.deleted_at)" @click.stop="requestStatusChange(record as User)"><template #icon><LockKeyhole v-if="record.status === 'active'" class="size-4" /><LockKeyholeOpen v-else class="size-4" /></template></AButton></ATooltip>
                        </div>
                    </template>
                </template>
            </ATable>
            <div v-if="meta.total > meta.per_page" class="account-pagination"><APagination :current="meta.current_page" :page-size="meta.per_page" :total="meta.total" :show-size-changer="false" responsive @change="load" /></div>
        </ACard>
    </section>

    <AModal
        :open="Boolean(selected)"
        :footer="null"
        :width="960"
        :mask-closable="false"
        :closable="!saving"
        :keyboard="!saving"
        wrap-class-name="account-editor-modal account-editor-modal--compact"
        centered
        @cancel="requestEditorClose"
    >
        <template v-if="selected" #title>
            <div class="flex min-w-0 items-center gap-3 pr-10">
                <UserAvatar :name="selected.name" :avatar-url="selected.avatar_url" />
                <div class="min-w-0 flex-1">
                    <strong class="block truncate text-[15px] font-bold text-blue-950">{{ selected.name }}</strong>
                    <span class="mt-0.5 block truncate text-xs font-normal text-slate-500">{{ selected.email }}</span>
                </div>
                <ATag class="!m-0 shrink-0" :color="selected.status === 'active' ? 'success' : 'error'">{{ displayStatus(selected) }}</ATag>
            </div>
        </template>
        <template v-if="selected">
            <AAlert v-if="editorError" type="error" show-icon closable :message="editorError" class="mb-3" @close="editorError = ''" />
            <ATabs v-model:active-key="activeTab" class="account-editor-tabs">
                <ATabPane key="info" tab="Thông tin">
                    <AForm :model="editForm" :rules="infoRules" :disabled="saving" layout="vertical" class="m-0 [&_.ant-form-item]:mb-4 [&_.ant-form-item-label>label]:text-xs [&_.ant-form-item-label>label]:font-semibold [&_.ant-form-item-label>label]:text-slate-700" @finish="saveInfo">
                        <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                            <AFormItem label="Họ và tên" name="name" required><AInput v-model:value="editForm.name" size="large" class="!rounded-[10px]" /></AFormItem>
                            <AFormItem label="Email" name="email" required><AInput v-model:value="editForm.email" size="large" type="email" class="!rounded-[10px]" /></AFormItem>
                            <AFormItem class="md:col-span-2" label="Số điện thoại" name="phone"><AInput v-model:value="editForm.phone" size="large" inputmode="tel" autocomplete="tel" class="!rounded-[10px]" /></AFormItem>
                        </div>
                        <div class="flex justify-end pt-1">
                            <AButton type="primary" size="large" html-type="submit" :loading="saving" class="w-full rounded-[10px] font-semibold sm:w-auto sm:min-w-44"><template #icon><Save class="size-4" /></template>Lưu thông tin</AButton>
                        </div>
                    </AForm>
                </ATabPane>
                <ATabPane key="access" tab="Phân quyền">
                    <div class="space-y-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600"><ShieldCheck aria-hidden="true" class="size-5 stroke-[1.75]" /></span>
                                <div class="min-w-0">
                                    <h3 class="m-0 text-sm font-bold leading-6 text-blue-950">Vai trò và quyền truy cập</h3>
                                    <p class="mt-0.5 mb-0 text-pretty text-xs leading-5 text-slate-500">Xem nhanh quyền đang có; mở trình chỉnh sửa khi cần thay đổi.</p>
                                </div>
                            </div>
                            <AButton type="primary" :disabled="saving" class="shrink-0 rounded-[10px] font-semibold max-sm:w-full" @click="permissionEditorOpen = true">
                                <template #icon><Pencil class="size-4" /></template>Chỉnh sửa
                            </AButton>
                        </div>

                        <section class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-white p-4 sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="m-0 text-[11px] font-bold uppercase tracking-[0.08em] text-blue-600">Vai trò hiện tại</p>
                                    <h4 class="mt-1 mb-0 text-base font-bold text-blue-950">{{ displayRole(selectedRole) }}</h4>
                                    <p class="mt-1 mb-0 text-xs leading-5 text-slate-500">{{ roleDescriptions[selectedRole] ?? 'Quyền truy cập được thiết lập cho tài khoản này.' }}</p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <ATag color="blue" class="!m-0">{{ selected.permissions.length }} quyền đang dùng</ATag>
                                    <ATag v-if="customPermissionCount" color="purple" class="!m-0">{{ customPermissionCount }} tùy chỉnh</ATag>
                                </div>
                            </div>
                        </section>

                        <div v-if="selectedPermissionGroups.length" class="grid gap-3 sm:grid-cols-2">
                            <section v-for="group in selectedPermissionGroups" :key="group.id" class="rounded-xl border border-slate-200 bg-white p-4 transition-colors hover:border-blue-200">
                                <div class="mb-2.5 flex items-center justify-between gap-3">
                                    <h4 class="m-0 text-xs font-bold text-blue-950">{{ group.label }}</h4>
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold tabular-nums text-slate-600">{{ group.permissions.length }}</span>
                                </div>
                                <ul class="m-0 grid list-none gap-2 p-0">
                                    <li v-for="permission in group.permissions.slice(0, 3)" :key="permission" class="flex items-start gap-2 text-xs leading-5 text-slate-600">
                                        <Check aria-hidden="true" class="mt-0.5 size-3.5 shrink-0 stroke-[2.25] text-blue-500" />
                                        <span>{{ displayPermission(permission) }}</span>
                                    </li>
                                </ul>
                                <p v-if="group.permissions.length > 3" class="mt-2 mb-0 pl-[22px] text-[11px] font-semibold text-blue-600">+{{ group.permissions.length - 3 }} quyền khác</p>
                            </section>
                        </div>
                        <AAlert v-else type="info" show-icon message="Tài khoản chưa có quyền sử dụng tính năng nào." />
                    </div>
                </ATabPane>
                <ATabPane key="security" tab="Bảo mật">
                    <div class="mx-auto w-full max-w-3xl">
                        <div class="mb-5 flex items-start gap-3">
                            <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600"><KeyRound aria-hidden="true" class="size-5 stroke-[1.75]" /></span>
                            <div class="min-w-0"><h3 class="m-0 text-sm font-bold leading-6 text-blue-950">Đặt lại mật khẩu</h3><p class="mt-0.5 mb-0 text-pretty text-xs leading-5 text-slate-500">Mật khẩu mới sẽ thay thế mật khẩu hiện tại và thu hồi toàn bộ phiên đăng nhập cũ.</p></div>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <label class="block"><span class="mb-2 block text-xs font-semibold text-slate-700">Mật khẩu mới</span><AInputPassword v-model:value="resetPassword" size="large" class="!rounded-[10px]" autocomplete="new-password" placeholder="Ít nhất 8 ký tự" /></label>
                            <label class="block"><span class="mb-2 block text-xs font-semibold text-slate-700">Xác nhận mật khẩu mới</span><AInputPassword v-model:value="resetPasswordConfirmation" size="large" class="!rounded-[10px]" autocomplete="new-password" placeholder="Nhập lại mật khẩu mới" @press-enter="requestPasswordReset" /></label>
                        </div>
                        <div class="mt-5 flex justify-end">
                            <AButton type="primary" size="large" :loading="saving" class="w-full rounded-[10px] font-semibold sm:w-auto sm:min-w-48" @click="requestPasswordReset"><template #icon><KeyRound class="size-4" /></template>Đặt mật khẩu mới</AButton>
                        </div>
                    </div>
                </ATabPane>
            </ATabs>
        </template>
    </AModal>

    <AccountPermissionEditorModal
        :open="permissionEditorOpen"
        :account="selected"
        :options="options"
        :role-options="accountEditorRoleOptions"
        :loading="saving"
        @close="permissionEditorOpen = false"
        @save="requestSaveAccess"
    />

    <AModal :open="createOpen" title="Tạo tài khoản" :confirm-loading="saving" :closable="!saving" :keyboard="!saving" :mask-closable="false" :cancel-button-props="{ disabled: saving }" ok-text="Tạo tài khoản" cancel-text="Hủy" @cancel="closeCreateModal" @ok="requestCreate">
        <AForm :model="createForm" :rules="createRules" :disabled="saving" layout="vertical" class="mt-5"><AFormItem label="Họ và tên" name="name" required><AInput v-model:value="createForm.name" size="large" /></AFormItem><AFormItem label="Email" name="email" required><AInput v-model:value="createForm.email" size="large" type="email" /></AFormItem><AFormItem label="Số điện thoại" name="phone"><AInput v-model:value="createForm.phone" size="large" inputmode="tel" autocomplete="tel" /></AFormItem><AFormItem label="Mật khẩu" name="password" required><AInputPassword v-model:value="createForm.password" size="large" /></AFormItem><AFormItem label="Vai trò" name="role" required><ASelect v-model:value="createForm.role" size="large" placeholder="Chọn vai trò" :options="accountCreationRoleOptions" /></AFormItem></AForm>
    </AModal>

    <AdminActionConfirmModal :open="confirmOpen" :title="pendingAction?.title ?? ''" :description="pendingAction?.description ?? ''" :confirm-text="pendingAction?.confirmText" :target-name="pendingAction?.target?.name" :target-email="pendingAction?.target?.email" :require-password="confirmNeedsPassword" :danger="pendingAction?.danger" :loading="saving" :error-message="confirmError" @close="closeSensitiveConfirm" @confirm="executeSensitiveAction" />
    <AdminActionConfirmModal :open="discardOpen" title="Bỏ các thay đổi chưa lưu?" description="Những nội dung bạn vừa chỉnh sửa sẽ không được lưu lại." confirm-text="Bỏ thay đổi" danger @close="discardOpen = false" @confirm="closeEditor" />
</template>

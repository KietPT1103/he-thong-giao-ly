<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import AEmpty from "ant-design-vue/es/empty";
import AInput from "ant-design-vue/es/input";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import ATag from "ant-design-vue/es/tag";
import ATabs, { TabPane as ATabPane } from "ant-design-vue/es/tabs";
import {
    Bell, BookOpen, Check, ChevronRight, Church, ClipboardCheck,
    GraduationCap, Search, ShieldCheck, UserRoundCog, UsersRound,
} from "lucide-vue-next";
import type { Component } from "vue";
import type { AccountOptions } from "../api/accounts";
import {
    displayPermission,
    displayRole,
    permissionGroups,
    roleDescriptions,
} from "../constants/permissionCatalog";
import type { User } from "../types/api";

interface AccessPayload {
    role: string;
    granted_permissions: string[];
    denied_permissions: string[];
}

const props = defineProps<{
    open: boolean;
    account: User | null;
    options: AccountOptions;
    roleOptions: Array<{ label: string; value: string }>;
    loading?: boolean;
}>();
const emit = defineEmits<{
    close: [];
    save: [payload: AccessPayload];
}>();

const activeTab = ref<"role" | "custom">("role");
const search = ref("");
const activeGroup = ref("");
const draft = reactive<AccessPayload>({ role: "", granted_permissions: [], denied_permissions: [] });
const initialSnapshot = ref("");

const groupIcons: Record<string, Component> = {
    system: Church,
    accounts: UserRoundCog,
    catechism: GraduationCap,
    classes: BookOpen,
    family: UsersRound,
    attendance: ClipboardCheck,
    leave: ShieldCheck,
    notifications: Bell,
};

const roleDefaults = computed(() =>
    props.options.roles.find((role) => role.name === draft.role)?.permissions ?? [],
);
const isAdminRole = computed(() => draft.role === "admin");
const availablePermissions = computed(() => new Set(props.options.permissions));
const normalizedSearch = computed(() => search.value.trim().toLocaleLowerCase("vi-VN"));
const groupedPermissions = computed(() => permissionGroups
    .map((group) => ({
        ...group,
        permissions: group.permissions.filter((permission) => {
            if (!availablePermissions.value.has(permission)) return false;
            if (activeTab.value === "role" && !roleDefaults.value.includes(permission)) return false;
            return !normalizedSearch.value
                || displayPermission(permission).toLocaleLowerCase("vi-VN").includes(normalizedSearch.value)
                || group.label.toLocaleLowerCase("vi-VN").includes(normalizedSearch.value);
        }),
    }))
    .filter((group) => group.permissions.length > 0));
const snapshot = computed(() => JSON.stringify({
    role: draft.role,
    granted: [...draft.granted_permissions].sort(),
    denied: [...draft.denied_permissions].sort(),
}));
const dirty = computed(() => snapshot.value !== initialSnapshot.value);
const effectiveCount = computed(() => new Set([
    ...roleDefaults.value,
    ...draft.granted_permissions,
].filter((permission) => !draft.denied_permissions.includes(permission))).size);

function initialize() {
    if (!props.open || !props.account) return;
    draft.role = props.account.roles[0] ?? "";
    draft.granted_permissions = [...(props.account.granted_permissions ?? [])];
    draft.denied_permissions = [...(props.account.denied_permissions ?? [])];
    activeTab.value = "role";
    search.value = "";
    activeGroup.value = "";
    initialSnapshot.value = snapshot.value;
}

function changeRole(role: unknown) {
    if (props.loading || typeof role !== "string") return;
    draft.role = role;
    draft.granted_permissions = [];
    draft.denied_permissions = [];
    if (role === "admin") activeTab.value = "role";
}

function permissionState(permission: string): "default" | "grant" | "deny" {
    if (draft.denied_permissions.includes(permission)) return "deny";
    if (draft.granted_permissions.includes(permission)) return "grant";
    return "default";
}

function isPermissionEffective(permission: string) {
    if (draft.denied_permissions.includes(permission)) return false;
    return draft.granted_permissions.includes(permission) || roleDefaults.value.includes(permission);
}

function setPermission(permission: string, state: "default" | "grant" | "deny") {
    if (props.loading || isAdminRole.value) return;
    draft.granted_permissions = draft.granted_permissions.filter((item) => item !== permission);
    draft.denied_permissions = draft.denied_permissions.filter((item) => item !== permission);
    if (state === "grant") draft.granted_permissions.push(permission);
    if (state === "deny") draft.denied_permissions.push(permission);
}

async function goToGroup(groupId: string) {
    activeGroup.value = groupId;
    await nextTick();
    document.getElementById(`permission-group-${groupId}`)?.scrollIntoView({ behavior: "smooth", block: "start" });
}

function requestClose() {
    if (!props.loading) emit("close");
}

function save() {
    if (!dirty.value || props.loading) return;
    emit("save", {
        role: draft.role,
        granted_permissions: [...draft.granted_permissions],
        denied_permissions: [...draft.denied_permissions],
    });
}

watch(() => [props.open, props.account?.id], initialize);
watch(groupedPermissions, (groups) => {
    if (!groups.some((group) => group.id === activeGroup.value)) activeGroup.value = groups[0]?.id ?? "";
}, { immediate: true });
</script>

<template>
    <AModal
        :open="open"
        :width="1180"
        :footer="null"
        :mask-closable="false"
        :closable="!loading"
        :keyboard="!loading"
        centered
        wrap-class-name="permission-editor-modal"
        @cancel="requestClose"
    >
        <template #title>
            <div class="pr-10">
                <h2 class="m-0 truncate text-lg font-bold tracking-[-0.02em] text-blue-950 sm:text-xl">Sửa phân quyền của {{ account?.name }}</h2>
                <p class="mt-1 mb-0 truncate text-xs font-normal text-slate-500">{{ account?.email }}</p>
            </div>
        </template>

        <div v-if="account" class="flex min-h-0 flex-1 flex-col">
            <ATabs v-model:active-key="activeTab" class="permission-editor-tabs">
                <ATabPane key="role" tab="Phân quyền theo vai trò" />
                <ATabPane key="custom" tab="Quyền tùy chỉnh" :disabled="isAdminRole" />
            </ATabs>

            <div class="permission-toolbar">
                <label class="permission-role-field">
                    <span>Vai trò</span>
                    <ASelect
                        :value="draft.role"
                        size="large"
                        class="min-w-0 flex-1"
                        :options="roleOptions"
                        :disabled="loading"
                        @change="changeRole"
                    />
                </label>
                <div class="permission-role-summary">
                    <b>{{ effectiveCount }} quyền đang sử dụng</b>
                    <span>{{ roleDescriptions[draft.role] ?? "Quyền truy cập theo vai trò đã chọn." }}</span>
                </div>
                <AInput v-model:value="search" allow-clear size="large" class="permission-search" :disabled="loading" placeholder="Tìm quyền hoặc module">
                    <template #prefix><Search aria-hidden="true" class="size-4 text-slate-400" /></template>
                </AInput>
            </div>

            <AAlert
                v-if="isAdminRole"
                type="info"
                show-icon
                message="Quản trị viên luôn có toàn bộ quyền trong hệ thống."
                class="mx-4 mt-3 sm:mx-6"
            />
            <div v-else-if="activeTab === 'custom'" class="permission-legend">
                <span><i class="is-default" />Theo vai trò</span>
                <span><i class="is-granted" />Cho phép riêng</span>
                <span><i class="is-denied" />Chặn riêng</span>
            </div>

            <div class="permission-workspace">
                <main class="permission-group-list" aria-live="polite">
                    <AEmpty v-if="!groupedPermissions.length" :image="AEmpty.PRESENTED_IMAGE_SIMPLE" description="Không tìm thấy quyền phù hợp." />
                    <section
                        v-for="group in groupedPermissions"
                        :id="`permission-group-${group.id}`"
                        :key="group.id"
                        class="permission-group-card scroll-mt-3"
                    >
                        <header>
                            <span class="permission-group-icon"><component :is="groupIcons[group.id]" aria-hidden="true" /></span>
                            <div><h3>{{ group.label }}</h3><p>{{ group.description }}</p></div>
                            <ATag class="!m-0 shrink-0" color="blue">{{ group.permissions.length }} quyền</ATag>
                        </header>
                        <ul>
                            <li v-for="permission in group.permissions" :key="permission">
                                <div class="permission-name">
                                    <span class="permission-check" :class="isPermissionEffective(permission) ? 'is-active' : ''"><Check aria-hidden="true" /></span>
                                    <div><b>{{ displayPermission(permission) }}</b><small v-if="activeTab === 'custom'">{{ roleDefaults.includes(permission) ? 'Được cấp sẵn theo vai trò' : 'Không có trong vai trò mặc định' }}</small></div>
                                </div>
                                <div v-if="activeTab === 'custom'" class="permission-state-control" :aria-label="`Thiết lập ${displayPermission(permission)}`">
                                    <button type="button" :disabled="loading" :class="{ active: permissionState(permission) === 'default' }" @click="setPermission(permission, 'default')">Theo vai trò</button>
                                    <button type="button" class="grant" :disabled="loading" :class="{ active: permissionState(permission) === 'grant' }" @click="setPermission(permission, 'grant')">Cho phép</button>
                                    <button type="button" class="deny" :disabled="loading" :class="{ active: permissionState(permission) === 'deny' }" @click="setPermission(permission, 'deny')">Chặn</button>
                                </div>
                            </li>
                        </ul>
                    </section>
                </main>

                <nav v-if="groupedPermissions.length > 1" class="permission-group-nav" aria-label="Nhóm quyền">
                    <button v-for="group in groupedPermissions" :key="group.id" type="button" :disabled="loading" :class="{ active: activeGroup === group.id }" @click="goToGroup(group.id)">
                        <span>{{ group.label }}</span><ChevronRight aria-hidden="true" />
                    </button>
                </nav>
            </div>

            <footer class="permission-editor-footer">
                <p><ShieldCheck aria-hidden="true" />Thay đổi quyền sẽ thu hồi các phiên đăng nhập khác của tài khoản.</p>
                <div><AButton size="large" :disabled="loading" @click="requestClose">Bỏ qua</AButton><AButton type="primary" size="large" :loading="loading" :disabled="!dirty" @click="save">Lưu phân quyền</AButton></div>
            </footer>
        </div>
    </AModal>
</template>

<style scoped>
:global(.permission-editor-modal.ant-modal-centered){display:flex;align-items:center;justify-content:center;padding:16px}
:global(.permission-editor-modal.ant-modal-centered::before){display:none}
:global(.permission-editor-modal .ant-modal){top:auto;margin:0;max-width:calc(100vw - 2rem);padding-bottom:0}
:global(.permission-editor-modal .ant-modal-content){display:flex;height:min(780px,calc(100dvh - 2rem));overflow:hidden;flex-direction:column;border:1px solid #dbe3ee;border-radius:16px;box-shadow:0 28px 80px rgba(4,15,35,.26)}
:global(.permission-editor-modal .ant-modal-header){margin:0;padding:20px 24px 16px;border-bottom:1px solid #e2e8f0}
:global(.permission-editor-modal .ant-modal-body){display:flex;min-height:0;flex:1;overflow:hidden}
:global(.permission-editor-modal .ant-modal-close){top:14px;inset-inline-end:14px;width:40px;height:40px;border-radius:10px}
:global(.permission-editor-modal .ant-modal-close:hover){background:#f1f5f9;color:#172554}
.permission-editor-tabs{flex:none;padding:0 24px}.permission-editor-tabs :deep(.ant-tabs-nav){margin:0}.permission-editor-tabs :deep(.ant-tabs-tab){min-height:52px;font-weight:650}
.permission-toolbar{display:grid;grid-template-columns:minmax(15rem,.8fr) minmax(15rem,1fr) minmax(15rem,.8fr);align-items:end;gap:16px;padding:14px 24px;border-bottom:1px solid #e2e8f0;background:#f8fafc}
.permission-role-field{display:flex;min-width:0;align-items:center;gap:10px}.permission-role-field>span{flex:none;font-size:12px;font-weight:700;color:#475569}.permission-role-summary{min-width:0}.permission-role-summary b,.permission-role-summary span{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.permission-role-summary b{font-size:12px;color:#172554}.permission-role-summary span{margin-top:3px;font-size:11px;color:#64748b}.permission-search{width:100%;border-radius:10px}
.permission-legend{display:flex;flex-wrap:wrap;gap:14px;padding:10px 24px;border-bottom:1px solid #e2e8f0;background:#fff;font-size:11px;color:#64748b}.permission-legend span{display:inline-flex;align-items:center;gap:6px}.permission-legend i{width:8px;height:8px;border-radius:50%;background:#cbd5e1}.permission-legend .is-granted{background:#2563eb}.permission-legend .is-denied{background:#e11d48}
.permission-workspace{display:grid;min-height:0;flex:1;grid-template-columns:minmax(0,1fr) 210px;gap:16px;overflow:hidden;padding:16px 24px;background:#f1f5f9}.permission-group-list{min-height:0;overflow-y:auto;overscroll-behavior:contain;padding-right:4px;scrollbar-width:thin}.permission-group-card{overflow:hidden;margin-bottom:12px;border:1px solid #dbe3ee;border-radius:14px;background:#fff}.permission-group-card:last-child{margin-bottom:0}.permission-group-card header{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:12px;padding:14px 16px;border-bottom:1px solid #e2e8f0}.permission-group-icon{display:grid;width:38px;height:38px;place-items:center;border-radius:10px;background:#eff6ff;color:#2563eb}.permission-group-icon :deep(svg){width:18px;height:18px;stroke-width:1.8}.permission-group-card h3,.permission-group-card p{margin:0}.permission-group-card h3{font-size:13px;font-weight:750;color:#172554}.permission-group-card p{margin-top:2px;font-size:11px;line-height:1.5;color:#64748b}.permission-group-card ul{margin:0;padding:0;list-style:none}.permission-group-card li{display:flex;min-height:56px;align-items:center;justify-content:space-between;gap:16px;padding:10px 16px;border-top:1px solid #edf0f4}.permission-group-card li:first-child{border-top:0}.permission-name{display:flex;min-width:0;align-items:center;gap:10px}.permission-name b,.permission-name small{display:block}.permission-name b{font-size:12px;font-weight:650;color:#334155}.permission-name small{margin-top:2px;font-size:10px;color:#94a3b8}.permission-check{display:grid;width:24px;height:24px;flex:none;place-items:center;border:1px solid #cbd5e1;border-radius:7px;background:#fff;color:transparent}.permission-check.is-active{border-color:#93c5fd;background:#eff6ff;color:#2563eb}.permission-check :deep(svg){width:14px;height:14px;stroke-width:2.5}
.permission-state-control{display:grid;flex:none;grid-template-columns:repeat(3,1fr);overflow:hidden;border:1px solid #dbe3ee;border-radius:9px;background:#fff}.permission-state-control button{min-height:34px;border:0;border-left:1px solid #dbe3ee;background:#fff;padding:0 10px;color:#64748b;font-size:10px;font-weight:650;transition:background-color .16s ease,color .16s ease}.permission-state-control button:first-child{border-left:0}.permission-state-control button:hover{background:#f8fafc;color:#172554}.permission-state-control button.active{background:#e2e8f0;color:#172554}.permission-state-control button.grant.active{background:#2563eb;color:#fff}.permission-state-control button.deny.active{background:#fff1f2;color:#be123c}
.permission-group-nav{align-self:start;overflow:hidden;border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:6px}.permission-group-nav button{display:flex;width:100%;min-height:42px;align-items:center;justify-content:space-between;gap:8px;border:0;border-radius:9px;background:transparent;padding:8px 10px;color:#475569;font-size:11px;font-weight:600;text-align:left;transition:background-color .16s ease,color .16s ease}.permission-group-nav button:hover{background:#f8fafc;color:#172554}.permission-group-nav button.active{background:#eff6ff;color:#1d4ed8}.permission-group-nav :deep(svg){width:14px;height:14px}
.permission-editor-footer{display:flex;flex:none;align-items:center;justify-content:space-between;gap:16px;padding:14px 24px;border-top:1px solid #e2e8f0;background:#fff}.permission-editor-footer p{display:flex;max-width:55ch;align-items:center;gap:8px;margin:0;color:#64748b;font-size:11px;line-height:1.5}.permission-editor-footer p :deep(svg){width:16px;height:16px;flex:none;color:#2563eb}.permission-editor-footer>div{display:flex;gap:8px}.permission-editor-footer :deep(.ant-btn){min-width:112px;border-radius:10px;font-weight:650}
@media(max-width:900px){.permission-toolbar{grid-template-columns:1fr 1fr}.permission-role-summary{display:none}.permission-search{grid-column:2}.permission-workspace{grid-template-columns:minmax(0,1fr)}.permission-group-nav{display:none}}
@media(max-width:639px){:global(.permission-editor-modal.ant-modal-centered){padding:6px}:global(.permission-editor-modal .ant-modal){width:100%!important;max-width:100%;margin:0}:global(.permission-editor-modal .ant-modal-content){height:calc(100dvh - 12px);border-radius:14px}:global(.permission-editor-modal .ant-modal-header){padding:16px}:global(.permission-editor-modal .ant-modal-close){top:10px;inset-inline-end:8px}.permission-editor-tabs{padding:0 16px}.permission-editor-tabs :deep(.ant-tabs-nav-list){width:100%}.permission-editor-tabs :deep(.ant-tabs-tab){flex:1;justify-content:center;margin:0!important;padding-inline:0;font-size:12px}.permission-editor-tabs :deep(.ant-tabs-nav-operations){display:none!important}.permission-toolbar{grid-template-columns:minmax(0,1fr);gap:10px;padding:12px 16px}.permission-role-field{align-items:flex-start;flex-direction:column;gap:6px}.permission-role-field :deep(.ant-select){width:100%}.permission-search{grid-column:auto}.permission-legend{gap:8px 12px;padding:9px 16px}.permission-workspace{padding:10px}.permission-group-card header{grid-template-columns:auto minmax(0,1fr);padding:12px}.permission-group-card header :deep(.ant-tag){display:none}.permission-group-card li{align-items:stretch;flex-direction:column;gap:9px;padding:12px}.permission-state-control{width:100%}.permission-state-control button{min-height:38px}.permission-editor-footer{align-items:stretch;flex-direction:column;padding:10px 12px}.permission-editor-footer p{display:none}.permission-editor-footer>div{display:grid;grid-template-columns:1fr 1fr}.permission-editor-footer :deep(.ant-btn){width:100%;min-width:0}}
@media(prefers-reduced-motion:reduce){.permission-state-control button,.permission-group-nav button{transition:none}}
</style>

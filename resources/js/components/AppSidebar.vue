<script setup lang="ts">
import { computed, ref, watch, type Component } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useAuthStore } from "../stores/authStore";
import UserAvatar from "./UserAvatar.vue";
import {
    Bell, BookOpen, CalendarDays, CheckSquare, ChevronDown,
    Church, CircleHelp, ClipboardList, Gift, GraduationCap,
    Home, LogOut, QrCode, Settings, UserRound, Users,
} from "lucide-vue-next";

type NavItem = { to: string; label: string; icon: Component; permission?: string };
const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ close: [] }>();
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const userMenuOpen = ref(false);
const role = computed(() => ["admin", "teacher", "parent", "child"].find((item) => auth.roles.includes(item)) ?? "");
const roleLabel = computed(() => ({
    admin: "Không gian quản trị",
    teacher: "Không gian giáo lý viên",
    parent: "Không gian phụ huynh",
    child: "Không gian thiếu nhi",
}[role.value] ?? "Hệ thống giáo lý"));
const navigationByRole: Record<string, NavItem[]> = {
    admin: [
        { to: "/admin", label: "Tổng quan", icon: Home },
        { to: "/admin/accounts", label: "Quản lý tài khoản", icon: UserRound, permission: "manage-users" },
        { to: "/admin/parishes", label: "Giáo xứ", icon: Church, permission: "manage-system-settings" },
        { to: "/admin/teachers", label: "Giáo lý viên", icon: GraduationCap, permission: "manage-users" },
        { to: "/admin/parents", label: "Phụ huynh", icon: Users, permission: "view-parents" },
        { to: "/admin/children", label: "Thiếu nhi", icon: UserRound, permission: "view-children" },
        { to: "/admin/classes", label: "Lớp học", icon: BookOpen, permission: "view-classes" },
        { to: "/admin/announcements", label: "Thông báo", icon: Bell, permission: "manage-announcements" },
    ],
    teacher: [
        { to: "/teacher", label: "Tổng quan", icon: Home },
        { to: "/teacher/classes", label: "Lớp của tôi", icon: GraduationCap, permission: "view-classes" },
        { to: "/teacher/children", label: "Thiếu nhi", icon: Users, permission: "view-children" },
        { to: "/teacher/schedule", label: "Lịch dạy", icon: CalendarDays, permission: "view-classes" },
        { to: "/teacher/attendance", label: "Điểm danh lớp", icon: CheckSquare, permission: "view-attendance" },
        { to: "/teacher/assignments", label: "Bài tập", icon: BookOpen, permission: "view-classes" },
        { to: "/teacher/submissions", label: "Bài cần chấm", icon: ClipboardList, permission: "view-classes" },
        { to: "/teacher/announcements", label: "Thông báo lớp", icon: Bell, permission: "view-notifications" },
    ],
    parent: [
        { to: "/parent", label: "Tổng quan", icon: Home },
        { to: "/parent/children", label: "Các con của tôi", icon: Users },
        { to: "/parent/schedule", label: "Lịch học", icon: CalendarDays },
        { to: "/parent/mass-attendance", label: "Lịch sử tham dự", icon: Church },
        { to: "/parent/assignments", label: "Bài tập", icon: BookOpen },
        { to: "/parent/points", label: "Điểm thưởng", icon: Gift },
        { to: "/parent/notifications", label: "Thông báo", icon: Bell, permission: "view-notifications" },
    ],
    child: [
        { to: "/child", label: "Tổng quan", icon: Home },
        { to: "/child/schedule", label: "Lịch học", icon: CalendarDays },
        { to: "/child/mass", label: "Thánh lễ", icon: Church },
        { to: "/child/assignments", label: "Bài tập", icon: BookOpen },
        { to: "/child/points", label: "Điểm thưởng", icon: Gift },
        { to: "/child/my-qr", label: "Quét QR điểm danh", icon: QrCode, permission: "check-in-attendance-qr" },
    ],
};
const navigation = computed(() => (navigationByRole[role.value] ?? []).filter((item) => !item.permission || auth.hasPermission(item.permission)));
watch(() => route.fullPath, () => { userMenuOpen.value = false; });
async function signOut() { await auth.logout(); await router.push("/login"); }
</script>

<template>
    <button v-if="open" class="fixed inset-0 z-30 bg-slate-950/55 backdrop-blur-[2px] lg:hidden" aria-label="Đóng menu điều hướng" @click="emit('close')" />
    <aside id="app-sidebar" :class="open ? 'translate-x-0' : '-translate-x-full'" class="sidebar fixed inset-y-0 left-0 z-40 flex w-[min(19rem,calc(100vw-1rem))] flex-col overflow-hidden px-3 pb-[max(1rem,env(safe-area-inset-bottom))] pt-[max(1rem,env(safe-area-inset-top))] text-white transition-transform duration-300 sm:px-4 lg:w-70 lg:translate-x-0 lg:py-5">
        <div class="sidebar-brand flex min-h-13 items-center gap-3 px-1">
            <div class="sidebar-brand-mark size-11 shrink-0 overflow-hidden rounded-xl">
                <img :src="'/favicon-htgl.png'" alt="" width="44" height="44" aria-hidden="true" />
            </div>
            <div class="sidebar-brand-copy min-w-0 flex-1"><p class="truncate text-sm font-bold">Hành Trang Đức Tin</p><p class="truncate text-xs">Giáo phận Cần Thơ</p></div>
        </div>
        <nav class="sidebar-nav min-h-0 flex-1 space-y-1 overflow-y-auto py-3" aria-label="Điều hướng chính">
            <RouterLink v-for="item in navigation" :key="item.to" :to="item.to" class="sidebar-link group flex min-h-12 items-center gap-3 px-3 text-[13px] font-medium" active-class="sidebar-active" @click="emit('close')">
                <component :is="item.icon" class="size-5 shrink-0" /><span class="truncate">{{ item.label }}</span>
            </RouterLink>
        </nav>
        <div class="sidebar-profile relative mt-4 pt-4">
            <button class="sidebar-profile-trigger flex w-full items-center gap-3 rounded-xl p-2 text-left" aria-controls="sidebar-user-menu" :aria-expanded="userMenuOpen" @click="userMenuOpen = !userMenuOpen">
                <UserAvatar :name="auth.user?.name ?? ''" :avatar-url="auth.user?.avatar_url" />
                <span class="min-w-0 flex-1"><b class="block truncate text-xs">{{ auth.user?.name }}</b><small class="sidebar-role-label block truncate text-[10px]">{{ roleLabel }}</small></span>
                <ChevronDown class="sidebar-profile-chevron size-4 shrink-0 transition-transform duration-300 ease-out" :class="userMenuOpen ? 'rotate-180' : 'rotate-0'" />
            </button>
            <Transition name="profile-menu">
                <div v-if="userMenuOpen" id="sidebar-user-menu" class="profile-popover absolute z-50 max-w-[calc(100vw-2rem)] rounded-xl p-2">
                    <RouterLink to="/account" class="menu-action"><UserRound />Tài khoản</RouterLink>
                    <button class="menu-action" disabled><Settings />Cài đặt</button>
                    <button class="menu-action" disabled><CircleHelp />Trợ giúp</button>
                    <div class="sidebar-menu-divider my-1" />
                    <button class="menu-action is-danger" @click="signOut"><LogOut />Đăng xuất</button>
                </div>
            </Transition>
        </div>
    </aside>
</template>

<style scoped>
.sidebar{border-right:1px solid rgba(250,249,245,.08);background:#081a3e;box-shadow:2px 0 2px rgba(15,23,42,.01);transition-timing-function:cubic-bezier(.16,1,.3,1)}
.sidebar-brand{height:3.5rem;margin-bottom:.35rem}
.sidebar-brand-mark{align-self:center;border:0;background:transparent;box-shadow:none}
.sidebar-brand-mark img{display:block;width:100%;height:100%;object-fit:cover}
.sidebar-brand-copy{display:flex;height:2.75rem;flex-direction:column;justify-content:center;gap:.2rem}
.sidebar-brand-copy p{margin:0;line-height:1.2}
.sidebar-brand-copy p:first-child{color:#faf9f5;letter-spacing:-.015em}
.sidebar-brand-copy p:last-child{color:#aebed8}
.sidebar-nav{overflow-x:hidden;overscroll-behavior:contain;scrollbar-color:rgba(250,249,245,.18) transparent;scrollbar-width:thin}
.sidebar-link{max-width:100%;border:1px solid transparent;border-radius:.625rem;color:#cbd8ed;transition:background-color .18s ease,color .18s ease}
.sidebar-link:hover{background:rgba(250,249,245,.04);color:#faf9f5}
.sidebar-link :deep(svg){color:#9fb4d4;transition:color .18s ease,transform .18s ease}
.sidebar-link:hover :deep(svg){color:#faf9f5;transform:scale(1.04)}
.sidebar-active{border-color:#faf9f5;background:#f0eee6;color:#0b214d;box-shadow:0 2px 2px rgba(0,0,0,.01)}
.sidebar-active:hover{border-color:#faf9f5;background:#faf9f5;color:#0b214d;transform:none}
.sidebar-active :deep(svg),.sidebar-active:hover :deep(svg){color:#185fce;transform:none}
.sidebar-profile{border-top:1px solid rgba(250,249,245,.1)}
.sidebar-profile-trigger{border:1px solid transparent;color:#faf9f5;transition:background-color .18s ease,border-color .18s ease}
.sidebar-profile-trigger:hover{border-color:rgba(250,249,245,.09);background:rgba(250,249,245,.06)}
.sidebar-role-label,.sidebar-profile-chevron{color:#9fb4d4}
.menu-action{display:flex;width:100%;min-height:2.5rem;align-items:center;gap:.65rem;border-radius:.5rem;padding:.6rem .7rem;color:#292927;font-size:.75rem;text-align:left;transition:background-color .16s ease,color .16s ease}.menu-action:hover:not(:disabled){background:#f0eee6;color:#141413}.menu-action:disabled{color:#87867f;opacity:.62}.menu-action.is-danger{color:#b44f35}.menu-action.is-danger:hover:not(:disabled){background:#f8e9e3;color:#8f3824}.menu-action :deep(svg){width:1rem;height:1rem}
.sidebar-menu-divider{border-top:1px solid #e3dacc}
.profile-popover{bottom:calc(100% + .75rem);left:0;width:100%;transform-origin:bottom center;border:1px solid #e3dacc;background:#faf9f5;box-shadow:0 18px 44px rgba(4,15,35,.24)}
.profile-menu-enter-active,.profile-menu-leave-active{transition:opacity .18s ease,transform .26s cubic-bezier(.16,1,.3,1)}
.profile-menu-enter-from,.profile-menu-leave-to{opacity:0;transform:translateY(.5rem) scale(.97)}
.profile-menu-enter-to,.profile-menu-leave-from{opacity:1;transform:translateY(0) scale(1)}
@media(prefers-reduced-motion:reduce){.sidebar,.sidebar-link,.sidebar-link :deep(svg),.profile-menu-enter-active,.profile-menu-leave-active{transition:none}.sidebar-link:hover :deep(svg),.profile-menu-enter-from,.profile-menu-leave-to{transform:none}}
</style>

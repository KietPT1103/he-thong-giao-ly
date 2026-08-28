<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { Bell, Menu } from "lucide-vue-next";
import AppSidebar from "./components/AppSidebar.vue";
import { useAuthStore } from "./stores/authStore";
import { Toaster } from "./components/ui/sonner";
import "vue-sonner/style.css";

const route = useRoute();
const auth = useAuthStore();
const open = ref(false);
const publicPage = computed(() => Boolean(route.meta.public));
const roleLabel = computed(() => ({ admin: "Quản trị hệ thống", teacher: "Giáo lý viên", parent: "Phụ huynh", child: "Thiếu nhi" }[auth.roles[0] ?? ""] ?? "Hệ thống giáo lý"));
const fullWidthContent = computed(() => route.path === "/teacher/attendance" || route.path.startsWith("/teacher/attendance/"));

function closeNavigation() {
    open.value = false;
}

function handleEscape(event: KeyboardEvent) {
    if (event.key === "Escape") closeNavigation();
}

watch(() => route.fullPath, closeNavigation);
watch(open, (isOpen) => {
    document.body.classList.toggle("navigation-open", isOpen);
});
onMounted(() => window.addEventListener("keydown", handleEscape));
onBeforeUnmount(() => {
    window.removeEventListener("keydown", handleEscape);
    document.body.classList.remove("navigation-open");
});
</script>

<template>
    <Toaster position="top-right" rich-colors close-button />
    <RouterView v-if="publicPage" />
    <div v-else-if="auth.isLoading" class="grid min-h-screen place-items-center text-primary-700">Đang kiểm tra phiên đăng nhập…</div>
    <div v-else class="min-h-screen bg-surface-soft">
        <AppSidebar :open="open" @close="closeNavigation" />
        <div class="min-w-0 transition-[padding] duration-300 lg:pl-70">
            <header class="app-shell-header sticky top-0 z-20 min-h-16 border-b border-slate-200/80 backdrop-blur sm:min-h-18">
                <div class="app-shell-header-inner mx-auto w-full px-3 sm:px-5 lg:px-7">
                    <button class="header-menu-button grid size-11 shrink-0 place-items-center rounded-xl text-slate-600 transition-colors hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 lg:hidden" aria-label="Mở menu điều hướng" aria-controls="app-sidebar" :aria-expanded="open" @click="open = true"><Menu class="size-5" /></button>
                    <div class="header-title min-w-0"><h1 class="truncate text-sm font-semibold text-ink sm:text-base">{{ route.meta.title }}</h1><p class="truncate text-[11px] text-slate-500 sm:text-xs">{{ roleLabel }}</p></div>
                    <button disabled class="header-notification grid size-10 shrink-0 place-items-center rounded-xl border border-slate-200 bg-slate-50 text-slate-400" aria-label="Thông báo đang được hoàn thiện" title="Thông báo đang được hoàn thiện"><Bell class="size-5" /></button>
                </div>
            </header>
            <main class="app-content mx-auto w-full px-3 py-4 pb-24 sm:px-5 sm:py-6 lg:px-7 lg:py-7" :class="{ 'app-content--full': fullWidthContent }"><RouterView /></main>
        </div>
    </div>
</template>

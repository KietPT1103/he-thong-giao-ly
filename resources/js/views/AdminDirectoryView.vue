<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import {
    Search,
    RefreshCw,
    Database,
    ChevronLeft,
    ChevronRight,
} from "lucide-vue-next";
import {
    getAdminDirectory,
    type AdminListItem,
    type AdminListMeta,
} from "../api/admin";

const route = useRoute(),
    items = ref<AdminListItem[]>([]),
    meta = ref<AdminListMeta>({
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: 0,
    });
const search = ref(""),
    loading = ref(true),
    error = ref("");
const module = computed(() => String(route.meta.module || ""));
const descriptions: Record<string, string> = {
    parishes: "Theo dõi các giáo xứ và quy mô thiếu nhi trong hệ thống.",
    teachers: "Danh sách giáo lý viên cùng giáo xứ và lớp được phân công.",
    parents: "Tài khoản phụ huynh và số thiếu nhi đang liên kết.",
    children: "Hồ sơ thiếu nhi, lớp học và trạng thái hiện tại.",
    classes: "Các lớp giáo lý theo khối, niên khóa và phòng học.",
    announcements: "Thông báo đã đăng và phạm vi người nhận.",
};
async function load(page = 1) {
    loading.value = true;
    error.value = "";
    try {
        const response = await getAdminDirectory(module.value, {
            search: search.value || undefined,
            page,
        });
        items.value = response.data.data;
        meta.value = response.data.meta as unknown as AdminListMeta;
    } catch {
        error.value = "Không thể tải dữ liệu. Vui lòng thử lại.";
    } finally {
        loading.value = false;
    }
}
function submit() {
    load(1);
}
watch(
    module,
    () => {
        search.value = "";
        load(1);
    },
    { immediate: true },
);
</script>
<template>
    <div class="space-y-6">
        <header class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold text-ink">
                    {{ route.meta.title }}
                </h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ descriptions[module] }}
                </p>
            </div>
            <div
                class="rounded-xl bg-primary-50 px-4 py-2 text-sm font-semibold text-primary-700"
            >
                {{ meta.total.toLocaleString("vi-VN") }} bản ghi
            </div>
        </header>
        <form
            class="flex flex-wrap gap-3 rounded-2xl border border-slate-200 bg-white p-4"
            @submit.prevent="submit"
        >
            <label
                class="flex min-h-11 min-w-0 flex-1 items-center gap-2 rounded-xl bg-slate-50 px-3 sm:min-w-72"
                ><Search class="size-4 text-slate-400" /><input
                    v-model="search"
                    class="w-full bg-transparent text-sm outline-none"
                    :placeholder="`Tìm trong ${String(route.meta.title).toLowerCase()}...`" /></label
            ><button
                class="min-h-11 rounded-xl bg-primary-600 px-5 text-sm font-semibold text-white"
            >
                Tìm kiếm</button
            ><button
                type="button"
                title="Tải lại"
                class="grid size-11 place-items-center rounded-xl border border-slate-200 text-slate-600"
                @click="load(meta.current_page)"
            >
                <RefreshCw class="size-4" />
            </button>
        </form>

        <div v-if="loading" class="space-y-3">
            <div
                v-for="i in 6"
                :key="i"
                class="h-20 animate-pulse rounded-2xl bg-slate-200"
            />
        </div>
        <section
            v-else-if="error"
            class="rounded-2xl border border-rose-200 bg-white p-10 text-center"
        >
            <p class="text-rose-700">{{ error }}</p>
            <button
                class="mt-4 rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white"
                @click="load(1)"
            >
                Thử lại
            </button>
        </section>
        <section
            v-else-if="!items.length"
            class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center"
        >
            <Database class="mx-auto size-10 text-slate-400" />
            <h3 class="mt-4 font-semibold text-ink">Chưa có dữ liệu</h3>
            <p class="mt-1 text-sm text-slate-500">
                Không tìm thấy bản ghi phù hợp với điều kiện hiện tại.
            </p>
        </section>
        <section
            v-else
            class="overflow-hidden rounded-2xl border border-slate-200 bg-white"
        >
            <div
                class="hidden grid-cols-[minmax(180px,1.25fr)_minmax(180px,1fr)_minmax(220px,1.2fr)_130px] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 md:grid"
            >
                <span>Thông tin</span><span>Liên hệ/Mã</span
                ><span>Phân loại</span><span>Trạng thái</span>
            </div>
            <article
                v-for="item in items"
                :key="item.id"
                class="grid gap-3 border-b border-slate-100 p-5 last:border-0 md:grid-cols-[minmax(180px,1.25fr)_minmax(180px,1fr)_minmax(220px,1.2fr)_130px] md:items-center md:gap-4"
            >
                <div class="min-w-0">
                    <p class="truncate font-semibold text-ink">
                        {{ item.name }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">#{{ item.id }}</p>
                </div>
                <div class="min-w-0">
                    <p class="truncate text-sm text-slate-700">
                        {{ item.secondary }}
                    </p>
                    <p class="mt-1 truncate text-xs text-slate-500">
                        {{ item.code }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <span
                        v-for="detail in item.details"
                        :key="detail"
                        class="rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-600"
                        >{{ detail }}</span
                    >
                </div>
                <span
                    class="w-fit rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"
                    >{{ item.status }}</span
                >
            </article>
        </section>

        <footer
            v-if="meta.last_page > 1"
            class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-3"
        >
            <p class="text-sm text-slate-500">
                Trang {{ meta.current_page }}/{{ meta.last_page }}
            </p>
            <div class="flex gap-2">
                <button
                    :disabled="meta.current_page <= 1"
                    class="grid size-10 place-items-center rounded-xl border border-slate-200 disabled:opacity-40"
                    @click="load(meta.current_page - 1)"
                >
                    <ChevronLeft class="size-4" /></button
                ><button
                    :disabled="meta.current_page >= meta.last_page"
                    class="grid size-10 place-items-center rounded-xl border border-slate-200 disabled:opacity-40"
                    @click="load(meta.current_page + 1)"
                >
                    <ChevronRight class="size-4" />
                </button>
            </div>
        </footer>
    </div>
</template>

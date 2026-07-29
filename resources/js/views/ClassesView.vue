<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { Search, Users, GraduationCap } from "lucide-vue-next";
import { getTeacherClasses } from "../api/teacher";
import type { CatechismClass } from "../types/api";
const classes = ref<CatechismClass[]>([]),
    query = ref(""),
    loading = ref(true),
    error = ref("");
const filtered = computed(() =>
    classes.value.filter((item) =>
        `${item.name} ${item.code} ${item.level?.name}`
            .toLowerCase()
            .includes(query.value.toLowerCase()),
    ),
);
async function load() {
    loading.value = true;
    error.value = "";
    try {
        classes.value = (await getTeacherClasses()).data.data;
    } catch {
        error.value = "Không thể tải danh sách lớp.";
    } finally {
        loading.value = false;
    }
}
onMounted(load);
</script>
<template>
    <div>
        <div>
            <h2 class="text-xl font-bold text-ink">Lớp của tôi</h2>
            <p class="mt-1 text-sm text-slate-500">
                Các lớp được phân công trong niên khóa hiện tại.
            </p>
        </div>
        <div
            class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white"
        >
            <div class="border-b border-slate-100 p-4">
                <label
                    class="flex w-full max-w-sm items-center gap-2 rounded-xl bg-slate-50 px-3 py-2 text-slate-400"
                    ><Search class="size-4" /><input
                        v-model="query"
                        aria-label="Tìm lớp"
                        class="w-full bg-transparent text-sm text-slate-700 outline-none"
                        placeholder="Tìm lớp học..."
                /></label>
            </div>
            <div v-if="loading" class="space-y-3 p-5">
                <div
                    v-for="i in 3"
                    :key="i"
                    class="h-20 animate-pulse rounded-xl bg-slate-100"
                />
            </div>
            <div v-else-if="error" class="p-8 text-center text-rose-700">
                {{ error }}
                <button
                    class="ml-2 font-semibold text-primary-600"
                    @click="load"
                >
                    Thử lại
                </button>
            </div>
            <div v-else-if="!filtered.length" class="p-10 text-center">
                <GraduationCap class="mx-auto size-9 text-slate-400" />
                <p class="mt-3 font-medium text-ink">
                    Không tìm thấy lớp phù hợp
                </p>
            </div>
            <div v-else class="divide-y divide-slate-100">
                <article
                    v-for="item in filtered"
                    :key="item.id"
                    class="flex flex-col items-stretch gap-3 p-4 sm:flex-row sm:items-center sm:gap-4 sm:p-5"
                >
                    <span
                        class="grid size-12 place-items-center rounded-xl bg-primary-100 font-bold text-primary-700"
                        >{{ item.level?.name?.slice(0, 1) }}</span
                    >
                    <div class="min-w-42 flex-1">
                        <h3 class="font-semibold text-ink">{{ item.name }}</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ item.level?.name }} ·
                            {{ item.classroom?.name || "Chưa xếp phòng" }}
                        </p>
                    </div>
                    <p class="flex items-center gap-1.5 text-sm text-slate-500">
                        <Users class="size-4" />{{
                            item.children_count || 0
                        }}
                        thiếu nhi
                    </p>
                    <div class="flex flex-wrap gap-3 text-sm">
                        <RouterLink
                            :to="`/teacher/classes/${item.id}`"
                            class="font-medium text-primary-600"
                            >Xem lớp</RouterLink
                        ><RouterLink
                            :to="`/teacher/attendance?class=${item.id}`"
                            class="font-medium text-primary-600"
                            >Điểm danh</RouterLink
                        >
                    </div>
                </article>
            </div>
        </div>
    </div>
</template>

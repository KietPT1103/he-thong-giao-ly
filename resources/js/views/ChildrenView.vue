<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AInput from "ant-design-vue/es/input";
import ATag from "ant-design-vue/es/tag";
import { BookOpen, Search, Users } from "lucide-vue-next";
import { getClassChildren, getTeacherClasses } from "../api/teacher";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";
import type { Child } from "../types/api";
type Row = Child & { className: string };
const rows = ref<Row[]>([]),
    query = ref(""),
    loading = ref(true),
    error = ref("");
const filtered = computed(() =>
    rows.value.filter((x) =>
        `${x.full_name} ${x.saint_name} ${x.code}`
            .toLowerCase()
            .includes(query.value.toLowerCase()),
    ),
);
async function load() {
    loading.value = true;
    error.value = "";
    try {
        const classes = (await getTeacherClasses()).data.data;
        const pages = await Promise.all(
            classes.map((c) => getClassChildren(c.id)),
        );
        rows.value = pages.flatMap((r, i) =>
            r.data.data.map((child) => ({
                ...child,
                className: classes[i].name,
            })),
        );
    } catch {
        error.value = "Không thể tải danh sách thiếu nhi.";
    } finally {
        loading.value = false;
    }
}
onMounted(load);
</script>
<template>
    <section class="teacher-page-stack">
        <TeacherPageHeader title="Thiếu nhi" description="Danh sách thiếu nhi thuộc các lớp bạn đang phụ trách." :count="`${filtered.length} thiếu nhi`" />
        <ACard :bordered="false" class="teacher-card">
            <div class="teacher-toolbar">
                <div class="teacher-toolbar-main">
                    <AInput v-model:value="query" allow-clear size="large" aria-label="Tìm thiếu nhi" placeholder="Tìm theo tên, mã hoặc tên thánh">
                        <template #prefix><Search class="size-4 text-slate-400" /></template>
                    </AInput>
                </div>
            </div>
            <div v-if="loading" class="space-y-3 p-5" aria-busy="true" aria-label="Đang tải danh sách thiếu nhi">
                <div v-for="i in 5" :key="i" class="h-16 animate-pulse rounded-xl bg-slate-100" />
            </div>
            <div v-else-if="error" class="p-4">
                <AAlert type="error" show-icon :message="error"><template #action><AButton size="small" @click="load">Thử lại</AButton></template></AAlert>
            </div>
            <div v-else-if="!filtered.length" class="teacher-empty-state">
                <Users class="size-10 text-slate-400" />
                <h3>Không có thiếu nhi phù hợp</h3>
                <p>Thử thay đổi từ khóa hoặc kiểm tra lại phạm vi lớp được phân công.</p>
            </div>
            <ul v-else class="teacher-list">
                <li v-for="student in filtered" :key="`${student.id}-${student.className}`" class="teacher-list-row">
                    <span class="teacher-mark">{{ student.full_name.split(" ").slice(-2).map((word) => word[0]).join("") }}</span>
                    <div class="teacher-list-copy"><b>{{ student.full_name }}</b><small>{{ student.code }} · {{ student.saint_name || "Chưa cập nhật tên thánh" }}</small></div>
                    <div class="teacher-row-actions"><ATag color="blue"><span class="inline-flex items-center gap-1"><BookOpen class="size-3.5" />{{ student.className }}</span></ATag><ATag color="success">Đang học</ATag></div>
                </li>
            </ul>
        </ACard>
    </section>
</template>

<script setup lang="ts">
import { computed } from "vue";
import ACard from "ant-design-vue/es/card";
import { Construction } from "lucide-vue-next";
import { useRoute } from "vue-router";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";
const route = useRoute();
const isTeacherPage = computed(() => route.path.startsWith("/teacher"));
const title = computed(() => String(route.meta.title ?? "Tính năng"));
</script>
<template>
    <section v-if="isTeacherPage" class="teacher-page-stack">
        <TeacherPageHeader :title="title" description="Tính năng đang được hoàn thiện cho không gian giáo lý viên." />
        <ACard :bordered="false" class="teacher-card"><div class="teacher-empty-state"><span class="teacher-mark teacher-empty-icon text-amber-600"><Construction class="size-7" /></span><h3>{{ title }} đang được phát triển</h3><p>Module chưa có đủ backend và kiểm thử nên chưa mở thao tác. Các chức năng đang hoạt động vẫn sử dụng bình thường.</p></div></ACard>
    </section>
    <section
        v-else
        class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"
    >
        <Construction class="mx-auto size-10 text-amber-500" />
        <h2 class="mt-4 text-xl font-bold text-ink">{{ route.meta.title }}</h2>
        <p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500">
            Module này chưa có đủ backend và kiểm thử nên chưa mở thao tác.
            Trạng thái được hiển thị rõ để tránh tạo CTA giả; tiến độ nằm trong
            tài liệu audit.
        </p>
    </section>
</template>

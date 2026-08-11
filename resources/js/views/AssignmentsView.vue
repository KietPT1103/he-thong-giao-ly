<script setup lang="ts">
import { BookOpen, LockKeyhole } from "lucide-vue-next";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";
</script>
<template>
    <section class="teacher-page-stack">
        <TeacherPageHeader title="Bài tập" description="Giao bài, theo dõi bài nộp và chấm điểm." />
        <ACard :bordered="false" class="teacher-card">
            <div class="teacher-empty-state">
                <span class="teacher-mark teacher-empty-icon"><BookOpen class="size-7" /></span>
                <h3>Module bài tập chưa được mở</h3>
                <p>Backend bài tập, bài nộp và quyền theo phạm vi lớp sẽ được xây dựng ở giai đoạn tiếp theo. Thao tác giao bài được khóa để tránh tạo dữ liệu giả.</p>
                <AButton disabled size="large" class="mt-5"><template #icon><LockKeyhole class="size-4" /></template>Giao bài tập · Đang phát triển</AButton>
            </div>
        </ACard>
    </section>
</template>

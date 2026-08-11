<script setup lang="ts">
defineProps<{
    title: string;
    description: string;
    count?: string;
}>();
</script>

<template>
    <header class="teacher-page-header" :aria-label="title">
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <p>{{ description }}</p>
            <span v-if="count" class="teacher-count-badge">{{ count }}</span>
        </div>
        <div v-if="$slots.actions" class="teacher-page-actions">
            <slot name="actions" />
        </div>
    </header>
</template>

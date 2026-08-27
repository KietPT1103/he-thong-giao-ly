<script setup lang="ts">
import { computed, ref, watch } from "vue";

const props = withDefaults(defineProps<{
    name: string;
    avatarUrl?: string | null;
    size?: "sm" | "md";
}>(), {
    avatarUrl: null,
    size: "md",
});
const failed = ref(false);
const initials = computed(() => props.name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(-2)
    .map((part) => part.charAt(0))
    .join("")
    .toLocaleUpperCase("vi"));

watch(() => props.avatarUrl, () => { failed.value = false; });
</script>

<template>
    <span class="user-avatar" :class="`user-avatar--${size}`" aria-hidden="true">
        <img v-if="avatarUrl && !failed" :src="avatarUrl" alt="" loading="lazy" @error="failed = true" />
        <span v-else>{{ initials || "?" }}</span>
    </span>
</template>

<style scoped>
.user-avatar{display:grid;flex:none;place-items:center;overflow:hidden;border:1px solid #dbe7f8;border-radius:50%;background:#e8f1ff;color:#2364d7;font-weight:750;line-height:1}.user-avatar--sm{width:36px;height:36px;font-size:11px}.user-avatar--md{width:40px;height:40px;font-size:12px}.user-avatar img{width:100%;height:100%;object-fit:cover}
</style>

<script setup lang="ts">
import { computed } from "vue";
import { useRoute } from "vue-router";

const route = useRoute();
defineProps<{ attached?: boolean }>();
const classQuery = computed(() => route.query.class ? { class: String(route.query.class) } : undefined);
</script>

<template>
    <nav class="attendance-section-nav" :class="{'attendance-section-nav--attached':attached}" aria-label="Điều hướng điểm danh">
        <RouterLink :to="{ path: '/teacher/attendance', query: classQuery }">Điểm danh</RouterLink>
        <RouterLink :to="{ path: '/teacher/attendance/sessions', query: classQuery }">Danh sách phiên</RouterLink>
    </nav>
</template>

<style scoped>
.attendance-section-nav{display:flex;min-height:44px;align-items:flex-end;gap:26px;border-bottom:1px solid #dbe3ee;background:#fff;padding-inline:4px}.attendance-section-nav a{position:relative;display:inline-flex;min-height:44px;align-items:center;color:#52627c;font-size:12px;font-weight:650;text-decoration:none;transition:color 140ms ease}.attendance-section-nav a::after{position:absolute;right:0;bottom:-1px;left:0;height:2px;border-radius:2px 2px 0 0;background:#2476f3;content:"";opacity:0;transform:scaleX(.6);transition:opacity 140ms ease,transform 140ms ease}.attendance-section-nav a:hover{color:#185fce}.attendance-section-nav a:focus-visible{border-radius:6px;outline:2px solid #93c5fd;outline-offset:3px}.attendance-section-nav a.router-link-exact-active{color:#145fd1}.attendance-section-nav a.router-link-exact-active::after{opacity:1;transform:scaleX(1)}@media(max-width:639px){.attendance-section-nav{gap:20px;overflow-x:auto}.attendance-section-nav a{min-width:max-content;min-height:48px}}
.attendance-section-nav a{font-size:14px}
@media(max-width:639px){.attendance-section-nav{overflow-y:hidden}}
.attendance-section-nav--attached{margin-top:-16px;margin-inline:-12px;padding-inline:12px}@media(min-width:640px){.attendance-section-nav--attached{margin-top:-24px;margin-inline:-20px;padding-inline:20px}}@media(min-width:1024px){.attendance-section-nav--attached{margin-top:-28px;margin-inline:-28px;padding-inline:28px}}
</style>

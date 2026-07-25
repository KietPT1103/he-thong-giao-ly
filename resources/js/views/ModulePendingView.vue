<script setup lang="ts">
import {Construction} from 'lucide-vue-next'; import {useRoute} from 'vue-router';
const route=useRoute();
</script>
<template><section class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center"><Construction class="mx-auto size-10 text-amber-500"/><h2 class="mt-4 text-xl font-bold text-ink">{{route.meta.title}}</h2><p class="mx-auto mt-2 max-w-lg text-sm leading-6 text-slate-500">Module này chưa có đủ backend và kiểm thử nên chưa mở thao tác. Trạng thái được hiển thị rõ để tránh tạo CTA giả; tiến độ nằm trong tài liệu audit.</p></section></template>

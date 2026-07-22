<script setup>
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { Bell, BookOpen, CalendarDays, CheckSquare, ChevronDown, GraduationCap, Home, Menu, Users, X } from 'lucide-vue-next';
const route=useRoute(), open=ref(false);
const title=computed(()=>route.meta.title||'Hành Trang Đức Tin');
const navigation=[['/', 'Tổng quan', Home],['/lop-hoc','Lớp học',GraduationCap],['/diem-danh','Điểm danh',CheckSquare],['/bai-tap','Bài tập',BookOpen],['/thieu-nhi','Thiếu nhi',Users],['/lich-hoc','Lịch học',CalendarDays]];
</script>
<template>
 <div class="min-h-screen">
  <aside :class="open?'translate-x-0':'-translate-x-full'" class="fixed inset-y-0 left-0 z-40 flex w-72 flex-col bg-ink px-4 py-6 text-white transition-transform lg:translate-x-0">
   <div class="mb-10 flex items-center gap-3 px-3"><div class="grid size-10 place-items-center rounded-xl bg-primary-500"><BookOpen class="size-5"/></div><div><p class="font-semibold">Hành Trang Đức Tin</p><p class="text-xs text-blue-200">Giáo xứ An Bình</p></div><button class="ml-auto lg:hidden" @click="open=false"><X/></button></div>
   <p class="px-3 pb-3 text-xs font-medium uppercase tracking-wider text-blue-300">Không gian giáo lý viên</p>
   <nav class="space-y-1"><RouterLink v-for="([to,label,icon]) in navigation" :key="to" :to="to" @click="open=false" class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm text-blue-100 transition hover:bg-white/10" active-class="!bg-primary-600 !text-white"><component :is="icon" class="size-5"/>{{label}}</RouterLink></nav>
   <div class="mt-auto rounded-xl bg-white/10 p-4"><p class="text-sm font-medium">Niên khóa 2025–2026</p><p class="mt-1 text-xs leading-5 text-blue-200">Đang hoạt động · 6 lớp</p></div>
  </aside>
  <div class="lg:pl-72"><header class="sticky top-0 z-30 flex h-18 items-center justify-between border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-7"><div class="flex items-center gap-3"><button class="rounded-lg p-2 lg:hidden" @click="open=true"><Menu/></button><div><p class="text-xs text-slate-500">Giáo xứ An Bình</p><h1 class="font-semibold text-ink">{{title}}</h1></div></div><div class="flex items-center gap-3"><button aria-label="Thông báo" class="relative rounded-xl p-2 text-slate-500 hover:bg-slate-100"><Bell class="size-5"/><span class="absolute right-2 top-2 size-2 rounded-full bg-rose-500"></span></button><button class="flex items-center gap-2 rounded-xl border border-slate-200 py-1.5 pl-1.5 pr-2 text-sm"><span class="grid size-7 place-items-center rounded-lg bg-primary-100 font-semibold text-primary-700">HL</span><span class="hidden sm:inline">Hồng Lan</span><ChevronDown class="size-4"/></button></div></header>
   <main class="mx-auto max-w-7xl p-4 pb-24 sm:p-7"><RouterView/></main>
  </div>
 </div>
</template>

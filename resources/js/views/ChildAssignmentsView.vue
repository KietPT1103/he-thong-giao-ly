<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";
import AButton from "ant-design-vue/es/button";
import {
    ArrowRight, BookOpenCheck, CalendarClock, CheckCircle2, CircleAlert,
    Clock3, RefreshCw, RotateCcw,
} from "lucide-vue-next";
import { getChildAssignments } from "../api/learning";
import type { Assignment, Submission } from "../types/learning";

const router = useRouter();
const assignments = ref<Assignment[]>([]);
const loading = ref(true);
const error = ref("");
const currentFilter = ref<"todo" | "done" | "all">("todo");

function latest(item: Assignment): Submission | undefined { return item.submissions?.[0]; }
function state(item: Assignment) {
    const submission = latest(item);
    if (submission?.status === "released") return "released";
    if (submission && ["submitted", "grading", "graded"].includes(submission.status)) return "waiting";
    if (submission && ["in_progress", "reopened"].includes(submission.status)) return "progress";
    return "todo";
}
const visible = computed(() => assignments.value.filter((item) => {
    if (currentFilter.value === "all") return true;
    if (currentFilter.value === "done") return ["released", "waiting"].includes(state(item));
    return ["todo", "progress"].includes(state(item));
}));
const dueSoon = computed(() => assignments.value.filter((item) => {
    if (!["todo", "progress"].includes(state(item)) || !item.due_at) return false;
    const hours = (new Date(item.due_at).getTime() - Date.now()) / 3600000;
    return hours >= 0 && hours <= 72;
}).length);

function apiMessage(value: unknown) { return (value as { response?: { data?: { message?: string } } }).response?.data?.message ?? "Không thể tải bài tập. Hãy thử lại."; }
function formatDate(value: string | null) {
    return value ? new Intl.DateTimeFormat("vi-VN", { weekday: "short", day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" }).format(new Date(value)) : "Không giới hạn";
}
function deadlineTone(item: Assignment) {
    if (!item.due_at || !["todo", "progress"].includes(state(item))) return "";
    const hours = (new Date(item.due_at).getTime() - Date.now()) / 3600000;
    return hours < 0 ? "overdue" : hours <= 72 ? "soon" : "";
}
function classNames(item: Assignment) { return [...new Set(item.targets?.map((target) => target.catechism_class?.name).filter(Boolean) ?? [])].join(", ") || "Lớp giáo lý"; }
function stateCopy(item: Assignment) {
    return {
        todo: { label: "Chưa làm", action: "Bắt đầu", tone: "blue" },
        progress: { label: "Đang làm", action: "Tiếp tục", tone: "amber" },
        waiting: { label: "Đã nộp · chờ kết quả", action: "Xem bài", tone: "neutral" },
        released: { label: "Đã có kết quả", action: "Xem kết quả", tone: "green" },
    }[state(item)];
}
async function load() {
    loading.value = true; error.value = "";
    try { assignments.value = (await getChildAssignments()).data.data.data; }
    catch (exception) { error.value = apiMessage(exception); }
    finally { loading.value = false; }
}
onMounted(load);
</script>

<template>
    <section class="child-work-page">
        <header class="child-heading"><div><h1>Việc cần làm</h1><p>Hoàn thành từng bài theo hạn nộp. Câu trả lời được tự động lưu khi em đang làm.</p></div><span v-if="dueSoon"><CalendarClock />{{ dueSoon }} bài sắp đến hạn</span></header>
        <nav class="child-tabs" aria-label="Lọc bài tập"><button v-for="item in [{key:'todo',label:'Cần làm'},{key:'done',label:'Đã nộp'},{key:'all',label:'Tất cả'}]" :key="item.key" type="button" :class="{active:currentFilter===item.key}" @click="currentFilter=item.key as typeof currentFilter">{{ item.label }}</button></nav>

        <div v-if="error" class="child-state error" role="alert"><CircleAlert /><div><b>Chưa tải được bài tập</b><p>{{ error }}</p></div><AButton @click="load"><RefreshCw />Thử lại</AButton></div>
        <div v-else-if="loading" class="child-loading"><span v-for="i in 4" :key="i" /></div>
        <div v-else-if="!visible.length" class="child-state"><CheckCircle2 /><div><b>{{ currentFilter==='todo' ? 'Em đã hoàn thành hết việc cần làm' : 'Chưa có bài tập trong mục này' }}</b><p>{{ currentFilter==='todo' ? 'Khi có bài mới, hệ thống sẽ hiển thị tại đây và gửi thông báo cho em.' : 'Hãy chọn mục khác để xem bài tập.' }}</p></div></div>
        <section v-else class="child-assignment-list" aria-label="Danh sách bài tập">
            <article v-for="item in visible" :key="item.id" class="child-assignment-row">
                <span class="subject-mark" :class="stateCopy(item).tone"><BookOpenCheck /></span>
                <div class="child-assignment-main"><div><span class="state-label" :class="stateCopy(item).tone">{{ stateCopy(item).label }}</span><small>{{ classNames(item) }}</small></div><h2>{{ item.title }}</h2><p>{{ item.description || `${item.questions_count ?? 0} câu hỏi · ${item.max_score} điểm` }}</p></div>
                <div class="child-assignment-meta"><span :class="deadlineTone(item)"><CalendarClock />{{ formatDate(item.due_at) }}</span><span><RotateCcw />{{ item.allowed_attempts || '∞' }} lượt</span><span v-if="item.time_limit_minutes"><Clock3 />{{ item.time_limit_minutes }} phút</span></div>
                <AButton type="primary" :ghost="state(item)==='waiting'" @click="router.push(`/child/assignments/${item.id}`)">{{ stateCopy(item).action }}<ArrowRight /></AButton>
            </article>
        </section>
    </section>
</template>

<style scoped>
.child-work-page{display:grid;max-width:1040px;margin:0 auto;gap:18px}.child-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;padding:8px 2px}.child-heading h1{margin:0;color:#172554;font-size:26px;font-weight:780;letter-spacing:-.025em}.child-heading p{max-width:65ch;margin:7px 0 0;color:#64748b;font-size:13px;line-height:1.6}.child-heading>span{display:flex;flex:none;align-items:center;gap:7px;border-radius:999px;background:#fff5dc;padding:7px 11px;color:#a94e08;font-size:10px;font-weight:700}.child-heading svg{width:15px}.child-tabs{display:flex;gap:4px;border-bottom:1px solid #dbe3ee}.child-tabs button{min-height:42px;border:0;border-bottom:2px solid transparent;background:transparent;padding:8px 14px;color:#64748b;font-size:12px;font-weight:650}.child-tabs button.active{border-bottom-color:#2563eb;color:#1d4ed8}.child-assignment-list{overflow:hidden;border:1px solid #dbe3ee;border-radius:14px;background:#fff;box-shadow:0 12px 32px rgba(15,23,42,.045)}.child-assignment-row{display:grid;grid-template-columns:46px minmax(0,1fr) 190px auto;align-items:center;gap:16px;padding:18px 20px}.child-assignment-row+article{border-top:1px solid #e7edf5}.subject-mark{display:grid;width:44px;height:44px;place-items:center;border-radius:11px;background:#eaf2ff;color:#2563eb}.subject-mark svg{width:21px}.subject-mark.amber{background:#fff5dc;color:#b45309}.subject-mark.green{background:#e9f9ef;color:#15803d}.subject-mark.neutral{background:#eef2f7;color:#64748b}.child-assignment-main{min-width:0}.child-assignment-main>div{display:flex;align-items:center;gap:8px}.state-label{border-radius:999px;background:#eff6ff;padding:3px 7px;color:#1d4ed8;font-size:9px;font-weight:700}.state-label.amber{background:#fff5dc;color:#a94e08}.state-label.green{background:#e9f9ef;color:#15803d}.state-label.neutral{background:#eef2f7;color:#52627c}.child-assignment-main small{color:#64748b;font-size:9px}.child-assignment-main h2{overflow:hidden;margin:7px 0 0;color:#172554;font-size:15px;font-weight:750;text-overflow:ellipsis;white-space:nowrap}.child-assignment-main p{overflow:hidden;margin:5px 0 0;color:#64748b;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.child-assignment-meta{display:grid;gap:6px}.child-assignment-meta span{display:flex;align-items:center;gap:6px;color:#64748b;font-size:10px;font-variant-numeric:tabular-nums}.child-assignment-meta svg{width:14px}.child-assignment-meta .soon{color:#a94e08;font-weight:650}.child-assignment-meta .overdue{color:#b42318;font-weight:650}.child-assignment-row>.ant-btn{display:flex;min-width:108px;align-items:center;justify-content:center;gap:6px;border-radius:9px;font-size:11px;font-weight:700}.child-assignment-row>.ant-btn svg{width:14px}.child-state{display:flex;min-height:260px;align-items:center;justify-content:center;gap:16px;border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:30px}.child-state>svg{width:38px;color:#16a34a}.child-state b{color:#172554;font-size:14px}.child-state p{max-width:55ch;margin:5px 0 0;color:#64748b;font-size:11px;line-height:1.6}.child-state.error>svg{color:#dc2626}.child-state .ant-btn{display:flex;align-items:center;gap:6px;margin-left:12px}.child-state .ant-btn svg{width:14px}.child-loading{display:grid;gap:1px;overflow:hidden;border-radius:14px;background:#e7edf5}.child-loading span{height:92px;background:linear-gradient(90deg,#fff 20%,#f5f7fa 50%,#fff 80%);background-size:200% 100%;animation:child-loading 1.4s infinite}@keyframes child-loading{to{background-position:-200% 0}}
@media(min-width:1600px){.child-work-page{max-width:1280px;gap:22px}.child-assignment-row{grid-template-columns:52px minmax(0,1fr) 240px auto;gap:20px;padding:20px 24px}.subject-mark{width:48px;height:48px}.child-assignment-main h2{font-size:16px}.child-assignment-main p,.child-assignment-meta span{font-size:11px}.child-assignment-row>.ant-btn{min-width:124px}}
@media(max-width:820px){.child-assignment-row{grid-template-columns:44px minmax(0,1fr) auto}.child-assignment-meta{grid-column:2}.child-assignment-row>.ant-btn{grid-column:3;grid-row:1/3}}
@media(max-width:560px){.child-work-page{gap:14px}.child-heading{align-items:flex-start;flex-direction:column}.child-heading h1{font-size:23px}.child-assignment-row{grid-template-columns:38px minmax(0,1fr);gap:11px;padding:15px 14px}.subject-mark{width:38px;height:38px}.child-assignment-meta{grid-column:2}.child-assignment-row>.ant-btn{grid-column:1/-1;grid-row:auto;min-height:42px}.child-state{align-items:flex-start;flex-direction:column}.child-state .ant-btn{margin-left:0}}
@media(prefers-reduced-motion:reduce){.child-loading span{animation:none}}
</style>

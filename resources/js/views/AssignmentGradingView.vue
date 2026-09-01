<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import AButton from "ant-design-vue/es/button";
import AInput from "ant-design-vue/es/input";
import AInputNumber from "ant-design-vue/es/input-number";
import ASelect from "ant-design-vue/es/select";
import ATextarea from "ant-design-vue/es/input/TextArea";
import AModal from "ant-design-vue/es/modal";
import {
    ArrowLeft, CheckCircle2, ChevronLeft, ChevronRight, CircleAlert, ClipboardCheck,
    Download, Eye, Paperclip, RefreshCw, RotateCcw, Save, Search, Send, UserRound,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import {
    getAssignmentSubmissions, getTeacherAssignment, getTeacherAssignments,
    gradeSubmission, releaseAssignmentResults, reopenSubmission,
} from "../api/learning";
import type { Assignment, Submission, SubmissionAnswer } from "../types/learning";

const route = useRoute();
const router = useRouter();
const assignments = ref<Assignment[]>([]);
const assignmentId = ref<number | undefined>(Number(route.params.id) || undefined);
const assignment = ref<Assignment | null>(null);
const submissions = ref<Submission[]>([]);
const selectedId = ref<number | null>(null);
const loading = ref(true);
const saving = ref(false);
const releasing = ref(false);
const error = ref("");
const search = ref("");
const status = ref<string>();
const generalFeedback = ref("");
const correctionReason = ref("");
const reopenOpen = ref(false);
const reopenReason = ref("");
const scores = reactive<Record<number, number>>({});
const feedback = reactive<Record<number, string>>({});

const selected = computed(() => submissions.value.find((item) => item.id === selectedId.value) ?? submissions.value[0] ?? null);
const selectedIndex = computed(() => submissions.value.findIndex((item) => item.id === selected.value?.id));
const essayAnswers = computed(() => selected.value?.answers?.filter((answer) => answer.question?.type === "essay") ?? []);
const completedCount = computed(() => submissions.value.filter((item) => ["graded", "released"].includes(item.status)).length);
const pendingCount = computed(() => submissions.value.filter((item) => item.status === "grading").length);
const statusOptions = [
    { value: "grading", label: "Cần chấm tự luận" }, { value: "graded", label: "Đã chấm" },
    { value: "released", label: "Đã công bố" }, { value: "submitted", label: "Đã nộp" },
];

function apiMessage(value: unknown, fallback: string) {
    return (value as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;
}
function formatDate(value: string | null | undefined) {
    return value ? new Intl.DateTimeFormat("vi-VN", { dateStyle: "short", timeStyle: "short" }).format(new Date(value)) : "—";
}
function formatFileSize(bytes: number) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}
function answerText(answer: SubmissionAnswer) {
    const value = answer.answer as { text?: string; selected?: number[] } | undefined;
    if (value?.text) return value.text;
    if (value?.selected && answer.question?.options) return value.selected.map((index) => answer.question?.options?.[index]?.content).filter(Boolean).join(", ");
    return "Không có câu trả lời";
}
function hydrateGrade(item: Submission | null) {
    Object.keys(scores).forEach((key) => delete scores[Number(key)]);
    Object.keys(feedback).forEach((key) => delete feedback[Number(key)]);
    item?.answers?.forEach((answer) => {
        scores[answer.assignment_question_id] = Number(answer.auto_score ?? 0) + Number(answer.manual_score ?? 0);
        feedback[answer.assignment_question_id] = answer.feedback ?? "";
    });
    generalFeedback.value = item?.general_feedback ?? "";
    correctionReason.value = "";
}
async function loadAssignments() {
    const response = await getTeacherAssignments();
    assignments.value = response.data.data.data.filter((item) => (item.submissions_count ?? 0) > 0);
    if (!assignmentId.value) assignmentId.value = assignments.value[0]?.id;
}
async function loadSubmissions() {
    if (!assignmentId.value) { submissions.value = []; assignment.value = null; loading.value = false; return; }
    loading.value = true; error.value = "";
    try {
        const [assignmentResponse, submissionResponse] = await Promise.all([
            getTeacherAssignment(assignmentId.value),
            getAssignmentSubmissions(assignmentId.value, { search: search.value || undefined, status: status.value }),
        ]);
        assignment.value = assignmentResponse.data.data;
        submissions.value = submissionResponse.data.data.data;
        if (!submissions.value.some((item) => item.id === selectedId.value)) selectedId.value = submissions.value[0]?.id ?? null;
        hydrateGrade(selected.value);
    } catch (exception) { error.value = apiMessage(exception, "Không thể tải danh sách bài nộp."); }
    finally { loading.value = false; }
}
async function initialize() {
    try { await loadAssignments(); await loadSubmissions(); }
    catch (exception) { error.value = apiMessage(exception, "Không thể mở bàn chấm bài."); loading.value = false; }
}
async function saveGrade() {
    if (!selected.value || !assignment.value) return;
    const gradingAnswers = selected.value.answers?.map((answer) => ({
        question_id: answer.assignment_question_id,
        score: scores[answer.assignment_question_id] ?? Number(answer.auto_score ?? 0),
        feedback: feedback[answer.assignment_question_id] || undefined,
        rubric_scores: [],
    })) ?? [];
    if (!gradingAnswers.length) { toast.error("Bài nộp chưa có câu trả lời để chấm."); return; }
    saving.value = true;
    try {
        const updated = (await gradeSubmission(selected.value.id, {
            version: selected.value.version, general_feedback: generalFeedback.value || undefined,
            reason: selected.value.status === "released" ? correctionReason.value || undefined : undefined,
            answers: gradingAnswers,
        })).data.data;
        const index = submissions.value.findIndex((item) => item.id === updated.id);
        if (index >= 0) submissions.value[index] = updated;
        hydrateGrade(updated);
        toast.success("Đã lưu điểm và nhận xét.");
    } catch (exception) { toast.error(apiMessage(exception, "Không thể lưu điểm.")); }
    finally { saving.value = false; }
}
function releaseResults() {
    if (!assignment.value) return;
    AModal.confirm({
        title: "Công bố kết quả cho Thiếu nhi?",
        content: pendingCount.value ? `Còn ${pendingCount.value} bài chưa chấm xong. Hãy hoàn tất trước khi công bố.` : "Sau khi công bố, Thiếu nhi sẽ thấy điểm, nhận xét và đáp án nếu đã bật trong bài tập.",
        okText: "Công bố kết quả", cancelText: "Chưa công bố", okButtonProps: { disabled: pendingCount.value > 0 },
        async onOk() {
            releasing.value = true;
            try { await releaseAssignmentResults(assignment.value!.id); toast.success("Đã công bố kết quả và gửi thông báo."); await loadSubmissions(); }
            catch (exception) { toast.error(apiMessage(exception, "Không thể công bố kết quả.")); }
            finally { releasing.value = false; }
        },
    });
}
async function reopenCurrent() {
    if (!selected.value || !reopenReason.value.trim()) { toast.error("Hãy nhập lý do mở lại bài."); return; }
    saving.value = true;
    try {
        const updated = (await reopenSubmission(selected.value.id, reopenReason.value)).data.data;
        const index = submissions.value.findIndex((item) => item.id === updated.id);
        if (index >= 0) submissions.value[index] = updated;
        reopenOpen.value = false; reopenReason.value = ""; hydrateGrade(updated);
        toast.success("Đã mở lại bài và thông báo cho Thiếu nhi.");
    } catch (exception) { toast.error(apiMessage(exception, "Không thể mở lại bài.")); }
    finally { saving.value = false; }
}
function changeSelection(id: number) { selectedId.value = id; hydrateGrade(selected.value); }
function moveSelection(direction: -1 | 1) {
    const next = submissions.value[selectedIndex.value + direction];
    if (next) changeSelection(next.id);
}
watch(assignmentId, loadSubmissions);
watch(status, loadSubmissions);
onMounted(initialize);
</script>

<template>
    <section class="grading-page">
        <header class="grading-header">
            <div><AButton type="text" @click="router.push('/teacher/assignments')"><ArrowLeft />Bài tập</AButton><h1>Bàn chấm bài</h1><p>Chấm phần tự luận, để lại nhận xét và công bố khi toàn bộ bài đã sẵn sàng.</p></div>
            <AButton v-if="assignment" type="primary" :loading="releasing" :disabled="assignment.status==='released'" @click="releaseResults"><Send />{{ assignment.status==='released' ? 'Đã công bố' : 'Công bố kết quả' }}</AButton>
        </header>
        <section class="grading-toolbar">
            <label><span>Bài tập</span><ASelect v-model:value="assignmentId" :options="assignments.map(item=>({value:item.id,label:item.title}))" placeholder="Chọn bài có lượt nộp" /></label>
            <div v-if="assignment" class="grading-summary"><span><b>{{ submissions.length }}</b><small>Bài nộp</small></span><i /><span><b>{{ pendingCount }}</b><small>Cần chấm</small></span><i /><span><b>{{ completedCount }}</b><small>Đã xong</small></span></div>
        </section>

        <div v-if="error" class="grading-state error" role="alert"><CircleAlert /><div><b>Chưa tải được bàn chấm</b><p>{{ error }}</p></div><AButton @click="initialize"><RefreshCw />Thử lại</AButton></div>
        <div v-else-if="loading" class="grading-loading"><span /><span /></div>
        <div v-else-if="!assignmentId || !submissions.length" class="grading-state"><ClipboardCheck /><div><b>Chưa có bài nộp để chấm</b><p>Khi Thiếu nhi nộp bài, danh sách sẽ xuất hiện tại đây.</p></div></div>
        <section v-else class="grading-workspace">
            <aside class="submission-master">
                <div class="submission-filters"><AInput v-model:value="search" allow-clear placeholder="Tìm Thiếu nhi" @press-enter="loadSubmissions"><template #prefix><Search /></template></AInput><ASelect v-model:value="status" allow-clear placeholder="Mọi trạng thái" :options="statusOptions" /></div>
                <button v-for="item in submissions" :key="item.id" type="button" class="submission-row" :class="{selected:selected?.id===item.id}" @click="changeSelection(item.id)">
                    <span class="student-avatar"><UserRound /></span><span><b>{{ item.child?.full_name }}</b><small>{{ item.child?.code }} · Lượt {{ item.attempt_number }}</small></span><span class="submission-status" :class="item.status">{{ item.status==='grading'?'Cần chấm':item.status==='graded'?'Đã chấm':item.status==='released'?'Đã công bố':'Đã nộp' }}</span><small>{{ formatDate(item.submitted_at) }}</small>
                </button>
            </aside>

            <main v-if="selected" class="grading-detail">
                <header><div><small>{{ selected.child?.code }} · Lượt {{ selected.attempt_number }}</small><h2>{{ selected.child?.full_name }}</h2><p>Nộp lúc {{ formatDate(selected.submitted_at) }}<span v-if="selected.is_late"> · Nộp trễ</span></p></div><div class="submission-pager"><button type="button" :disabled="selectedIndex<=0" aria-label="Bài trước" @click="moveSelection(-1)"><ChevronLeft /></button><span>{{ selectedIndex+1 }}/{{ submissions.length }}</span><button type="button" :disabled="selectedIndex>=submissions.length-1" aria-label="Bài sau" @click="moveSelection(1)"><ChevronRight /></button></div></header>
                <section v-if="selected.files?.length" class="submission-files" aria-label="Tệp đính kèm bài nộp">
                    <div class="submission-files-heading"><Paperclip /><span><b>Tệp Thiếu nhi đã nộp</b><small>{{ selected.files.length }} tệp đính kèm</small></span></div>
                    <div class="submission-file-list">
                        <a v-for="file in selected.files" :key="file.id" :href="file.download_url" class="submission-file" target="_blank" rel="noopener">
                            <span><b>{{ file.original_name }}</b><small>{{ formatFileSize(file.size) }}</small></span><Download aria-hidden="true" />
                        </a>
                    </div>
                </section>
                <section class="answer-review">
                    <article v-for="answer in selected.answers" :key="answer.id ?? answer.assignment_question_id" class="graded-answer">
                        <div class="answer-question"><span :class="answer.question?.type==='essay'?'essay':'auto'">{{ answer.question?.type==='essay'?'Tự luận':'Tự chấm' }}</span><small>{{ answer.question?.points }} điểm</small><h3>{{ answer.question?.prompt }}</h3></div>
                        <blockquote>{{ answerText(answer) }}</blockquote>
                        <div class="grade-controls"><label><span>Điểm</span><AInputNumber v-model:value="scores[answer.assignment_question_id]" :min="0" :max="answer.question?.points ?? 0" :disabled="answer.question?.type!=='essay'" /></label><label><span>Nhận xét câu trả lời</span><ATextarea v-model:value="feedback[answer.assignment_question_id]" :rows="2" placeholder="Gợi ý giúp em cải thiện…" /></label></div>
                    </article>
                </section>
                <section class="general-feedback"><label><span>Nhận xét chung gửi Thiếu nhi</span><ATextarea v-model:value="generalFeedback" :rows="4" placeholder="Điểm mạnh và điều em cần cố gắng…" /></label><label v-if="selected.status==='released'"><span>Lý do điều chỉnh điểm *</span><AInput v-model:value="correctionReason" placeholder="Ghi rõ lý do để lưu lịch sử đối soát" /></label></section>
                <footer><div><Eye /><span><small>Điểm dự kiến</small><b>{{ Object.values(scores).reduce((sum,value)=>sum+Number(value||0),0) }} điểm gốc</b></span></div><span class="grade-footer-actions"><AButton v-if="['graded','released'].includes(selected.status)" @click="reopenOpen=true"><RotateCcw />Mở lại bài</AButton><AButton type="primary" :loading="saving" @click="saveGrade"><Save />Lưu điểm và nhận xét</AButton></span></footer>
            </main>
        </section>
        <AModal :open="reopenOpen" centered title="Mở lại bài cho Thiếu nhi" :footer="null" width="480px" @cancel="reopenOpen=false"><div class="reopen-form"><p>Em sẽ tiếp tục trên chính lượt làm này; điểm và lịch sử chấm cũ vẫn được giữ để đối soát.</p><label><span>Lý do *</span><AInput v-model:value="reopenReason" :maxlength="1000" placeholder="Ví dụ: Cho em sửa lại theo hướng dẫn" /></label><footer><AButton @click="reopenOpen=false">Hủy</AButton><AButton type="primary" :loading="saving" @click="reopenCurrent"><RotateCcw />Mở lại bài</AButton></footer></div></AModal>
    </section>
</template>

<style scoped>
.grading-page{display:grid;gap:16px}.grading-header{display:flex;align-items:flex-end;justify-content:space-between;gap:20px}.grading-header>div>.ant-btn{display:flex;align-items:center;gap:6px;margin-left:-12px;color:#64748b}.grading-header svg{width:16px}.grading-header h1{margin:6px 0 0;color:#172554;font-size:23px;font-weight:770;letter-spacing:-.025em}.grading-header p{margin:5px 0 0;color:#64748b;font-size:11px}.grading-header>.ant-btn{display:flex;min-height:42px;align-items:center;gap:7px;border-radius:10px;font-weight:700}.grading-toolbar{display:flex;min-height:68px;align-items:center;justify-content:space-between;gap:18px;border:1px solid #dbe3ee;border-radius:13px;background:#fff;padding:12px 16px}.grading-toolbar>label{display:flex;min-width:0;flex:1;align-items:center;gap:10px}.grading-toolbar>label>span{flex:none;color:#475569;font-size:10px;font-weight:700}.grading-toolbar :deep(.ant-select){width:min(440px,100%)}.grading-summary{display:flex;align-items:center;gap:15px}.grading-summary>span{text-align:center}.grading-summary b,.grading-summary small{display:block}.grading-summary b{color:#172554;font-size:14px;font-variant-numeric:tabular-nums}.grading-summary small{margin-top:2px;color:#64748b;font-size:8px}.grading-summary i{width:1px;height:28px;background:#dbe3ee}.grading-workspace{display:grid;grid-template-columns:310px minmax(0,1fr);overflow:hidden;min-height:620px;border:1px solid #dbe3ee;border-radius:14px;background:#fff;box-shadow:0 14px 36px rgba(15,23,42,.045)}.submission-master{border-right:1px solid #dbe3ee}.submission-filters{display:grid;grid-template-columns:1fr;gap:8px;padding:12px;border-bottom:1px solid #e7edf5;background:#fbfdff}.submission-filters :deep(.ant-input-affix-wrapper),.submission-filters :deep(.ant-select-selector){border-radius:8px!important}.submission-filters svg{width:14px;color:#94a3b8}.submission-row{display:grid;width:100%;grid-template-columns:36px minmax(0,1fr) auto;align-items:center;gap:9px;border:0;border-bottom:1px solid #edf1f6;background:#fff;padding:12px;text-align:left;transition:background-color .15s ease,box-shadow .15s ease}.submission-row:hover{background:#fbfdff}.submission-row.selected{background:#f3f7ff;box-shadow:inset 3px 0 #2563eb}.student-avatar{display:grid;width:34px;height:34px;grid-row:1/3;place-items:center;border-radius:50%;background:#eaf2ff;color:#2563eb}.student-avatar svg{width:16px}.submission-row b,.submission-row small{display:block}.submission-row b{overflow:hidden;color:#172554;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.submission-row small{grid-column:2;color:#64748b;font-size:8px}.submission-status{border-radius:999px;background:#eef2f7;padding:3px 7px;color:#52627c;font-size:8px;font-weight:700}.submission-status.grading{background:#fff5dc;color:#a94e08}.submission-status.graded{background:#e9f9ef;color:#15803d}.submission-status.released{background:#f2edff;color:#6d28d9}.grading-detail{min-width:0}.grading-detail>header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 22px;border-bottom:1px solid #e7edf5}.grading-detail>header small{color:#1d4ed8;font-size:8px;font-weight:700}.grading-detail>header h2{margin:4px 0 0;color:#172554;font-size:18px;font-weight:750}.grading-detail>header p{margin:4px 0 0;color:#64748b;font-size:9px}.grading-detail>header p span{color:#b42318;font-weight:650}.submission-pager{display:flex;align-items:center;gap:8px}.submission-pager button{display:grid;width:34px;height:34px;place-items:center;border:1px solid #dbe3ee;border-radius:8px;background:#fff;color:#475569}.submission-pager button:disabled{opacity:.35}.submission-pager svg{width:15px}.submission-pager span{color:#64748b;font-size:9px;font-variant-numeric:tabular-nums}.answer-review{display:grid;gap:12px;padding:18px 22px;background:#f8fafc}.graded-answer{overflow:hidden;border:1px solid #dbe3ee;border-radius:12px;background:#fff}.answer-question{padding:13px 15px;border-bottom:1px solid #edf1f6}.answer-question>span{display:inline-flex;border-radius:999px;background:#eaf2ff;padding:3px 7px;color:#1d4ed8;font-size:8px;font-weight:700}.answer-question>span.essay{background:#fff5dc;color:#a94e08}.answer-question>small{margin-left:7px;color:#64748b;font-size:8px}.answer-question h3{margin:7px 0 0;color:#334155;font-size:11px;font-weight:700;line-height:1.5}.graded-answer blockquote{margin:0;padding:14px 15px;color:#475569;font-size:11px;line-height:1.7;white-space:pre-wrap}.grade-controls{display:grid;grid-template-columns:110px minmax(0,1fr);gap:12px;padding:13px 15px;border-top:1px solid #edf1f6;background:#fbfdff}.grade-controls label,.general-feedback label{display:grid;gap:6px}.grade-controls label>span,.general-feedback label>span{color:#475569;font-size:9px;font-weight:700}.grade-controls :deep(.ant-input-number){width:100%}.grade-controls :deep(.ant-input),.grade-controls :deep(.ant-input-number),.general-feedback :deep(.ant-input){border-radius:8px}.general-feedback{display:grid;gap:13px;padding:18px 22px;border-top:1px solid #e7edf5}.grading-detail>footer{position:sticky;bottom:0;display:flex;align-items:center;justify-content:space-between;gap:16px;border-top:1px solid #dbe3ee;background:#fff;padding:12px 22px;box-shadow:0 -8px 20px rgba(15,23,42,.04)}.grading-detail>footer>div{display:flex;align-items:center;gap:8px}.grading-detail>footer>div>svg{width:18px;color:#2563eb}.grading-detail>footer small,.grading-detail>footer b{display:block}.grading-detail>footer small{color:#64748b;font-size:8px}.grading-detail>footer b{margin-top:2px;color:#172554;font-size:10px}.grading-detail>footer>.ant-btn{display:flex;min-height:40px;align-items:center;gap:7px;border-radius:9px;font-weight:700}.grading-detail>footer>.ant-btn svg{width:15px}.grading-state{display:flex;min-height:300px;align-items:center;justify-content:center;gap:14px;border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:24px}.grading-state>svg{width:36px;color:#2563eb}.grading-state.error>svg{color:#dc2626}.grading-state b{color:#172554}.grading-state p{margin:4px 0 0;color:#64748b;font-size:10px}.grading-state .ant-btn{display:flex;align-items:center;gap:6px;margin-left:12px}.grading-state .ant-btn svg{width:14px}.grading-loading{display:grid;grid-template-columns:310px 1fr;gap:1px;height:500px;background:#dbe3ee}.grading-loading span{background:linear-gradient(90deg,#fff,#f4f7fa,#fff);background-size:200% 100%;animation:loading 1.4s infinite}@keyframes loading{to{background-position:-200% 0}}
.grade-footer-actions{display:flex;gap:8px}.grade-footer-actions>.ant-btn{display:flex;min-height:40px;align-items:center;gap:7px;border-radius:9px;font-weight:700}.grade-footer-actions svg{width:15px}.reopen-form{display:grid;gap:14px}.reopen-form>p{margin:0;color:#64748b;font-size:11px;line-height:1.6}.reopen-form label{display:grid;gap:6px}.reopen-form label>span{color:#475569;font-size:10px;font-weight:700}.reopen-form footer{display:flex;justify-content:flex-end;gap:8px;padding-top:12px;border-top:1px solid #e7edf5}.reopen-form footer .ant-btn{display:flex;align-items:center;gap:6px;border-radius:8px}.reopen-form footer svg{width:14px}
.submission-files{display:grid;grid-template-columns:auto minmax(0,1fr);gap:18px;padding:14px 22px;border-bottom:1px solid #dbe3ee;background:#fbfdff}.submission-files-heading{display:flex;align-items:center;gap:9px;color:#2563eb}.submission-files-heading>svg{width:17px}.submission-files-heading b,.submission-files-heading small{display:block}.submission-files-heading b{color:#334155;font-size:10px}.submission-files-heading small{margin-top:2px;color:#64748b;font-size:8px}.submission-file-list{display:flex;min-width:0;flex-wrap:wrap;justify-content:flex-end;gap:7px}.submission-file{display:flex;max-width:260px;align-items:center;gap:10px;border:1px solid #dbe3ee;border-radius:9px;background:#fff;padding:8px 10px;color:inherit;text-decoration:none;transition:border-color .15s ease,box-shadow .15s ease}.submission-file:hover{border-color:#93b4ec;box-shadow:0 4px 12px rgba(37,99,235,.08)}.submission-file>span{min-width:0}.submission-file b,.submission-file small{display:block}.submission-file b{overflow:hidden;color:#334155;font-size:9px;text-overflow:ellipsis;white-space:nowrap}.submission-file small{margin-top:2px;color:#64748b;font-size:8px}.submission-file>svg{width:14px;flex:none;color:#2563eb}
@media(min-width:1536px){.grading-page{gap:20px}.grading-header p{font-size:12px}.grading-toolbar{padding:14px 20px}.grading-toolbar>label>span{font-size:11px}.grading-summary small{font-size:10px}.grading-workspace{grid-template-columns:380px minmax(0,1fr);min-height:max(680px,calc(100dvh - 270px))}.submission-filters{padding:16px}.submission-row{gap:11px;padding:14px 16px}.submission-row b{font-size:13px}.submission-row small,.submission-status{font-size:10px}.grading-detail>header{padding:22px 28px}.grading-detail>header small{font-size:10px}.grading-detail>header h2{font-size:20px}.grading-detail>header p{font-size:11px}.submission-pager span{font-size:10px}.submission-files{padding:16px 28px}.submission-files-heading b,.submission-file b{font-size:11px}.submission-files-heading small,.submission-file small{font-size:10px}.answer-review{grid-template-columns:repeat(2,minmax(0,1fr));align-content:start;align-items:start;gap:16px;padding:22px 28px}.answer-question>span,.answer-question>small{font-size:10px}.answer-question h3,.graded-answer blockquote{font-size:13px}.grade-controls label>span,.general-feedback label>span{font-size:11px}.general-feedback{padding:22px 28px}.grading-detail>footer{padding:14px 28px}.grading-detail>footer small{font-size:10px}.grading-detail>footer b{font-size:12px}.grading-state p{font-size:12px}.grading-loading{grid-template-columns:380px 1fr;height:680px}}
@media(max-width:900px){.grading-workspace{grid-template-columns:1fr}.submission-master{border-right:0}.submission-row:not(.selected){display:none}.submission-filters{grid-template-columns:1fr 1fr}.grading-detail{border-top:1px solid #dbe3ee}.grading-loading{grid-template-columns:1fr}}
@media(max-width:600px){.grading-header{align-items:stretch;flex-direction:column}.grading-header>.ant-btn{justify-content:center}.grading-toolbar{align-items:stretch;flex-direction:column}.grading-toolbar>label{display:grid}.grading-summary{justify-content:center}.submission-filters{grid-template-columns:1fr}.grading-detail>header,.submission-files,.answer-review,.general-feedback{padding-inline:14px}.submission-files{grid-template-columns:1fr}.submission-file-list{justify-content:flex-start}.submission-file{width:100%;max-width:none;justify-content:space-between}.grade-controls{grid-template-columns:1fr}.grading-detail>footer{align-items:stretch;flex-direction:column;padding-inline:14px}.grading-detail>footer>.ant-btn{justify-content:center}.grading-state{align-items:flex-start;flex-direction:column}.grading-state .ant-btn{margin-left:0}}
@media(prefers-reduced-motion:reduce){.submission-row{transition:none}.grading-loading span{animation:none}}
</style>

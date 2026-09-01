<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AButton from "ant-design-vue/es/button";
import ATextarea from "ant-design-vue/es/input/TextArea";
import Modal from "ant-design-vue/es/modal";
import {
    ArrowLeft, BookOpenCheck, CalendarClock, Check, CheckCircle2, CircleAlert,
    Clock3, LoaderCircle, Paperclip, RefreshCw, RotateCcw, Save, Send, Trash2, Upload,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import {
    deleteSubmissionFile, getChildAssignment, saveSubmissionAnswers, startAssignmentAttempt, submitAssignment, uploadSubmissionFile,
} from "../api/learning";
import type { Assignment, AssignmentQuestion, Submission } from "../types/learning";

type AnswerValue = { selected?: number[]; text?: string };
const route = useRoute();
const router = useRouter();
const assignmentId = Number(route.params.id);
const assignment = ref<Assignment | null>(null);
const submission = ref<Submission | null>(null);
const answers = reactive<Record<number, AnswerValue>>({});
const loading = ref(true);
const starting = ref(false);
const saving = ref(false);
const submitting = ref(false);
const uploading = ref(false);
const error = ref("");
const saveState = ref<"idle" | "dirty" | "saving" | "saved" | "error">("idle");
const savedAt = ref<Date | null>(null);
const attachmentInput = ref<HTMLInputElement | null>(null);
const now = ref(Date.now());
let saveTimer: ReturnType<typeof setTimeout> | undefined;
let clockTimer: ReturnType<typeof setInterval> | undefined;

const questions = computed(() => assignment.value?.questions ?? []);
const active = computed(() => submission.value && ["in_progress", "reopened"].includes(submission.value.status));
const released = computed(() => submission.value?.status === "released");
const waiting = computed(() => submission.value && ["submitted", "grading", "graded"].includes(submission.value.status));
const answeredCount = computed(() => questions.value.filter((question) => hasAnswer(question.id!)).length);
const progress = computed(() => questions.value.length ? Math.round(answeredCount.value / questions.value.length * 100) : 0);
const remainingSeconds = computed(() => {
    if (!active.value || !assignment.value?.time_limit_minutes || !submission.value) return null;
    const end = new Date(submission.value.started_at).getTime() + assignment.value.time_limit_minutes * 60000;
    return Math.max(0, Math.floor((end - now.value) / 1000));
});
const timerText = computed(() => {
    if (remainingSeconds.value === null) return "Không giới hạn";
    const minutes = Math.floor(remainingSeconds.value / 60);
    const seconds = remainingSeconds.value % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
});
const attemptsUsed = computed(() => assignment.value?.submissions?.length ?? 0);
const canStart = computed(() => {
    const limit = assignment.value?.allowed_attempts ?? 1;
    return !submission.value || (limit === 0 || attemptsUsed.value < limit);
});

function apiMessage(value: unknown, fallback: string) {
    return (value as { response?: { data?: { message?: string; code?: string } } }).response?.data?.message ?? fallback;
}
function formatDate(value: string | null | undefined) {
    return value ? new Intl.DateTimeFormat("vi-VN", { dateStyle: "full", timeStyle: "short" }).format(new Date(value)) : "Không giới hạn";
}
function hydrateAnswers(current: Submission | null) {
    Object.keys(answers).forEach((key) => delete answers[Number(key)]);
    current?.answers?.forEach((answer) => {
        answers[answer.assignment_question_id] = (answer.answer as AnswerValue | undefined) ?? {};
    });
}
function latestSubmission(item: Assignment) {
    return item.submissions?.find((candidate) => ["in_progress", "reopened"].includes(candidate.status))
        ?? item.submissions?.[0] ?? null;
}
async function load() {
    loading.value = true; error.value = "";
    try {
        assignment.value = (await getChildAssignment(assignmentId)).data.data;
        submission.value = latestSubmission(assignment.value);
        hydrateAnswers(submission.value);
    } catch (exception) { error.value = apiMessage(exception, "Không thể mở bài tập này."); }
    finally { loading.value = false; }
}
async function start() {
    starting.value = true;
    try {
        submission.value = (await startAssignmentAttempt(assignmentId)).data.data;
        hydrateAnswers(submission.value);
        toast.success(submission.value.attempt_number > 1 ? "Đã bắt đầu lượt làm mới." : "Đã bắt đầu làm bài.");
    } catch (exception) { toast.error(apiMessage(exception, "Không thể bắt đầu lượt làm.")); }
    finally { starting.value = false; }
}
function hasAnswer(questionId: number) {
    const answer = answers[questionId];
    return Boolean(answer?.selected?.length || answer?.text?.trim());
}
function selectOne(questionId: number, optionIndex: number) {
    answers[questionId] = { selected: [optionIndex] };
    scheduleSave();
}
function toggleMany(questionId: number, optionIndex: number, checked: boolean) {
    const selected = new Set(answers[questionId]?.selected ?? []);
    checked ? selected.add(optionIndex) : selected.delete(optionIndex);
    answers[questionId] = { selected: [...selected].sort((a, b) => a - b) };
    scheduleSave();
}
function updateText(questionId: number, value: string) {
    answers[questionId] = { text: value };
    scheduleSave();
}
function answerPayload() {
    return questions.value.map((question) => ({ question_id: question.id!, answer: answers[question.id!] ?? {} }));
}
function scheduleSave() {
    if (!active.value) return;
    saveState.value = "dirty";
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => void persistAnswers(), 900);
}
async function persistAnswers() {
    if (!submission.value || !active.value || saving.value || saveState.value === "saved") return;
    clearTimeout(saveTimer);
    saving.value = true; saveState.value = "saving";
    try {
        const result = (await saveSubmissionAnswers(submission.value.id, {
            version: submission.value.version, answers: answerPayload(),
        })).data.data;
        submission.value.version = result.version;
        savedAt.value = new Date(result.saved_at);
        saveState.value = "saved";
    } catch (exception) {
        saveState.value = "error";
        const code = (exception as { response?: { data?: { code?: string } } }).response?.data?.code;
        if (code === "VERSION_CONFLICT") await load();
        else toast.error(apiMessage(exception, "Chưa lưu được câu trả lời. Hãy thử lại."));
    } finally { saving.value = false; }
}
async function finish(auto = false) {
    if (!submission.value || submitting.value) return;
    if (saveState.value === "dirty" || saveState.value === "error") await persistAnswers();
    if (saveState.value === "error") return;
    submitting.value = true;
    try {
        submission.value = (await submitAssignment(submission.value.id)).data.data;
        if (assignment.value) {
            assignment.value.submissions = [submission.value, ...(assignment.value.submissions ?? []).filter((item) => item.id !== submission.value?.id)];
        }
        toast.success(auto ? "Hết giờ. Hệ thống đã nộp phần bài em đã làm." : "Đã nộp bài thành công.");
    } catch (exception) { toast.error(apiMessage(exception, "Không thể nộp bài.")); }
    finally { submitting.value = false; }
}
async function addFiles(event: Event) {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);
    if (!submission.value || !files.length) return;
    uploading.value = true;
    try {
        for (const file of files) {
            submission.value.files = [...(submission.value.files ?? []), (await uploadSubmissionFile(submission.value.id, file)).data.data];
        }
        toast.success("Đã đính kèm tệp vào bài làm.");
    } catch (exception) { toast.error(apiMessage(exception, "Tệp không hợp lệ hoặc đã vượt giới hạn 5 tệp.")); }
    finally { uploading.value = false; if (attachmentInput.value) attachmentInput.value.value = ""; }
}
async function removeFile(id: number) {
    try { await deleteSubmissionFile(id); if (submission.value) submission.value.files = (submission.value.files ?? []).filter((file) => file.id !== id); toast.success("Đã xóa tệp."); }
    catch (exception) { toast.error(apiMessage(exception, "Không thể xóa tệp.")); }
}
function confirmSubmit() {
    const unanswered = questions.value.length - answeredCount.value;
    Modal.confirm({
        title: "Nộp bài ngay?",
        content: unanswered ? `Em còn ${unanswered} câu chưa trả lời. Sau khi nộp, lượt làm này sẽ được khóa.` : "Sau khi nộp, em không thể sửa câu trả lời trong lượt này.",
        okText: "Nộp bài", cancelText: "Tiếp tục làm", async onOk() { await finish(); },
    });
}
function answerFor(question: AssignmentQuestion) {
    return submission.value?.answers?.find((item) => item.assignment_question_id === question.id);
}
onMounted(async () => {
    await load();
    clockTimer = setInterval(() => {
        now.value = Date.now();
        if (active.value && remainingSeconds.value === 0 && !submitting.value) void finish(true);
    }, 1000);
});
onBeforeUnmount(() => { clearTimeout(saveTimer); clearInterval(clockTimer); if (saveState.value === "dirty") void persistAnswers(); });
</script>

<template>
    <section class="take-page">
        <header class="take-topbar">
            <AButton type="text" @click="router.push('/child/assignments')"><ArrowLeft />Việc cần làm</AButton>
            <div v-if="active" class="save-indicator" aria-live="polite">
                <LoaderCircle v-if="saveState==='saving'" class="spin" /><Save v-else-if="saveState==='dirty'" /><Check v-else-if="saveState==='saved'" /><CircleAlert v-else-if="saveState==='error'" />
                <span>{{ saveState==='saving' ? 'Đang lưu…' : saveState==='dirty' ? 'Chưa lưu thay đổi mới' : saveState==='saved' ? 'Đã tự động lưu' : saveState==='error' ? 'Lưu chưa thành công' : 'Tự động lưu đã bật' }}</span>
            </div>
        </header>

        <div v-if="loading" class="take-loading"><span /><span /><span /></div>
        <div v-else-if="error || !assignment" class="take-state error" role="alert"><CircleAlert /><div><b>Không thể mở bài tập</b><p>{{ error }}</p></div><AButton @click="load"><RefreshCw />Thử lại</AButton></div>
        <template v-else>
            <section v-if="!active" class="assignment-intro">
                <span class="intro-mark"><BookOpenCheck /></span>
                <div class="intro-copy"><small>{{ assignment.targets?.[0]?.catechism_class?.name || 'Lớp giáo lý' }}</small><h1>{{ assignment.title }}</h1><p>{{ assignment.description || 'Đọc kỹ từng câu hỏi trước khi nộp bài.' }}</p></div>
                <dl><div><dt><CalendarClock />Hạn nộp</dt><dd>{{ formatDate(assignment.recipient?.due_at ?? assignment.due_at) }}</dd></div><div><dt><Clock3 />Thời gian</dt><dd>{{ assignment.time_limit_minutes ? `${assignment.time_limit_minutes} phút` : 'Không giới hạn' }}</dd></div><div><dt><RotateCcw />Số lượt</dt><dd>{{ assignment.allowed_attempts || 'Không giới hạn' }}</dd></div><div><dt><BookOpenCheck />Nội dung</dt><dd>{{ questions.length }} câu · {{ assignment.max_score }} điểm</dd></div></dl>
                <div v-if="waiting" class="result-state waiting"><Clock3 /><div><b>Em đã nộp bài</b><p>Kết quả sẽ hiển thị sau khi Giáo lý viên hoàn tất chấm và công bố.</p></div></div>
                <div v-else-if="released" class="released-result"><CheckCircle2 /><div><small>Kết quả lượt {{ submission?.attempt_number }}</small><strong>{{ submission?.final_score ?? 0 }}/{{ assignment.max_score }}</strong><p>{{ submission?.general_feedback || 'Đã hoàn thành bài tập.' }}</p></div></div>
                <AButton v-if="!waiting && !released && canStart" type="primary" size="large" :loading="starting" @click="start">Bắt đầu làm bài</AButton>
                <AButton v-else-if="released && canStart" size="large" :loading="starting" @click="start"><RotateCcw />Làm lượt mới</AButton>
            </section>

            <template v-else>
                <section class="take-heading">
                    <div><small>Lượt {{ submission?.attempt_number }}</small><h1>{{ assignment.title }}</h1><p>{{ assignment.description }}</p></div>
                    <div class="timer" :class="{ urgent:remainingSeconds!==null && remainingSeconds<=300 }"><Clock3 /><span><small>Thời gian còn lại</small><b>{{ timerText }}</b></span></div>
                </section>
                <div class="progress-track" role="progressbar" aria-label="Tiến độ làm bài" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress"><span :style="{transform:`scaleX(${progress/100})`}" /><small>{{ answeredCount }}/{{ questions.length }} câu đã trả lời</small></div>
                <main class="question-sheet">
                    <article v-for="(question,index) in questions" :key="question.id" class="take-question" :class="{ answered:hasAnswer(question.id!) }">
                        <header><span>{{ index+1 }}</span><div><small>{{ question.points }} điểm</small><h2>{{ question.prompt }}</h2></div><CheckCircle2 v-if="hasAnswer(question.id!)" /></header>
                        <div v-if="question.options" class="take-options">
                            <label v-for="(option,optionIndex) in question.options" :key="optionIndex" :class="{ selected:answers[question.id!]?.selected?.includes(optionIndex) }">
                                <input v-if="question.type!=='multiple_choice'" type="radio" :name="`answer-${question.id}`" :checked="answers[question.id!]?.selected?.includes(optionIndex)" @change="selectOne(question.id!,optionIndex)">
                                <input v-else type="checkbox" :checked="answers[question.id!]?.selected?.includes(optionIndex)" @change="toggleMany(question.id!,optionIndex,($event.target as HTMLInputElement).checked)">
                                <span>{{ String.fromCharCode(65+optionIndex) }}</span><b>{{ option.content }}</b>
                            </label>
                        </div>
                        <ATextarea v-else :value="answers[question.id!]?.text ?? ''" :rows="question.type==='essay'?7:2" :placeholder="question.type==='essay'?'Trình bày câu trả lời của em…':'Nhập câu trả lời…'" @update:value="updateText(question.id!,String($event))" />
                    </article>
                </main>
                <section class="submission-attachments"><header><div><h2>Tệp bài làm</h2><p>Không bắt buộc · tối đa 5 tệp, 20 MB/tệp</p></div><input ref="attachmentInput" hidden multiple type="file" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.mp3,.m4a,.wav,.mp4" @change="addFiles"><AButton :loading="uploading" :disabled="(submission?.files?.length ?? 0)>=5" @click="attachmentInput?.click()"><Upload />Thêm tệp</AButton></header><ul v-if="submission?.files?.length"><li v-for="file in submission.files" :key="file.id"><Paperclip /><a :href="file.download_url">{{ file.original_name }}</a><span>{{ Math.ceil(file.size/1024) }} KB</span><button type="button" aria-label="Xóa tệp" @click="removeFile(file.id)"><Trash2 /></button></li></ul><p v-else>Chưa có tệp đính kèm.</p></section>
                <footer class="take-footer"><div><span>{{ progress }}%</span><p>Kiểm tra lại câu trả lời trước khi nộp.</p></div><AButton :loading="saving" @click="persistAnswers"><Save />Lưu ngay</AButton><AButton type="primary" size="large" :loading="submitting" @click="confirmSubmit"><Send />Nộp bài</AButton></footer>
            </template>
        </template>
    </section>
</template>

<style scoped>
.take-page{display:grid;max-width:900px;margin:0 auto;gap:18px}.take-topbar{display:flex;min-height:42px;align-items:center;justify-content:space-between;gap:12px}.take-topbar>.ant-btn{display:flex;align-items:center;gap:6px;color:#475569}.take-topbar svg{width:16px}.save-indicator{display:flex;align-items:center;gap:6px;color:#64748b;font-size:10px}.save-indicator svg{width:14px;color:#16a34a}.save-indicator .spin{color:#2563eb;animation:spin 1s linear infinite}.save-indicator:has(svg:last-child){color:#b42318}.assignment-intro{display:grid;justify-items:center;border:1px solid #dbe3ee;border-radius:16px;background:#fff;padding:38px;text-align:center;box-shadow:0 16px 40px rgba(15,23,42,.05)}.intro-mark{display:grid;width:58px;height:58px;place-items:center;border-radius:14px;background:#eaf2ff;color:#2563eb}.intro-mark svg{width:28px}.intro-copy{max-width:68ch;margin-top:18px}.intro-copy small{color:#1d4ed8;font-size:10px;font-weight:700}.intro-copy h1{margin:7px 0 0;color:#172554;font-size:27px;font-weight:780;letter-spacing:-.03em;text-wrap:balance}.intro-copy p{margin:10px 0 0;color:#64748b;font-size:12px;line-height:1.7}.assignment-intro dl{display:grid;width:100%;grid-template-columns:repeat(4,1fr);margin:26px 0;border-block:1px solid #e7edf5}.assignment-intro dl>div{padding:15px 10px}.assignment-intro dl>div+div{border-left:1px solid #e7edf5}.assignment-intro dt{display:flex;align-items:center;justify-content:center;gap:5px;color:#64748b;font-size:9px}.assignment-intro dt svg{width:13px}.assignment-intro dd{margin:6px 0 0;color:#172554;font-size:11px;font-weight:700}.assignment-intro>.ant-btn{display:flex;min-width:180px;align-items:center;justify-content:center;gap:7px;border-radius:10px;font-weight:700}.result-state,.released-result{display:flex;width:100%;align-items:center;justify-content:center;gap:12px;margin-bottom:20px;border-radius:12px;padding:15px}.result-state{background:#fff8e8;color:#8a4608}.result-state svg,.released-result>svg{width:25px}.result-state b,.result-state p{display:block;margin:0}.result-state b{font-size:12px}.result-state p{margin-top:3px;font-size:10px}.released-result{background:#eaf8ef;color:#166534}.released-result small,.released-result strong,.released-result p{display:block}.released-result strong{margin-top:3px;font-size:25px;font-variant-numeric:tabular-nums}.released-result p{margin:4px 0 0;font-size:10px}.take-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:20px}.take-heading>div:first-child{min-width:0}.take-heading small{color:#1d4ed8;font-size:9px;font-weight:700}.take-heading h1{margin:5px 0 0;color:#172554;font-size:21px;font-weight:760;letter-spacing:-.02em}.take-heading p{margin:6px 0 0;color:#64748b;font-size:11px}.timer{display:flex;flex:none;align-items:center;gap:9px;border-radius:11px;background:#eff6ff;padding:10px 13px;color:#1d4ed8}.timer>svg{width:20px}.timer span small,.timer span b{display:block}.timer span small{color:#526f9c;font-size:8px}.timer span b{margin-top:2px;font-size:16px;font-variant-numeric:tabular-nums}.timer.urgent{background:#feeceb;color:#b42318}.timer.urgent span small{color:#9f4a42}.progress-track{position:relative;height:30px;overflow:hidden;border-radius:9px;background:#e7edf5}.progress-track>span{display:block;width:100%;height:100%;transform-origin:left;background:#2563eb;transition:transform .4s cubic-bezier(.16,1,.3,1)}.progress-track small{position:absolute;inset:0;display:grid;place-items:center;color:#172554;font-size:9px;font-weight:750;mix-blend-mode:multiply}.question-sheet{display:grid;gap:14px}.take-question{border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:20px;box-shadow:0 8px 24px rgba(15,23,42,.035)}.take-question.answered{border-color:#bfdbfe}.take-question>header{display:flex;align-items:flex-start;gap:12px}.take-question>header>span{display:grid;width:30px;height:30px;flex:none;place-items:center;border-radius:9px;background:#eef3f9;color:#475569;font-size:11px;font-weight:750}.take-question.answered>header>span{background:#eaf2ff;color:#1d4ed8}.take-question>header>div{min-width:0;flex:1}.take-question>header small{color:#64748b;font-size:8px}.take-question>header h2{margin:4px 0 0;color:#172554;font-size:14px;font-weight:700;line-height:1.55}.take-question>header>svg{width:18px;color:#16a34a}.take-options{display:grid;gap:8px;margin-top:16px;padding-left:42px}.take-options label{display:grid;grid-template-columns:18px 28px minmax(0,1fr);align-items:center;gap:9px;cursor:pointer;border:1px solid #dbe3ee;border-radius:10px;padding:11px 12px;transition:border-color .15s ease,background-color .15s ease}.take-options label:hover{border-color:#93c5fd;background:#fbfdff}.take-options label.selected{border-color:#60a5fa;background:#f3f7ff}.take-options input{accent-color:#2563eb}.take-options label>span{display:grid;width:26px;height:26px;place-items:center;border-radius:7px;background:#eef3f9;color:#52627c;font-size:9px;font-weight:750}.take-options label.selected>span{background:#2563eb;color:#fff}.take-options b{color:#334155;font-size:11px;font-weight:600}.take-question :deep(.ant-input){margin-top:16px;border-radius:10px;font-size:12px}.take-footer{position:sticky;z-index:6;bottom:10px;display:flex;align-items:center;gap:10px;border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:12px 14px;box-shadow:0 14px 36px rgba(15,23,42,.14)}.take-footer>div{min-width:0;flex:1}.take-footer>div span{color:#172554;font-size:12px;font-weight:750}.take-footer>div p{margin:2px 0 0;color:#64748b;font-size:9px}.take-footer>.ant-btn{display:flex;min-height:40px;align-items:center;gap:6px;border-radius:9px;font-weight:700}.take-footer svg{width:15px}.take-state{display:flex;min-height:280px;align-items:center;justify-content:center;gap:14px;border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:24px}.take-state>svg{width:34px;color:#dc2626}.take-state b{color:#991b1b}.take-state p{margin:4px 0 0;color:#64748b;font-size:11px}.take-state .ant-btn{display:flex;align-items:center;gap:6px}.take-state .ant-btn svg{width:14px}.take-loading{display:grid;gap:12px}.take-loading span{height:130px;border-radius:14px;background:linear-gradient(90deg,#eef2f7,#fff,#eef2f7);background-size:200% 100%;animation:loading 1.4s infinite}@keyframes spin{to{transform:rotate(360deg)}}@keyframes loading{to{background-position:-200% 0}}
.submission-attachments{border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:16px}.submission-attachments>header{display:flex;align-items:center;justify-content:space-between;gap:12px}.submission-attachments h2{margin:0;color:#172554;font-size:13px}.submission-attachments header p{margin:3px 0 0;color:#64748b;font-size:9px}.submission-attachments .ant-btn{display:flex;align-items:center;gap:6px;border-radius:8px}.submission-attachments .ant-btn svg{width:14px}.submission-attachments>p{margin:13px 0 0;color:#94a3b8;font-size:10px}.submission-attachments ul{display:grid;gap:6px;margin:12px 0 0;padding:0;list-style:none}.submission-attachments li{display:grid;grid-template-columns:18px minmax(0,1fr) auto 30px;align-items:center;gap:7px;border-top:1px solid #edf1f6;padding-top:7px}.submission-attachments li>svg{width:15px;color:#2563eb}.submission-attachments li a{overflow:hidden;color:#1d4ed8;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.submission-attachments li>span{color:#64748b;font-size:8px}.submission-attachments li button{display:grid;width:30px;height:30px;place-items:center;border:0;border-radius:7px;background:transparent;color:#b42318}.submission-attachments li button svg{width:13px}
@media(min-width:1600px){.take-page{max-width:1040px;gap:22px}.assignment-intro{padding:44px 52px}.take-heading{padding:24px 28px}.question-sheet{gap:18px}.take-question{padding:24px 28px}.take-question>header h2{max-width:72ch;font-size:15px}.take-options{gap:10px}.take-options label{padding:12px 14px}.take-options b{font-size:12px}.submission-attachments{padding:20px 24px}.take-footer{padding:14px 18px}}
@media(max-width:640px){.assignment-intro{padding:25px 16px}.intro-copy h1{font-size:22px}.assignment-intro dl{grid-template-columns:1fr 1fr}.assignment-intro dl>div:nth-child(3){border-left:0}.assignment-intro dl>div:nth-child(n+3){border-top:1px solid #e7edf5}.take-heading{align-items:stretch;flex-direction:column;padding:16px}.timer{justify-content:center}.take-question{padding:16px 13px}.take-options{padding-left:0}.submission-attachments>header{align-items:flex-start;flex-direction:column}.take-footer{bottom:6px;display:grid;grid-template-columns:1fr 1fr}.take-footer>div{grid-column:1/-1}.take-footer>.ant-btn{justify-content:center}.take-state{align-items:flex-start;flex-direction:column}}
@media(prefers-reduced-motion:reduce){.progress-track>span{transition:none}.spin,.take-loading span{animation:none}}
</style>

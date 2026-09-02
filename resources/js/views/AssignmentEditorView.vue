<script setup lang="ts">
import { computed, onMounted, reactive, ref, toRaw, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import AButton from "ant-design-vue/es/button";
import AInput from "ant-design-vue/es/input";
import ATextarea from "ant-design-vue/es/input/TextArea";
import AInputNumber from "ant-design-vue/es/input-number";
import ASelect from "ant-design-vue/es/select";
import ASwitch from "ant-design-vue/es/switch";
import {
    ArrowLeft, ArrowRight, Check, CheckCircle2, ChevronDown, ChevronUp,
    CircleAlert, CopyPlus, Eye, FileText, GripVertical, Paperclip, Plus, Save, Send, Trash2, Upload, UsersRound,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import { getClassChildren, getTeacherClasses } from "../api/teacher";
import { createAssignment, deleteAssignmentFile, getTeacherAssignment, publishAssignment, updateAssignment, uploadAssignmentFile } from "../api/learning";
import type { Child } from "../types/api";
import type { AssignmentInput, AssignmentQuestion, LearningFile, QuestionType } from "../types/learning";

const route = useRoute();
const router = useRouter();
const assignmentId = computed(() => Number(route.params.id) || null);
const step = ref(1);
const loading = ref(Boolean(assignmentId.value));
const saving = ref(false);
const uploading = ref(false);
const error = ref("");
const classes = ref<Array<{ id: number; name: string; code: string }>>([]);
const childrenByClass = reactive<Record<number, Child[]>>({});
const targetClassIds = ref<number[]>([]);
const targetChildren = reactive<Record<number, number[]>>({});
const questionTypeToAdd = ref<QuestionType>("single_choice");
const attachmentInput = ref<HTMLInputElement | null>(null);
const attachments = ref<LearningFile[]>([]);

const form = reactive<AssignmentInput>({
    title: "", description: "", type: "hybrid", max_score: 10, passing_score: 5,
    opens_at: null, due_at: null, time_limit_minutes: null, allowed_attempts: 1,
    score_method: "highest", allow_resume: true, allow_late: false, late_penalty_percent: 0,
    shuffle_questions: false, shuffle_options: false, allow_backtracking: true,
    result_release_mode: "manual", results_release_at: null, show_answers: true,
    targets: [], questions: [],
});
const steps = [
    { title: "Thông tin", hint: "Tên và mô tả" }, { title: "Câu hỏi", hint: "Xây dựng đề" },
    { title: "Người nhận", hint: "Lớp và thời gian" }, { title: "Thiết lập", hint: "Lượt làm, kết quả" },
    { title: "Xem trước", hint: "Kiểm tra và giao" },
];
const questionTypes: Array<{ value: QuestionType; label: string }> = [
    { value: "single_choice", label: "Một đáp án" }, { value: "multiple_choice", label: "Nhiều đáp án" },
    { value: "true_false", label: "Đúng / Sai" }, { value: "short_answer", label: "Trả lời ngắn" },
    { value: "essay", label: "Tự luận" },
];
const typeLabel = (type: QuestionType) => questionTypes.find((item) => item.value === type)?.label ?? type;
const totalPoints = computed(() => form.questions.reduce((sum, item) => sum + Number(item.points || 0), 0));
const selectedRecipientText = computed(() => targetClassIds.value.map((id) => {
    const item = classes.value.find((candidate) => candidate.id === id);
    const count = targetChildren[id]?.length ?? 0;
    return `${item?.name ?? "Lớp"}${count ? ` · ${count} em` : " · cả lớp"}`;
}));

function apiMessage(value: unknown, fallback: string) {
    const data = (value as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } }).response?.data;
    const firstFieldError = Object.values(data?.errors ?? {}).flat().find(Boolean);
    return firstFieldError ?? data?.message ?? fallback;
}
function localDateTime(value: string | null | undefined) {
    if (!value) return null;
    const date = new Date(value);
    const offset = date.getTimezoneOffset() * 60000;
    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}
function newQuestion(type: QuestionType): AssignmentQuestion {
    const base: AssignmentQuestion = { type, prompt: "", explanation: "", points: 1, position: form.questions.length + 1 };
    if (type === "single_choice") base.options = [{ content: "", is_correct: true }, { content: "", is_correct: false }];
    if (type === "multiple_choice") { base.options = [{ content: "", is_correct: true }, { content: "", is_correct: false }]; base.settings = { partial_credit: true }; }
    if (type === "true_false") base.options = [{ content: "Đúng", is_correct: true }, { content: "Sai", is_correct: false }];
    if (type === "short_answer") base.accepted_answers = [""];
    if (type === "essay") base.rubric = [{ label: "Nội dung", points: 1 }];
    return base;
}
function addQuestion() { form.questions.push(newQuestion(questionTypeToAdd.value)); }
function duplicateQuestion(index: number) {
    const clone = structuredClone(form.questions[index]);
    clone.id = undefined;
    form.questions.splice(index + 1, 0, clone);
    reindexQuestions();
}
function removeQuestion(index: number) { form.questions.splice(index, 1); reindexQuestions(); }
function moveQuestion(index: number, direction: -1 | 1) {
    const destination = index + direction;
    if (destination < 0 || destination >= form.questions.length) return;
    [form.questions[index], form.questions[destination]] = [form.questions[destination], form.questions[index]];
    reindexQuestions();
}
function reindexQuestions() { form.questions.forEach((item, index) => { item.position = index + 1; }); }
function addOption(question: AssignmentQuestion) { question.options ??= []; question.options.push({ content: "", is_correct: false }); }
function updateTimeLimit(value: unknown) { form.time_limit_minutes = value === null || value === undefined || value === "" ? null : Number(value); }
function setSingleCorrect(question: AssignmentQuestion, optionIndex: number) {
    question.options?.forEach((option, index) => { option.is_correct = index === optionIndex; });
}
async function loadChildren(classId: number) {
    if (childrenByClass[classId]) return;
    try { childrenByClass[classId] = (await getClassChildren(classId, { status: "studying" })).data.data; }
    catch { childrenByClass[classId] = []; }
}
function validateCurrentStep() {
    if (step.value === 1 && !form.title.trim()) return "Hãy nhập tên bài tập.";
    if (step.value === 2) {
        if (!form.questions.length) return "Hãy thêm ít nhất một câu hỏi.";
        if (form.questions.some((item) => !item.prompt.trim())) return "Mỗi câu hỏi cần có nội dung.";
        if (form.questions.some((item) => Number(item.points) <= 0)) return "Điểm câu hỏi phải lớn hơn 0.";
        for (const [index, question] of form.questions.entries()) {
            const number = index + 1;
            if (["single_choice", "multiple_choice", "true_false"].includes(question.type)) {
                const options = question.options ?? [];
                if (options.length < 2 || options.some((option) => !option.content.trim())) return `Câu ${number} cần ít nhất hai phương án có nội dung.`;
                const correctCount = options.filter((option) => option.is_correct).length;
                if ((question.type === "single_choice" || question.type === "true_false") && correctCount !== 1) return `Câu ${number} cần chọn đúng một đáp án.`;
                if (question.type === "multiple_choice" && correctCount < 1) return `Câu ${number} cần chọn ít nhất một đáp án đúng.`;
            }
            if (question.type === "short_answer" && !(question.accepted_answers ?? []).some((answer) => answer.trim())) return `Câu ${number} cần ít nhất một đáp án được chấp nhận.`;
        }
    }
    if (step.value === 3 && !targetClassIds.value.length) return "Hãy chọn ít nhất một lớp nhận bài.";
    if (step.value === 3 && form.opens_at && form.due_at && new Date(form.due_at) <= new Date(form.opens_at)) return "Hạn nộp phải sau thời điểm mở bài.";
    if (step.value === 4 && form.result_release_mode === "scheduled" && !form.results_release_at) return "Hãy chọn thời điểm công bố kết quả.";
    if (step.value === 4 && Number(form.passing_score) > Number(form.max_score)) return "Điểm đạt không thể lớn hơn thang điểm.";
    return "";
}
function next() {
    const message = validateCurrentStep();
    if (message) { toast.error(message); return; }
    step.value = Math.min(5, step.value + 1);
    window.scrollTo({ top: 0, behavior: "smooth" });
}
function payload(saveAsDraft = false): AssignmentInput {
    return {
        ...structuredClone(toRaw(form)),
        save_as_draft: saveAsDraft,
        targets: targetClassIds.value.map((id) => ({ catechism_class_id: id, child_ids: targetChildren[id] ?? [] })),
        questions: form.questions.map((item, index) => ({ ...item, position: index + 1 })),
    };
}
async function save(andPublish = false) {
    if (!andPublish) {
        if (!form.title.trim()) { step.value = 1; toast.error("Hãy nhập tên bài tập trước khi lưu bản nháp."); return; }
    } else {
        for (const targetStep of [1, 2, 3, 4]) {
            step.value = targetStep;
            const message = validateCurrentStep();
            if (message) { toast.error(message); return; }
        }
        step.value = 5;
    }
    saving.value = true;
    try {
        const response = assignmentId.value
            ? await updateAssignment(assignmentId.value, payload(!andPublish))
            : await createAssignment(payload(!andPublish));
        const saved = response.data.data;
        if (andPublish) {
            await publishAssignment(saved.id);
            toast.success("Đã phát hành bài tập. Thiếu nhi đã nhận được thông báo.");
        } else toast.success("Đã lưu bản nháp bài tập.");
        await router.push("/teacher/assignments");
    } catch (exception) { toast.error(apiMessage(exception, "Không thể lưu bài tập. Hãy kiểm tra các trường và thử lại.")); }
    finally { saving.value = false; }
}
async function addAttachments(event: Event) {
    const files = Array.from((event.target as HTMLInputElement).files ?? []);
    if (!assignmentId.value) { toast.info("Hãy lưu bản nháp trước khi thêm tệp."); return; }
    if (!files.length) return;
    uploading.value = true;
    try {
        for (const file of files) attachments.value.push((await uploadAssignmentFile(assignmentId.value, file)).data.data);
        toast.success("Đã thêm tệp vào bài tập.");
    } catch (exception) { toast.error(apiMessage(exception, "Tệp không hợp lệ hoặc đã vượt giới hạn 5 tệp.")); }
    finally { uploading.value = false; if (attachmentInput.value) attachmentInput.value.value = ""; }
}
async function removeAttachment(id: number) {
    try { await deleteAssignmentFile(id); attachments.value = attachments.value.filter((file) => file.id !== id); toast.success("Đã xóa tệp."); }
    catch (exception) { toast.error(apiMessage(exception, "Không thể xóa tệp.")); }
}
async function initialize() {
    try {
        const classResponse = await getTeacherClasses();
        classes.value = classResponse.data.data.map((item) => ({ id: item.id, name: item.name, code: item.code }));
        if (!assignmentId.value) { form.questions.push(newQuestion("single_choice")); return; }
        const assignment = (await getTeacherAssignment(assignmentId.value)).data.data;
        attachments.value = assignment.files ?? [];
        Object.assign(form, {
            ...assignment, description: assignment.description ?? "",
            opens_at: localDateTime(assignment.opens_at), due_at: localDateTime(assignment.due_at),
            results_release_at: localDateTime(assignment.results_release_at),
            questions: structuredClone(assignment.questions ?? []),
        });
        form.questions.forEach((item) => { item.explanation ??= ""; item.settings ??= {}; item.options ??= []; item.accepted_answers ??= []; item.rubric ??= []; });
        const targets = assignment.targets ?? [];
        targetClassIds.value = [...new Set(targets.map((item) => item.catechism_class_id))];
        for (const id of targetClassIds.value) {
            targetChildren[id] = targets.filter((item) => item.catechism_class_id === id).map((item) => item.child_id).filter((id): id is number => Boolean(id));
            await loadChildren(id);
        }
    } catch (exception) { error.value = apiMessage(exception, "Không thể tải dữ liệu bài tập."); }
    finally { loading.value = false; }
}
watch(targetClassIds, (ids) => ids.forEach(loadChildren), { deep: true });
onMounted(initialize);
</script>

<template>
    <section class="editor-page">
        <header class="editor-topbar">
            <AButton type="text" class="back-button" @click="router.push('/teacher/assignments')"><ArrowLeft />Danh sách bài tập</AButton>
            <div><h1>{{ assignmentId ? 'Chỉnh sửa bài tập' : 'Tạo bài tập mới' }}</h1><p>Bản nháp chỉ được gửi khi thầy/cô chủ động phát hành.</p></div>
            <AButton :loading="saving" @click="save(false)"><Save />Lưu bản nháp</AButton>
        </header>

        <nav class="stepper" aria-label="Các bước tạo bài tập">
            <button v-for="(item,index) in steps" :key="item.title" type="button" :class="{ active:step===index+1, done:step>index+1 }" :aria-label="`Bước ${index+1}: ${item.title}`" :aria-current="step===index+1?'step':undefined" @click="step=index+1">
                <span>{{ step>index+1 ? '✓' : index+1 }}</span><span><b>{{ item.title }}</b><small>{{ item.hint }}</small></span>
            </button>
        </nav>

        <div v-if="loading" class="editor-loading"><span /><span /><span /></div>
        <div v-else-if="error" class="editor-error" role="alert"><CircleAlert /><div><b>Không thể mở trình soạn</b><p>{{ error }}</p></div><AButton @click="initialize">Thử lại</AButton></div>
        <form v-else class="editor-surface" @submit.prevent>
            <section v-show="step===1" class="editor-step" aria-labelledby="step-info">
                <header><span><FileText /></span><div><h2 id="step-info">Thông tin bài tập</h2><p>Đặt tên rõ ràng để Thiếu nhi biết mình cần hoàn thành điều gì.</p></div></header>
                <div class="field-grid">
                    <label class="field wide"><span>Tên bài tập *</span><AInput v-model:value="form.title" size="large" :maxlength="255" placeholder="Ví dụ: Ôn tập Bài 5 – Các Bí tích" show-count /></label>
                    <label class="field wide"><span>Mô tả và hướng dẫn</span><ATextarea v-model:value="form.description" :rows="5" :maxlength="10000" placeholder="Yêu cầu, tài liệu cần chuẩn bị hoặc lưu ý khi làm bài…" /></label>
                    <label class="field"><span>Hình thức</span><ASelect v-model:value="form.type" :options="[{value:'quiz',label:'Trắc nghiệm'},{value:'hybrid',label:'Kết hợp trắc nghiệm và tự luận'},{value:'submission',label:'Bài nộp'}]" /></label>
                    <label class="field"><span>Thang điểm</span><AInputNumber v-model:value="form.max_score" :min="1" :max="1000" /></label>
                    <label class="field"><span>Điểm đạt</span><AInputNumber v-model:value="form.passing_score" :min="0" :max="form.max_score" /></label>
                    <div class="attachment-field wide"><div><span>Tệp đính kèm</span><small>PDF, Word, PowerPoint, Excel, ảnh, âm thanh hoặc video · tối đa 5 tệp, 20 MB/tệp</small></div><input ref="attachmentInput" type="file" hidden multiple accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.webp,.mp3,.m4a,.wav,.mp4" @change="addAttachments"><AButton :disabled="!assignmentId" :loading="uploading" @click="attachmentInput?.click()"><Upload />Thêm tệp</AButton><p v-if="!assignmentId">Lưu bản nháp một lần để bật tải tệp.</p><ul v-if="attachments.length"><li v-for="file in attachments" :key="file.id"><Paperclip /><a :href="file.download_url">{{ file.original_name }}</a><span>{{ Math.ceil(file.size/1024) }} KB</span><button type="button" aria-label="Xóa tệp" @click="removeAttachment(file.id)"><Trash2 /></button></li></ul></div>
                </div>
            </section>

            <section v-show="step===2" class="editor-step question-step" aria-labelledby="step-questions">
                <header><span><CopyPlus /></span><div><h2 id="step-questions">Xây dựng đề bài</h2><p>Kết hợp câu tự chấm và tự luận trong cùng một bài.</p></div><strong>{{ form.questions.length }} câu · {{ totalPoints }} điểm gốc</strong></header>
                <div class="question-toolbar"><ASelect v-model:value="questionTypeToAdd" :options="questionTypes" /><AButton type="primary" @click="addQuestion"><Plus />Thêm câu hỏi</AButton></div>
                <div v-if="!form.questions.length" class="question-empty"><CopyPlus /><b>Đề bài đang trống</b><p>Chọn một dạng câu hỏi ở trên để bắt đầu.</p></div>
                <article v-for="(question,index) in form.questions" :key="question.id ?? `new-${index}`" class="question-editor">
                    <header class="question-heading"><GripVertical /><span>Câu {{ index+1 }}</span><i>{{ typeLabel(question.type) }}</i><div><button type="button" :disabled="index===0" aria-label="Đưa câu hỏi lên" @click="moveQuestion(index,-1)"><ChevronUp /></button><button type="button" :disabled="index===form.questions.length-1" aria-label="Đưa câu hỏi xuống" @click="moveQuestion(index,1)"><ChevronDown /></button><button type="button" aria-label="Nhân đôi câu hỏi" @click="duplicateQuestion(index)"><CopyPlus /></button><button type="button" class="danger" aria-label="Xóa câu hỏi" @click="removeQuestion(index)"><Trash2 /></button></div></header>
                    <div class="question-body">
                        <label class="field wide"><span>Nội dung câu hỏi *</span><ATextarea v-model:value="question.prompt" :rows="2" :maxlength="5000" placeholder="Nhập câu hỏi…" /></label>
                        <label class="field points"><span>Điểm</span><AInputNumber v-model:value="question.points" :min="0.25" :step="0.25" /></label>
                        <div v-if="question.options" class="option-list wide"><span>Phương án trả lời · chọn đáp án đúng</span>
                            <div v-for="(option,optionIndex) in question.options" :key="optionIndex" class="option-row">
                                <input v-if="question.type!=='multiple_choice'" type="radio" :name="`correct-${index}`" :checked="option.is_correct" :aria-label="`Đặt phương án ${optionIndex+1} là đáp án đúng`" @change="setSingleCorrect(question,optionIndex)">
                                <input v-else v-model="option.is_correct" type="checkbox" :aria-label="`Phương án ${optionIndex+1} đúng`">
                                <AInput v-model:value="option.content" :disabled="question.type==='true_false'" :placeholder="`Phương án ${optionIndex+1}`" />
                                <button v-if="question.type!=='true_false' && (question.options?.length ?? 0)>2" type="button" aria-label="Xóa phương án" @click="question.options?.splice(optionIndex,1)"><Trash2 /></button>
                            </div>
                            <AButton v-if="question.type!=='true_false'" type="dashed" @click="addOption(question)"><Plus />Thêm phương án</AButton>
                        </div>
                        <div v-if="question.type==='short_answer'" class="accepted-list wide"><span>Đáp án được chấp nhận</span><div v-for="(_,answerIndex) in question.accepted_answers" :key="answerIndex"><AInput v-model:value="question.accepted_answers![answerIndex]" placeholder="Nhập đáp án" /><button type="button" aria-label="Xóa đáp án" @click="question.accepted_answers?.splice(answerIndex,1)"><Trash2 /></button></div><AButton type="dashed" @click="question.accepted_answers?.push('')"><Plus />Thêm cách viết</AButton></div>
                        <div v-if="question.type==='essay'" class="rubric-list wide"><span>Tiêu chí chấm</span><div v-for="(criterion,rubricIndex) in question.rubric" :key="rubricIndex"><AInput v-model:value="criterion.label" placeholder="Tên tiêu chí" /><AInputNumber v-model:value="criterion.points" :min="0" /><button type="button" aria-label="Xóa tiêu chí" @click="question.rubric?.splice(rubricIndex,1)"><Trash2 /></button></div><AButton type="dashed" @click="question.rubric?.push({label:'',points:0})"><Plus />Thêm tiêu chí</AButton></div>
                        <label class="field wide"><span>Giải thích đáp án <small>(hiện sau khi công bố nếu được bật)</small></span><ATextarea v-model:value="question.explanation" :rows="2" placeholder="Giải thích ngắn gọn…" /></label>
                    </div>
                </article>
            </section>

            <section v-show="step===3" class="editor-step" aria-labelledby="step-audience">
                <header><span><UsersRound /></span><div><h2 id="step-audience">Người nhận và thời gian</h2><p>Chọn nhiều lớp; để trống danh sách Thiếu nhi nghĩa là giao cho cả lớp.</p></div></header>
                <div class="field-grid">
                    <label class="field wide"><span>Lớp nhận bài *</span><ASelect v-model:value="targetClassIds" mode="multiple" :options="classes.map(item=>({value:item.id,label:`${item.name} · ${item.code}`}))" placeholder="Chọn một hoặc nhiều lớp" /></label>
                    <div v-for="classItem in classes.filter(item=>targetClassIds.includes(item.id))" :key="classItem.id" class="audience-row wide"><div><b>{{ classItem.name }}</b><small>{{ classItem.code }}</small></div><ASelect v-model:value="targetChildren[classItem.id]" mode="multiple" allow-clear :options="(childrenByClass[classItem.id] ?? []).map(child=>({value:child.id,label:`${child.full_name} · ${child.code}`}))" placeholder="Cả lớp" /></div>
                    <label class="field"><span>Mở bài lúc</span><input v-model="form.opens_at" class="native-input" type="datetime-local"></label>
                    <label class="field"><span>Hạn nộp</span><input v-model="form.due_at" class="native-input" type="datetime-local"></label>
                </div>
            </section>

            <section v-show="step===4" class="editor-step" aria-labelledby="step-settings">
                <header><span><CheckCircle2 /></span><div><h2 id="step-settings">Thiết lập lượt làm và kết quả</h2><p>Các mặc định phù hợp cho bài ôn tập thông thường; chỉ đổi khi cần.</p></div></header>
                <div class="setting-list">
                    <label><span><b>Số lượt làm</b><small>Đặt 0 nếu không giới hạn</small></span><AInputNumber v-model:value="form.allowed_attempts" :min="0" :max="20" /></label>
                    <label><span><b>Giới hạn thời gian</b><small>Để trống nếu không đếm giờ</small></span><AInputNumber :value="form.time_limit_minutes ?? undefined" :min="1" :max="480" addon-after="phút" @update:value="updateTimeLimit" /></label>
                    <label><span><b>Cách lấy điểm</b><small>Khi Thiếu nhi làm nhiều lượt</small></span><ASelect v-model:value="form.score_method" :options="[{value:'highest',label:'Điểm cao nhất'},{value:'latest',label:'Lượt gần nhất'},{value:'average',label:'Điểm trung bình'}]" /></label>
                    <label><span><b>Cho phép tiếp tục</b><small>Tự lưu và mở lại lượt đang làm</small></span><ASwitch v-model:checked="form.allow_resume" /></label>
                    <label><span><b>Cho phép nộp trễ</b><small>Gắn nhãn trễ và áp dụng mức trừ nếu có</small></span><ASwitch v-model:checked="form.allow_late" /></label>
                    <label v-if="form.allow_late"><span><b>Trừ điểm khi trễ</b><small>Tỷ lệ trên điểm cuối</small></span><AInputNumber v-model:value="form.late_penalty_percent" :min="0" :max="100" addon-after="%" /></label>
                    <label><span><b>Công bố kết quả</b><small>Thiếu nhi chưa thấy điểm trước thời điểm này</small></span><ASelect v-model:value="form.result_release_mode" :options="[{value:'manual',label:'Giáo lý viên công bố'},{value:'immediate',label:'Ngay sau khi chấm xong'},{value:'scheduled',label:'Theo lịch'}]" /></label>
                    <label v-if="form.result_release_mode==='scheduled'"><span><b>Thời điểm công bố</b></span><input v-model="form.results_release_at" class="native-input" type="datetime-local"></label>
                    <label><span><b>Hiện đáp án sau công bố</b><small>Kèm giải thích đã nhập trong đề</small></span><ASwitch v-model:checked="form.show_answers" /></label>
                    <label><span><b>Trộn thứ tự câu hỏi</b></span><ASwitch v-model:checked="form.shuffle_questions" /></label>
                    <label><span><b>Trộn phương án trả lời</b></span><ASwitch v-model:checked="form.shuffle_options" /></label>
                </div>
            </section>

            <section v-show="step===5" class="editor-step preview-step" aria-labelledby="step-preview">
                <header><span><Eye /></span><div><h2 id="step-preview">Kiểm tra trước khi giao</h2><p>Đây là những gì sẽ được phát hành đến Thiếu nhi.</p></div></header>
                <div class="preview-summary"><div><small>Tên bài tập</small><b>{{ form.title || 'Chưa nhập tên' }}</b><p>{{ form.description || 'Không có mô tả.' }}</p></div><dl><div><dt>Câu hỏi</dt><dd>{{ form.questions.length }} câu</dd></div><div><dt>Điểm gốc</dt><dd>{{ totalPoints }}</dd></div><div><dt>Thang điểm</dt><dd>{{ form.max_score }}</dd></div><div><dt>Lượt làm</dt><dd>{{ form.allowed_attempts || 'Không giới hạn' }}</dd></div></dl></div>
                <section class="preview-audience"><h3>Người nhận</h3><p v-if="!selectedRecipientText.length">Chưa chọn lớp.</p><span v-for="item in selectedRecipientText" :key="item"><UsersRound />{{ item }}</span></section>
                <ol class="preview-questions"><li v-for="(question,index) in form.questions" :key="index"><span>{{ index+1 }}</span><div><small>{{ typeLabel(question.type) }} · {{ question.points }} điểm</small><b>{{ question.prompt || 'Câu hỏi chưa có nội dung' }}</b></div></li></ol>
                <div class="publish-note"><Check /><p><b>Snapshot người nhận sẽ được tạo khi phát hành.</b> Việc chuyển lớp sau đó không làm mất lịch sử đã giao và bài nộp.</p></div>
            </section>

            <footer class="editor-footer">
                <AButton :disabled="step===1 || saving" @click="step--"><ArrowLeft />Quay lại</AButton>
                <div><AButton :loading="saving" @click="save(false)"><Save />Lưu bản nháp</AButton><AButton v-if="step<5" type="primary" @click="next">Tiếp tục<ArrowRight /></AButton><AButton v-else type="primary" :loading="saving" @click="save(true)"><Send />Phát hành bài tập</AButton></div>
            </footer>
        </form>
    </section>
</template>

<style scoped>
.editor-page{container-name:assignment-editor;container-type:inline-size}
.editor-page{display:grid;gap:18px}.editor-topbar{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;gap:20px}.editor-topbar>div{text-align:center}.editor-topbar h1{margin:0;color:#172554;font-size:21px;font-weight:760;letter-spacing:-.02em}.editor-topbar p{margin:4px 0 0;color:#64748b;font-size:11px}.editor-topbar>.ant-btn:last-child{justify-self:end}.editor-topbar .ant-btn{display:flex;align-items:center;gap:7px;border-radius:9px}.editor-topbar svg{width:16px}.back-button{justify-self:start;color:#475569}.stepper{display:grid;grid-template-columns:repeat(5,1fr);overflow:hidden;border:1px solid #dbe3ee;border-radius:14px;background:#fff}.stepper button{position:relative;display:flex;min-width:0;align-items:center;gap:10px;border:0;background:#fff;padding:14px 16px;text-align:left}.stepper button+button{border-left:1px solid #e7edf5}.stepper button>span:first-child{display:grid;width:28px;height:28px;flex:none;place-items:center;border:1px solid #cbd5e1;border-radius:50%;color:#64748b;font-size:11px;font-weight:750}.stepper b,.stepper small{display:block}.stepper b{color:#334155;font-size:11px}.stepper small{margin-top:2px;color:#94a3b8;font-size:9px}.stepper button.active{background:#f3f7ff}.stepper button.active>span:first-child{border-color:#2563eb;background:#2563eb;color:#fff}.stepper button.active b{color:#174f9f}.stepper button.done>span:first-child{border-color:#86efac;background:#e9f9ef;color:#15803d}.editor-surface{overflow:hidden;border:1px solid #dbe3ee;border-radius:14px;background:#fff;box-shadow:0 14px 36px rgba(15,23,42,.045)}.editor-step>header{display:flex;align-items:center;gap:13px;padding:20px 24px;border-bottom:1px solid #e7edf5}.editor-step>header>span{display:grid;width:42px;height:42px;flex:none;place-items:center;border-radius:10px;background:#eaf2ff;color:#2563eb}.editor-step>header svg{width:20px}.editor-step>header>div{min-width:0;flex:1}.editor-step h2{margin:0;color:#172554;font-size:17px;font-weight:750}.editor-step>header p{margin:4px 0 0;color:#64748b;font-size:11px}.editor-step>header>strong{color:#174f9f;font-size:11px}.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px;padding:24px}.field{display:grid;min-width:0;align-content:start;gap:7px}.field.wide,.wide{grid-column:1/-1}.field>span,.option-list>span,.accepted-list>span,.rubric-list>span{color:#475569;font-size:11px;font-weight:700}.field small{color:#94a3b8;font-weight:500}.field :deep(.ant-input),.field :deep(.ant-input-number),.field :deep(.ant-select),.field :deep(.ant-select-selector){width:100%;border-radius:9px!important}.field :deep(.ant-input-number),.field :deep(.ant-select-selector){min-height:40px}.native-input{width:100%;min-height:40px;border:1px solid #d9d9d9;border-radius:9px;background:#fff;padding:8px 11px;color:#334155;font:inherit;font-size:12px}.question-toolbar{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;background:#f8fafc;border-bottom:1px solid #e7edf5}.question-toolbar :deep(.ant-select){width:210px}.question-toolbar .ant-btn{display:flex;align-items:center;gap:6px}.question-toolbar svg{width:15px}.question-editor{margin:18px 20px;border:1px solid #dbe3ee;border-radius:12px;background:#fff}.question-heading{display:flex;align-items:center;gap:9px;padding:10px 12px;border-bottom:1px solid #e7edf5;background:#fbfdff}.question-heading>svg{width:16px;color:#94a3b8}.question-heading>span{color:#172554;font-size:12px;font-weight:750}.question-heading>i{border-radius:999px;background:#eef3f9;padding:3px 8px;color:#52627c;font-size:9px;font-style:normal;font-weight:650}.question-heading>div{display:flex;margin-left:auto}.question-heading button,.option-row>button,.accepted-list>div>button,.rubric-list>div>button{display:grid;width:32px;height:32px;place-items:center;border:0;border-radius:7px;background:transparent;color:#64748b}.question-heading button:hover:not(:disabled){background:#eaf2ff;color:#1d4ed8}.question-heading button.danger:hover{background:#feeceb;color:#b42318}.question-heading button:disabled{opacity:.3}.question-heading button svg,.option-row>button svg,.accepted-list button svg,.rubric-list button svg{width:14px}.question-body{display:grid;grid-template-columns:minmax(0,1fr) 100px;gap:16px;padding:18px}.option-list,.accepted-list,.rubric-list{display:grid;gap:8px}.option-row{display:grid;grid-template-columns:18px minmax(0,1fr) 32px;align-items:center;gap:8px}.option-row:has(button:only-child){grid-template-columns:18px minmax(0,1fr)}.option-row input{accent-color:#2563eb}.accepted-list>div{display:grid;grid-template-columns:minmax(0,1fr) 32px;gap:8px}.rubric-list>div{display:grid;grid-template-columns:minmax(0,1fr) 120px 32px;gap:8px}.question-empty{display:grid;min-height:250px;place-items:center;align-content:center;padding:24px;text-align:center}.question-empty svg{width:36px;color:#2563eb}.question-empty b{margin-top:10px;color:#172554}.question-empty p{margin:5px 0 0;color:#64748b;font-size:11px}.audience-row{display:grid;grid-template-columns:180px minmax(0,1fr);align-items:center;gap:16px;border:1px solid #dbe3ee;border-radius:11px;padding:12px}.audience-row b,.audience-row small{display:block}.audience-row b{color:#172554;font-size:12px}.audience-row small{margin-top:2px;color:#64748b;font-size:10px}.setting-list{display:grid}.setting-list>label{display:grid;grid-template-columns:minmax(0,1fr) 240px;align-items:center;gap:20px;padding:15px 24px;border-bottom:1px solid #edf1f6}.setting-list>label:last-child{border-bottom:0}.setting-list b,.setting-list small{display:block}.setting-list b{color:#334155;font-size:12px}.setting-list small{margin-top:4px;color:#64748b;font-size:10px}.setting-list :deep(.ant-input-number),.setting-list :deep(.ant-select),.setting-list>.native-input{width:240px}.setting-list :deep(.ant-switch){justify-self:end}.preview-summary{display:grid;grid-template-columns:minmax(0,1fr) 310px;gap:24px;padding:24px;border-bottom:1px solid #e7edf5}.preview-summary small{color:#64748b;font-size:10px}.preview-summary b{display:block;margin-top:6px;color:#172554;font-size:20px;letter-spacing:-.02em}.preview-summary p{max-width:65ch;margin:8px 0 0;color:#64748b;font-size:11px;line-height:1.65}.preview-summary dl{display:grid;grid-template-columns:1fr 1fr;margin:0;border:1px solid #dbe3ee;border-radius:11px}.preview-summary dl>div{padding:11px}.preview-summary dt{color:#64748b;font-size:9px}.preview-summary dd{margin:4px 0 0;color:#172554;font-size:12px;font-weight:700}.preview-audience{padding:18px 24px;border-bottom:1px solid #e7edf5}.preview-audience h3{margin:0 0 10px;color:#172554;font-size:12px}.preview-audience>span{display:inline-flex;align-items:center;gap:5px;margin:0 7px 7px 0;border-radius:999px;background:#eff6ff;padding:5px 9px;color:#1d4ed8;font-size:10px;font-weight:650}.preview-audience svg{width:13px}.preview-questions{display:grid;gap:0;margin:0;padding:0;list-style:none}.preview-questions li{display:flex;gap:12px;padding:14px 24px;border-bottom:1px solid #edf1f6}.preview-questions li>span{display:grid;width:26px;height:26px;flex:none;place-items:center;border-radius:8px;background:#eef3f9;color:#475569;font-size:10px;font-weight:750}.preview-questions small,.preview-questions b{display:block}.preview-questions small{color:#64748b;font-size:9px}.preview-questions b{margin-top:4px;color:#334155;font-size:11px}.publish-note{display:flex;align-items:flex-start;gap:10px;margin:18px 24px;border:1px solid #bfdbfe;border-radius:11px;background:#f3f7ff;padding:13px;color:#174f9f}.publish-note svg{width:17px;flex:none}.publish-note p{margin:0;font-size:10px;line-height:1.6}.editor-footer{position:sticky;z-index:6;bottom:0;display:flex;align-items:center;justify-content:space-between;gap:12px;border-top:1px solid #dbe3ee;background:#fff;padding:13px 20px;box-shadow:0 -8px 22px rgba(15,23,42,.04)}.editor-footer>div{display:flex;gap:8px}.editor-footer .ant-btn{display:flex;min-height:39px;align-items:center;gap:7px;border-radius:9px;font-weight:650}.editor-footer svg{width:15px}.editor-loading{display:grid;gap:1px}.editor-loading span{height:100px;border-radius:10px;background:linear-gradient(90deg,#eef2f7,#fff,#eef2f7);background-size:200% 100%;animation:loading 1.4s infinite}@keyframes loading{to{background-position:-200% 0}}.editor-error{display:flex;align-items:center;gap:14px;border:1px solid #fecaca;border-radius:14px;background:#fff;padding:24px}.editor-error>svg{width:28px;color:#dc2626}.editor-error>div{flex:1}.editor-error b{color:#991b1b}.editor-error p{margin:4px 0 0;color:#64748b;font-size:11px}
.attachment-field{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:8px;border:1px solid #dbe3ee;border-radius:11px;background:#fbfdff;padding:13px}.attachment-field>div>span,.attachment-field>div>small{display:block}.attachment-field>div>span{color:#475569;font-size:11px;font-weight:700}.attachment-field>div>small{margin-top:3px;color:#64748b;font-size:9px}.attachment-field>.ant-btn{display:flex;align-items:center;gap:6px;border-radius:8px}.attachment-field>.ant-btn svg{width:14px}.attachment-field>p{grid-column:1/-1;margin:0;color:#a94e08;font-size:9px}.attachment-field ul{display:grid;grid-column:1/-1;gap:5px;margin:4px 0 0;padding:0;list-style:none}.attachment-field li{display:grid;grid-template-columns:16px minmax(0,1fr) auto 28px;align-items:center;gap:7px;border-top:1px solid #e7edf5;padding-top:7px}.attachment-field li>svg{width:14px;color:#2563eb}.attachment-field li a{overflow:hidden;color:#1d4ed8;font-size:10px;text-overflow:ellipsis;white-space:nowrap}.attachment-field li>span{color:#64748b;font-size:8px}.attachment-field li button{display:grid;width:28px;height:28px;place-items:center;border:0;border-radius:7px;background:transparent;color:#b42318}.attachment-field li button svg{width:13px}
/* A slightly larger reading scale keeps this five-step editor legible on wide desktop displays. */
.editor-topbar h1{font-size:24px;line-height:1.2}.editor-topbar p{font-size:12px;line-height:1.45}.stepper b{font-size:12px;line-height:1.3}.stepper small{font-size:10px;line-height:1.35}.stepper button>span:first-child{font-size:12px}.editor-step h2{font-size:19px;line-height:1.3}.editor-step>header p,.editor-step>header>strong{font-size:12px;line-height:1.5}.field>span,.option-list>span,.accepted-list>span,.rubric-list>span{font-size:12px;line-height:1.45}.field :deep(.ant-input),.field :deep(.ant-input-number-input),.field :deep(.ant-select-selection-item),.field :deep(.ant-select-selection-placeholder),.native-input{font-size:13px}.question-heading>span{font-size:13px}.question-heading>i{font-size:10px}.question-empty p{font-size:12px}.audience-row b{font-size:13px}.audience-row small{font-size:11px}.setting-list b{font-size:13px}.setting-list small{font-size:11px;line-height:1.45}.preview-summary small{font-size:11px}.preview-summary p{font-size:12px}.preview-summary dt{font-size:10px}.preview-summary dd,.preview-audience h3{font-size:13px}.preview-audience>span{font-size:11px}.preview-questions small{font-size:10px}.preview-questions b{font-size:12px}.publish-note p{font-size:11px}.attachment-field>div>span{font-size:12px}.attachment-field>div>small,.attachment-field>p{font-size:10px}.attachment-field li a{font-size:11px}.attachment-field li>span{font-size:9px}
@media(min-width:1600px){.editor-page{width:100%;max-width:1480px;margin-inline:auto;gap:22px}.stepper button{padding:17px 20px}.editor-step>header{padding:22px 30px}.field-grid{gap:22px 28px;padding:28px 32px}.question-toolbar{padding:16px 28px}.question-editor{margin:22px 28px}.question-body{grid-template-columns:minmax(0,1fr) 140px;gap:20px;padding:22px}.audience-row{grid-template-columns:220px minmax(0,1fr);padding:14px 16px}.setting-list>label{grid-template-columns:minmax(0,1fr) 280px;padding:17px 32px}.setting-list :deep(.ant-input-number),.setting-list :deep(.ant-select),.setting-list>.native-input{width:280px}.preview-summary{grid-template-columns:minmax(0,1fr) 380px;gap:32px;padding:30px 32px}.preview-audience,.preview-questions li{padding-inline:32px}.editor-footer{padding:15px 28px}}
@media(max-width:900px){.editor-topbar{grid-template-columns:1fr auto}.editor-topbar>div{grid-column:1/-1;grid-row:1;text-align:left}.back-button{grid-column:1;grid-row:2}.editor-topbar>.ant-btn:last-child{grid-column:2;grid-row:2}.stepper{overflow-x:auto;grid-template-columns:repeat(5,minmax(145px,1fr))}.preview-summary{grid-template-columns:1fr}.setting-list>label{grid-template-columns:1fr 200px}.setting-list :deep(.ant-input-number),.setting-list :deep(.ant-select),.setting-list>.native-input{width:200px}}
@container assignment-editor (max-width:760px){.editor-topbar{grid-template-columns:1fr auto}.editor-topbar>div{grid-column:1/-1;grid-row:1;text-align:left}.back-button{grid-column:1;grid-row:2}.editor-topbar>.ant-btn:last-child{grid-column:2;grid-row:2}.stepper{overflow-x:auto;grid-template-columns:repeat(5,minmax(145px,1fr))}.preview-summary{grid-template-columns:1fr}.setting-list>label{grid-template-columns:1fr 200px}.setting-list :deep(.ant-input-number),.setting-list :deep(.ant-select),.setting-list>.native-input{width:200px}}
@media(max-width:640px){.field-grid{grid-template-columns:1fr;padding:16px}.field.wide,.wide{grid-column:auto}.editor-step>header{align-items:flex-start;padding:16px}.editor-step>header>span{width:36px;height:36px}.question-toolbar{display:grid;grid-template-columns:1fr auto;padding:12px}.question-toolbar :deep(.ant-select){width:100%}.question-editor{margin:12px}.question-body{grid-template-columns:1fr;padding:14px}.question-body .wide{grid-column:auto}.option-row{grid-template-columns:18px minmax(0,1fr) 32px}.rubric-list>div{grid-template-columns:minmax(0,1fr) 86px 32px}.audience-row{grid-template-columns:1fr}.setting-list>label{grid-template-columns:1fr;padding:14px 16px}.setting-list :deep(.ant-input-number),.setting-list :deep(.ant-select),.setting-list>.native-input{width:100%}.setting-list :deep(.ant-switch){justify-self:start}.preview-summary{padding:18px 16px}.preview-audience,.preview-questions li{padding-inline:16px}.editor-footer{align-items:stretch;flex-direction:column;padding:12px}.editor-footer>div{display:grid;grid-template-columns:1fr 1fr}.editor-footer .ant-btn{justify-content:center}.editor-footer>button{align-self:start}.publish-note{margin:14px 16px}}
.editor-page,.editor-page>*{min-width:0}.setting-list>label>span{min-width:0}.setting-list :deep(.ant-input-number-group-wrapper){width:240px;max-width:100%;min-width:0}.setting-list :deep(.ant-input-number-group){width:100%}.setting-list :deep(.ant-input-number-group-wrapper .ant-input-number){width:100%!important;min-width:0}.setting-list :deep(.ant-input-number-group-addon){white-space:nowrap}.stepper{scrollbar-width:thin;scrollbar-color:#cbd5e1 transparent}.question-heading{min-width:0}.question-heading>div{flex:none}.preview-summary>div{min-width:0}.preview-questions li>div{min-width:0}.preview-questions b{overflow-wrap:anywhere}
@container assignment-editor (max-width:1100px){.stepper button{gap:8px;padding:12px 10px}.setting-list>label{grid-template-columns:minmax(0,1fr) minmax(210px,240px)}.setting-list :deep(.ant-input-number),.setting-list :deep(.ant-input-number-group-wrapper),.setting-list :deep(.ant-select),.setting-list>.native-input{width:100%}}
@container assignment-editor (max-width:760px){.editor-page{gap:14px}.stepper{grid-template-columns:repeat(5,minmax(0,1fr));overflow:visible}.stepper button{justify-content:center;padding:11px 6px}.stepper small{display:none}.field-grid{gap:14px;padding:20px}.setting-list>label{grid-template-columns:minmax(0,1fr) minmax(180px,210px);gap:14px;padding-inline:20px}.question-editor{margin-inline:16px}.question-body{padding:16px}.preview-summary{gap:18px}}
@container assignment-editor (max-width:640px){.field-grid{grid-template-columns:1fr;padding:16px}.field.wide,.wide{grid-column:auto}.setting-list>label{grid-template-columns:1fr;padding:14px 16px}.setting-list :deep(.ant-input-number),.setting-list :deep(.ant-input-number-group-wrapper),.setting-list :deep(.ant-select),.setting-list>.native-input{width:100%}.attachment-field{grid-template-columns:1fr}.attachment-field>.ant-btn{width:100%;justify-content:center}.question-heading{flex-wrap:wrap}.question-heading>div{width:100%;justify-content:flex-end;border-top:1px solid #edf1f6;padding-top:6px}.editor-step>header{flex-wrap:wrap}.editor-step>header>strong{width:100%;padding-left:49px}.editor-footer>button{width:100%;align-self:stretch}}
@container assignment-editor (max-width:480px){.editor-topbar{grid-template-columns:1fr;gap:10px}.editor-topbar>div,.back-button,.editor-topbar>.ant-btn:last-child{grid-column:1}.editor-topbar>div{grid-row:1}.back-button{grid-row:2;justify-self:start}.editor-topbar>.ant-btn:last-child{grid-row:3;width:100%;justify-self:stretch;justify-content:center}.stepper button>span:nth-child(2){display:none}.question-toolbar{grid-template-columns:1fr}.question-toolbar .ant-btn{justify-content:center}.rubric-list>div{grid-template-columns:minmax(0,1fr) 76px 32px}.editor-footer>div{grid-template-columns:1fr}.attachment-field li{grid-template-columns:16px minmax(0,1fr) 28px}.attachment-field li>span{display:none}.preview-summary dl{grid-template-columns:1fr}.publish-note{align-items:flex-start}.editor-error{align-items:flex-start;flex-direction:column}.editor-error .ant-btn{width:100%}}
@media(prefers-reduced-motion:reduce){.editor-loading span{animation:none}}
</style>

<!--
THESIS: Bàn điều phối học tập đặt việc cần xử lý trước, danh mục bài tập sau.
PRIMARY ACTION: Tạo và giao bài tập.
HIERARCHY: Hàng đợi → bộ lọc/danh sách → panel chi tiết theo ngữ cảnh.
COMPOSITION: Các hàng dữ liệu rõ nhịp; desktop master-detail, mobile tuần tự.
VISUAL WORLD: Be Vietnam Pro, trắng/slate, xanh cho hành động; amber/đỏ chỉ cảnh báo thật.
STATES: Loading, lỗi có thử lại, empty có hành động, focus rõ và không cuộn ngang ở 360px.
-->
<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import AButton from "ant-design-vue/es/button";
import AInput from "ant-design-vue/es/input";
import AInputNumber from "ant-design-vue/es/input-number";
import ASelect from "ant-design-vue/es/select";
import AModal from "ant-design-vue/es/modal";
import {
    Archive, ArrowRight, BookOpenCheck, CalendarClock, CheckCircle2, CircleStop,
    CircleAlert, ClipboardCheck, Clock3, Download, FilePenLine, Plus, RefreshCw, Search,
    Send, UsersRound,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";
import DateTimePicker from "../components/ui/DateTimePicker.vue";
import { getTeacherClasses } from "../api/teacher";
import {
    archiveAssignment, assignmentReportExportUrl, changeAssignmentDueDate, closeAssignment,
    getTeacherAssignment, getTeacherAssignments, publishAssignment, setAssignmentAccommodation, withdrawAssignment,
} from "../api/learning";
import type { Assignment, AssignmentStatus } from "../types/learning";

const router = useRouter();
const assignments = ref<Assignment[]>([]);
const selectedId = ref<number | null>(null);
const classes = ref<Array<{ id: number; name: string; code: string }>>([]);
const loading = ref(true);
const actionId = ref<number | null>(null);
const error = ref("");
const search = ref("");
const status = ref<string>();
const classId = ref<number>();
const managementOpen = ref(false);
const managementMode = ref<"due" | "accommodation" | "withdraw">("due");
const managementDue = ref("");
const managementChildId = ref<number>();
const managementExtraAttempts = ref(1);
const managementReason = ref("");
const recipientOptions = ref<Array<{ value: number; label: string }>>([]);
let searchTimer: ReturnType<typeof setTimeout> | undefined;

const selected = computed(() => assignments.value.find((item) => item.id === selectedId.value) ?? assignments.value[0] ?? null);
const managementDueError = computed(() => {
    if (!managementDue.value) return "";
    const dueAt = new Date(managementDue.value).getTime();
    if (dueAt <= Date.now()) return "Hạn nộp mới phải sau thời điểm hiện tại.";
    if (selected.value?.opens_at && dueAt <= new Date(selected.value.opens_at).getTime()) {
        return "Hạn nộp mới phải sau thời gian mở bài.";
    }
    return "";
});
const drafts = computed(() => assignments.value.filter((item) => item.status === "draft" || item.status === "scheduled").length);
const waitingToGrade = computed(() => assignments.value.filter((item) =>
    (item.submissions_count ?? 0) > 0 && !["released", "archived", "withdrawn"].includes(item.status),
).length);
const statusOptions = [
    { value: "draft", label: "Bản nháp" }, { value: "scheduled", label: "Đã lên lịch" },
    { value: "published", label: "Đang mở" }, { value: "grading", label: "Đang chấm" },
    { value: "released", label: "Đã công bố" }, { value: "closed", label: "Đã đóng" },
];
const statusCopy: Record<AssignmentStatus, { label: string; tone: string }> = {
    draft: { label: "Bản nháp", tone: "neutral" }, scheduled: { label: "Đã lên lịch", tone: "blue" },
    published: { label: "Đang mở", tone: "green" }, closed: { label: "Đã đóng", tone: "neutral" },
    grading: { label: "Đang chấm", tone: "amber" }, released: { label: "Đã công bố", tone: "violet" },
    archived: { label: "Đã lưu trữ", tone: "neutral" }, withdrawn: { label: "Đã thu hồi", tone: "red" },
};

function apiMessage(value: unknown, fallback: string) {
    return (value as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;
}
function formatDate(value: string | null | undefined) {
    if (!value) return "Không giới hạn";
    return new Intl.DateTimeFormat("vi-VN", {
        day: "2-digit", month: "2-digit", year: "numeric", hour: "2-digit", minute: "2-digit", hour12: false,
    }).format(new Date(value));
}
function formatOpeningDate(value: string | null | undefined) {
    return value ? formatDate(value) : "Ngay khi phát hành";
}
function isOverdue(item: Assignment) {
    return Boolean(item.due_at && new Date(item.due_at) < new Date() && !["released", "archived"].includes(item.status));
}
async function load() {
    loading.value = true;
    error.value = "";
    try {
        const response = await getTeacherAssignments({ search: search.value || undefined, status: status.value, class_id: classId.value });
        assignments.value = response.data.data.data;
        if (!assignments.value.some((item) => item.id === selectedId.value)) selectedId.value = assignments.value[0]?.id ?? null;
    } catch (exception) {
        error.value = apiMessage(exception, "Không thể tải danh sách bài tập. Hãy kiểm tra kết nối và thử lại.");
    } finally { loading.value = false; }
}
async function loadClasses() {
    try {
        const response = await getTeacherClasses();
        classes.value = response.data.data.map((item) => ({ id: item.id, name: item.name, code: item.code }));
    } catch { /* Danh sách bài vẫn dùng được khi bộ lọc lớp lỗi. */ }
}
async function publish(item: Assignment) {
    actionId.value = item.id;
    try {
        await publishAssignment(item.id);
        toast.success("Đã phát hành bài tập đến đúng danh sách Thiếu nhi.");
        await load();
    } catch (exception) { toast.error(apiMessage(exception, "Không thể phát hành bài tập.")); }
    finally { actionId.value = null; }
}
function archive(item: Assignment) {
    AModal.confirm({
        title: item.status === "draft" ? "Xóa bản nháp này?" : "Lưu trữ bài tập này?",
        content: item.status === "draft" ? "Bản nháp chưa phát hành sẽ được xóa." : "Lịch sử người nhận và bài nộp vẫn được giữ lại.",
        okText: item.status === "draft" ? "Xóa bản nháp" : "Lưu trữ", cancelText: "Hủy", okType: "danger",
        async onOk() {
            actionId.value = item.id;
            try { await archiveAssignment(item.id); toast.success("Đã cập nhật bài tập."); await load(); }
            catch (exception) { toast.error(apiMessage(exception, "Không thể lưu trữ bài tập.")); }
            finally { actionId.value = null; }
        },
    });
}
function localDateTime(value: string | null | undefined) {
    if (!value) return "";
    const date = new Date(value);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, "0");
    const day = String(date.getDate()).padStart(2, "0");
    const hour = String(date.getHours()).padStart(2, "0");
    const minute = String(date.getMinutes()).padStart(2, "0");
    return `${year}-${month}-${day}T${hour}:${minute}`;
}
function managementDuePayload() {
    return managementDue.value || null;
}
async function openManagement(mode: "due" | "accommodation" | "withdraw") {
    if (!selected.value) return;
    managementMode.value = mode; managementDue.value = localDateTime(selected.value.due_at);
    managementChildId.value = undefined; managementExtraAttempts.value = 1; managementReason.value = "";
    if (mode === "accommodation") {
        try {
            const detail = (await getTeacherAssignment(selected.value.id)).data.data;
            recipientOptions.value = (detail.recipients ?? []).map((item) => ({ value: item.child_id, label: `${item.child?.full_name ?? 'Thiếu nhi'} · ${item.child?.code ?? ''}` }));
        } catch (exception) { toast.error(apiMessage(exception, "Không thể tải danh sách người nhận.")); return; }
    }
    managementOpen.value = true;
}
async function saveManagement() {
    if (!selected.value) return;
    const dueAt = managementDuePayload();
    if (managementMode.value === "due" && !dueAt) { toast.error("Hãy chọn hạn nộp mới."); return; }
    if (managementMode.value !== "withdraw" && managementDueError.value) { toast.error(managementDueError.value); return; }
    if (managementMode.value === "accommodation" && (!managementChildId.value || !managementReason.value.trim())) {
        toast.error("Hãy chọn Thiếu nhi và nhập lý do."); return;
    }
    if (managementMode.value === "withdraw" && !managementReason.value.trim()) { toast.error("Hãy nhập lý do thu hồi."); return; }
    actionId.value = selected.value.id;
    try {
        if (managementMode.value === "due") {
            await changeAssignmentDueDate(selected.value.id, dueAt!);
            toast.success("Đã đổi hạn và thông báo đến Thiếu nhi.");
        } else if (managementMode.value === "accommodation") {
            await setAssignmentAccommodation(selected.value.id, managementChildId.value!, { due_at: dueAt, extra_attempts: managementExtraAttempts.value, reason: managementReason.value });
            toast.success("Đã cấp ngoại lệ và thông báo cho Thiếu nhi.");
        } else {
            await withdrawAssignment(selected.value.id, managementReason.value);
            toast.success("Đã thu hồi bài tập.");
        }
        managementOpen.value = false; await load();
    } catch (exception) { toast.error(apiMessage(exception, "Không thể cập nhật bài tập.")); }
    finally { actionId.value = null; }
}
function closeCurrent(item: Assignment) {
    AModal.confirm({ title: "Đóng bài tập?", content: "Thiếu nhi sẽ không thể bắt đầu lượt làm mới. Các bài đã nộp vẫn được giữ để chấm.", okText: "Đóng bài", cancelText: "Hủy", async onOk() {
        actionId.value = item.id;
        try { await closeAssignment(item.id); toast.success("Đã đóng bài tập."); await load(); }
        catch (exception) { toast.error(apiMessage(exception, "Không thể đóng bài tập.")); }
        finally { actionId.value = null; }
    } });
}
function withdrawCurrent(item: Assignment) {
    selectedId.value = item.id;
    void openManagement("withdraw");
}
function scheduleSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(load, 300);
}
watch([status, classId], load);
onMounted(() => Promise.all([load(), loadClasses()]));
</script>

<template>
    <section class="assignment-page">
        <TeacherPageHeader title="Bài tập" description="Theo dõi việc cần xử lý, giao bài và công bố kết quả từ một nơi.">
            <template #actions><AButton type="primary" size="large" class="primary-action" @click="router.push('/teacher/assignments/new')"><Plus />Tạo bài tập</AButton></template>
        </TeacherPageHeader>

        <section class="work-queue" aria-labelledby="work-queue-title">
            <div class="queue-heading"><div><h2 id="work-queue-title">Việc cần xử lý</h2><p>Ưu tiên những bước đang chặn tiến độ của lớp.</p></div><span>{{ drafts + waitingToGrade }} việc</span></div>
            <div class="queue-items">
                <button type="button" @click="status='draft'">
                    <span class="queue-icon blue"><FilePenLine /></span><span><b>{{ drafts }} bản nháp</b><small>Hoàn thiện đề và người nhận</small></span><ArrowRight />
                </button>
                <button type="button" @click="router.push('/teacher/submissions')">
                    <span class="queue-icon amber"><ClipboardCheck /></span><span><b>{{ waitingToGrade }} bài có lượt nộp</b><small>Kiểm tra và chấm phần tự luận</small></span><ArrowRight />
                </button>
            </div>
        </section>

        <section class="assignment-register" aria-labelledby="assignment-register-title">
            <header class="register-header"><div><h2 id="assignment-register-title">Danh mục bài tập</h2><p>{{ assignments.length }} bài theo bộ lọc hiện tại</p></div></header>
            <div class="filters">
                <AInput v-model:value="search" allow-clear placeholder="Tìm theo tên bài tập" aria-label="Tìm bài tập" @input="scheduleSearch"><template #prefix><Search /></template></AInput>
                <ASelect v-model:value="status" allow-clear size="large" popup-class-name="assignment-select-dropdown" placeholder="Tất cả trạng thái" :options="statusOptions" aria-label="Lọc theo trạng thái" />
                <ASelect v-model:value="classId" allow-clear size="large" popup-class-name="assignment-select-dropdown" placeholder="Tất cả lớp" :options="classes.map(item=>({value:item.id,label:`${item.name} · ${item.code}`}))" aria-label="Lọc theo lớp" />
                <AButton :loading="loading" aria-label="Tải lại danh sách" @click="load"><RefreshCw /></AButton>
            </div>

            <div v-if="error" class="state-panel error-state" role="alert"><CircleAlert /><div><b>Chưa tải được bài tập</b><p>{{ error }}</p></div><AButton @click="load">Thử lại</AButton></div>
            <div v-else-if="loading" class="assignment-skeleton" aria-label="Đang tải bài tập"><span v-for="i in 5" :key="i" /></div>
            <div v-else-if="!assignments.length" class="state-panel empty-state"><BookOpenCheck /><div><b>Chưa có bài tập phù hợp</b><p>Điều chỉnh bộ lọc hoặc tạo bài tập đầu tiên cho lớp.</p></div><AButton type="primary" @click="router.push('/teacher/assignments/new')"><Plus />Tạo bài tập</AButton></div>
            <div v-else class="register-layout">
                <div class="assignment-list" role="list" aria-label="Danh sách bài tập">
                    <button v-for="item in assignments" :key="item.id" type="button" class="assignment-row" :class="{ selected: selected?.id===item.id }" @click="selectedId=item.id">
                        <span class="row-main"><span class="status-dot" :class="statusCopy[item.status].tone" /><span><b>{{ item.title }}</b><small>{{ item.targets?.map(target=>target.catechism_class?.name).filter(Boolean).join(', ') || 'Lớp đã giao' }}</small></span></span>
                        <span class="row-meta"><span class="status-badge" :class="statusCopy[item.status].tone">{{ statusCopy[item.status].label }}</span><small :class="{ overdue: isOverdue(item) }"><CalendarClock />{{ formatDate(item.due_at) }}</small></span>
                        <span class="row-count"><b>{{ item.submissions_count ?? 0 }}/{{ item.recipients_count ?? 0 }}</b><small>đã nộp</small></span>
                    </button>
                </div>

                <Transition name="detail-panel" mode="out-in">
                    <aside v-if="selected" :key="selected.id" class="assignment-detail" aria-label="Chi tiết bài tập đang chọn">
                        <div class="detail-title"><span class="status-badge" :class="statusCopy[selected.status].tone">{{ statusCopy[selected.status].label }}</span><h3>{{ selected.title }}</h3><p>{{ selected.description || 'Không có mô tả thêm.' }}</p></div>
                        <dl>
                            <div><dt><UsersRound />Người nhận</dt><dd>{{ selected.recipients_count ?? 0 }} Thiếu nhi</dd></div>
                            <div><dt><BookOpenCheck />Nội dung</dt><dd>{{ selected.questions_count ?? 0 }} câu · {{ selected.max_score }} điểm</dd></div>
                            <div><dt><Clock3 />Mở bài</dt><dd>{{ formatOpeningDate(selected.opens_at) }}</dd></div>
                            <div><dt><CalendarClock />Hạn nộp</dt><dd :class="{ overdue: isOverdue(selected) }">{{ formatDate(selected.due_at) }}</dd></div>
                            <div><dt><CheckCircle2 />Lượt nộp</dt><dd>{{ selected.submissions_count ?? 0 }} bài</dd></div>
                        </dl>
                        <div class="detail-actions">
                            <AButton v-if="selected.status==='draft'" type="primary" :loading="actionId===selected.id" @click="publish(selected)"><Send />Phát hành</AButton>
                            <AButton v-if="selected.status==='draft'" @click="router.push(`/teacher/assignments/${selected.id}/edit`)"><FilePenLine />Chỉnh sửa</AButton>
                            <AButton v-if="!['draft','archived','withdrawn'].includes(selected.status)" @click="openManagement('due')"><CalendarClock />Đổi hạn nộp</AButton>
                            <AButton v-if="!['draft','archived','withdrawn'].includes(selected.status)" @click="openManagement('accommodation')"><UsersRound />Ngoại lệ cá nhân</AButton>
                            <AButton v-if="(selected.submissions_count ?? 0)>0" type="primary" ghost @click="router.push(`/teacher/assignments/${selected.id}/submissions`)"><ClipboardCheck />Chấm bài</AButton>
                            <AButton v-if="['scheduled','published','grading'].includes(selected.status)" :loading="actionId===selected.id" @click="closeCurrent(selected)"><CircleStop />Đóng bài</AButton>
                            <a v-if="selected.status==='released'" class="ant-btn ant-btn-default" :href="assignmentReportExportUrl(selected.id)"><Download />Xuất kết quả</a>
                            <AButton v-if="!['draft','archived','withdrawn'].includes(selected.status)" danger :loading="actionId===selected.id" @click="withdrawCurrent(selected)">Thu hồi</AButton>
                            <AButton danger :loading="actionId===selected.id" @click="archive(selected)"><Archive />Lưu trữ</AButton>
                        </div>
                    </aside>
                </Transition>
            </div>
        </section>

        <AModal :open="managementOpen" centered wrap-class-name="assignment-management-modal" :title="managementMode==='due'?'Đổi hạn nộp':managementMode==='accommodation'?'Ngoại lệ cho Thiếu nhi':'Thu hồi bài tập'" :footer="null" :width="760" @cancel="managementOpen=false">
            <div class="management-form">
                <p v-if="managementMode==='due'">Hạn mới được cập nhật cho người nhận chưa có ngoại lệ riêng và hệ thống sẽ gửi thông báo.</p>
                <p v-else-if="managementMode==='accommodation'">Dùng khi một em cần thêm thời gian hoặc thêm lượt làm vì lý do cụ thể.</p>
                <p v-else>Bài tập sẽ ngừng hoạt động nhưng snapshot người nhận và toàn bộ bài nộp vẫn được lưu.</p>
                <section v-if="managementMode==='due' && selected" class="schedule-context" aria-label="Mốc thời gian hiện tại">
                    <div class="schedule-point open-point">
                        <span class="schedule-icon"><Clock3 /></span>
                        <span><small>Thời gian mở bài</small><strong>{{ formatOpeningDate(selected.opens_at) }}</strong><em>Thiếu nhi bắt đầu làm bài từ mốc này</em></span>
                    </div>
                    <ArrowRight class="schedule-direction" aria-hidden="true" />
                    <div class="schedule-point due-point">
                        <span class="schedule-icon"><CalendarClock /></span>
                        <span><small>Hạn nộp hiện tại</small><strong>{{ formatDate(selected.due_at) }}</strong><em>Bài cần được nộp trước mốc này</em></span>
                    </div>
                </section>
                <label v-if="managementMode==='accommodation'">
                    <span>Thiếu nhi *</span>
                    <ASelect v-model:value="managementChildId" show-search size="large" popup-class-name="assignment-select-dropdown" :options="recipientOptions" placeholder="Chọn người nhận" />
                </label>
                <div v-if="managementMode!=='withdraw'" class="date-field">
                    <span>{{ managementMode==='due' ? 'Chọn hạn nộp mới *' : 'Gia hạn đến' }}</span>
                    <DateTimePicker v-model="managementDue" date-label="Chọn ngày hạn nộp" time-label="Chọn giờ hạn nộp" summary-label="Hạn nộp mới" :disabled="actionId===selected?.id" />
                    <small v-if="managementDueError" class="field-error">{{ managementDueError }}</small>
                    <small v-else class="field-hint">Hạn nộp phải sau thời gian mở bài và thời điểm hiện tại.</small>
                </div>
                <label v-if="managementMode==='accommodation'"><span>Số lượt làm thêm</span><AInputNumber v-model:value="managementExtraAttempts" :min="0" :max="20" /></label>
                <label v-if="managementMode!=='due'"><span>Lý do *</span><AInput v-model:value="managementReason" :maxlength="1000" placeholder="Ghi rõ lý do để lưu lịch sử" /></label>
                <footer><AButton @click="managementOpen=false">Hủy</AButton><AButton :type="managementMode==='withdraw'?'default':'primary'" :danger="managementMode==='withdraw'" :loading="actionId===selected?.id" @click="saveManagement">{{ managementMode==='due'?'Cập nhật hạn':managementMode==='accommodation'?'Lưu ngoại lệ':'Thu hồi bài tập' }}</AButton></footer>
            </div>
        </AModal>
    </section>
</template>

<style scoped>
.assignment-page{display:grid;gap:20px}.primary-action{min-height:42px;border-radius:10px;font-weight:650}.primary-action svg,.filters svg,.detail-actions svg{width:16px;height:16px}.work-queue,.assignment-register{overflow:hidden;border:1px solid #dbe3ee;border-radius:14px;background:#fff;box-shadow:0 10px 30px rgba(15,23,42,.045)}.queue-heading,.register-header{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:18px 20px;border-bottom:1px solid #e7edf5}.queue-heading h2,.register-header h2{margin:0;color:#172554;font-size:17px;font-weight:750;letter-spacing:-.015em}.queue-heading p,.register-header p{margin:4px 0 0;color:#64748b;font-size:12px}.queue-heading>span{border-radius:999px;background:#eff6ff;padding:5px 10px;color:#1d4ed8;font-size:11px;font-weight:700}.queue-items{display:grid;grid-template-columns:1fr 1fr}.queue-items button{display:flex;min-width:0;align-items:center;gap:12px;border:0;background:#fff;padding:16px 20px;text-align:left;transition:background-color .16s ease}.queue-items button+button{border-left:1px solid #e7edf5}.queue-items button:hover{background:#f8fbff}.queue-items button>span:nth-child(2){min-width:0;flex:1}.queue-items b,.queue-items small{display:block}.queue-items b{color:#172554;font-size:14px}.queue-items small{margin-top:3px;color:#64748b;font-size:11px}.queue-items>button>svg{width:17px;color:#94a3b8}.queue-icon{display:grid;width:40px;height:40px;flex:none;place-items:center;border-radius:10px}.queue-icon svg{width:19px}.queue-icon.blue{background:#eaf2ff;color:#2563eb}.queue-icon.amber{background:#fff5dc;color:#b45309}.filters{display:grid;grid-template-columns:minmax(240px,1fr) 180px 220px auto;align-items:center;gap:10px;padding:14px 16px;border-bottom:1px solid #e7edf5}.filters :deep(.ant-select){width:100%}.filters :deep(.ant-input-affix-wrapper),.filters :deep(.ant-select-selector),.filters :deep(.ant-btn){min-height:40px;border-radius:9px!important}.filters :deep(.ant-select-selector){display:flex;align-items:center}.filters :deep(.ant-select-selection-item),.filters :deep(.ant-select-selection-placeholder){line-height:38px!important}.filters :deep(.ant-btn){display:grid;width:48px;padding:0;place-items:center}.filters :deep(.ant-input-prefix svg){width:16px;color:#94a3b8}.register-layout{display:grid;grid-template-columns:minmax(0,1.55fr) minmax(290px,.8fr);min-height:420px}.assignment-list{min-width:0;border-right:1px solid #e7edf5}.assignment-row{display:grid;width:100%;grid-template-columns:minmax(0,1fr) 150px 72px;align-items:center;gap:16px;border:0;border-bottom:1px solid #edf1f6;background:#fff;padding:15px 18px;text-align:left;transition:background-color .15s ease,box-shadow .15s ease}.assignment-row:hover{background:#fbfdff}.assignment-row.selected{background:#f3f7ff;box-shadow:inset 3px 0 #2563eb}.row-main{display:flex;min-width:0;align-items:flex-start;gap:10px}.row-main>span:last-child{min-width:0}.row-main b,.row-main small,.row-count b,.row-count small{display:block}.row-main b{overflow:hidden;color:#172554;font-size:13px;font-weight:700;text-overflow:ellipsis;white-space:nowrap}.row-main small{overflow:hidden;margin-top:4px;color:#64748b;font-size:11px;text-overflow:ellipsis;white-space:nowrap}.status-dot{width:8px;height:8px;flex:none;margin-top:5px;border-radius:50%;background:#94a3b8}.status-dot.blue{background:#3b82f6}.status-dot.green{background:#16a34a}.status-dot.amber{background:#d97706}.status-dot.violet{background:#7c3aed}.status-dot.red{background:#dc2626}.row-meta{display:grid;justify-items:start;gap:6px}.row-meta small{display:flex;align-items:center;gap:5px;color:#64748b;font-size:10px;font-variant-numeric:tabular-nums}.row-meta small svg{width:13px}.status-badge{display:inline-flex;min-height:23px;align-items:center;border-radius:999px;background:#f1f5f9;padding:3px 8px;color:#475569;font-size:10px;font-weight:700}.status-badge.blue{background:#eaf2ff;color:#1d4ed8}.status-badge.green{background:#e9f9ef;color:#15803d}.status-badge.amber{background:#fff5dc;color:#a94e08}.status-badge.violet{background:#f2edff;color:#6d28d9}.status-badge.red{background:#feeceb;color:#b42318}.row-count{text-align:right}.row-count b{color:#172554;font-size:13px;font-variant-numeric:tabular-nums}.row-count small{margin-top:3px;color:#64748b;font-size:10px}.overdue{color:#b42318!important;font-weight:650}.assignment-detail{align-self:start;padding:22px}.detail-title h3{margin:12px 0 0;color:#172554;font-size:20px;font-weight:760;letter-spacing:-.02em;text-wrap:balance}.detail-title p{margin:8px 0 0;color:#64748b;font-size:12px;line-height:1.65}.assignment-detail dl{display:grid;gap:0;margin:20px 0;border-block:1px solid #e7edf5}.assignment-detail dl>div{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0}.assignment-detail dl>div+div{border-top:1px solid #edf1f6}.assignment-detail dt{display:flex;align-items:center;gap:7px;color:#64748b;font-size:11px}.assignment-detail dt svg{width:15px;color:#94a3b8}.assignment-detail dd{margin:0;color:#172554;font-size:11px;font-weight:650;text-align:right}.detail-actions{display:grid;grid-template-columns:1fr;gap:8px}.detail-actions :deep(.ant-btn),.detail-actions>a{display:flex;min-height:39px;align-items:center;justify-content:center;gap:7px;border-radius:9px;font-size:12px;font-weight:650}.state-panel{display:flex;min-height:240px;align-items:center;justify-content:center;gap:14px;padding:28px;text-align:left}.state-panel>svg{width:38px;height:38px;flex:none;color:#2563eb}.state-panel b{color:#172554;font-size:15px}.state-panel p{max-width:55ch;margin:5px 0 0;color:#64748b;font-size:12px;line-height:1.6}.state-panel .ant-btn{margin-left:12px}.error-state>svg{color:#dc2626}.assignment-skeleton{display:grid;gap:1px;background:#edf1f6}.assignment-skeleton span{height:68px;background:linear-gradient(90deg,#fff 20%,#f3f6fa 50%,#fff 80%);background-size:200% 100%;animation:skeleton 1.4s infinite}@keyframes skeleton{to{background-position:-200% 0}}.detail-panel-enter-active,.detail-panel-leave-active{transition:opacity .16s ease,transform .28s cubic-bezier(.16,1,.3,1)}.detail-panel-enter-from,.detail-panel-leave-to{opacity:0;transform:translateX(10px)}
.management-form{display:grid;gap:18px;padding-top:4px}.management-form>p{margin:0;color:#64748b;font-size:13px;line-height:1.65}.management-form label,.date-field{display:grid;gap:8px}.management-form label>span,.date-field>span{color:#334155;font-size:13px;font-weight:750}.management-form :deep(.ant-select),.management-form :deep(.ant-input-number){width:100%}.management-form :deep(.ant-select-selector),.management-form :deep(.ant-input-number),.management-form :deep(.ant-input){min-height:44px;border-radius:10px!important;font-size:14px}.management-form :deep(.ant-select-selector){display:flex;align-items:center}.management-form :deep(.ant-select-selection-item),.management-form :deep(.ant-select-selection-placeholder){font-size:14px;line-height:42px!important}.schedule-context{display:grid;grid-template-columns:minmax(0,1fr) 24px minmax(0,1fr);align-items:center;gap:10px;border:1px solid #dbe7f6;border-radius:13px;background:#f8fbff;padding:15px}.schedule-point{display:flex;min-width:0;align-items:flex-start;gap:11px}.schedule-point>span:last-child{display:grid;min-width:0;gap:3px}.schedule-icon{display:grid;width:38px;height:38px;flex:none;place-items:center;border-radius:10px;background:#eaf2ff;color:#2563eb}.schedule-icon svg{width:18px;height:18px}.due-point .schedule-icon{background:#eef2f7;color:#52627c}.schedule-point small{color:#64748b;font-size:10px;font-weight:750;letter-spacing:.04em;text-transform:uppercase}.schedule-point strong{overflow:hidden;color:#172554;font-size:14px;font-weight:780;text-overflow:ellipsis;white-space:nowrap;font-variant-numeric:tabular-nums}.schedule-point em{color:#64748b;font-size:11px;font-style:normal;line-height:1.45}.schedule-direction{width:18px;color:#94a3b8}.date-field{border-top:1px solid #e7edf5;padding-top:17px}.date-field :deep(.date-time-picker){margin-top:2px}.field-hint,.field-error{font-size:11px;line-height:1.5}.field-hint{color:#64748b}.field-error{color:#b42318;font-weight:650}.management-form footer{display:flex;justify-content:flex-end;gap:10px;margin-top:0;padding-top:15px;border-top:1px solid #e7edf5}.management-form footer .ant-btn{min-width:112px;min-height:42px;border-radius:9px;font-size:13px;font-weight:650}
@media(min-width:1536px){.assignment-page{gap:24px}.queue-heading,.register-header{padding:20px 24px}.queue-items button{padding:18px 24px}.queue-items b{font-size:15px}.queue-items small{font-size:12px}.filters{grid-template-columns:minmax(320px,1fr) 210px 250px auto;padding:16px 20px}.register-layout{grid-template-columns:minmax(0,1.75fr) minmax(380px,.75fr);min-height:max(520px,calc(100dvh - 390px))}.assignment-row{grid-template-columns:minmax(0,1fr) 190px 88px;gap:20px;padding:17px 22px}.row-main b{font-size:14px}.row-main small{font-size:12px}.row-meta small,.status-badge,.row-count small{font-size:11px}.row-count b{font-size:14px}.assignment-detail{padding:28px}.assignment-detail dt,.assignment-detail dd{font-size:12px}.detail-actions{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:960px){.filters{grid-template-columns:1fr 1fr}.filters>:first-child{grid-column:1/-1}.register-layout{grid-template-columns:1fr}.assignment-list{border-right:0}.assignment-detail{border-top:1px solid #e7edf5}.detail-actions{grid-template-columns:repeat(2,1fr)}}
@media(max-width:640px){.assignment-page{gap:14px}.queue-items{grid-template-columns:1fr}.queue-items button+button{border-top:1px solid #e7edf5;border-left:0}.filters{grid-template-columns:1fr;padding:12px}.filters>:first-child{grid-column:auto}.assignment-row{grid-template-columns:minmax(0,1fr) auto;gap:10px;padding:14px}.row-meta{grid-column:1}.row-count{grid-column:2;grid-row:1/3}.assignment-detail{padding:18px 14px}.detail-actions{grid-template-columns:1fr}.state-panel{align-items:flex-start;flex-direction:column}.state-panel .ant-btn{margin-left:0}.schedule-context{grid-template-columns:1fr;gap:12px;padding:13px}.schedule-direction{transform:rotate(90deg);justify-self:start;margin-left:10px}.schedule-point strong{white-space:normal}.management-form footer{display:grid;grid-template-columns:1fr 1fr}.management-form footer .ant-btn{width:100%;margin:0}}
@media(prefers-reduced-motion:reduce){.assignment-row,.queue-items button,.detail-panel-enter-active,.detail-panel-leave-active{transition:none}.assignment-skeleton span{animation:none}}
</style>

<style>
.assignment-management-modal .ant-modal-content{overflow:hidden;border:1px solid #e2e8f0;border-radius:16px;padding:24px;box-shadow:0 24px 70px rgba(15,23,42,.2)}
.assignment-management-modal .ant-modal-header{margin-bottom:10px}
.assignment-management-modal .ant-modal-title{color:#172554;font-size:19px;font-weight:780;letter-spacing:-.015em}
.assignment-management-modal .ant-modal-body{max-height:calc(100dvh - 150px);overflow-y:auto;overscroll-behavior:contain;padding-right:3px;scrollbar-color:#cbd5e1 transparent;scrollbar-width:thin}
.assignment-page .filters .ant-input-affix-wrapper,.assignment-page .filters .ant-select-selector,.assignment-page .filters .ant-btn{min-height:44px;border-radius:10px!important}.assignment-page .filters .ant-input,.assignment-page .filters .ant-select-selection-item,.assignment-page .filters .ant-select-selection-placeholder{font-size:14px}.assignment-page .filters .ant-select-selection-item,.assignment-page .filters .ant-select-selection-placeholder{line-height:42px!important}
.assignment-select-dropdown .ant-select-item{min-height:42px;padding:10px 12px;font-size:14px;line-height:1.5}
.assignment-select-dropdown .ant-select-item-option-content{overflow:hidden;text-overflow:ellipsis}
.assignment-select-dropdown .ant-select-item-option-selected:not(.ant-select-item-option-disabled){background:#eaf2ff;color:#1d4ed8;font-weight:700}
@media(max-width:640px){.assignment-management-modal .ant-modal{max-width:calc(100vw - 20px);margin:10px auto;padding-bottom:0}.assignment-management-modal .ant-modal-content{padding:20px 15px}.assignment-management-modal .ant-modal-body{max-height:calc(100dvh - 100px)}}
</style>

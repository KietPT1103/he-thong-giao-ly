<!--
THESIS: Điểm danh là một bàn làm việc tức thời, không phải biểu mẫu dài; lớp, phiên và trạng thái phải đọc được trong một lượt quét.
OWN-WORLD: Nền slate sáng, mặt bàn trắng, xanh hệ thống cho hành động và màu ngữ nghĩa tiết chế cho từng trạng thái chuyên cần.
STORY: GLV chọn lớp và phiên, rà từng em, xử lý ngoại lệ rồi lưu với một điểm kết thúc rõ ràng.
FIRST VIEWPORT: Ngữ cảnh và hành động mở phiên ở trên, ba chỉ số thật, tiếp theo là bảng điểm danh rộng với footer thao tác sticky.
FORM: Operate-mode attendance workspace, bám sát ảnh tham khảo và hệ thống dashboard hiện tại.
-->
<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import AModal from "ant-design-vue/es/modal";
import ASelect from "ant-design-vue/es/select";
import { CalendarCheck2, CalendarDays, Check, ClipboardCheck, Clock3, Info, MonitorUp, Plus, QrCode, RotateCcw, Save, Settings2, ShieldCheck, Square, TrendingUp, UsersRound } from "lucide-vue-next";
import QrcodeVue from "qrcode.vue";
import { toast } from "vue-sonner";
import { cancelAttendanceSession, createAttendanceSession, deleteAttendanceSession, endAttendanceSession, getAttendanceWorkspace, saveAttendance } from "../api/teacher";
import { createQrForAttendanceSession, type AttendanceSessionQrPayload } from "../api/qr";
import AttendanceStatusSelect from "../components/attendance/AttendanceStatusSelect.vue";
import AttendanceSectionNav from "../components/attendance/AttendanceSectionNav.vue";
import AttendanceSessionActions from "../components/attendance/AttendanceSessionActions.vue";
import DateTimePicker from "../components/ui/DateTimePicker.vue";
import type { AttendanceSession, AttendanceStatus, Child } from "../types/api";

type Row = Child & { attendanceStatus: AttendanceStatus };
type SessionAction = "cancel" | "delete";
const route = useRoute();
const router = useRouter();
const classes = ref<Array<{ id: number; name: string; code: string }>>([]);
const classId = ref<number | "">("");
const sessions = ref<AttendanceSession[]>([]);
const sessionId = ref<number | "">("");
const rows = ref<Row[]>([]);
const originalStatuses = ref(new Map<number, AttendanceStatus>());
const loading = ref(true);
const sessionsTotal = ref(0);
const saving = ref(false);
const error = ref("");
const showCreate = ref(false);
const heldAt = ref(new Date().toISOString().slice(0, 16));
const showQr = ref(false);
const qrPayload = ref<AttendanceSessionQrPayload | null>(null);
const qrPresentationPanel = ref<HTMLElement | null>(null);
const actionSessionId = ref<number | null>(null);
const actionKind = ref<SessionAction | null>(null);

const selectedClass = computed(() => classes.value.find(item => item.id === Number(classId.value)));
const selectedSession = computed(() => sessions.value.find(item => item.id === Number(sessionId.value)));
const classOptions = computed(() => classes.value.map(item => ({ value: item.id, label: `${item.name} · ${item.code}` })));
const sessionOptions = computed(() => sessions.value.map(item => ({ value: item.id, label: `${formatSession(item.held_at)} · ${sessionStatusLabel(item.status)}` })));
const attendedCount = computed(() => rows.value.filter(row => row.attendanceStatus === "present" || row.attendanceStatus === "late").length);
const recordedCount = computed(() => rows.value.filter(row => row.attendanceStatus !== "unknown").length);
const attendanceRate = computed(() => rows.value.length ? Math.round((attendedCount.value / rows.value.length) * 100) : 0);
const isDirty = computed(() => rows.value.some(row => originalStatuses.value.get(row.id) !== row.attendanceStatus));
const isActive = computed(() => selectedSession.value?.status === "active");
const qrValue = computed(() => qrPayload.value ? new URL(qrPayload.value.scan_url, window.location.origin).toString() : "");
const qrSessionTime = computed(() => qrPayload.value ? new Intl.DateTimeFormat("vi-VN", { hour: "2-digit", minute: "2-digit", hour12: false }).format(new Date(qrPayload.value.session.held_at)) : "—");
const qrSessionDate = computed(() => qrPayload.value ? new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "long", year: "numeric" }).format(new Date(qrPayload.value.session.held_at)) : "—");

function formatSession(value: string) {
    return new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}
function sessionStatusLabel(status: AttendanceSession["status"]) {
    if (status === "active") return "Đang diễn ra";
    if (status === "cancelled") return "Đã hủy";
    return "Đã kết thúc";
}
function initials(name: string) {
    return name.split(" ").filter(Boolean).slice(-2).map(word => word[0]).join("").toUpperCase();
}
async function loadClasses() {
    loading.value = true;
    error.value = "";
    try {
        const requested = Number(route.query.class);
        if (typeof route.query.held_at === "string") heldAt.value = new Date(route.query.held_at).toISOString().slice(0, 16);
        await loadWorkspace(Number.isFinite(requested) && requested > 0 ? requested : undefined);
    } catch {
        error.value = "Không thể tải dữ liệu điểm danh.";
        loading.value = false;
    }
}
async function loadWorkspace(requestedClassId?: number) {
    loading.value = true;
    sessionId.value = "";
    rows.value = [];
    originalStatuses.value = new Map();
    try {
        const workspace = (await getAttendanceWorkspace(requestedClassId)).data.data;
        classes.value = workspace.classes;
        classId.value = workspace.selected_class_id ?? "";
        if (classId.value && String(route.query.class ?? "") !== String(classId.value)) {
            void router.replace({ query: { ...route.query, class: String(classId.value) } });
        }
        sessions.value = workspace.sessions.data ?? [];
        sessionsTotal.value = workspace.session_history_total ?? workspace.sessions.total ?? sessions.value.length;
        rows.value = workspace.children.map(child => ({ ...child, attendanceStatus: "unknown" }));
        const requestedSession = Number(route.query.session);
        const selected = sessions.value.find(item => item.id === requestedSession) ?? sessions.value.find(item => item.status === "active");
        if (selected) { sessionId.value = selected.id; selectSession(); if (route.query.qr === "1") await openQr(); }
        if (route.query.new === "1" && !sessions.value.some(item => item.status === "active")) showCreate.value = true;
    } catch { error.value = "Không thể tải lớp hoặc các phiên điểm danh."; }
    finally { loading.value = false; }
}
function selectSession() {
    if (!sessionId.value) return;
    const session = sessions.value.find(item => item.id === Number(sessionId.value));
    if (!session) return;
    const byChild = new Map(session.attendances.map(item => [item.child_id, item.status]));
    rows.value = rows.value.map(row => ({ ...row, attendanceStatus: byChild.get(row.id) ?? "unknown" }));
    originalStatuses.value = new Map(rows.value.map(row => [row.id, row.attendanceStatus]));
}
async function createSession() {
    if (!classId.value || saving.value) return;
    saving.value = true;
    error.value = "";
    try {
        const created = (await createAttendanceSession(Number(classId.value), { held_at: new Date(heldAt.value).toISOString() })).data.data;
        sessions.value.unshift(created);
        sessionsTotal.value += 1;
        sessionId.value = created.id;
        rows.value = rows.value.map(row => ({ ...row, attendanceStatus: "unknown" }));
        originalStatuses.value = new Map(rows.value.map(row => [row.id, row.attendanceStatus]));
        showCreate.value = false;
        toast.success("Đã mở phiên điểm danh.");
    } catch (exception) {
        const response = (exception as { response?: { data?: { code?: string; message?: string; data?: AttendanceSession } } }).response?.data;
        if (response?.code === "ACTIVE_ATTENDANCE_SESSION_EXISTS" && response.data) {
            if (!sessions.value.some(item => item.id === response.data!.id)) sessions.value.unshift(response.data);
            sessionId.value = response.data.id;
            selectSession();
            showCreate.value = false;
            toast.info("Đã chuyển đến phiên điểm danh đang mở.");
            return;
        }
        error.value = response?.message || "Không thể tạo phiên điểm danh.";
        toast.error(error.value);
    } finally { saving.value = false; }
}
async function save() {
    if (!sessionId.value || saving.value) return;
    saving.value = true;
    error.value = "";
    try {
        await saveAttendance(Number(sessionId.value), rows.value.map(row => ({ child_id: row.id, status: row.attendanceStatus })));
        originalStatuses.value = new Map(rows.value.map(row => [row.id, row.attendanceStatus]));
        toast.success("Đã lưu điểm danh vào hệ thống.");
    } catch {
        error.value = "Không thể lưu điểm danh. Vui lòng thử lại.";
        toast.error(error.value);
    } finally { saving.value = false; }
}
function markAll() { rows.value = rows.value.map(row => ({ ...row, attendanceStatus: "present" })); }
function discardChanges() { rows.value = rows.value.map(row => ({ ...row, attendanceStatus: originalStatuses.value.get(row.id) ?? "unknown" })); }
function closeCreateSession() { if (!saving.value) showCreate.value = false; }
function openSessionManager() {
    if (classId.value) void router.push({ path: "/teacher/attendance/sessions", query: { class: classId.value } });
}
function beginAction(session: AttendanceSession, kind: SessionAction) {
    actionSessionId.value = session.id;
    actionKind.value = kind;
}
function finishAction() {
    actionSessionId.value = null;
    actionKind.value = null;
}
function confirmCancel(session: AttendanceSession) {
    AModal.confirm({
        title: "Hủy phiên điểm danh?",
        content: `Phiên ${formatSession(session.held_at)} sẽ chuyển sang Đã hủy. Dữ liệu đã ghi nhận vẫn được giữ lại và QR sẽ ngừng hoạt động.`,
        okText: "Hủy phiên", cancelText: "Quay lại", okButtonProps: { danger: true },
        async onOk() {
            beginAction(session, "cancel");
            try {
                const cancelled = (await cancelAttendanceSession(session.id)).data.data;
                sessions.value = sessions.value.filter(item => item.id !== cancelled.id);
                sessionId.value = "";
                rows.value = rows.value.map(row => ({ ...row, attendanceStatus: "unknown" }));
                originalStatuses.value = new Map(rows.value.map(row => [row.id, row.attendanceStatus]));
                qrPayload.value = null;
                showQr.value = false;
                toast.success("Đã hủy phiên điểm danh.");
            } catch { toast.error("Không thể hủy phiên điểm danh."); }
            finally { finishAction(); }
        },
    });
}
function confirmDelete(session: AttendanceSession) {
    AModal.confirm({
        title: "Xóa phiên điểm danh?",
        content: `Phiên ${formatSession(session.held_at)} và toàn bộ dữ liệu điểm danh của phiên sẽ bị xóa vĩnh viễn. Thao tác này không thể hoàn tác.`,
        okText: "Xóa phiên", cancelText: "Quay lại", okButtonProps: { danger: true },
        async onOk() {
            beginAction(session, "delete");
            try {
                await deleteAttendanceSession(session.id);
                sessions.value = sessions.value.filter(item => item.id !== session.id);
                sessionsTotal.value = Math.max(0, sessionsTotal.value - 1);
                const nextSession = sessions.value.find(item => item.status === "active") ?? sessions.value[0];
                sessionId.value = nextSession?.id ?? "";
                if (nextSession) selectSession();
                else {
                    rows.value = rows.value.map(row => ({ ...row, attendanceStatus: "unknown" }));
                    originalStatuses.value = new Map(rows.value.map(row => [row.id, row.attendanceStatus]));
                }
                const query = { ...route.query };
                delete query.session;
                void router.replace({ query });
                qrPayload.value = null;
                showQr.value = false;
                toast.success("Đã xóa phiên điểm danh.");
            } catch { toast.error("Không thể xóa phiên điểm danh."); }
            finally { finishAction(); }
        },
    });
}
async function openQr() {
    if (!sessionId.value || !isActive.value) { toast.info("Hãy mở một phiên điểm danh trước khi tạo QR."); return; }
    saving.value = true;
    try { qrPayload.value = (await createQrForAttendanceSession(Number(sessionId.value), new Date(Date.now() + 20 * 60_000).toISOString())).data.data; showQr.value = true; }
    catch (exception) { toast.error((exception as {response?:{data?:{message?:string}}}).response?.data?.message || "Không thể tạo mã QR."); }
    finally { saving.value = false; }
}
async function presentQr() {
    if (!qrPresentationPanel.value) return;
    try {
        if (document.fullscreenElement) await document.exitFullscreen();
        else await qrPresentationPanel.value.requestFullscreen();
    } catch {
        toast.error("Trình duyệt không thể mở chế độ trình chiếu QR.");
    }
}
async function endSession() {
    if (!sessionId.value || !isActive.value) return;
    saving.value = true;
    try {
        const ended = (await endAttendanceSession(Number(sessionId.value))).data.data;
        sessions.value = sessions.value.filter(item => item.id !== ended.id);
        sessionId.value = "";
        rows.value = rows.value.map(row => ({ ...row, attendanceStatus: "unknown" }));
        originalStatuses.value = new Map(rows.value.map(row => [row.id, row.attendanceStatus]));
        qrPayload.value = null;
        showQr.value = false;
        toast.success("Đã kết thúc phiên điểm danh.");
    }
    catch { toast.error("Không thể kết thúc phiên điểm danh."); }
    finally { saving.value = false; }
}

onMounted(loadClasses);
</script>

<template>
    <section class="attendance-page">
        <AttendanceSectionNav attached />

        <AAlert v-if="error" type="error" show-icon closable :message="error" @close="error=''" />

        <div v-if="loading" class="attendance-metrics attendance-metrics-loading" aria-busy="true" aria-label="Đang tải tổng quan điểm danh">
            <article v-for="item in 3" :key="item"><span /><div><i /><i /></div></article>
        </div>
        <div v-else class="attendance-metrics" aria-label="Tổng quan điểm danh">
            <article><span class="metric-icon metric-blue"><UsersRound /></span><div><strong>{{ rows.length }}</strong><small>thiếu nhi</small></div></article>
            <article><span class="metric-icon metric-green"><CalendarCheck2 /></span><div><strong>{{ sessionsTotal }}</strong><small>phiên điểm danh</small></div></article>
            <article><span class="metric-icon metric-violet"><TrendingUp /></span><div><strong>{{ attendanceRate }}%</strong><small>Tỷ lệ có mặt <Info title="Tính cả Có mặt và Đi trễ" /></small></div></article>
        </div>

        <section class="attendance-workspace">
            <div class="attendance-controls">
                <label>Lớp được phân công<ASelect v-model:value="classId" show-search option-filter-prop="label" placeholder="Chọn lớp" :loading="loading" :disabled="loading || saving" :options="classOptions" @change="loadWorkspace(Number($event))" /></label>
                <label>Phiên điểm danh<ASelect v-model:value="sessionId" placeholder="Chưa có phiên đang diễn ra" not-found-content="Không có phiên đang diễn ra" :loading="loading" :options="sessionOptions" :disabled="loading || !classId || saving" @change="selectSession" /></label>
                <div class="session-management-actions">
                    <AttendanceSessionActions v-if="selectedSession" :session="selectedSession" :show-view="false" :busy-action="actionSessionId===selectedSession.id?actionKind:null" @cancel="confirmCancel" @delete="confirmDelete" />
                    <AButton class="manage-sessions-button" :disabled="loading || !classId || saving" @click="openSessionManager"><Settings2 />Quản lý phiên</AButton>
                </div>
                <div class="attendance-control-actions">
                    <AButton class="mark-all-button" :disabled="loading || !sessionId || !isActive || !rows.length || saving" @click="markAll"><Check />Tất cả có mặt</AButton>
                    <AButton v-if="sessionId" class="session-action" :disabled="loading || !isActive || saving" @click="openQr"><QrCode />Tạo QR</AButton>
                    <AButton type="primary" class="open-session-button" :disabled="loading || !classId || saving" @click="showCreate=true"><Plus />Mở phiên mới</AButton>
                </div>
            </div>

            <div v-if="loading" class="attendance-loading" aria-busy="true" aria-label="Đang tải dữ liệu điểm danh"><span v-for="item in 6" :key="item" /></div>
            <div v-else-if="!classes.length" class="teacher-empty-state"><ClipboardCheck class="teacher-empty-icon text-slate-400" /><h3>Chưa có lớp phụ trách</h3><p>Liên hệ quản trị viên để được phân công trước khi điểm danh.</p></div>
            <div v-else-if="!sessionId" class="teacher-empty-state"><ClipboardCheck class="teacher-empty-icon text-slate-400" /><h3>Chưa có phiên đang diễn ra</h3><p>Mở phiên mới cho lớp {{ selectedClass?.name }} để bắt đầu ghi nhận điểm danh.</p></div>
            <template v-else>
                <div class="attendance-table" role="table" aria-label="Danh sách điểm danh">
                    <div class="attendance-table-head" role="row"><span>STT</span><span>Thiếu nhi</span><span>Trạng thái điểm danh</span></div>
                    <div v-for="(student,index) in rows" :key="student.id" class="attendance-row" role="row">
                        <span class="row-number">{{ index + 1 }}</span>
                        <div class="student-cell"><span class="student-avatar" :class="`avatar-${index%5}`">{{ initials(student.full_name) }}</span><div><b>{{ student.full_name }}</b><small>{{ student.code }}<template v-if="student.saint_name"> · {{ student.saint_name }}</template></small></div></div>
                        <AttendanceStatusSelect v-model="student.attendanceStatus" :disabled="saving || !isActive" :student-name="student.full_name" />
                    </div>
                </div>
                <footer class="attendance-footer">
                    <div class="session-summary"><span><CalendarDays /></span><div><small>Phiên điểm danh</small><strong>{{ selectedSession ? formatSession(selectedSession.held_at) : '—' }}</strong></div><i /><div><small>Đã ghi nhận</small><strong>{{ recordedCount }}/{{ rows.length }} thiếu nhi</strong></div></div>
                    <div class="footer-actions"><AButton v-if="isActive" danger :disabled="saving" @click="endSession"><Square />Kết thúc phiên</AButton><AButton :disabled="!isDirty || saving" @click="discardChanges"><RotateCcw />Hủy thay đổi</AButton><AButton type="primary" :disabled="!sessionId || !isActive || !isDirty" :loading="saving" @click="save"><Save />Lưu điểm danh</AButton></div>
                </footer>
            </template>
        </section>

        <AModal :open="showCreate" centered title="Mở phiên điểm danh mới" :footer="null" width="720px" wrap-class-name="create-attendance-session-modal" :closable="!saving" :keyboard="!saving" :mask-closable="false" @cancel="closeCreateSession">
            <div class="create-session-intro"><strong>Ngày và giờ bắt đầu <span>*</span></strong><p>Chọn thời điểm bắt đầu cho phiên điểm danh.</p></div>
            <DateTimePicker v-model="heldAt" :disabled="saving" />
            <footer class="create-session-actions"><AButton size="large" :disabled="saving" @click="closeCreateSession">Hủy</AButton><AButton type="primary" size="large" :loading="saving" :disabled="!heldAt" @click="createSession">Tạo phiên</AButton></footer>
        </AModal>
        <AModal v-model:open="showQr" centered title="QR điểm danh" :footer="null" width="900px" wrap-class-name="attendance-qr-modal">
            <section v-if="qrPayload" ref="qrPresentationPanel" class="qr-session-modal" aria-label="Mã QR và thông tin phiên điểm danh">
                <div class="qr-code-card" aria-label="Mã QR điểm danh">
                    <QrcodeVue :value="qrValue" :size="340" level="H" />
                </div>
                <div class="qr-session-details">
                    <article class="qr-class-card">
                        <span class="qr-detail-icon qr-detail-icon-shield"><ShieldCheck /></span>
                        <div><strong>{{ qrPayload.session.class.name }}</strong><small>{{ qrPayload.session.class.code }}</small></div>
                        <span class="qr-live-status"><i />Đang mở</span>
                    </article>
                    <article class="qr-detail-card">
                        <span class="qr-detail-icon"><Clock3 /></span>
                        <div><small>Thời gian</small><strong>{{ qrSessionTime }}</strong></div>
                    </article>
                    <article class="qr-detail-card">
                        <span class="qr-detail-icon"><CalendarDays /></span>
                        <div><small>Ngày</small><strong>{{ qrSessionDate }}</strong></div>
                    </article>
                    <article class="qr-detail-card">
                        <span class="qr-detail-icon qr-detail-icon-green"><UsersRound /></span>
                        <div><small>Đã có mặt</small><strong>{{ attendedCount }}/{{ rows.length }} thiếu nhi</strong></div>
                    </article>
                    <p class="qr-expiry-note"><Info />QR tự hết hạn sau 20 phút hoặc ngay khi kết thúc phiên.</p>
                </div>
                <footer class="qr-modal-actions">
                    <AButton @click="showQr=false">Đóng</AButton>
                    <AButton type="primary" @click="presentQr"><MonitorUp />Trình chiếu QR</AButton>
                </footer>
            </section>
        </AModal>
    </section>
</template>

<style scoped>
.attendance-page{display:grid;width:100%;min-width:0;gap:16px}.open-session-button{min-width:148px;border-radius:10px}.open-session-button svg{width:16px;height:16px}.attendance-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.attendance-metrics article{display:flex;min-height:94px;align-items:center;gap:14px;border:1px solid #e2e8f0;border-radius:12px;background:#fff;padding:16px 20px}.metric-icon{display:grid;width:48px;height:48px;flex:none;place-items:center;border-radius:11px}.metric-icon svg{width:21px;height:21px;stroke-width:1.9}.metric-blue{background:#eaf2ff;color:#2563eb}.metric-green{background:#e8faef;color:#16a34a}.metric-violet{background:#f2edff;color:#7c3aed}.attendance-metrics strong,.attendance-metrics small{display:block}.attendance-metrics strong{color:#0b214d;font-size:17px;font-weight:760;font-variant-numeric:tabular-nums}.attendance-metrics small{margin-top:5px;color:#64748b;font-size:11px}.attendance-metrics small svg{display:inline;width:12px;height:12px;margin-left:3px;vertical-align:-2px}.attendance-workspace{min-width:0;overflow:hidden;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 12px 32px rgba(15,23,42,.045)}.attendance-controls{display:grid;grid-template-columns:minmax(220px,1fr) minmax(220px,1fr) auto;align-items:end;gap:16px;padding:20px 24px;border-bottom:1px solid #e2e8f0}.attendance-controls label{display:grid;gap:7px;color:#475569;font-size:11px;font-weight:700}.attendance-controls :deep(.ant-select){width:100%}.attendance-controls :deep(.ant-select-selector){min-height:42px;border-radius:9px!important;padding-inline:12px!important}.attendance-controls :deep(.ant-select-selection-item),.attendance-controls :deep(.ant-select-selection-placeholder){line-height:40px!important;font-size:12px}.mark-all-button{min-width:148px;min-height:42px;border-color:#93c5fd;border-radius:9px;color:#174f9f;font-weight:650}.mark-all-button svg{width:16px;height:16px}.attendance-table{min-width:660px}.attendance-table-head,.attendance-row{display:grid;grid-template-columns:56px minmax(260px,1fr) 210px;align-items:center;column-gap:12px;padding-inline:24px}.attendance-table-head{min-height:44px;border-bottom:1px solid #dbe3ee;color:#64748b;font-size:10px;font-weight:700}.attendance-row{min-height:68px;border-bottom:1px solid #edf1f6;transition:background-color 140ms ease}.attendance-row:hover{background:#fbfdff}.row-number{color:#334155;font-size:11px;font-variant-numeric:tabular-nums;text-align:center}.student-cell{display:flex;min-width:0;align-items:center;gap:12px}.student-avatar{display:grid;width:38px;height:38px;flex:none;place-items:center;border-radius:50%;background:#eaf2ff;color:#2563eb;font-size:11px;font-weight:750}.avatar-1{background:#fff5dc;color:#d97706}.avatar-2{background:#f3e8ff;color:#7c3aed}.avatar-3{background:#e8faef;color:#16a34a}.avatar-4{background:#ffe8ef;color:#db2777}.student-cell>div{min-width:0}.student-cell b,.student-cell small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.student-cell b{color:#0b214d;font-size:12px;font-weight:700}.student-cell small{margin-top:3px;color:#64748b;font-size:10px}.attendance-footer{position:sticky;z-index:8;bottom:0;display:flex;min-height:76px;align-items:center;justify-content:space-between;gap:20px;border-top:1px solid #e2e8f0;background:#fff;padding:12px 24px;box-shadow:0 -8px 24px rgba(15,23,42,.04)}.session-summary{display:flex;min-width:0;align-items:center;gap:12px}.session-summary>span{display:grid;width:40px;height:40px;flex:none;place-items:center;border-radius:10px;background:#eaf2ff;color:#2563eb}.session-summary svg{width:17px;height:17px}.session-summary small,.session-summary strong{display:block}.session-summary small{color:#64748b;font-size:9px}.session-summary strong{max-width:230px;overflow:hidden;margin-top:3px;color:#0b214d;font-size:11px;font-weight:700;text-overflow:ellipsis;white-space:nowrap;font-variant-numeric:tabular-nums}.session-summary i{width:1px;height:34px;background:#e2e8f0}.footer-actions{display:flex;flex:none;gap:8px}.footer-actions :deep(.ant-btn){min-width:112px;height:36px;padding-inline:14px;border-radius:8px;font-size:12px;font-weight:650}.footer-actions :deep(.ant-btn-primary){min-width:132px}.footer-actions :deep(.ant-btn svg){width:15px;height:15px}.footer-actions :deep(.ant-btn:active){scale:.96}.attendance-loading{display:grid;gap:1px;background:#edf1f6}.attendance-loading span{height:68px;background:linear-gradient(90deg,#fff 20%,#f8fafc 50%,#fff 80%);background-size:200% 100%;animation:attendance-loading 1.5s infinite}@keyframes attendance-loading{to{background-position:-200% 0}}.create-session-field{display:grid;gap:7px;padding-top:8px;color:#475569;font-size:11px;font-weight:700}.create-session-field>span{color:#dc2626}.create-session-field :deep(.ant-input){border-radius:9px}@media(max-width:1023px){.attendance-controls{grid-template-columns:1fr 1fr}.mark-all-button{grid-column:1/-1;justify-self:end}.attendance-metrics article{padding:14px}.attendance-footer{align-items:flex-start;flex-direction:column}.footer-actions{width:100%;justify-content:flex-end}}@media(max-width:767px){.attendance-page{gap:12px}.attendance-metrics{grid-template-columns:1fr}.attendance-metrics article{min-height:76px}.metric-icon{width:42px;height:42px}.attendance-controls{grid-template-columns:1fr;padding:16px}.mark-all-button{grid-column:auto;width:100%}.attendance-table{min-width:0}.attendance-table-head{display:none}.attendance-row{grid-template-columns:36px minmax(0,1fr);gap:10px;padding:12px 14px}.attendance-row>:last-child{grid-column:2;width:100%}.row-number{text-align:left}.attendance-footer{position:static;padding:14px}.session-summary{align-items:flex-start;flex-wrap:wrap}.session-summary i{display:none}.footer-actions{display:grid;grid-template-columns:1fr;width:100%}.footer-actions :deep(.ant-btn){width:100%;min-height:44px}}@media(max-width:420px){.student-avatar{width:36px;height:36px}.session-summary>span{display:none}}@media(prefers-reduced-motion:reduce){.attendance-row{transition:none}.footer-actions :deep(.ant-btn){transition:none}.attendance-loading span{animation:none}}
.attendance-controls{grid-template-columns:minmax(220px,1fr) minmax(220px,1fr) auto auto}.session-action{min-height:42px;border-radius:9px;color:#1769e0}.session-action svg{width:16px;height:16px}.qr-session-modal{display:grid;grid-template-columns:minmax(0,.96fr) minmax(0,1.14fr);gap:20px 24px}.qr-code-card{display:grid;min-width:0;place-items:center;border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:24px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.7)}.qr-code-card canvas{display:block;max-width:100%;height:auto!important}.qr-session-details{display:grid;align-content:start;gap:10px;padding-left:20px;border-left:1px solid #e2e8f0}.qr-class-card,.qr-detail-card{display:flex;min-width:0;align-items:center;gap:14px;border:1px solid #dbe3ee;border-radius:12px;background:#fff;padding:12px 14px}.qr-class-card>div,.qr-detail-card>div{min-width:0}.qr-class-card strong,.qr-class-card small,.qr-detail-card strong,.qr-detail-card small{display:block}.qr-class-card strong{overflow:hidden;color:#0b214d;font-size:18px;font-weight:760;text-overflow:ellipsis;white-space:nowrap}.qr-class-card small{margin-top:2px;color:#475569;font-size:12px;font-weight:600}.qr-detail-card small{color:#64748b;font-size:11px}.qr-detail-card strong{margin-top:3px;color:#172554;font-size:16px;font-weight:730;font-variant-numeric:tabular-nums}.qr-detail-icon{display:grid;width:46px;height:46px;flex:none;place-items:center;border-radius:11px;background:#eaf2ff;color:#1769e0}.qr-detail-icon svg{width:22px;height:22px;stroke-width:2}.qr-detail-icon-shield{width:52px;height:52px}.qr-detail-icon-green{background:#e9f9ef;color:#138a50}.qr-live-status{display:inline-flex;min-height:28px;align-items:center;gap:7px;margin-left:auto;border-radius:999px;background:#e9f9ef;padding:5px 12px;color:#138a50;font-size:11px;font-weight:700;white-space:nowrap}.qr-live-status i{width:6px;height:6px;border-radius:50%;background:#22c55e}.qr-expiry-note{display:flex;align-items:flex-start;gap:8px;margin:4px 0 0;color:#64748b;font-size:11px;line-height:1.5;text-wrap:pretty}.qr-expiry-note svg{width:17px;height:17px;flex:none;margin-top:1px;color:#5b7193}.qr-modal-actions{display:flex;grid-column:1/-1;justify-content:flex-end;gap:10px;padding-top:18px;border-top:1px solid #e2e8f0}.qr-modal-actions :deep(.ant-btn){min-width:104px;height:42px;border-radius:9px;font-weight:650}.qr-modal-actions :deep(.ant-btn-primary){min-width:166px}.qr-modal-actions :deep(.ant-btn svg){width:17px;height:17px}.qr-modal-actions :deep(.ant-btn:active){scale:.96}.qr-session-modal:fullscreen{overflow:auto;align-content:center;width:100vw;height:100vh;padding:clamp(24px,5vw,80px);background:#f8fafc}.qr-session-modal:fullscreen .qr-code-card canvas{width:min(52vmin,560px)!important}.qr-session-modal:fullscreen .qr-modal-actions{display:none}@media(max-width:1023px){.attendance-controls{grid-template-columns:1fr 1fr}.session-action{width:100%}.qr-session-modal{grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:18px}.qr-code-card{padding:16px}.qr-session-details{padding-left:16px}}@media(max-width:767px){.attendance-controls{grid-template-columns:1fr}.qr-session-modal{grid-template-columns:1fr}.qr-code-card{padding:16px}.qr-session-details{padding:0;border-left:0}.qr-class-card strong{font-size:16px}.qr-modal-actions{position:sticky;bottom:-16px;margin-inline:-16px;padding:14px 16px;background:#fff}.qr-modal-actions :deep(.ant-btn){flex:1;min-height:44px}.qr-session-modal:fullscreen{display:block;padding:20px}.qr-session-modal:fullscreen .qr-session-details{margin-top:16px}}@media(prefers-reduced-motion:reduce){.qr-modal-actions :deep(.ant-btn){transition:none}}
.attendance-controls{grid-template-columns:minmax(220px,1fr) minmax(220px,1fr) auto}.attendance-control-actions{display:flex;align-items:center;gap:8px}.attendance-control-actions :deep(.ant-btn){min-height:42px;border-radius:9px}.attendance-metrics-loading article>span{width:48px;height:48px;flex:none;border-radius:11px;background:#eef2f7}.attendance-metrics-loading article>div{display:grid;width:100%;gap:8px}.attendance-metrics-loading i{display:block;height:12px;border-radius:6px;background:#eef2f7}.attendance-metrics-loading i:first-child{width:28%}.attendance-metrics-loading i:last-child{width:48%;height:9px}.attendance-metrics-loading article>span,.attendance-metrics-loading i{background-image:linear-gradient(90deg,#eef2f7 20%,#f8fafc 50%,#eef2f7 80%);background-size:200% 100%;animation:attendance-loading 1.5s infinite}.open-session-button{min-height:42px}@media(max-width:1279px){.attendance-controls{grid-template-columns:1fr 1fr}.attendance-control-actions{grid-column:1/-1;justify-content:flex-end}}@media(max-width:767px){.attendance-controls{grid-template-columns:1fr}.attendance-control-actions{display:grid;grid-column:auto;grid-template-columns:1fr}.attendance-control-actions :deep(.ant-btn){width:100%}}
.attendance-controls{grid-template-columns:minmax(220px,1fr) minmax(220px,1fr) auto auto}.session-management-actions{display:flex;align-items:center;gap:8px}.manage-sessions-button{min-height:42px;border-color:#bfdbfe;border-radius:9px;color:#1769e0;font-weight:650;white-space:nowrap}.manage-sessions-button:hover{border-color:#60a5fa!important;background:#eff6ff!important;color:#145fd1!important}.manage-sessions-button:active{scale:.96}.manage-sessions-button svg{width:16px;height:16px;stroke-width:2}.session-management-actions :deep(.ant-btn){min-height:42px}@media(max-width:1599px){.attendance-controls{grid-template-columns:1fr 1fr}.session-management-actions{grid-column:1;justify-content:flex-start}.attendance-control-actions{grid-column:2;justify-content:flex-end}}@media(max-width:1279px){.attendance-control-actions{grid-column:1/-1}}@media(max-width:767px){.attendance-controls{grid-template-columns:1fr}.session-management-actions{grid-column:auto;width:100%}.manage-sessions-button{flex:1}.attendance-control-actions{grid-column:auto}}@media(prefers-reduced-motion:reduce){.manage-sessions-button{transition:none}}
.create-session-intro{margin-bottom:16px}.create-session-intro strong{display:block;color:#334155;font-size:12px;font-weight:700}.create-session-intro strong span{color:#dc2626}.create-session-intro p{margin:5px 0 0;color:#64748b;font-size:11px;line-height:1.5}.create-session-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}.create-session-actions :deep(.ant-btn){min-width:104px;border-radius:10px;font-weight:650}.create-session-actions :deep(.ant-btn:active){scale:.96}.qr-code-card :deep(canvas){width:100%!important;max-width:340px;height:auto!important}@media(max-width:639px){.create-session-actions{display:grid;grid-template-columns:1fr 1fr}.create-session-actions :deep(.ant-btn){width:100%}}@media(prefers-reduced-motion:reduce){.create-session-actions :deep(.ant-btn){transition:none}}
.attendance-metrics strong{font-size:20px}.attendance-metrics small{font-size:13px}.attendance-controls label{font-size:13px}.attendance-controls :deep(.ant-select-selection-item),.attendance-controls :deep(.ant-select-selection-placeholder){font-size:14px}.attendance-table-head{font-size:12px}.row-number{font-size:13px}.student-avatar{font-size:12px}.student-cell b{font-size:15px}.student-cell small{font-size:12px}.session-summary small{font-size:11px}.session-summary strong{font-size:13px}.footer-actions :deep(.ant-btn){font-size:13px}.create-session-intro strong{font-size:14px}.create-session-intro p{font-size:13px}
</style>

<style>
.create-attendance-session-modal .ant-modal{max-width:calc(100vw - 24px);padding-bottom:0}.create-attendance-session-modal .ant-modal-content{overflow:hidden;border:1px solid #dbe3ee;border-radius:14px;padding:22px 24px 20px;box-shadow:0 24px 72px rgba(15,23,42,.2)}.create-attendance-session-modal .ant-modal-header{margin-bottom:16px}.create-attendance-session-modal .ant-modal-title{color:#172554;font-size:18px;font-weight:750;letter-spacing:-.015em}.create-attendance-session-modal .ant-modal-close{top:14px;inset-inline-end:14px;display:grid;width:40px;height:40px;place-items:center;border-radius:10px;color:#64748b}.create-attendance-session-modal .ant-modal-close:hover{background:#f1f5f9;color:#172554}@media(max-width:639px){.create-attendance-session-modal{padding:12px}.create-attendance-session-modal .ant-modal{width:100%!important;max-width:100%;margin:0}.create-attendance-session-modal .ant-modal-content{max-height:calc(100dvh - 24px);overflow-y:auto;padding:18px 16px 16px}.create-attendance-session-modal .ant-modal-title{padding-right:36px;font-size:16px}}
.attendance-qr-modal .ant-modal{max-width:calc(100vw - 32px);padding-bottom:0}.attendance-qr-modal .ant-modal-content{overflow:hidden;border:1px solid #dbe3ee;border-radius:16px;padding:22px 24px 20px;box-shadow:0 28px 80px rgba(15,23,42,.22)}.attendance-qr-modal .ant-modal-header{margin-bottom:20px}.attendance-qr-modal .ant-modal-title{color:#172554;font-size:20px;font-weight:760;letter-spacing:-.02em}.attendance-qr-modal .ant-modal-close{top:14px;inset-inline-end:14px;display:grid;width:40px;height:40px;place-items:center;border-radius:10px;color:#64748b}.attendance-qr-modal .ant-modal-close:hover{background:#f1f5f9;color:#172554}@media(max-width:767px){.attendance-qr-modal{padding:12px}.attendance-qr-modal .ant-modal{width:100%!important;max-width:100%;margin:0}.attendance-qr-modal .ant-modal-content{max-height:calc(100dvh - 24px);overflow-y:auto;padding:18px 16px 16px}.attendance-qr-modal .ant-modal-header{margin-bottom:16px}.attendance-qr-modal .ant-modal-title{padding-right:40px;font-size:18px}}
</style>

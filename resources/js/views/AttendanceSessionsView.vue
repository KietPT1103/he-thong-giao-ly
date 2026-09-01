<!--
THESIS: Lịch sử phiên là sổ vận hành của lớp, ưu tiên quét trạng thái và quay lại đúng phiên hơn là một dashboard thống kê khác.
OWN-WORLD: Mặt bàn trắng, đường phân cấp slate mảnh, xanh hệ thống cho điều hướng và hành động, badge trạng thái có cả chữ lẫn màu.
STORY: GLV chọn lớp, lọc vòng đời, nhận biết phiên đang mở rồi xem, hủy hoặc xóa phiên từ một menu thao tác nhất quán.
FIRST VIEWPORT: Điều hướng module ở trên; bên dưới là một bảng rộng duy nhất với lớp và hành động mở phiên cùng một hàng.
FORM: Operate-mode session register, mở rộng trực tiếp ngôn ngữ của workspace Điểm danh hiện tại.
-->
<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import AModal from "ant-design-vue/es/modal";
import APagination from "ant-design-vue/es/pagination";
import ASelect from "ant-design-vue/es/select";
import { CalendarDays, CheckCircle2, CircleOff, Clock3, Mail, Plus, UserRound, UsersRound } from "lucide-vue-next";
import { toast } from "vue-sonner";
import { cancelAttendanceSession, deleteAttendanceSession, getAttendanceSession, getAttendanceSessions, getTeacherClasses, type AttendanceSessionPage } from "../api/teacher";
import AttendanceSectionNav from "../components/attendance/AttendanceSectionNav.vue";
import AttendanceSessionActions from "../components/attendance/AttendanceSessionActions.vue";
import type { AttendanceSession, CatechismClass } from "../types/api";

type StatusFilter = "all" | "active" | "ended" | "cancelled";
type SessionAction = "cancel" | "delete";
const route = useRoute();
const router = useRouter();
const classes = ref<CatechismClass[]>([]);
const classId = ref<number | "">("");
const sessions = ref<AttendanceSession[]>([]);
const filter = ref<StatusFilter>("all");
const loading = ref(true);
const actionSessionId = ref<number | null>(null);
const actionKind = ref<SessionAction | null>(null);
const error = ref("");
const detailOpen = ref(false);
const detailLoading = ref(false);
const detailError = ref("");
const selectedSession = ref<AttendanceSession | null>(null);
const meta = ref<Pick<AttendanceSessionPage, "current_page" | "last_page" | "per_page" | "total">>({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

const selectedClass = computed(() => classes.value.find(item => item.id === Number(classId.value)));
const classOptions = computed(() => classes.value.map(item => ({ value: item.id, label: `${item.name} · ${item.code}` })));
const filters: Array<{ value: StatusFilter; label: string }> = [
    { value: "all", label: "Tất cả" },
    { value: "active", label: "Đang diễn ra" },
    { value: "ended", label: "Đã kết thúc" },
    { value: "cancelled", label: "Đã hủy" },
];

function formatSession(value: string) {
    return new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}
function presentCount(session: AttendanceSession) {
    return session.attendances.filter(item => item.status === "present" || item.status === "late").length;
}
function statusLabel(status: AttendanceSession["status"]) {
    if (status === "active") return "Đang diễn ra";
    if (status === "cancelled") return "Đã hủy";
    return "Đã kết thúc";
}
function attendanceStatusLabel(status: AttendanceSession["attendances"][number]["status"]) {
    const labels = {
        present: "Có mặt",
        late: "Đi trễ",
        excused_absence: "Vắng có phép",
        unexcused_absence: "Vắng không phép",
        left_early: "Về sớm",
        unknown: "Chưa xác định",
    } as const;
    return labels[status];
}
function formatArrival(value?: string | null) {
    if (!value) return "Chưa ghi nhận";
    return new Intl.DateTimeFormat("vi-VN", { hour: "2-digit", minute: "2-digit" }).format(new Date(value));
}

async function loadClasses() {
    loading.value = true;
    try {
        classes.value = (await getTeacherClasses()).data.data;
        const requested = Number(route.query.class);
        classId.value = classes.value.some(item => item.id === requested) ? requested : classes.value[0]?.id || "";
        if (classId.value) await loadSessions(1);
    } catch { error.value = "Không thể tải danh sách phiên điểm danh."; }
    finally { loading.value = false; }
}
async function loadSessions(page = 1) {
    if (!classId.value) return;
    loading.value = true;
    error.value = "";
    try {
        const result = (await getAttendanceSessions(Number(classId.value), page, filter.value === "all" ? undefined : filter.value)).data.data;
        sessions.value = result.data;
        meta.value = { current_page: result.current_page, last_page: result.last_page, per_page: result.per_page, total: result.total };
        await router.replace({ query: { ...route.query, class: String(classId.value) } });
    } catch { error.value = "Không thể tải các phiên của lớp đã chọn."; }
    finally { loading.value = false; }
}
function setFilter(value: StatusFilter) { filter.value = value; void loadSessions(1); }
async function openSession(session: AttendanceSession) {
    selectedSession.value = session;
    detailError.value = "";
    detailOpen.value = true;
    detailLoading.value = true;
    try {
        selectedSession.value = (await getAttendanceSession(session.id)).data.data;
    } catch {
        detailError.value = "Không thể tải danh sách điểm danh của phiên này. Hãy thử lại.";
    } finally {
        detailLoading.value = false;
    }
}
function openNewSession() { if (classId.value) void router.push({ path: "/teacher/attendance", query: { class: classId.value, new: "1" } }); }
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
        centered: true,
        okText: "Hủy phiên", cancelText: "Quay lại", okButtonProps: { danger: true },
        async onOk() {
            beginAction(session, "cancel");
            try { await cancelAttendanceSession(session.id); toast.success("Đã hủy phiên điểm danh."); await loadSessions(meta.value.current_page); }
            catch { toast.error("Không thể hủy phiên điểm danh."); }
            finally { finishAction(); }
        },
    });
}
function confirmDelete(session: AttendanceSession) {
    AModal.confirm({
        title: "Xóa phiên điểm danh?",
        content: `Phiên ${formatSession(session.held_at)} và toàn bộ dữ liệu điểm danh của phiên sẽ bị xóa vĩnh viễn. Thao tác này không thể hoàn tác.`,
        centered: true,
        okText: "Xóa phiên", cancelText: "Quay lại", okButtonProps: { danger: true },
        async onOk() {
            beginAction(session, "delete");
            try {
                await deleteAttendanceSession(session.id);
                toast.success("Đã xóa phiên điểm danh.");
                const page = sessions.value.length === 1 && meta.value.current_page > 1 ? meta.value.current_page - 1 : meta.value.current_page;
                await loadSessions(page);
            } catch { toast.error("Không thể xóa phiên điểm danh."); }
            finally { finishAction(); }
        },
    });
}

onMounted(loadClasses);
</script>

<template>
    <section class="session-list-page">
        <AttendanceSectionNav attached />
        <AAlert v-if="error" type="error" show-icon closable :message="error" @close="error=''" />

        <section class="session-register" aria-labelledby="session-register-title">
            <header class="register-header">
                <div class="register-title">
                    <h2 id="session-register-title">Phiên điểm danh của lớp</h2>
                    <p v-if="selectedClass">{{ selectedClass.name }} · {{ selectedClass.code }} <span aria-hidden="true">·</span> <strong>{{ meta.total }} phiên</strong></p>
                    <p v-else>Chọn lớp phụ trách để xem danh sách phiên.</p>
                </div>
                <div class="register-actions">
                    <label><span>Lớp phụ trách</span><ASelect v-model:value="classId" show-search option-filter-prop="label" :options="classOptions" :disabled="loading" @change="loadSessions(1)" /></label>
                    <AButton class="open-session-button" type="primary" size="large" :disabled="!classId || loading" @click="openNewSession"><Plus />Mở phiên mới</AButton>
                </div>
            </header>

            <div class="status-tabs" role="tablist" aria-label="Lọc trạng thái phiên">
                <button v-for="item in filters" :key="item.value" type="button" role="tab" :aria-selected="filter===item.value" :class="{active:filter===item.value}" @click="setFilter(item.value)">{{ item.label }}</button>
            </div>

            <div v-if="loading" class="session-skeleton" aria-busy="true" aria-label="Đang tải danh sách phiên"><span v-for="item in 5" :key="item" /></div>
            <div v-else-if="!sessions.length" class="session-empty"><CalendarDays /><h3>Chưa có phiên phù hợp</h3><p>Thử trạng thái khác hoặc mở phiên mới cho lớp này.</p><AButton type="primary" :disabled="!classId" @click="openNewSession"><Plus />Mở phiên mới</AButton></div>
            <div v-else class="session-table" role="table" aria-label="Danh sách phiên điểm danh">
                <div class="session-head" role="row"><span>STT</span><span>Ngày giờ bắt đầu</span><span>Trạng thái</span><span>Đã điểm danh</span><span>Ghi chú</span><span>Thao tác</span></div>
                <div v-for="(session,index) in sessions" :key="session.id" class="session-row" role="row" tabindex="0" @dblclick="openSession(session)" @keydown.enter="openSession(session)">
                    <span class="session-index">{{ (meta.current_page-1)*meta.per_page+index+1 }}</span>
                    <span class="session-time"><CalendarDays />{{ formatSession(session.held_at) }}</span>
                    <span><em class="status-badge" :class="`status-${session.status}`"><Clock3 v-if="session.status==='active'" /><CircleOff v-else-if="session.status==='cancelled'" /><CheckCircle2 v-else />{{ statusLabel(session.status) }}</em></span>
                    <span class="attendance-count"><UsersRound />{{ presentCount(session) }} lượt</span>
                    <span class="session-note">{{ session.note || "—" }}</span>
                    <span class="session-actions" @dblclick.stop>
                        <AttendanceSessionActions :session="session" :busy-action="actionSessionId===session.id?actionKind:null" @view="openSession" @cancel="confirmCancel" @delete="confirmDelete" />
                    </span>
                </div>
            </div>
            <footer v-if="meta.total>meta.per_page" class="session-pagination"><span>{{ meta.total }} phiên</span><APagination :current="meta.current_page" :page-size="meta.per_page" :total="meta.total" :show-size-changer="false" responsive @change="loadSessions" /></footer>
        </section>

        <AModal v-model:open="detailOpen" width="760px" :footer="null" centered wrap-class-name="attendance-detail-modal">
            <template #title>
                <div class="detail-modal-title">
                    <span><UsersRound aria-hidden="true" /></span>
                    <div>
                        <strong>Danh sách tài khoản đã điểm danh</strong>
                        <small v-if="selectedSession">{{ formatSession(selectedSession.held_at) }} · {{ selectedSession.catechism_class?.name || selectedClass?.name }}</small>
                    </div>
                </div>
            </template>
            <div class="detail-modal-body" aria-live="polite">
                <div v-if="detailLoading" class="detail-loading" aria-busy="true" aria-label="Đang tải chi tiết phiên"><span v-for="item in 3" :key="item" /></div>
                <AAlert v-else-if="detailError" type="error" show-icon :message="detailError" />
                <div v-else-if="!selectedSession?.attendances.length" class="detail-empty"><UsersRound aria-hidden="true" /><strong>Chưa có dữ liệu điểm danh</strong><p>Phiên này chưa ghi nhận tài khoản Thiếu nhi nào.</p></div>
                <div v-else class="attendance-detail-list" role="table" aria-label="Tài khoản trong phiên điểm danh">
                    <div class="attendance-detail-head" role="row"><span>Thiếu nhi</span><span>Tài khoản</span><span>Trạng thái</span><span>Giờ ghi nhận</span></div>
                    <div v-for="attendance in selectedSession.attendances" :key="attendance.id" class="attendance-detail-row" role="row">
                        <div class="detail-child"><span class="detail-avatar"><UserRound aria-hidden="true" /></span><div><strong>{{ attendance.child?.full_name || `Thiếu nhi #${attendance.child_id}` }}</strong><small>{{ attendance.child?.code || 'Chưa có mã' }}</small></div></div>
                        <div class="detail-account"><Mail aria-hidden="true" /><span>{{ attendance.child?.email || 'Chưa có tài khoản đăng nhập' }}</span></div>
                        <span><em class="attendance-status" :class="`attendance-${attendance.status}`">{{ attendanceStatusLabel(attendance.status) }}</em></span>
                        <div class="detail-arrival"><Clock3 aria-hidden="true" /><div><span>{{ formatArrival(attendance.arrived_at) }}</span><small v-if="attendance.note">{{ attendance.note }}</small></div></div>
                    </div>
                </div>
            </div>
        </AModal>
    </section>
</template>

<style scoped>
.session-list-page {
    display: grid;
    width: 100%;
    min-width: 0;
    gap: 16px;
}

.session-register {
    min-width: 0;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    box-shadow: 0 12px 32px rgba(15, 23, 42, .045);
}

.register-header {
    display: flex;
    min-height: 92px;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    padding: 20px 24px;
}

.register-title {
    min-width: 0;
}

.register-title h2,
.register-title p {
    margin: 0;
}

.register-title h2 {
    color: #0b214d;
    font-size: 17px;
    font-weight: 760;
    line-height: 1.35;
    text-wrap: balance;
}

.register-title p {
    margin-top: 5px;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
    text-wrap: pretty;
}

.register-title p strong {
    color: #245ca8;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
}

.register-actions {
    display: flex;
    flex: none;
    align-items: end;
    gap: 10px;
}

.register-actions label {
    display: grid;
    width: min(320px, 42vw);
    gap: 7px;
}

.register-actions label > span {
    color: #475569;
    font-size: 12px;
    font-weight: 700;
}

.register-actions :deep(.ant-select-selector) {
    min-height: 42px;
    border-radius: 9px !important;
    padding-inline: 12px !important;
}

.register-actions :deep(.ant-select-selection-item),
.register-actions :deep(.ant-select-selection-placeholder) {
    line-height: 40px !important;
    font-size: 13px;
}

.open-session-button {
    min-width: 148px;
    min-height: 42px;
    border-radius: 9px;
    font-weight: 650;
}

.open-session-button svg {
    width: 17px;
    height: 17px;
}

.status-tabs {
    display: flex;
    gap: 28px;
    overflow-x: auto;
    overflow-y: hidden;
    border-block: 1px solid #e2e8f0;
    padding-inline: 24px;
}

.status-tabs button {
    position: relative;
    min-width: max-content;
    min-height: 48px;
    border: 0;
    background: transparent;
    color: #64748b;
    cursor: pointer;
    font-size: 13px;
    font-weight: 650;
    transition: color 140ms ease;
}

.status-tabs button::after {
    position: absolute;
    right: 0;
    bottom: -1px;
    left: 0;
    height: 2px;
    border-radius: 2px 2px 0 0;
    background: #2476f3;
    content: "";
    opacity: 0;
    transform: scaleX(.6);
    transition: opacity 140ms ease, transform 140ms ease;
}

.status-tabs button:hover,
.status-tabs button.active {
    color: #145fd1;
}

.status-tabs button:focus-visible {
    border-radius: 6px;
    outline: 2px solid #93c5fd;
    outline-offset: -3px;
}

.status-tabs button.active::after {
    opacity: 1;
    transform: scaleX(1);
}

.session-table {
    min-width: 760px;
}

.session-head,
.session-row {
    display: grid;
    grid-template-columns: 56px minmax(220px, 1.25fr) 170px 150px minmax(170px, 1fr) 72px;
    align-items: center;
    gap: 14px;
    padding-inline: 24px;
}

.session-head {
    min-height: 48px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #52627c;
    font-size: 12px;
    font-weight: 700;
}

.session-head > span:first-child,
.session-head > span:last-child {
    text-align: center;
}

.session-row {
    min-height: 70px;
    border-bottom: 1px solid #edf1f6;
    color: #334155;
    font-size: 13px;
    line-height: 1.45;
    transition: background-color 140ms ease, box-shadow 140ms ease;
}

.session-row:last-child {
    border-bottom: 0;
}

.session-row:hover {
    background: #f8fbff;
}

.session-row:focus-visible {
    outline: none;
    box-shadow: inset 3px 0 #2563eb;
}

.session-index {
    color: #475569;
    font-variant-numeric: tabular-nums;
    text-align: center;
}

.session-time,
.attendance-count {
    display: flex;
    align-items: center;
    gap: 8px;
    font-variant-numeric: tabular-nums;
}

.session-time {
    color: #102a5c;
    font-weight: 700;
}

.session-time svg,
.attendance-count svg {
    width: 16px;
    height: 16px;
    flex: none;
    color: #6b83a6;
    stroke-width: 1.8;
}

.status-badge {
    display: inline-flex;
    min-height: 30px;
    align-items: center;
    gap: 6px;
    border-radius: 999px;
    padding: 5px 10px;
    font-size: 12px;
    font-style: normal;
    font-weight: 700;
    white-space: nowrap;
}

.status-badge svg {
    width: 14px;
    height: 14px;
}

.status-active {
    background: #e9f9ef;
    color: #147a45;
}

.status-ended {
    background: #eef2f7;
    color: #475569;
}

.status-cancelled {
    background: #fff1f2;
    color: #b42334;
}

.session-note {
    overflow: hidden;
    color: #52627c;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.session-actions {
    display: flex;
    justify-content: center;
}

.session-pagination {
    display: flex;
    min-height: 64px;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border-top: 1px solid #e2e8f0;
    padding: 10px 24px;
    color: #52627c;
    font-size: 13px;
    font-variant-numeric: tabular-nums;
}

.session-empty {
    display: grid;
    min-height: 320px;
    place-items: center;
    align-content: center;
    gap: 9px;
    padding: 32px;
    text-align: center;
}

.session-empty > svg {
    width: 42px;
    height: 42px;
    color: #94a3b8;
}

.session-empty h3,
.session-empty p {
    margin: 0;
}

.session-empty h3 {
    color: #1e3158;
    font-size: 16px;
}

.session-empty p {
    color: #64748b;
    font-size: 13px;
    text-wrap: pretty;
}

.session-empty .ant-btn {
    min-height: 42px;
    margin-top: 7px;
    border-radius: 9px;
}

.session-skeleton {
    display: grid;
    gap: 1px;
    background: #edf1f6;
}

.session-skeleton span {
    height: 70px;
    background: linear-gradient(90deg, #fff 20%, #f8fafc 50%, #fff 80%);
    background-size: 200% 100%;
    animation: session-loading 1.4s infinite;
}

.detail-modal-title {
    display: flex;
    align-items: center;
    gap: 11px;
    padding-right: 28px;
}

.detail-modal-title > span {
    display: grid;
    width: 38px;
    height: 38px;
    flex: none;
    place-items: center;
    border-radius: 10px;
    background: #eaf2ff;
    color: #2563eb;
}

.detail-modal-title svg {
    width: 19px;
}

.detail-modal-title strong,
.detail-modal-title small {
    display: block;
}

.detail-modal-title strong {
    color: #172554;
    font-size: 16px;
}

.detail-modal-title small {
    margin-top: 3px;
    color: #64748b;
    font-size: 12px;
    font-weight: 500;
}

.detail-modal-body {
    min-height: 220px;
}

.attendance-detail-list {
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}

.attendance-detail-head,
.attendance-detail-row {
    display: grid;
    grid-template-columns: minmax(180px, 1.2fr) minmax(190px, 1fr) 130px 130px;
    align-items: center;
    gap: 14px;
    padding-inline: 16px;
}

.attendance-detail-head {
    min-height: 42px;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #52627c;
    font-size: 11px;
    font-weight: 700;
}

.attendance-detail-row {
    min-height: 68px;
    border-bottom: 1px solid #edf1f6;
    color: #334155;
    font-size: 12px;
}

.attendance-detail-row:last-child {
    border-bottom: 0;
}

.detail-child,
.detail-account,
.detail-arrival {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 8px;
}

.detail-avatar {
    display: grid;
    width: 34px;
    height: 34px;
    flex: none;
    place-items: center;
    border-radius: 50%;
    background: #eef4ff;
    color: #2563eb;
}

.detail-avatar svg,
.detail-account svg,
.detail-arrival svg {
    width: 15px;
    flex: none;
}

.detail-child strong,
.detail-child small,
.detail-arrival span,
.detail-arrival small {
    display: block;
}

.detail-child strong {
    overflow: hidden;
    color: #172554;
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.detail-child small,
.detail-arrival small {
    margin-top: 2px;
    color: #64748b;
    font-size: 10px;
}

.detail-account {
    color: #52627c;
}

.detail-account span {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.attendance-status {
    display: inline-flex;
    min-height: 28px;
    align-items: center;
    border-radius: 999px;
    background: #eef2f7;
    padding: 5px 9px;
    color: #475569;
    font-size: 11px;
    font-style: normal;
    font-weight: 700;
    white-space: nowrap;
}

.attendance-present { background: #e9f9ef; color: #147a45; }
.attendance-late,
.attendance-left_early { background: #fff7e8; color: #9a4b08; }
.attendance-excused_absence { background: #eff6ff; color: #1d4ed8; }
.attendance-unexcused_absence { background: #fff1f2; color: #b42334; }

.detail-arrival {
    align-items: flex-start;
    color: #52627c;
}

.detail-loading {
    display: grid;
    gap: 8px;
}

.detail-loading span {
    height: 64px;
    border-radius: 10px;
    background: linear-gradient(90deg, #eef2f7 20%, #f8fafc 50%, #eef2f7 80%);
    background-size: 200% 100%;
    animation: session-loading 1.4s infinite;
}

.detail-empty {
    display: grid;
    min-height: 220px;
    place-items: center;
    align-content: center;
    gap: 7px;
    color: #64748b;
    text-align: center;
}

.detail-empty > svg {
    width: 36px;
}

.detail-empty strong { color: #172554; }
.detail-empty p { margin: 0; font-size: 12px; }

:global(.attendance-detail-modal .ant-modal-content) {
    border-radius: 14px;
    padding: 22px;
}

@keyframes session-loading {
    to { background-position: -200% 0; }
}

@media (max-width: 1023px) {
    .register-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .register-actions {
        width: 100%;
    }

    .register-actions label {
        width: min(100%, 420px);
    }

    .open-session-button {
        margin-left: auto;
    }
}

@media (max-width: 767px) {
    .session-list-page {
        gap: 12px;
    }

    .register-header {
        padding: 16px;
    }

    .register-actions {
        align-items: stretch;
        flex-direction: column;
    }

    .register-actions label,
    .open-session-button {
        width: 100%;
    }

    .open-session-button {
        margin-left: 0;
    }

    .status-tabs {
        gap: 20px;
        padding-inline: 16px;
        scrollbar-width: none;
    }

    .status-tabs::-webkit-scrollbar {
        display: none;
    }

    .session-register {
        overflow-x: auto;
    }

    .session-head {
        display: none;
    }

    .session-table {
        display: grid;
        min-width: 0;
        gap: 10px;
        background: #f8fafc;
        padding: 12px;
    }

    .session-row {
        position: relative;
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px;
        min-height: 0;
        border: 1px solid #e2e8f0 !important;
        border-radius: 12px;
        background: #fff;
        padding: 16px;
        font-size: 13px;
    }

    .session-index {
        position: absolute;
        top: 15px;
        right: 58px;
    }

    .session-time {
        padding-right: 76px;
    }

    .session-row > span:nth-child(3),
    .session-row > span:nth-child(4),
    .session-note {
        grid-column: 1;
    }

    .session-actions {
        position: absolute;
        top: 10px;
        right: 10px;
    }

    .session-pagination {
        align-items: flex-start;
        flex-direction: column;
        padding-inline: 16px;
    }

    .session-pagination :deep(.ant-pagination) {
        align-self: flex-end;
    }
}

@media (max-width: 640px) {
    :global(.attendance-detail-modal .ant-modal) {
        max-width: calc(100vw - 24px);
        margin: 12px auto;
    }

    .attendance-detail-head {
        display: none;
    }

    .attendance-detail-list {
        display: grid;
        gap: 9px;
        border: 0;
    }

    .attendance-detail-row {
        grid-template-columns: minmax(0, 1fr) auto;
        gap: 10px;
        min-height: 0;
        border: 1px solid #e2e8f0 !important;
        border-radius: 11px;
        padding: 13px;
    }

    .detail-account,
    .detail-arrival {
        grid-column: 1 / -1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .status-tabs button,
    .status-tabs button::after,
    .session-row {
        transition: none;
    }

    .session-skeleton span {
        animation: none;
    }
}
</style>

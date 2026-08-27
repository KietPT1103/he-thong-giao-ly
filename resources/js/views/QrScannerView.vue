<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AInput from "ant-design-vue/es/input";
import ASelect from "ant-design-vue/es/select";
import ATag from "ant-design-vue/es/tag";
import { CalendarClock, Download, History, QrCode, RefreshCw, ShieldCheck, Timer } from "lucide-vue-next";
import QrcodeVue from "qrcode.vue";
import {
    createAttendanceQr, getAttendanceSessionQr, getQrWorkspace,
    type AttendanceSessionQrPayload, type QrWorkspacePayload, type QrWorkspaceSession,
} from "../api/qr";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";

const classes = ref<QrWorkspacePayload["classes"]>([]);
const sessions = ref<QrWorkspaceSession[]>([]);
const classId = ref<number>();
const previousSessionId = ref<number>();
const heldAt = ref("");
const expiresAt = ref("");
const note = ref("");
const payload = ref<AttendanceSessionQrPayload | null>(null);
const qrContainer = ref<HTMLElement | null>(null);
const loadingWorkspace = ref(true);
const generating = ref(false);
const loadingQr = ref(false);
const errorMessage = ref("");
const nowMs = ref(Date.now());
let ticker: number | undefined;

const classOptions = computed(() => classes.value.map((item) => ({
    value: item.id,
    label: `${item.name} · ${item.code}`,
})));
const selectedClass = computed(() => classes.value.find((item) => item.id === classId.value));
const qrValue = computed(() => payload.value
    ? new URL(payload.value.scan_url, window.location.origin).toString()
    : "");
const previousSessionOptions = computed(() => sessions.value
    .filter((item) => item.catechism_class_id === classId.value)
    .filter((item) => item.qr_expires_at)
    .map((item) => ({
        value: item.id,
        label: `${formatDateTime(item.held_at)} · hết hạn ${formatTime(item.qr_expires_at!)}`,
        session: item,
    })));
const historyCount = computed(() => previousSessionOptions.value.length);
const remainingSeconds = computed(() => payload.value
    ? Math.max(0, Math.ceil((new Date(payload.value.session.qr_expires_at).getTime() - nowMs.value) / 1000))
    : 0);
const expired = computed(() => Boolean(payload.value) && remainingSeconds.value === 0);
const countdown = computed(() => {
    const minutes = Math.floor(remainingSeconds.value / 60).toString().padStart(2, "0");
    const seconds = (remainingSeconds.value % 60).toString().padStart(2, "0");
    return `${minutes}:${seconds}`;
});
const canGenerate = computed(() => Boolean(classId.value && heldAt.value && expiresAt.value) && !generating.value);

const apiMessage = (error: unknown, fallback: string) =>
    (error as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback;

function toLocalInput(date: Date) {
    const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000);
    return local.toISOString().slice(0, 16);
}

function setDefaultTimes() {
    const start = new Date();
    start.setSeconds(0, 0);
    const expiry = new Date(start.getTime() + 15 * 60_000);
    heldAt.value = toLocalInput(start);
    expiresAt.value = toLocalInput(expiry);
}

function formatDateTime(value: string) {
    return new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}

function formatTime(value: string) {
    return new Intl.DateTimeFormat("vi-VN", { hour: "2-digit", minute: "2-digit" }).format(new Date(value));
}

async function loadWorkspace() {
    try {
        const workspace = (await getQrWorkspace()).data.data;
        classes.value = workspace.classes;
        sessions.value = workspace.recent_sessions;
        if (classes.value.length === 1) classId.value = classes.value[0].id;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải không gian tạo QR.");
    } finally {
        loadingWorkspace.value = false;
    }
}

async function generateQr() {
    if (!canGenerate.value || !classId.value) return;
    generating.value = true;
    errorMessage.value = "";
    try {
        payload.value = (await createAttendanceQr(classId.value, {
            held_at: new Date(heldAt.value).toISOString(),
            qr_expires_at: new Date(expiresAt.value).toISOString(),
            note: note.value.trim() || undefined,
        })).data.data;
        previousSessionId.value = payload.value.session.id;
        const created: QrWorkspaceSession = {
            id: payload.value.session.id,
            catechism_class_id: payload.value.session.class.id,
            held_at: payload.value.session.held_at,
            qr_expires_at: payload.value.session.qr_expires_at,
            note: payload.value.session.note,
            class: payload.value.session.class,
        };
        sessions.value = [created, ...sessions.value.filter((item) => item.id !== created.id)].slice(0, 12);
        navigator.vibrate?.(80);
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tạo mã QR điểm danh.");
    } finally {
        generating.value = false;
    }
}

async function loadPreviousQr() {
    if (!previousSessionId.value) return;
    loadingQr.value = true;
    errorMessage.value = "";
    try {
        payload.value = (await getAttendanceSessionQr(previousSessionId.value)).data.data;
    } catch (error) {
        errorMessage.value = apiMessage(error, "Không thể tải lại mã QR này.");
    } finally {
        loadingQr.value = false;
    }
}

function downloadQr() {
    const canvas = qrContainer.value?.querySelector("canvas");
    if (!canvas || !payload.value) return;
    const link = document.createElement("a");
    link.download = `qr-diem-danh-${payload.value.session.class.code}-${payload.value.session.id}.png`;
    link.href = canvas.toDataURL("image/png");
    link.click();
}

watch(classId, () => {
    previousSessionId.value = undefined;
    payload.value = null;
});
watch(heldAt, (value, previous) => {
    if (!value || !previous) return;
    const oldStart = new Date(previous).getTime();
    const oldExpiry = new Date(expiresAt.value).getTime();
    const duration = Number.isFinite(oldExpiry - oldStart) ? Math.max(5 * 60_000, oldExpiry - oldStart) : 15 * 60_000;
    expiresAt.value = toLocalInput(new Date(new Date(value).getTime() + duration));
});
onMounted(() => {
    setDefaultTimes();
    void loadWorkspace();
    ticker = window.setInterval(() => { nowMs.value = Date.now(); }, 1000);
});
onBeforeUnmount(() => { if (ticker) window.clearInterval(ticker); });
</script>

<template>
    <section class="teacher-page-stack qr-page">
        <TeacherPageHeader title="Tạo QR điểm danh" description="Chọn lớp, giờ học và thời hạn. Thiếu nhi đăng nhập rồi quét mã này để tự điểm danh." />

        <AAlert v-if="errorMessage" type="error" show-icon closable :message="errorMessage" @close="errorMessage=''" />

        <div class="qr-workspace">
            <div class="qr-left-column">
                <ACard :bordered="false" class="teacher-card qr-config-card">
                <header class="qr-panel-header">
                    <span class="qr-panel-icon"><CalendarClock aria-hidden="true" /></span>
                    <div class="min-w-0 flex-1">
                        <h2>Thiết lập buổi học</h2>
                        <p>Chọn lớp và khoảng thời gian nhận điểm danh.</p>
                    </div>
                    <ATag color="blue" class="qr-lock-tag"><span><ShieldCheck aria-hidden="true" />Tự khóa theo hạn</span></ATag>
                </header>

                <div class="qr-config-body">
                    <AAlert v-if="!loadingWorkspace && !classes.length" type="warning" show-icon message="Chưa có lớp được phân công" description="Liên hệ quản trị viên để được phân công lớp trước khi tạo mã QR." />

                    <div class="qr-form">
                        <label class="qr-field">
                            <span class="qr-field-label">Lớp được phân công <i aria-hidden="true">*</i></span>
                            <ASelect v-model:value="classId" show-search option-filter-prop="label" size="large" placeholder="Chọn lớp" aria-required="true" :options="classOptions" :loading="loadingWorkspace" :disabled="generating || !classes.length" />
                        </label>

                        <div class="qr-time-grid">
                            <label class="qr-field">
                                <span class="qr-field-label">Bắt đầu buổi học <i aria-hidden="true">*</i></span>
                                <AInput v-model:value="heldAt" type="datetime-local" size="large" aria-required="true" :disabled="generating" />
                            </label>
                            <label class="qr-field">
                                <span class="qr-field-label">Kết thúc nhận QR <i aria-hidden="true">*</i></span>
                                <AInput v-model:value="expiresAt" type="datetime-local" size="large" aria-required="true" :disabled="generating" />
                            </label>
                        </div>

                        <label class="qr-field">
                            <span class="qr-field-label">Ghi chú <small>Không bắt buộc</small></span>
                            <AInput v-model:value="note" size="large" placeholder="Ví dụ: Điểm danh đầu giờ" :maxlength="500" :disabled="generating" />
                        </label>

                        <AButton type="primary" size="large" class="qr-generate-button active:scale-[.96] transition-transform" :disabled="!canGenerate" :loading="generating" @click="generateQr">
                            <template #icon><QrCode aria-hidden="true" class="size-4" /></template>{{ payload ? "Tạo mã QR mới" : "Tạo mã QR" }}
                        </AButton>
                    </div>
                </div>

                </ACard>

                <section class="teacher-card qr-history-card" aria-labelledby="qr-history-title">
                    <header class="qr-history-header">
                        <span class="qr-history-icon"><History aria-hidden="true" /></span>
                        <div><h2 id="qr-history-title">Mở lại QR gần đây</h2><p>Chọn phiên đã tạo để trình chiếu lại mã QR.</p></div>
                        <ATag v-if="historyCount" color="blue">{{ historyCount }} phiên</ATag>
                    </header>
                    <div class="qr-history-body">
                        <div v-if="loadingWorkspace" class="qr-history-skeleton" aria-label="Đang tải QR gần đây"><span /><span /><span /></div>
                        <div v-else-if="!classId" class="qr-history-empty"><CalendarClock aria-hidden="true" /><span><b>Chọn lớp để xem lịch sử</b><small>Các QR gần đây sẽ được lọc theo lớp đã chọn.</small></span></div>
                        <template v-else-if="historyCount">
                            <ASelect v-model:value="previousSessionId" size="large" class="qr-history-select" popup-class-name="qr-history-popup" aria-label="Chọn QR gần đây" placeholder="Chọn ngày và giờ" :options="previousSessionOptions" :disabled="generating || loadingQr" @change="loadPreviousQr">
                                <template #option="option">
                                    <div class="qr-history-option">
                                        <span><b>{{ formatDateTime(option.session.held_at) }}</b><small>{{ option.session.class.name }} · hết hạn {{ formatTime(option.session.qr_expires_at) }}</small></span>
                                        <ATag :color="new Date(option.session.qr_expires_at).getTime() > nowMs ? 'green' : 'default'">{{ new Date(option.session.qr_expires_at).getTime() > nowMs ? "Còn hạn" : "Đã hết hạn" }}</ATag>
                                    </div>
                                </template>
                            </ASelect>
                            <p class="qr-history-help">Danh sách hiển thị tối đa 12 phiên mới nhất của các lớp được phân công.</p>
                        </template>
                        <div v-else class="qr-history-empty"><QrCode aria-hidden="true" /><span><b>Chưa có QR gần đây</b><small>Mã vừa tạo sẽ xuất hiện tại đây ngay lập tức.</small></span></div>
                    </div>
                </section>
            </div>

            <ACard :bordered="false" class="teacher-card qr-preview-card" role="region" aria-label="Mã QR trình chiếu" :aria-busy="loadingQr">
                <header class="qr-preview-header">
                    <div class="min-w-0">
                        <p>Màn hình trình chiếu</p>
                        <h2>{{ payload?.session.class.name ?? selectedClass?.name ?? "Mã QR buổi học" }}</h2>
                    </div>
                    <span class="qr-preview-status" aria-live="polite"><ATag :color="payload ? (expired ? 'red' : 'green') : 'default'">{{ payload ? (expired ? "Đã hết hạn" : "Đang nhận điểm danh") : "Chưa tạo" }}</ATag></span>
                </header>

                <div v-if="payload" class="qr-active-state">
                    <div class="qr-code-column">
                        <div ref="qrContainer" class="qr-code-frame">
                            <QrcodeVue :value="qrValue" :size="248" level="M" render-as="canvas" />
                            <div v-if="expired" class="qr-expired-overlay"><div><Timer aria-hidden="true" /><b>Mã QR đã hết hạn</b></div></div>
                        </div>
                        <p>Hết hạn lúc {{ formatTime(payload.session.qr_expires_at) }}</p>
                    </div>

                    <div class="qr-session-details">
                        <dl class="qr-session-meta">
                            <div><dt>Mã lớp</dt><dd>{{ payload.session.class.code }}</dd></div>
                            <div><dt>Buổi học</dt><dd>{{ formatDateTime(payload.session.held_at) }}</dd></div>
                            <div v-if="payload.session.note"><dt>Ghi chú</dt><dd>{{ payload.session.note }}</dd></div>
                        </dl>

                        <div class="qr-countdown" :class="{ 'is-expired': expired }">
                            <span><Timer aria-hidden="true" />Thời gian còn lại</span>
                            <strong role="timer" :aria-label="`Thời gian còn lại ${countdown}`">{{ countdown }}</strong>
                            <small>{{ expired ? "Mọi lượt quét mới đã bị khóa." : "Mã tự động khóa khi đồng hồ về 00:00." }}</small>
                        </div>

                        <AButton size="large" class="qr-download-button active:scale-[.96] transition-transform" @click="downloadQr"><template #icon><Download aria-hidden="true" class="size-4" /></template>Tải ảnh QR</AButton>
                    </div>
                </div>

                <div v-else class="qr-empty-state" role="status">
                    <span class="qr-empty-icon"><QrCode aria-hidden="true" /></span>
                    <h3>Mã QR sẽ xuất hiện tại đây</h3>
                    <p>{{ selectedClass ? `Cấu hình đang chuẩn bị cho ${selectedClass.name}.` : "Chọn lớp để hoàn tất cấu hình buổi học." }}</p>

                    <dl class="qr-draft-summary">
                        <div><dt>Lớp</dt><dd>{{ selectedClass?.code ?? "Chưa chọn" }}</dd></div>
                        <div><dt>Bắt đầu</dt><dd>{{ heldAt ? formatTime(heldAt) : "Chưa đặt" }}</dd></div>
                        <div><dt>Hết hạn</dt><dd>{{ expiresAt ? formatTime(expiresAt) : "Chưa đặt" }}</dd></div>
                    </dl>
                </div>

                <footer class="qr-security-note">
                    <ShieldCheck aria-hidden="true" />
                    <p>Chỉ tài khoản thiếu nhi thuộc lớp mới được ghi nhận; mỗi em chỉ điểm danh một lần.</p>
                </footer>

                <div v-if="loadingQr" class="qr-loading-overlay" role="status" aria-label="Đang tải mã QR"><RefreshCw aria-hidden="true" /></div>
            </ACard>
        </div>
    </section>
</template>

<style scoped>
.qr-workspace{display:grid;grid-template-columns:minmax(20rem,.78fr) minmax(0,1.22fr);align-items:start;gap:1rem}.qr-left-column{display:grid;min-width:0;gap:1rem}.qr-config-card,.qr-preview-card{min-width:0}.qr-config-card{container-name:qr-config;container-type:inline-size}.qr-panel-header,.qr-preview-header{display:flex;min-width:0;align-items:center;gap:.75rem;border-bottom:1px solid #e2e8f0}.qr-panel-header{padding:1rem 1.25rem}.qr-panel-icon{display:grid;width:2.5rem;height:2.5rem;flex:none;place-items:center;border-radius:.75rem;background:#eff6ff;color:#2563eb}.qr-panel-icon svg{width:1.125rem;height:1.125rem;stroke-width:2}.qr-panel-header h2,.qr-preview-header h2{margin:0;color:#172554;font-size:.95rem;font-weight:700;line-height:1.4;text-wrap:balance}.qr-panel-header p,.qr-preview-header p{margin:.125rem 0 0;color:#64748b;font-size:.7rem;line-height:1.5;text-wrap:pretty}.qr-lock-tag{flex:none;margin:0}.qr-lock-tag span{display:inline-flex;align-items:center;gap:.3rem}.qr-lock-tag svg{width:.8rem;height:.8rem}.qr-config-body{padding:1.25rem}.qr-config-body>.ant-alert{margin-bottom:1rem}.qr-form{display:grid;gap:1rem}.qr-field{display:grid;min-width:0;gap:.4rem}.qr-field-label{display:flex;align-items:baseline;gap:.25rem;color:#334155;font-size:.75rem;font-weight:600;line-height:1.45}.qr-field-label i{color:#dc2626;font-style:normal}.qr-field-label small{margin-left:auto;color:#94a3b8;font-size:.65rem;font-weight:500}.qr-time-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}.qr-generate-button,.qr-download-button{min-height:2.75rem;width:100%;font-weight:600}.qr-preview-card{position:relative}.qr-preview-header{justify-content:space-between;background:#f8fafc;padding:.85rem 1.25rem}.qr-preview-header>div{min-width:0}.qr-preview-header p{margin:0;color:#64748b}.qr-preview-header h2{margin-top:.1rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.qr-preview-status{flex:none}.qr-preview-status .ant-tag{margin:0}.qr-active-state{display:grid;grid-template-columns:minmax(14rem,17rem) minmax(0,1fr);align-items:center;gap:1.5rem;padding:1.5rem}.qr-code-column{min-width:0;text-align:center}.qr-code-column>p{margin:.65rem 0 0;color:#64748b;font-size:.7rem;font-weight:500}.qr-code-frame{position:relative;display:grid;width:100%;max-width:17rem;aspect-ratio:1;place-items:center;margin-inline:auto;border-radius:1rem;background:#fff;padding:1rem;box-shadow:0 1px 2px rgba(15,23,42,.06),0 12px 28px rgba(15,23,42,.08);outline:1px solid oklch(0 0 0/.1);outline-offset:-1px}.qr-code-frame :deep(canvas){display:block;width:100%!important;height:auto!important}.qr-expired-overlay{position:absolute;inset:0;display:grid;place-items:center;border-radius:1rem;background:rgba(255,255,255,.94);padding:1rem;text-align:center;backdrop-filter:blur(4px)}.qr-expired-overlay svg{width:2rem;height:2rem;margin-inline:auto;color:#e11d48}.qr-expired-overlay b{display:block;margin-top:.5rem;color:#be123c;font-size:.8rem}.qr-session-details{min-width:0}.qr-session-meta{display:grid;margin:0}.qr-session-meta>div{display:grid;grid-template-columns:5rem minmax(0,1fr);gap:.75rem;padding:.65rem 0;border-bottom:1px solid #e2e8f0}.qr-session-meta>div:first-child{padding-top:0}.qr-session-meta dt{color:#64748b;font-size:.68rem}.qr-session-meta dd{min-width:0;margin:0;color:#1e293b;font-size:.75rem;font-weight:600;overflow-wrap:anywhere}.qr-countdown{margin-top:1rem;border-radius:.75rem;background:#0f172a;padding:.9rem 1rem;color:#fff}.qr-countdown.is-expired{background:#4c0519}.qr-countdown span{display:flex;align-items:center;gap:.4rem;color:#cbd5e1;font-size:.68rem;font-weight:500}.qr-countdown span svg{width:.9rem;height:.9rem}.qr-countdown strong{display:block;margin-top:.2rem;font-size:2rem;font-weight:700;font-variant-numeric:tabular-nums;line-height:1.15;letter-spacing:0}.qr-countdown small{display:block;margin-top:.35rem;color:#cbd5e1;font-size:.62rem;line-height:1.5}.qr-download-button{margin-top:1rem}.qr-empty-state{display:flex;min-height:21rem;flex-direction:column;align-items:center;justify-content:center;padding:2rem 1.5rem;text-align:center}.qr-empty-icon{display:grid;width:3.5rem;height:3.5rem;place-items:center;border-radius:.875rem;background:#f1f5f9;color:#94a3b8}.qr-empty-icon svg{width:1.75rem;height:1.75rem}.qr-empty-state h3{margin:1rem 0 0;color:#172554;font-size:.9rem;font-weight:700;text-wrap:balance}.qr-empty-state>p{max-width:28rem;margin:.35rem 0 0;color:#64748b;font-size:.75rem;line-height:1.65;text-wrap:pretty}.qr-draft-summary{display:grid;width:100%;max-width:30rem;grid-template-columns:repeat(3,minmax(0,1fr));margin:1.25rem 0 0;border-top:1px solid #e2e8f0}.qr-draft-summary>div{min-width:0;padding:.75rem .5rem 0}.qr-draft-summary dt{color:#94a3b8;font-size:.62rem}.qr-draft-summary dd{margin:.2rem 0 0;overflow:hidden;color:#334155;font-size:.72rem;font-weight:600;text-overflow:ellipsis;white-space:nowrap}.qr-security-note{display:flex;align-items:flex-start;gap:.65rem;border-top:1px solid #bfdbfe;background:#eff6ff;padding:.8rem 1.25rem;color:#1e3a8a}.qr-security-note svg{width:1rem;height:1rem;flex:none;margin-top:.1rem;color:#2563eb}.qr-security-note p{margin:0;font-size:.72rem;line-height:1.6;text-wrap:pretty}.qr-loading-overlay{position:absolute;inset:0;display:grid;place-items:center;background:rgba(255,255,255,.82)}.qr-loading-overlay svg{width:1.5rem;height:1.5rem;animation:spin 1s linear infinite;color:#2563eb}@keyframes spin{to{transform:rotate(360deg)}}
.qr-history-card{overflow:visible}.qr-history-header{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.75rem;padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0}.qr-history-icon{display:grid;width:2.25rem;height:2.25rem;place-items:center;border-radius:.625rem;background:#eff6ff;color:#2563eb}.qr-history-icon svg{width:1rem;height:1rem}.qr-history-header h2,.qr-history-header p{margin:0}.qr-history-header h2{color:#172554;font-size:.82rem;font-weight:700}.qr-history-header p{margin-top:.15rem;color:#64748b;font-size:.66rem;line-height:1.5}.qr-history-header .ant-tag{margin:0}.qr-history-body{padding:1rem 1.25rem}.qr-history-select{width:100%}.qr-history-select :deep(.ant-select-selector){min-height:2.75rem!important;border-radius:.625rem!important}.qr-history-help{margin:.6rem 0 0;color:#64748b;font-size:.64rem;line-height:1.5}.qr-history-empty{display:flex;min-height:4.5rem;align-items:center;gap:.75rem;color:#64748b}.qr-history-empty>svg{width:1.3rem;height:1.3rem;flex:none;color:#94a3b8}.qr-history-empty span{display:flex;min-width:0;flex-direction:column}.qr-history-empty b{color:#334155;font-size:.72rem}.qr-history-empty small{margin-top:.2rem;font-size:.64rem;line-height:1.45}.qr-history-skeleton{display:grid;gap:.5rem}.qr-history-skeleton span{height:.75rem;border-radius:.375rem;background:#f1f5f9;animation:history-pulse 1.2s ease-in-out infinite}.qr-history-skeleton span:nth-child(1){width:42%}.qr-history-skeleton span:nth-child(2){height:2.75rem;width:100%}.qr-history-skeleton span:nth-child(3){width:72%}@keyframes history-pulse{50%{opacity:.55}}:global(.qr-history-popup .ant-select-item){min-height:3.5rem;padding:.55rem .75rem}:global(.qr-history-popup .ant-select-item-option-content){overflow:visible}:global(.qr-history-option){display:flex;min-width:0;align-items:center;justify-content:space-between;gap:.75rem}:global(.qr-history-option>span){display:flex;min-width:0;flex-direction:column}:global(.qr-history-option b),:global(.qr-history-option small){overflow:hidden;text-overflow:ellipsis;white-space:nowrap}:global(.qr-history-option b){color:#1e293b;font-size:.72rem}:global(.qr-history-option small){margin-top:.15rem;color:#64748b;font-size:.64rem}:global(.qr-history-option .ant-tag){flex:none;margin:0;font-size:.6rem}
@container qr-config (max-width:28rem){.qr-time-grid{grid-template-columns:1fr}}@media(max-width:1199px){.qr-workspace{grid-template-columns:1fr}.qr-active-state{grid-template-columns:minmax(14rem,17rem) minmax(0,1fr)}}@media(max-width:767px){.qr-panel-header,.qr-preview-header{padding:.875rem 1rem}.qr-panel-header{align-items:flex-start;flex-wrap:wrap}.qr-lock-tag{margin-left:3.25rem}.qr-config-body{padding:1rem}.qr-time-grid{grid-template-columns:1fr}.qr-history-header,.qr-history-body{padding:.875rem 1rem}.qr-active-state{grid-template-columns:1fr;padding:1.25rem 1rem}.qr-code-frame{max-width:16rem}.qr-session-meta{margin-top:.25rem}.qr-empty-state{min-height:17rem;padding:1.5rem 1rem}.qr-draft-summary{grid-template-columns:1fr}.qr-draft-summary>div{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.65rem 0;border-bottom:1px solid #f1f5f9;text-align:left}.qr-draft-summary dd{margin:0}.qr-security-note{padding:.8rem 1rem}}@media(max-width:419px){.qr-lock-tag{margin-left:0}.qr-panel-header h2,.qr-preview-header h2{font-size:.875rem}.qr-preview-header{align-items:flex-start}.qr-history-header{grid-template-columns:auto minmax(0,1fr)}.qr-history-header>.ant-tag{grid-column:2;justify-self:start}.qr-countdown strong{font-size:1.75rem}}@media(prefers-reduced-motion:reduce){.qr-loading-overlay svg,.qr-history-skeleton span{animation:none}}
</style>

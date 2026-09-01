<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import AButton from "ant-design-vue/es/button";
import AInput from "ant-design-vue/es/input";
import ATextarea from "ant-design-vue/es/input/TextArea";
import ASelect from "ant-design-vue/es/select";
import ASwitch from "ant-design-vue/es/switch";
import Modal from "ant-design-vue/es/modal";
import {
    Bell, BellRing, Check, CheckCheck, CheckCircle2, CircleAlert, Megaphone, Pin,
    Plus, RefreshCw, Send, UsersRound, X,
} from "lucide-vue-next";
import { toast } from "vue-sonner";
import { useAuthStore } from "../stores/authStore";
import { getTeacherClasses } from "../api/teacher";
import {
    acknowledgeNotification, createAnnouncement, getNotifications, getTeacherAnnouncements,
    readAllNotifications, readNotification, remindAnnouncement, sendAnnouncement, withdrawAnnouncement,
} from "../api/learning";
import type { Announcement, AnnouncementInput } from "../types/learning";

const auth = useAuthStore();
const isTeacher = computed(() => auth.hasRole("teacher"));
const announcements = ref<Announcement[]>([]);
const selectedId = ref<number | null>(null);
const loading = ref(true);
const saving = ref(false);
const actionId = ref<number | null>(null);
const error = ref("");
const composing = ref(false);
const showUnreadOnly = ref(false);
const unreadCount = ref(0);
const classes = ref<Array<{ id: number; name: string; code: string }>>([]);
const form = reactive<AnnouncementInput>({
    title: "", body: "", importance: "normal", scheduled_at: null, expires_at: null,
    is_pinned: false, requires_acknowledgement: false, targets: [],
});
const targetClassIds = ref<number[]>([]);
const audience = ref<"children" | "parents" | "both">("children");
const selected = computed(() => announcements.value.find((item) => item.id === selectedId.value) ?? announcements.value[0] ?? null);
const statusCopy: Record<string, string> = { draft: "Bản nháp", scheduled: "Đã lên lịch", sent: "Đã gửi", withdrawn: "Đã thu hồi", expired: "Hết hạn", archived: "Đã lưu trữ" };

function apiMessage(value: unknown, fallback: string) { return (value as { response?: { data?: { message?: string } } }).response?.data?.message ?? fallback; }
function formatDate(value: string | null | undefined) {
    return value ? new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value)) : "Chưa gửi";
}
async function load() {
    loading.value = true; error.value = "";
    try {
        if (isTeacher.value) {
            const response = await getTeacherAnnouncements();
            announcements.value = response.data.data.data;
        } else {
            const response = await getNotifications(showUnreadOnly.value);
            announcements.value = response.data.data;
            unreadCount.value = Number(response.data.meta.unread_count ?? 0);
        }
        if (!announcements.value.some((item) => item.id === selectedId.value)) selectedId.value = announcements.value[0]?.id ?? null;
    } catch (exception) { error.value = apiMessage(exception, "Không thể tải thông báo."); }
    finally { loading.value = false; }
}
async function loadClasses() {
    if (!isTeacher.value) return;
    try { classes.value = (await getTeacherClasses()).data.data.map((item) => ({ id: item.id, name: item.name, code: item.code })); }
    catch { /* Form vẫn có thể đóng và danh sách vẫn hoạt động. */ }
}
function resetForm() {
    Object.assign(form, { title: "", body: "", importance: "normal", scheduled_at: null, expires_at: null, is_pinned: false, requires_acknowledgement: false, targets: [] });
    targetClassIds.value = []; audience.value = "children";
}
async function saveAnnouncement(sendNow: boolean) {
    if (!form.title.trim() || !form.body.trim()) { toast.error("Hãy nhập tiêu đề và nội dung thông báo."); return; }
    if (!targetClassIds.value.length) { toast.error("Hãy chọn ít nhất một lớp nhận thông báo."); return; }
    saving.value = true;
    try {
        const created = (await createAnnouncement({
            ...form,
            targets: targetClassIds.value.map((id) => ({ catechism_class_id: id, audience: audience.value, child_ids: [] })),
        })).data.data;
        if (sendNow) {
            await sendAnnouncement(created.id);
            toast.success(form.scheduled_at ? "Đã lên lịch gửi thông báo." : "Đã gửi thông báo đến đúng người nhận.");
        } else toast.success("Đã lưu bản nháp thông báo.");
        composing.value = false; resetForm(); await load();
    } catch (exception) { toast.error(apiMessage(exception, "Không thể lưu thông báo.")); }
    finally { saving.value = false; }
}
async function selectAnnouncement(item: Announcement) {
    selectedId.value = item.id;
    if (!isTeacher.value && !item.is_read) {
        try {
            const updated = (await readNotification(item.id)).data.data;
            Object.assign(item, updated); unreadCount.value = Math.max(0, unreadCount.value - 1);
            window.dispatchEvent(new Event("notifications:changed"));
        } catch { /* Chi tiết vẫn xem được khi đánh dấu đọc lỗi. */ }
    }
}
async function acknowledge(item: Announcement) {
    actionId.value = item.id;
    try { Object.assign(item, (await acknowledgeNotification(item.id)).data.data); toast.success("Đã xác nhận thông báo."); window.dispatchEvent(new Event("notifications:changed")); }
    catch (exception) { toast.error(apiMessage(exception, "Không thể xác nhận thông báo.")); }
    finally { actionId.value = null; }
}
async function markAllRead() {
    try { await readAllNotifications(); announcements.value.forEach((item) => { item.is_read = true; }); unreadCount.value = 0; window.dispatchEvent(new Event("notifications:changed")); toast.success("Đã đánh dấu tất cả là đã đọc."); }
    catch { toast.error("Không thể đánh dấu tất cả thông báo."); }
}
async function sendDraft(item: Announcement) {
    actionId.value = item.id;
    try { await sendAnnouncement(item.id); toast.success("Đã gửi thông báo."); await load(); }
    catch (exception) { toast.error(apiMessage(exception, "Không thể gửi thông báo.")); }
    finally { actionId.value = null; }
}
async function remind(item: Announcement) {
    actionId.value = item.id;
    try { const count = (await remindAnnouncement(item.id)).data.data.reminded_count; toast.success(count ? `Đã nhắc ${count} người chưa hoàn thành.` : "Mọi người đã đọc hoặc xác nhận."); }
    catch (exception) { toast.error(apiMessage(exception, "Không thể gửi nhắc lại.")); }
    finally { actionId.value = null; }
}
function withdraw(item: Announcement) {
    Modal.confirm({ title: "Thu hồi thông báo?", content: "Người nhận sẽ không còn thấy thông báo trong hộp thư. Lịch sử gửi vẫn được giữ lại.", okText: "Thu hồi", okType: "danger", cancelText: "Hủy", async onOk() {
        actionId.value = item.id;
        try { await withdrawAnnouncement(item.id); toast.success("Đã thu hồi thông báo."); await load(); }
        catch (exception) { toast.error(apiMessage(exception, "Không thể thu hồi thông báo.")); }
        finally { actionId.value = null; }
    } });
}
async function toggleUnread() { showUnreadOnly.value = !showUnreadOnly.value; await load(); }
onMounted(() => Promise.all([load(), loadClasses()]));
</script>

<template>
    <section class="notifications-page">
        <header class="notifications-heading"><div><h1>{{ isTeacher ? 'Thông báo lớp' : 'Thông báo' }}</h1><p>{{ isTeacher ? 'Gửi thông tin một chiều đến Thiếu nhi hoặc Phụ huynh, theo dõi đọc và xác nhận.' : 'Thông tin mới từ Giáo lý viên và các bài tập của em.' }}</p></div><div><AButton v-if="!isTeacher" :disabled="unreadCount===0" @click="markAllRead"><CheckCheck />Đọc tất cả</AButton><AButton v-else type="primary" @click="composing=!composing"><X v-if="composing"/><Plus v-else/>{{ composing ? 'Đóng trình soạn' : 'Tạo thông báo' }}</AButton></div></header>

        <Transition name="composer">
            <section v-if="isTeacher && composing" class="announcement-composer" aria-labelledby="compose-title">
                <header><Megaphone /><div><h2 id="compose-title">Soạn thông báo lớp</h2><p>Thông báo quan trọng có thể yêu cầu người nhận bấm xác nhận.</p></div></header>
                <div class="composer-fields">
                    <label class="wide"><span>Tiêu đề *</span><AInput v-model:value="form.title" :maxlength="255" placeholder="Nội dung chính cần mọi người chú ý" /></label>
                    <label class="wide"><span>Nội dung *</span><ATextarea v-model:value="form.body" :rows="5" :maxlength="20000" placeholder="Viết ngắn gọn, rõ việc cần làm và thời gian…" /></label>
                    <label><span>Lớp nhận *</span><ASelect v-model:value="targetClassIds" mode="multiple" :options="classes.map(item=>({value:item.id,label:`${item.name} · ${item.code}`}))" placeholder="Chọn lớp" /></label>
                    <label><span>Đối tượng</span><ASelect v-model:value="audience" :options="[{value:'children',label:'Thiếu nhi'},{value:'parents',label:'Phụ huynh'},{value:'both',label:'Thiếu nhi và Phụ huynh'}]" /></label>
                    <label><span>Mức độ</span><ASelect v-model:value="form.importance" :options="[{value:'normal',label:'Thông thường'},{value:'important',label:'Quan trọng'},{value:'urgent',label:'Khẩn'}]" /></label>
                    <label><span>Lên lịch gửi <small>(để trống nếu gửi ngay)</small></span><input v-model="form.scheduled_at" type="datetime-local"></label>
                    <label class="switch-field"><span><b>Ghim lên đầu hộp thư</b><small>Phù hợp với thông tin còn hiệu lực nhiều ngày</small></span><ASwitch v-model:checked="form.is_pinned" /></label>
                    <label class="switch-field"><span><b>Yêu cầu xác nhận</b><small>Thống kê riêng người chưa bấm “Đã hiểu”</small></span><ASwitch v-model:checked="form.requires_acknowledgement" /></label>
                </div>
                <footer><AButton :loading="saving" @click="saveAnnouncement(false)">Lưu bản nháp</AButton><AButton type="primary" :loading="saving" @click="saveAnnouncement(true)"><Send />{{ form.scheduled_at ? 'Lên lịch gửi' : 'Gửi thông báo' }}</AButton></footer>
            </section>
        </Transition>

        <nav v-if="!isTeacher" class="inbox-tabs" aria-label="Lọc thông báo"><button type="button" :class="{active:!showUnreadOnly}" @click="showUnreadOnly && toggleUnread()">Tất cả</button><button type="button" :class="{active:showUnreadOnly}" @click="!showUnreadOnly && toggleUnread()">Chưa đọc <span v-if="unreadCount">{{ unreadCount }}</span></button></nav>
        <div v-if="error" class="notifications-state error" role="alert"><CircleAlert /><div><b>Chưa tải được thông báo</b><p>{{ error }}</p></div><AButton @click="load"><RefreshCw />Thử lại</AButton></div>
        <div v-else-if="loading" class="notifications-loading"><span /><span /></div>
        <div v-else-if="!announcements.length" class="notifications-state"><Bell /><div><b>{{ showUnreadOnly ? 'Không còn thông báo chưa đọc' : 'Chưa có thông báo' }}</b><p>{{ isTeacher ? 'Tạo thông báo đầu tiên để gửi đến lớp.' : 'Thông báo mới sẽ xuất hiện tại đây.' }}</p></div></div>
        <section v-else class="inbox-workspace">
            <aside class="message-list">
                <button v-for="item in announcements" :key="item.id" type="button" class="message-row" :class="{selected:selected?.id===item.id,unread:!isTeacher&&!item.is_read}" @click="selectAnnouncement(item)">
                    <span class="importance-dot" :class="item.importance" /><span><span><Pin v-if="item.is_pinned" />{{ isTeacher ? statusCopy[item.status] : item.importance==='important'?'Quan trọng':item.importance==='urgent'?'Khẩn':'Thông báo' }}</span><b>{{ item.title }}</b><small>{{ item.body }}</small></span><time>{{ formatDate(item.sent_at ?? item.scheduled_at) }}</time>
                </button>
            </aside>
            <article v-if="selected" class="message-detail">
                <header><div><span class="importance-label" :class="selected.importance"><BellRing />{{ selected.importance==='urgent'?'Khẩn':selected.importance==='important'?'Quan trọng':'Thông báo' }}</span><h2>{{ selected.title }}</h2><p>{{ formatDate(selected.sent_at ?? selected.scheduled_at) }}</p></div><Pin v-if="selected.is_pinned" class="pin-icon" /></header>
                <div class="message-body">{{ selected.body }}</div>
                <dl v-if="isTeacher"><div><dt><UsersRound />Người nhận</dt><dd>{{ selected.recipient_count ?? 0 }}</dd></div><div><dt><Check />Chưa đọc</dt><dd>{{ selected.unread_count ?? 0 }}</dd></div><div v-if="selected.requires_acknowledgement"><dt><CheckCheck />Chưa xác nhận</dt><dd>{{ selected.unacknowledged_count ?? 0 }}</dd></div></dl>
                <div v-if="selected.source_type?.startsWith('assignment_')" class="source-note"><BellRing /><p>Thông báo này được tạo tự động từ một sự kiện bài tập.</p></div>
                <footer v-if="isTeacher"><AButton v-if="selected.status==='draft'" type="primary" :loading="actionId===selected.id" @click="sendDraft(selected)"><Send />Gửi ngay</AButton><AButton v-if="selected.status==='sent'" :loading="actionId===selected.id" @click="remind(selected)"><BellRing />Nhắc người chưa hoàn thành</AButton><AButton v-if="['sent','scheduled'].includes(selected.status)" danger :loading="actionId===selected.id" @click="withdraw(selected)">Thu hồi</AButton></footer>
                <footer v-else-if="selected.requires_acknowledgement && !selected.is_acknowledged"><AButton type="primary" size="large" :loading="actionId===selected.id" @click="acknowledge(selected)"><Check />Em đã đọc và hiểu</AButton></footer>
                <div v-else-if="!isTeacher && selected.is_acknowledged" class="acknowledged"><CheckCircle2 /><span>Em đã xác nhận thông báo này.</span></div>
            </article>
        </section>
    </section>
</template>

<style scoped>
.notifications-page{display:grid;gap:16px}.notifications-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:16px}.notifications-heading h1{margin:0;color:#172554;font-size:23px;font-weight:770;letter-spacing:-.025em}.notifications-heading p{max-width:65ch;margin:6px 0 0;color:#64748b;font-size:11px}.notifications-heading>div:last-child{display:flex;gap:8px}.notifications-heading .ant-btn{display:flex;min-height:40px;align-items:center;gap:7px;border-radius:9px;font-weight:650}.notifications-heading svg{width:16px}.announcement-composer{overflow:hidden;border:1px solid #bfdbfe;border-radius:14px;background:#fff;box-shadow:0 14px 34px rgba(37,99,235,.08)}.announcement-composer>header{display:flex;align-items:center;gap:12px;padding:17px 20px;border-bottom:1px solid #dbeafe;background:#f8fbff}.announcement-composer>header>svg{width:23px;color:#2563eb}.announcement-composer h2{margin:0;color:#172554;font-size:15px;font-weight:750}.announcement-composer header p{margin:3px 0 0;color:#64748b;font-size:10px}.composer-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;padding:20px}.composer-fields label{display:grid;gap:6px}.composer-fields label.wide{grid-column:1/-1}.composer-fields label>span{color:#475569;font-size:10px;font-weight:700}.composer-fields label>span small{color:#94a3b8;font-weight:500}.composer-fields :deep(.ant-input),.composer-fields :deep(.ant-select-selector),.composer-fields input{min-height:39px;border-radius:8px!important}.composer-fields input{border:1px solid #d9d9d9;padding:7px 10px;color:#334155;font:inherit;font-size:11px}.composer-fields .switch-field{display:flex;align-items:center;justify-content:space-between;border:1px solid #e2e8f0;border-radius:10px;padding:11px}.switch-field b,.switch-field small{display:block}.switch-field small{margin-top:3px}.announcement-composer>footer{display:flex;justify-content:flex-end;gap:8px;border-top:1px solid #e7edf5;padding:12px 20px}.announcement-composer>footer .ant-btn{display:flex;align-items:center;gap:6px;border-radius:8px}.announcement-composer>footer svg{width:15px}.composer-enter-active,.composer-leave-active{transition:opacity .16s ease,transform .3s cubic-bezier(.16,1,.3,1),clip-path .3s cubic-bezier(.16,1,.3,1)}.composer-enter-from,.composer-leave-to{opacity:0;transform:translateY(-8px);clip-path:inset(0 0 100% 0)}.inbox-tabs{display:flex;border-bottom:1px solid #dbe3ee}.inbox-tabs button{min-height:40px;border:0;border-bottom:2px solid transparent;background:transparent;padding:8px 13px;color:#64748b;font-size:11px;font-weight:650}.inbox-tabs button.active{border-bottom-color:#2563eb;color:#1d4ed8}.inbox-tabs span{margin-left:4px;border-radius:999px;background:#2563eb;padding:1px 5px;color:#fff;font-size:8px}.inbox-workspace{display:grid;grid-template-columns:minmax(280px,.82fr) minmax(0,1.5fr);overflow:hidden;min-height:510px;border:1px solid #dbe3ee;border-radius:14px;background:#fff;box-shadow:0 12px 34px rgba(15,23,42,.045)}.message-list{border-right:1px solid #dbe3ee}.message-row{display:grid;width:100%;grid-template-columns:8px minmax(0,1fr) auto;align-items:start;gap:9px;border:0;border-bottom:1px solid #edf1f6;background:#fff;padding:14px;text-align:left;transition:background-color .15s ease,box-shadow .15s ease}.message-row:hover{background:#fbfdff}.message-row.selected{background:#f3f7ff;box-shadow:inset 3px 0 #2563eb}.message-row.unread{background:#f8fbff}.importance-dot{width:7px;height:7px;margin-top:5px;border-radius:50%;background:#94a3b8}.importance-dot.important{background:#d97706}.importance-dot.urgent{background:#dc2626}.message-row>span:nth-child(2){min-width:0}.message-row>span:nth-child(2)>span{display:flex;align-items:center;gap:4px;color:#64748b;font-size:8px;font-weight:650}.message-row>span:nth-child(2)>span svg{width:10px;color:#d97706}.message-row b,.message-row small{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.message-row b{margin-top:5px;color:#172554;font-size:11px}.message-row.unread b{font-weight:780}.message-row small{margin-top:4px;color:#64748b;font-size:9px}.message-row time{color:#94a3b8;font-size:7px;white-space:nowrap}.message-detail{min-width:0;padding:24px}.message-detail>header{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding-bottom:18px;border-bottom:1px solid #e7edf5}.importance-label{display:inline-flex;align-items:center;gap:5px;border-radius:999px;background:#eef2f7;padding:4px 8px;color:#52627c;font-size:8px;font-weight:700}.importance-label.important{background:#fff5dc;color:#a94e08}.importance-label.urgent{background:#feeceb;color:#b42318}.importance-label svg{width:12px}.message-detail h2{margin:10px 0 0;color:#172554;font-size:21px;font-weight:770;letter-spacing:-.025em;text-wrap:balance}.message-detail header p{margin:6px 0 0;color:#64748b;font-size:9px}.pin-icon{width:18px;color:#d97706}.message-body{max-width:72ch;min-height:160px;padding:22px 0;color:#334155;font-size:12px;line-height:1.85;white-space:pre-wrap}.message-detail dl{display:flex;gap:0;margin:0;border-block:1px solid #e7edf5}.message-detail dl>div{min-width:110px;padding:12px}.message-detail dl>div+div{border-left:1px solid #e7edf5}.message-detail dt{display:flex;align-items:center;gap:5px;color:#64748b;font-size:8px}.message-detail dt svg{width:12px}.message-detail dd{margin:5px 0 0;color:#172554;font-size:13px;font-weight:750}.source-note{display:flex;align-items:center;gap:8px;margin-top:16px;border-radius:9px;background:#eff6ff;padding:10px;color:#1d4ed8}.source-note svg{width:15px}.source-note p{margin:0;font-size:9px}.message-detail>footer{display:flex;flex-wrap:wrap;gap:8px;margin-top:20px}.message-detail>footer .ant-btn{display:flex;min-height:39px;align-items:center;gap:6px;border-radius:8px;font-weight:650}.message-detail>footer svg{width:14px}.acknowledged{display:flex;align-items:center;gap:7px;margin-top:20px;color:#15803d;font-size:10px;font-weight:650}.acknowledged svg{width:16px}.notifications-state{display:flex;min-height:280px;align-items:center;justify-content:center;gap:14px;border:1px solid #dbe3ee;border-radius:14px;background:#fff;padding:24px}.notifications-state>svg{width:35px;color:#2563eb}.notifications-state.error>svg{color:#dc2626}.notifications-state b{color:#172554}.notifications-state p{margin:4px 0 0;color:#64748b;font-size:10px}.notifications-state .ant-btn{display:flex;align-items:center;gap:6px;margin-left:12px}.notifications-state .ant-btn svg{width:14px}.notifications-loading{display:grid;grid-template-columns:.82fr 1.5fr;height:440px;gap:1px;background:#dbe3ee}.notifications-loading span{background:linear-gradient(90deg,#fff,#f4f7fa,#fff);background-size:200% 100%;animation:loading 1.4s infinite}@keyframes loading{to{background-position:-200% 0}}
@media(min-width:1600px){.notifications-page{gap:20px}.announcement-composer>header{padding:20px 26px}.composer-fields{grid-template-columns:repeat(3,minmax(0,1fr));gap:18px 22px;padding:24px 26px}.composer-fields label.wide{grid-column:1/-1}.announcement-composer>footer{padding:14px 26px}.inbox-workspace{grid-template-columns:440px minmax(0,1fr);min-height:max(620px,calc(100dvh - 230px))}.message-row{gap:11px;padding:16px 18px}.message-row>span:nth-child(2)>span{font-size:9px}.message-row b{font-size:12px}.message-row small{font-size:10px}.message-row time{font-size:9px}.message-detail{padding:32px 40px}.message-body{font-size:13px;line-height:1.8}.notifications-loading{grid-template-columns:440px 1fr;height:620px}}
@media(max-width:800px){.inbox-workspace{grid-template-columns:1fr}.message-list{border-right:0}.message-row:not(.selected){display:none}.message-detail{border-top:1px solid #dbe3ee}.notifications-loading{grid-template-columns:1fr}.message-body{min-height:100px}}
@media(max-width:600px){.notifications-heading{align-items:stretch;flex-direction:column}.notifications-heading>div:last-child{display:grid}.notifications-heading .ant-btn{justify-content:center}.composer-fields{grid-template-columns:1fr;padding:15px}.composer-fields label.wide{grid-column:auto}.message-detail{padding:18px 14px}.message-detail h2{font-size:18px}.message-detail dl{display:grid}.message-detail dl>div+div{border-top:1px solid #e7edf5;border-left:0}.notifications-state{align-items:flex-start;flex-direction:column}.notifications-state .ant-btn{margin-left:0}}
@media(prefers-reduced-motion:reduce){.composer-enter-active,.composer-leave-active,.message-row{transition:none}.notifications-loading span{animation:none}}
</style>

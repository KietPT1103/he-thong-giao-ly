<script setup lang="ts">
import AButton from "ant-design-vue/es/button";
import { BookOpen, CalendarCheck, CalendarClock, Clock3, ListChecks, MapPin, NotebookText, QrCode, School, UsersRound, X } from "lucide-vue-next";
import { formatLongDate, type TeachingCalendarEvent } from "./scheduleCalendar";

defineProps<{event:TeachingCalendarEvent|null;closable?:boolean}>();
defineEmits<{close:[];attendance:[];qr:[];classList:[];requestChange:[]}>();
</script>

<template>
    <aside class="schedule-detail" aria-label="Chi tiết buổi dạy">
        <template v-if="event">
            <div class="detail-topline"><span>Buổi dạy</span><button v-if="closable" type="button" aria-label="Đóng chi tiết" @click="$emit('close')"><X /></button></div>
            <div class="detail-identity"><span><School /></span><div><h2>{{ event.classItem.name }}</h2><p>Niên khóa {{ event.classItem.academic_year?.name || "Chưa cập nhật" }}</p></div></div>
            <dl class="detail-facts">
                <div><dt><Clock3 />Thời gian</dt><dd><strong>{{ event.startsAt }} – {{ event.endsAt }}</strong><span>{{ formatLongDate(event.date) }}</span></dd></div>
                <div><dt><MapPin />Phòng học</dt><dd><strong>{{ event.classItem.classroom?.name || "Chưa xếp phòng" }}</strong></dd></div>
                <div><dt><UsersRound />Số thiếu nhi</dt><dd><strong>{{ event.classItem.children_count ?? 0 }} em</strong></dd></div>
                <div><dt><BookOpen />Giáo trình</dt><dd><strong>Chưa cập nhật</strong><span>Thông tin giáo trình chưa có trong lịch lớp.</span></dd></div>
                <div><dt><NotebookText />Ghi chú</dt><dd><strong>Chưa có ghi chú</strong></dd></div>
            </dl>
            <div class="detail-actions">
                <h3>Thao tác nhanh</h3>
                <AButton type="primary" size="large" block @click="$emit('attendance')"><template #icon><CalendarCheck /></template>Đi đến điểm danh</AButton>
                <AButton size="large" block @click="$emit('qr')"><template #icon><QrCode /></template>Tạo QR điểm danh</AButton>
                <AButton size="large" block @click="$emit('classList')"><template #icon><ListChecks /></template>Xem danh sách lớp</AButton>
                <AButton size="large" block class="request-change-button" @click="$emit('requestChange')"><template #icon><CalendarClock /></template>Yêu cầu đổi lịch</AButton>
            </div>
        </template>
        <div v-else class="detail-empty"><CalendarClock /><h2>Chọn một buổi dạy</h2><p>Thông tin lớp và các thao tác nhanh sẽ xuất hiện tại đây.</p></div>
    </aside>
</template>

<style scoped>
.schedule-detail{min-width:0;background:#fff}.detail-topline{display:flex;min-height:48px;align-items:center;justify-content:space-between;padding:0 16px;border-bottom:1px solid #edf1f6}.detail-topline>span{border-radius:7px;background:#eff6ff;padding:5px 8px;color:#1d4ed8;font-size:9px;font-weight:750;text-transform:uppercase}.detail-topline button{display:grid;width:40px;height:40px;cursor:pointer;place-items:center;border:0;border-radius:10px;background:transparent;color:#64748b}.detail-topline button:hover{background:#f1f5f9;color:#0f172a}.detail-topline svg{width:16px;height:16px}.detail-identity{display:flex;align-items:center;gap:12px;padding:18px 16px}.detail-identity>span{display:grid;width:48px;height:48px;flex:none;place-items:center;border-radius:14px;background:#eaf3ff;color:#2563eb;box-shadow:inset 0 0 0 1px rgba(37,99,235,.08)}.detail-identity svg{width:23px;height:23px;stroke-width:1.8}.detail-identity h2,.detail-identity p{margin:0}.detail-identity h2{color:#10234b;font-size:16px;font-weight:760;letter-spacing:-.02em;text-wrap:balance}.detail-identity p{margin-top:3px;color:#64748b;font-size:10px}.detail-facts{margin:0;padding:0 16px}.detail-facts>div{display:grid;grid-template-columns:104px minmax(0,1fr);gap:10px;padding:12px 0;border-top:1px solid #edf1f6}.detail-facts dt{display:flex;align-items:flex-start;gap:7px;color:#64748b;font-size:10px;font-weight:600}.detail-facts dt svg{width:14px;height:14px;flex:none;color:#7c8da6;stroke-width:1.8}.detail-facts dd{margin:0}.detail-facts strong,.detail-facts span{display:block}.detail-facts strong{color:#263957;font-size:10px;font-weight:700;line-height:1.45}.detail-facts span{margin-top:2px;color:#64748b;font-size:9px;line-height:1.5;text-wrap:pretty}.detail-actions{display:grid;gap:8px;margin:4px 16px 16px;padding-top:16px;border-top:1px solid #e2e8f0}.detail-actions h3{margin:0 0 2px;color:#334155;font-size:10px;font-weight:700}.detail-actions :deep(.ant-btn){min-height:40px;border-radius:10px;font-size:11px;font-weight:650}.detail-actions :deep(svg){width:15px;height:15px;stroke-width:2}.request-change-button{margin-top:4px;color:#475569}.detail-empty{display:grid;min-height:420px;place-items:center;align-content:center;padding:24px;text-align:center}.detail-empty>svg{width:34px;height:34px;color:#94a3b8}.detail-empty h2{margin:12px 0 0;color:#334155;font-size:13px}.detail-empty p{max-width:28ch;margin:5px 0 0;color:#64748b;font-size:10px;line-height:1.6;text-wrap:pretty}@media(max-width:1023px){.schedule-detail{min-height:100%}.detail-identity{padding-top:8px}.detail-actions :deep(.ant-btn){min-height:44px}}
.detail-topline{min-height:56px;padding-inline:24px}.detail-topline>span{padding:6px 10px;font-size:10px}.detail-topline button{width:44px;height:44px}.detail-topline svg{width:18px;height:18px}.detail-identity{gap:16px;padding:24px}.detail-identity>span{width:64px;height:64px;border-radius:16px}.detail-identity svg{width:30px;height:30px}.detail-identity h2{font-size:22px}.detail-identity p{margin-top:5px;font-size:13px}.detail-facts{display:grid;grid-template-columns:1fr 1fr;gap:0 24px;padding:0 24px}.detail-facts>div{grid-template-columns:120px minmax(0,1fr);gap:14px;padding:16px 0}.detail-facts>div:first-child,.detail-facts>div:nth-child(4),.detail-facts>div:nth-child(5){grid-column:1/-1}.detail-facts dt{gap:8px;font-size:12px}.detail-facts dt svg{width:17px;height:17px}.detail-facts strong{font-size:13px}.detail-facts span{margin-top:4px;font-size:11px}.detail-actions{grid-template-columns:1fr 1fr;gap:10px;margin:8px 24px 24px;padding-top:20px}.detail-actions h3{grid-column:1/-1;margin-bottom:2px;font-size:12px}.detail-actions :deep(.ant-btn){min-height:46px;border-radius:10px;font-size:12px}.detail-actions :deep(svg){width:17px;height:17px}.request-change-button{margin-top:0}@media(max-width:639px){.detail-topline{padding-inline:16px}.detail-identity{padding:20px 16px}.detail-identity>span{width:54px;height:54px}.detail-identity h2{font-size:19px}.detail-facts{display:block;padding-inline:16px}.detail-facts>div{grid-template-columns:112px minmax(0,1fr);padding:14px 0}.detail-actions{grid-template-columns:1fr;margin:6px 16px 16px}.detail-actions h3{grid-column:auto}}
</style>

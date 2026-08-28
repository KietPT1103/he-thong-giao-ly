<script setup lang="ts">
import AButton from "ant-design-vue/es/button";
import APopover from "ant-design-vue/es/popover";
import { CircleOff, Eye, MoreHorizontal, Trash2 } from "lucide-vue-next";
import type { AttendanceSession } from "../../types/api";

withDefaults(defineProps<{
    session: AttendanceSession;
    busyAction?: "cancel" | "delete" | null;
    showView?: boolean;
}>(), {
    busyAction: null,
    showView: true,
});

defineEmits<{
    view: [session: AttendanceSession];
    cancel: [session: AttendanceSession];
    delete: [session: AttendanceSession];
}>();

function formatSession(value: string) {
    return new Intl.DateTimeFormat("vi-VN", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value));
}
</script>

<template>
    <APopover trigger="click" placement="bottomRight" overlay-class-name="attendance-session-action-popover">
        <template #content>
            <div class="attendance-session-action-menu" role="menu" :aria-label="`Thao tác phiên ${formatSession(session.held_at)}`">
                <button v-if="showView" type="button" role="menuitem" @click="$emit('view', session)"><Eye aria-hidden="true" />Xem chi tiết</button>
                <button v-if="session.status==='active'" type="button" role="menuitem" :disabled="Boolean(busyAction)" @click="$emit('cancel', session)"><CircleOff aria-hidden="true" />Hủy phiên</button>
                <button type="button" role="menuitem" class="danger" :disabled="Boolean(busyAction)" @click="$emit('delete', session)"><Trash2 aria-hidden="true" />Xóa phiên</button>
            </div>
        </template>
        <AButton class="attendance-session-more-button" :loading="Boolean(busyAction)" :aria-label="`Mở thao tác phiên ${formatSession(session.held_at)}`">
            <MoreHorizontal v-if="!busyAction" aria-hidden="true" />
        </AButton>
    </APopover>
</template>

<style scoped>
.attendance-session-more-button {
    display: grid;
    width: 40px;
    min-width: 40px;
    height: 40px;
    place-items: center;
    border-color: #dbe3ee;
    border-radius: 9px;
    background: #fff;
    padding: 0;
    color: #52627c;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    transition: border-color 140ms ease, background-color 140ms ease, color 140ms ease, box-shadow 140ms ease, scale 100ms ease;
}

.attendance-session-more-button:hover,
.attendance-session-more-button:focus-visible {
    border-color: #93c5fd;
    background: #eff6ff;
    color: #1769e0;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
}

.attendance-session-more-button:active {
    scale: .96;
}

.attendance-session-more-button svg {
    width: 19px;
    height: 19px;
    stroke-width: 2;
}

.attendance-session-action-menu {
    display: grid;
    min-width: 184px;
    gap: 2px;
}

.attendance-session-action-menu button {
    display: flex;
    min-height: 42px;
    align-items: center;
    gap: 10px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    padding: 8px 10px;
    color: #334155;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    text-align: left;
    transition: background-color 140ms ease, color 140ms ease;
}

.attendance-session-action-menu button:hover {
    background: #f1f5f9;
    color: #12396f;
}

.attendance-session-action-menu button:focus-visible {
    outline: 2px solid #93c5fd;
    outline-offset: -2px;
}

.attendance-session-action-menu button.danger {
    color: #dc2626;
}

.attendance-session-action-menu button.danger:hover {
    background: #fff1f2;
    color: #b91c1c;
}

.attendance-session-action-menu button:disabled {
    cursor: not-allowed;
    opacity: .5;
}

.attendance-session-action-menu svg {
    width: 17px;
    height: 17px;
    flex: none;
    stroke-width: 2;
}

:global(.attendance-session-action-popover .ant-popover-inner) {
    border-radius: 12px;
    padding: 5px;
    box-shadow: 0 12px 30px rgba(15, 23, 42, .14), 0 0 0 1px rgba(15, 23, 42, .06);
}

:global(.attendance-session-action-popover .ant-popover-inner-content) {
    padding: 0;
}

:global(.attendance-session-action-popover .ant-popover-arrow) {
    display: none;
}

@media (pointer: coarse) {
    .attendance-session-more-button {
        width: 44px;
        min-width: 44px;
        height: 44px;
    }
}

@media (prefers-reduced-motion: reduce) {
    .attendance-session-more-button,
    .attendance-session-action-menu button {
        transition: none;
    }
}
</style>

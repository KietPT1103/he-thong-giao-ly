<script setup lang="ts">
import { MapPin, UsersRound } from "lucide-vue-next";
import type { TeachingCalendarEvent } from "./scheduleCalendar";

defineProps<{ event: TeachingCalendarEvent; selected?: boolean; compact?: boolean }>();
defineEmits<{ select: [event: TeachingCalendarEvent] }>();
</script>

<template>
    <button
        type="button"
        class="schedule-event"
        :class="[`schedule-event--${event.tone}`, { 'is-selected': selected, 'is-compact': compact }]"
        :aria-pressed="selected"
        :aria-label="`${event.classItem.name}, ${event.startsAt} đến ${event.endsAt}`"
        @click="$emit('select', event)"
    >
        <span class="schedule-event-time">{{ event.startsAt }} – {{ event.endsAt }}</span>
        <strong>{{ event.classItem.name }}</strong>
        <span v-if="!compact"><MapPin />{{ event.classItem.classroom?.name || "Chưa xếp phòng" }}</span>
        <span v-if="!compact"><UsersRound />{{ event.classItem.children_count ?? 0 }} thiếu nhi</span>
    </button>
</template>

<style scoped>
.schedule-event{display:flex;width:100%;min-width:0;cursor:pointer;flex-direction:column;align-items:flex-start;gap:3px;overflow:hidden;border:1px solid transparent;border-radius:8px;padding:7px 8px;text-align:left;box-shadow:0 1px 2px rgba(15,23,42,.04);transition:border-color 140ms ease,box-shadow 140ms ease,transform 140ms ease}.schedule-event:hover{transform:translateY(-1px);box-shadow:0 5px 14px rgba(15,23,42,.1)}.schedule-event:active{transform:scale(.96)}.schedule-event.is-selected{border-color:#2563eb;box-shadow:0 0 0 2px rgba(37,99,235,.13),0 6px 16px rgba(37,99,235,.12)}.schedule-event--blue{background:#eaf3ff;color:#174f9f}.schedule-event--violet{background:#f1edff;color:#5b3fa3}.schedule-event--green{background:#eaf8f1;color:#23745a}.schedule-event--amber{background:#fff6dc;color:#8a5a09}.schedule-event-time{font-size:9px;font-weight:650;font-variant-numeric:tabular-nums;line-height:1.25}.schedule-event strong{display:block;max-width:100%;overflow:hidden;font-size:11px;font-weight:750;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}.schedule-event>span:not(.schedule-event-time){display:flex;max-width:100%;align-items:center;gap:3px;overflow:hidden;font-size:9px;line-height:1.35;text-overflow:ellipsis;white-space:nowrap}.schedule-event svg{width:10px;height:10px;flex:none;stroke-width:1.8}.schedule-event.is-compact{min-height:24px;padding:4px 6px}.schedule-event.is-compact strong{font-size:10px}.schedule-event.is-compact .schedule-event-time{display:none}@media(prefers-reduced-motion:reduce){.schedule-event{transition:none}.schedule-event:hover,.schedule-event:active{transform:none}}
.schedule-event{gap:4px;padding:8px 9px}.schedule-event-time{font-size:10px}.schedule-event strong{font-size:12px}.schedule-event>span:not(.schedule-event-time){font-size:10px}.schedule-event svg{width:11px;height:11px}
</style>

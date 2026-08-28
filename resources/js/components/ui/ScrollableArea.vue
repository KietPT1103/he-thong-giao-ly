<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, useAttrs } from "vue";

defineOptions({ inheritAttrs: false });

withDefaults(defineProps<{ as?: string }>(), { as: "div" });

const attrs = useAttrs();
const viewport = ref<HTMLElement | null>(null);
const thumbHeight = ref(0);
const thumbTop = ref(4);
const canScrollVertically = ref(false);
let resizeObserver: ResizeObserver | null = null;
let mutationObserver: MutationObserver | null = null;
let dragStartY = 0;
let dragStartScrollTop = 0;

function updateThumb() {
    const element = viewport.value;
    if (!element) return;
    const trackHeight = Math.max(element.clientHeight - 8, 0);
    const scrollRange = element.scrollHeight - element.clientHeight;
    canScrollVertically.value = scrollRange > 1;
    if (!canScrollVertically.value) return;
    thumbHeight.value = Math.min(trackHeight, Math.max(48, (element.clientHeight / element.scrollHeight) * trackHeight));
    const thumbRange = trackHeight - thumbHeight.value;
    thumbTop.value = 4 + (element.scrollTop / scrollRange) * thumbRange;
}

function onThumbPointerDown(event: PointerEvent) {
    const element = viewport.value;
    if (!element) return;
    event.preventDefault();
    dragStartY = event.clientY;
    dragStartScrollTop = element.scrollTop;
    window.addEventListener("pointermove", onThumbPointerMove);
    window.addEventListener("pointerup", stopDragging, { once: true });
}

function onTrackPointerDown(event: PointerEvent) {
    const element = viewport.value;
    if (!element || (event.target as HTMLElement).classList.contains("ui-scroll-thumb")) return;
    const track = event.currentTarget as HTMLElement;
    const pointerPosition = event.clientY - track.getBoundingClientRect().top - thumbHeight.value / 2;
    const trackRange = Math.max(element.clientHeight - 8 - thumbHeight.value, 1);
    const scrollRange = element.scrollHeight - element.clientHeight;
    element.scrollTop = (Math.min(Math.max(pointerPosition, 0), trackRange) / trackRange) * scrollRange;
}

function onThumbPointerMove(event: PointerEvent) {
    const element = viewport.value;
    if (!element) return;
    const trackRange = Math.max(element.clientHeight - 8 - thumbHeight.value, 1);
    const scrollRange = element.scrollHeight - element.clientHeight;
    element.scrollTop = dragStartScrollTop + ((event.clientY - dragStartY) / trackRange) * scrollRange;
}

function stopDragging() {
    window.removeEventListener("pointermove", onThumbPointerMove);
}

onMounted(async () => {
    await nextTick();
    updateThumb();
    if (!viewport.value) return;
    resizeObserver = new ResizeObserver(updateThumb);
    resizeObserver.observe(viewport.value);
    if (viewport.value.firstElementChild) resizeObserver.observe(viewport.value.firstElementChild);
    mutationObserver = new MutationObserver(updateThumb);
    mutationObserver.observe(viewport.value, { childList: true, subtree: true });
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    mutationObserver?.disconnect();
    stopDragging();
});
</script>

<template>
    <div class="ui-scroll-shell">
        <component :is="as" ref="viewport" v-bind="attrs" class="ui-scroll-viewport" @scroll.passive="updateThumb">
            <slot />
        </component>
        <div v-if="canScrollVertically" class="ui-scroll-track" aria-hidden="true" @pointerdown="onTrackPointerDown">
            <span class="ui-scroll-thumb" :style="{ height: `${thumbHeight}px`, transform: `translateY(${thumbTop}px)` }" @pointerdown="onThumbPointerDown" />
        </div>
    </div>
</template>

<style scoped>
.ui-scroll-shell{position:relative;min-width:0}.ui-scroll-viewport{scrollbar-width:none}.ui-scroll-viewport::-webkit-scrollbar{display:none;width:0;height:0}.ui-scroll-track{position:absolute;z-index:20;top:4px;right:2px;bottom:4px;width:8px;cursor:pointer;border-radius:999px;background:#eff6ff;box-shadow:inset 0 0 0 1px rgba(59,130,246,.1);pointer-events:auto}.ui-scroll-thumb{position:absolute;top:-4px;right:1px;width:6px;cursor:grab;border-radius:999px;background:#3b82f6;box-shadow:0 0 0 1px rgba(255,255,255,.8),0 1px 3px rgba(37,99,235,.22);touch-action:none;transition:background-color 140ms ease}.ui-scroll-thumb:hover{background:#2563eb}.ui-scroll-thumb:active{cursor:grabbing;background:#1d4ed8}@media(prefers-reduced-motion:reduce){.ui-scroll-thumb{transition:none}}
</style>

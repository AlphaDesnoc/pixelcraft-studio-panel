<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import "emoji-picker-element";

const props = defineProps({
  open: { type: Boolean, required: true },
  triggerRef: { type: Object, default: null },
  placement: { type: String, default: "top" },
});

const emit = defineEmits(["update:open", "select"]);

const rootRef = ref(null);
const pickerRef = ref(null);
const popoverStyle = ref({ visibility: "hidden" });
let boundPicker = null;

function bindPickerListener() {
  unbindPickerListener();
  const el = pickerRef.value;
  if (!el) {
    return;
  }
  boundPicker = el;
  boundPicker.addEventListener("emoji-click", onEmojiClick);
}

function unbindPickerListener() {
  if (!boundPicker) {
    return;
  }
  boundPicker.removeEventListener("emoji-click", onEmojiClick);
  boundPicker = null;
}

function resolveTrigger() {
  const ref = props.triggerRef;
  if (!ref) {
    return null;
  }
  return ref instanceof HTMLElement ? ref : ref.value ?? null;
}

function recalcPosition() {
  const el = resolveTrigger();

  if (!el || typeof el.getBoundingClientRect !== "function") {
    popoverStyle.value = { visibility: "hidden" };
    return;
  }

  const rect = el.getBoundingClientRect();
  const width = 352;
  const height = 435;
  const margin = 8;
  const viewportW = window.innerWidth;
  const viewportH = window.innerHeight;

  let left = rect.left;
  let top = props.placement === "top" ? rect.top - height - margin : rect.bottom + margin;

  if (left + width > viewportW - margin) {
    left = viewportW - width - margin;
  }
  if (left < margin) {
    left = margin;
  }

  if (top < margin) {
    top = rect.bottom + margin;
  }
  if (top + height > viewportH - margin) {
    top = Math.max(margin, rect.top - height - margin);
  }

  popoverStyle.value = {
    position: "fixed",
    top: `${top}px`,
    left: `${left}px`,
    zIndex: 90,
    visibility: "visible",
  };
}

function onEmojiClick(event) {
  const emoji = event.detail?.unicode;
  if (!emoji) {
    return;
  }
  emit("select", emoji);
  emit("update:open", false);
}

function eventPathIncludesPopover(event) {
  const path = event.composedPath?.() ?? [];
  if (rootRef.value && path.includes(rootRef.value)) {
    return true;
  }
  const trigger = resolveTrigger();
  return Boolean(trigger && path.includes(trigger));
}

function onDocClick(event) {
  if (!props.open) {
    return;
  }
  if (eventPathIncludesPopover(event)) {
    return;
  }
  emit("update:open", false);
}

function onKey(event) {
  if (event.key === "Escape" && props.open) {
    emit("update:open", false);
  }
}

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) {
      unbindPickerListener();
      return;
    }
    popoverStyle.value = { visibility: "hidden" };
    await nextTick();
    bindPickerListener();
    recalcPosition();
  },
);

onMounted(() => {
  document.addEventListener("click", onDocClick, true);
  document.addEventListener("keydown", onKey);
  window.addEventListener("resize", recalcPosition);
  window.addEventListener("scroll", recalcPosition, true);
});

onBeforeUnmount(() => {
  unbindPickerListener();
  document.removeEventListener("click", onDocClick, true);
  document.removeEventListener("keydown", onKey);
  window.removeEventListener("resize", recalcPosition);
  window.removeEventListener("scroll", recalcPosition, true);
});
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      ref="rootRef"
      :style="popoverStyle"
      class="emoji-picker-shell overflow-hidden rounded-xl border border-border shadow-2xl"
      @click.stop
    >
      <emoji-picker
        ref="pickerRef"
        locale="fr"
        class="emoji-picker-themed"
      />
    </div>
  </Teleport>
</template>

<style>
@font-face {
  font-family: "Twemoji Mozilla";
  src: url("https://cdn.jsdelivr.net/gh/mozilla/twemoji-colr@v0.7.1/TwemojiMozilla.woff2")
    format("woff2");
  font-weight: normal;
  font-style: normal;
  font-display: swap;
}

.emoji-picker-shell {
  background: hsl(var(--popover));
}

.emoji-picker-themed {
  --emoji-size: 1.375rem;
  --num-columns: 8;
  --emoji-font-family: "Twemoji Mozilla", "Apple Color Emoji", "Segoe UI Emoji", sans-serif;
  --background: hsl(var(--popover));
  --border-color: hsl(var(--border));
  --button-active-background: hsl(var(--muted));
  --button-hover-background: hsl(var(--muted) / 0.65);
  --category-font-color: hsl(var(--muted-foreground));
  --input-border-color: hsl(var(--border));
  --input-font-color: hsl(var(--foreground));
  --input-placeholder-color: hsl(var(--muted-foreground));
  --outline-color: hsl(var(--ring));
  --indicator-color: hsl(var(--primary));
  width: 352px;
  height: 435px;
}
</style>

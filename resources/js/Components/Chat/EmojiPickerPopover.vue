<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import "emoji-picker-element";

const props = defineProps({
  open: { type: Boolean, required: true },
  triggerRef: { type: Object, default: null },
  placement: { type: String, default: "top" },
});

const emit = defineEmits(["update:open", "select"]);

const rootRef = ref(null);
const pickerRef = ref(null);
const popoverStyle = ref({});

function recalcPosition() {
  const el =
    props.triggerRef instanceof HTMLElement
      ? props.triggerRef
      : props.triggerRef?.value;

  if (!el || typeof el.getBoundingClientRect !== "function") {
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

function onDocPointer(event) {
  if (!props.open) {
    return;
  }
  if (rootRef.value?.contains(event.target)) {
    return;
  }
  const el =
    props.triggerRef instanceof HTMLElement
      ? props.triggerRef
      : props.triggerRef?.value;
  if (el?.contains(event.target)) {
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
  (isOpen) => {
    if (isOpen) {
      requestAnimationFrame(recalcPosition);
    }
  },
);

onMounted(() => {
  pickerRef.value?.addEventListener("emoji-click", onEmojiClick);
  document.addEventListener("pointerdown", onDocPointer);
  document.addEventListener("keydown", onKey);
  window.addEventListener("resize", recalcPosition);
  window.addEventListener("scroll", recalcPosition, true);
});

onBeforeUnmount(() => {
  pickerRef.value?.removeEventListener("emoji-click", onEmojiClick);
  document.removeEventListener("pointerdown", onDocPointer);
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
      @pointerdown.stop
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
.emoji-picker-shell {
  background: hsl(var(--popover));
}

.emoji-picker-themed {
  --emoji-size: 1.375rem;
  --num-columns: 8;
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

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Ban } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";

const props = defineProps({
  open: { type: Boolean, required: true },
  modelValue: { type: String, default: null },
  title: { type: String, default: "Couleur" },
  triggerRef: { type: Object, default: null },
  allowClear: { type: Boolean, default: true },
});

const emits = defineEmits(["update:open", "apply", "clear"]);

const rootRef = ref(null);
const svRef = ref(null);
const hueRef = ref(null);
const h = ref(220);
const s = ref(1);
const v = ref(1);
const hex = ref("#6366f1");

function clamp(n, min, max) {
  return Math.max(min, Math.min(max, n));
}

function hsvToHex(hh, ss, vv) {
  const c = vv * ss;
  const x = c * (1 - Math.abs(((hh / 60) % 2) - 1));
  const m = vv - c;
  let r = 0,
    g = 0,
    b = 0;
  if (hh < 60) {
    r = c;
    g = x;
  } else if (hh < 120) {
    r = x;
    g = c;
  } else if (hh < 180) {
    g = c;
    b = x;
  } else if (hh < 240) {
    g = x;
    b = c;
  } else if (hh < 300) {
    r = x;
    b = c;
  } else {
    r = c;
    b = x;
  }
  const toHex = (n) => {
    const v = Math.round((n + m) * 255);
    return clamp(v, 0, 255).toString(16).padStart(2, "0");
  };
  return "#" + toHex(r) + toHex(g) + toHex(b);
}

function hexToHsv(input) {
  const str = (input ?? "").trim();
  let h = str.startsWith("#") ? str.slice(1) : str;
  if (h.length === 3) {
    h = h
      .split("")
      .map((c) => c + c)
      .join("");
  }
  if (h.length !== 6 || !/^[0-9a-fA-F]{6}$/.test(h)) return null;
  const r = parseInt(h.substr(0, 2), 16) / 255;
  const g = parseInt(h.substr(2, 2), 16) / 255;
  const b = parseInt(h.substr(4, 2), 16) / 255;
  const max = Math.max(r, g, b),
    min = Math.min(r, g, b);
  const d = max - min;
  const vv = max;
  const ss = max === 0 ? 0 : d / max;
  let hue = 0;
  if (d !== 0) {
    if (max === r) hue = ((g - b) / d) % 6;
    else if (max === g) hue = (b - r) / d + 2;
    else hue = (r - g) / d + 4;
    hue *= 60;
    if (hue < 0) hue += 360;
  }
  return { h: hue, s: ss, v: vv };
}

function syncFromHex(value) {
  const hsv = hexToHsv(value);
  if (!hsv) return;
  h.value = hsv.h;
  s.value = hsv.s;
  v.value = hsv.v;
  hex.value = "#" + value.replace("#", "").toLowerCase();
}

function updateHex() {
  hex.value = hsvToHex(h.value, s.value, v.value);
}

watch(
  () => [h.value, s.value, v.value],
  () => {
    updateHex();
  },
);

watch(
  () => props.open,
  (open) => {
    if (open) {
      const start = props.modelValue || "#6366f1";
      syncFromHex(start);
    }
  },
  { immediate: true },
);

const hueColor = computed(() => `hsl(${h.value}, 100%, 50%)`);

const svPos = computed(() => ({
  left: `${s.value * 100}%`,
  top: `${(1 - v.value) * 100}%`,
}));

const huePos = computed(() => ({
  left: `${(h.value / 360) * 100}%`,
}));

const popoverStyle = ref({});

function recalcPosition() {
  if (!props.triggerRef) return;
  const el = props.triggerRef instanceof HTMLElement ? props.triggerRef : props.triggerRef.value;
  if (!el || typeof el.getBoundingClientRect !== "function") return;
  const rect = el.getBoundingClientRect();
  popoverStyle.value = {
    position: "fixed",
    top: `${rect.bottom + 6}px`,
    left: `${rect.left}px`,
    zIndex: 80,
  };
}

let draggingSv = false;
let draggingHue = false;

function onSvPointerDown(e) {
  draggingSv = true;
  updateSv(e);
  window.addEventListener("pointermove", onSvPointerMove);
  window.addEventListener("pointerup", onSvPointerUp);
}
function onSvPointerMove(e) {
  if (draggingSv) updateSv(e);
}
function onSvPointerUp() {
  draggingSv = false;
  window.removeEventListener("pointermove", onSvPointerMove);
  window.removeEventListener("pointerup", onSvPointerUp);
}
function updateSv(e) {
  if (!svRef.value) return;
  const rect = svRef.value.getBoundingClientRect();
  const x = clamp(e.clientX - rect.left, 0, rect.width);
  const y = clamp(e.clientY - rect.top, 0, rect.height);
  s.value = rect.width === 0 ? 0 : x / rect.width;
  v.value = rect.height === 0 ? 0 : 1 - y / rect.height;
}

function onHuePointerDown(e) {
  draggingHue = true;
  updateHue(e);
  window.addEventListener("pointermove", onHuePointerMove);
  window.addEventListener("pointerup", onHuePointerUp);
}
function onHuePointerMove(e) {
  if (draggingHue) updateHue(e);
}
function onHuePointerUp() {
  draggingHue = false;
  window.removeEventListener("pointermove", onHuePointerMove);
  window.removeEventListener("pointerup", onHuePointerUp);
}
function updateHue(e) {
  if (!hueRef.value) return;
  const rect = hueRef.value.getBoundingClientRect();
  const x = clamp(e.clientX - rect.left, 0, rect.width);
  h.value = rect.width === 0 ? 0 : (x / rect.width) * 360;
}

function onHexInput(e) {
  const value = e.target.value;
  if (!value.startsWith("#")) hex.value = "#" + value.replace(/[^0-9a-fA-F]/g, "");
  else hex.value = "#" + value.slice(1).replace(/[^0-9a-fA-F]/g, "");
  const hsv = hexToHsv(hex.value);
  if (hsv) {
    h.value = hsv.h;
    s.value = hsv.s;
    v.value = hsv.v;
  }
}

function apply() {
  emits("apply", hex.value);
  emits("update:open", false);
}

function clearColor() {
  emits("clear");
  emits("update:open", false);
}

function onDocPointer(e) {
  if (!props.open) return;
  if (rootRef.value?.contains(e.target)) return;
  const el = props.triggerRef instanceof HTMLElement ? props.triggerRef : props.triggerRef?.value;
  if (el && el.contains(e.target)) return;
  emits("update:open", false);
}

function onKey(e) {
  if (e.key === "Escape" && props.open) emits("update:open", false);
}

onMounted(() => {
  document.addEventListener("pointerdown", onDocPointer);
  document.addEventListener("keydown", onKey);
  window.addEventListener("resize", recalcPosition);
  window.addEventListener("scroll", recalcPosition, true);
});
onBeforeUnmount(() => {
  document.removeEventListener("pointerdown", onDocPointer);
  document.removeEventListener("keydown", onKey);
  window.removeEventListener("resize", recalcPosition);
  window.removeEventListener("scroll", recalcPosition, true);
});

watch(
  () => props.open,
  (o) => {
    if (o) requestAnimationFrame(recalcPosition);
  },
);
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      ref="rootRef"
      :style="popoverStyle"
      class="w-[240px] rounded-xl border border-border bg-popover p-3 text-popover-foreground shadow-2xl"
      @pointerdown.stop
    >
      <div class="mb-2 text-sm font-semibold">{{ title }}</div>

      <div
        ref="svRef"
        class="relative h-[140px] w-full cursor-crosshair select-none overflow-hidden rounded-md"
        :style="{ backgroundColor: hueColor }"
        @pointerdown="onSvPointerDown"
      >
        <div
          class="absolute inset-0"
          style="background: linear-gradient(to right, #fff, transparent)"
        />
        <div
          class="absolute inset-0"
          style="background: linear-gradient(to top, #000, transparent)"
        />
        <div
          class="pointer-events-none absolute h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow"
          :style="svPos"
        />
      </div>

      <div
        ref="hueRef"
        class="relative mt-3 h-3 w-full cursor-pointer overflow-hidden rounded-full"
        style="background: linear-gradient(to right, #ff0000, #ffff00, #00ff00, #00ffff, #0000ff, #ff00ff, #ff0000)"
        @pointerdown="onHuePointerDown"
      >
        <div
          class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow"
          :style="{ ...huePos, backgroundColor: hueColor }"
        />
      </div>

      <div class="mt-3 flex items-center gap-2">
        <input
          :value="hex"
          type="text"
          maxlength="7"
          class="h-8 w-full rounded-md border border-input bg-background px-2 font-mono text-xs uppercase outline-none focus:ring-2 focus:ring-ring"
          @input="onHexInput"
        />
        <button
          v-if="allowClear"
          type="button"
          class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-input text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground"
          title="Aucune couleur"
          @click="clearColor"
        >
          <Ban class="h-3.5 w-3.5" />
        </button>
      </div>

      <Button class="mt-3 h-8 w-full" @click="apply">Appliquer</Button>
    </div>
  </Teleport>
</template>

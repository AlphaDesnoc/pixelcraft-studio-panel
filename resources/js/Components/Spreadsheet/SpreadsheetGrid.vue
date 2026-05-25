<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { cellKey, indexToColLetters, normalizeRange } from "./cells.js";
import { evalCell, formatValue } from "./formula.js";

const props = defineProps({
  rows: { type: Number, required: true },
  cols: { type: Number, required: true },
  cells: { type: Object, required: true },
  anchor: { type: Object, default: null },
  focus: { type: Object, default: null },
});

const emits = defineEmits([
  "update:anchor",
  "update:focus",
  "cell-change",
  "request-clear",
  "navigate",
]);

const COL_WIDTH = 100;
const ROW_HEIGHT = 28;
const HEADER_HEIGHT = 28;
const ROW_HEADER_WIDTH = 44;

const editing = ref(null);
const editorValue = ref("");
const editorRef = ref(null);
const gridRef = ref(null);
const dragging = ref(false);

function cellAt(col, row) {
  return props.cells[cellKey(col, row)] ?? null;
}

function evaluatedDisplay(col, row) {
  const cell = cellAt(col, row);
  if (!cell) return "";
  if (cell.v == null || cell.v === "") return "";
  const result = evalCell(cell.v, props.cells, cellKey(col, row));
  return formatValue(result);
}

function cellStyle(col, row) {
  const cell = cellAt(col, row);
  if (!cell) return {};
  const style = {};
  if (cell.bg) style.backgroundColor = cell.bg;
  if (cell.fg) style.color = cell.fg;
  if (cell.b) style.fontWeight = "600";
  if (cell.i) style.fontStyle = "italic";
  return style;
}

function isSelected(col, row) {
  if (!props.anchor) return false;
  const target = props.focus ?? props.anchor;
  const r = normalizeRange(props.anchor, target);
  return row >= r.r1 && row <= r.r2 && col >= r.c1 && col <= r.c2;
}

function isAnchorCell(col, row) {
  return props.anchor && props.anchor.row === row && props.anchor.col === col;
}

function selectionRect() {
  if (!props.anchor) return null;
  const target = props.focus ?? props.anchor;
  const r = normalizeRange(props.anchor, target);
  return {
    left: r.c1 * COL_WIDTH,
    top: r.r1 * ROW_HEIGHT,
    width: (r.c2 - r.c1 + 1) * COL_WIDTH,
    height: (r.r2 - r.r1 + 1) * ROW_HEIGHT,
  };
}

function selectAnchorOnly(col, row) {
  commitEditor();
  emits("update:anchor", { col, row });
  emits("update:focus", { col, row });
}

function onCellPointerDown(e, col, row) {
  if (e.button !== 0) return;
  if (editing.value) commitEditor();
  if (e.shiftKey && props.anchor) {
    emits("update:focus", { col, row });
    return;
  }
  dragging.value = true;
  emits("update:anchor", { col, row });
  emits("update:focus", { col, row });
  window.addEventListener("pointerup", onPointerUp, { once: true });
}

function onCellPointerEnter(col, row) {
  if (!dragging.value) return;
  emits("update:focus", { col, row });
}

function onPointerUp() {
  dragging.value = false;
}

function onCellDblClick(col, row) {
  startEdit(col, row);
}

function startEdit(col, row, initial = null) {
  const cell = cellAt(col, row);
  const raw = initial != null ? initial : cell?.v ?? "";
  editing.value = { col, row };
  editorValue.value = raw == null ? "" : String(raw);
  emits("update:anchor", { col, row });
  emits("update:focus", { col, row });
  nextTick(() => {
    editorRef.value?.focus();
    if (initial == null) editorRef.value?.select();
    else {
      const el = editorRef.value;
      if (el) {
        el.setSelectionRange(el.value.length, el.value.length);
      }
    }
  });
}

function commitEditor() {
  if (!editing.value) return;
  const { col, row } = editing.value;
  emits("cell-change", { col, row, value: editorValue.value });
  editing.value = null;
}

function cancelEditor() {
  editing.value = null;
}

function navigateBy(dCol, dRow) {
  const a = props.anchor;
  if (!a) return;
  const nc = Math.max(0, Math.min(props.cols - 1, a.col + dCol));
  const nr = Math.max(0, Math.min(props.rows - 1, a.row + dRow));
  emits("update:anchor", { col: nc, row: nr });
  emits("update:focus", { col: nc, row: nr });
  emits("navigate", { col: nc, row: nr });
}

function onKeyDown(e) {
  if (editing.value) {
    if (e.key === "Enter") {
      e.preventDefault();
      commitEditor();
      navigateBy(0, 1);
    } else if (e.key === "Tab") {
      e.preventDefault();
      commitEditor();
      navigateBy(e.shiftKey ? -1 : 1, 0);
    } else if (e.key === "Escape") {
      e.preventDefault();
      cancelEditor();
    }
    return;
  }
  if (!props.anchor) return;
  if (e.key === "ArrowUp") {
    e.preventDefault();
    navigateBy(0, -1);
  } else if (e.key === "ArrowDown") {
    e.preventDefault();
    navigateBy(0, 1);
  } else if (e.key === "ArrowLeft") {
    e.preventDefault();
    navigateBy(-1, 0);
  } else if (e.key === "ArrowRight" || e.key === "Tab") {
    e.preventDefault();
    navigateBy(e.shiftKey && e.key === "Tab" ? -1 : 1, 0);
  } else if (e.key === "Enter" || e.key === "F2") {
    e.preventDefault();
    startEdit(props.anchor.col, props.anchor.row);
  } else if (e.key === "Delete" || e.key === "Backspace") {
    e.preventDefault();
    emits("request-clear", { what: "values" });
  } else if (
    e.key.length === 1 &&
    !e.metaKey &&
    !e.ctrlKey &&
    !e.altKey
  ) {
    e.preventDefault();
    startEdit(props.anchor.col, props.anchor.row, e.key);
  }
}

defineExpose({ startEdit, commitEditor, navigateBy });

onMounted(() => {
  window.addEventListener("keydown", onKeyDown);
});
onBeforeUnmount(() => {
  window.removeEventListener("keydown", onKeyDown);
  window.removeEventListener("pointerup", onPointerUp);
});

const totalWidth = computed(() => ROW_HEADER_WIDTH + props.cols * COL_WIDTH);
const totalHeight = computed(() => HEADER_HEIGHT + props.rows * ROW_HEIGHT);

const colArray = computed(() => Array.from({ length: props.cols }, (_, i) => i));
const rowArray = computed(() => Array.from({ length: props.rows }, (_, i) => i));

const selRect = computed(() => selectionRect());
</script>

<template>
  <div
    ref="gridRef"
    class="relative h-full w-full overflow-auto rounded-xl border border-border bg-card"
    tabindex="0"
  >
    <div
      class="relative"
      :style="{
        width: totalWidth + 'px',
        height: totalHeight + 'px',
      }"
    >
      <div
        class="sticky top-0 z-30 flex h-[28px] bg-muted/40 backdrop-blur-sm"
        :style="{ width: totalWidth + 'px' }"
      >
        <div
          class="sticky left-0 z-40 h-full border-b border-r border-border bg-muted/60"
          :style="{ width: ROW_HEADER_WIDTH + 'px', minWidth: ROW_HEADER_WIDTH + 'px' }"
        />
        <div
          v-for="c in colArray"
          :key="'h-' + c"
          class="flex h-full items-center justify-center border-b border-r border-border text-[11px] font-medium text-muted-foreground"
          :class="
            anchor &&
            ((focus ?? anchor).col >= Math.min(anchor.col, (focus ?? anchor).col) &&
              c >= Math.min(anchor.col, (focus ?? anchor).col) &&
              c <= Math.max(anchor.col, (focus ?? anchor).col))
              ? 'bg-primary/10 text-primary'
              : ''
          "
          :style="{ width: COL_WIDTH + 'px', minWidth: COL_WIDTH + 'px' }"
        >
          {{ indexToColLetters(c) }}
        </div>
      </div>

      <div
        v-for="r in rowArray"
        :key="'r-' + r"
        class="flex"
        :style="{ height: ROW_HEIGHT + 'px' }"
      >
        <div
          class="sticky left-0 z-20 flex items-center justify-center border-b border-r border-border bg-muted/40 text-[11px] font-medium text-muted-foreground"
          :class="
            anchor &&
            r >= Math.min(anchor.row, (focus ?? anchor).row) &&
            r <= Math.max(anchor.row, (focus ?? anchor).row)
              ? 'bg-primary/10 text-primary'
              : ''
          "
          :style="{
            width: ROW_HEADER_WIDTH + 'px',
            minWidth: ROW_HEADER_WIDTH + 'px',
            height: ROW_HEIGHT + 'px',
          }"
        >
          {{ r + 1 }}
        </div>
        <div
          v-for="c in colArray"
          :key="'c-' + r + '-' + c"
          class="relative flex items-center overflow-hidden border-b border-r border-border/70 px-1.5 text-xs text-foreground"
          :style="{
            width: COL_WIDTH + 'px',
            minWidth: COL_WIDTH + 'px',
            height: ROW_HEIGHT + 'px',
            ...cellStyle(c, r),
          }"
          @pointerdown="(e) => onCellPointerDown(e, c, r)"
          @pointerenter="onCellPointerEnter(c, r)"
          @dblclick="onCellDblClick(c, r)"
        >
          <span class="truncate">{{ evaluatedDisplay(c, r) }}</span>
        </div>
      </div>

      <div
        v-if="selRect && !editing"
        class="pointer-events-none absolute border-2 border-primary/80 bg-primary/5"
        :style="{
          left: ROW_HEADER_WIDTH + selRect.left + 'px',
          top: HEADER_HEIGHT + selRect.top + 'px',
          width: selRect.width + 'px',
          height: selRect.height + 'px',
        }"
      />

      <div
        v-if="editing"
        class="absolute z-50"
        :style="{
          left: ROW_HEADER_WIDTH + editing.col * COL_WIDTH + 'px',
          top: HEADER_HEIGHT + editing.row * ROW_HEIGHT + 'px',
          width: COL_WIDTH + 'px',
          height: ROW_HEIGHT + 'px',
        }"
      >
        <input
          ref="editorRef"
          v-model="editorValue"
          class="h-full w-full border-2 border-primary bg-background px-1.5 text-xs text-foreground outline-none"
          @blur="commitEditor"
        />
      </div>
    </div>
  </div>
</template>

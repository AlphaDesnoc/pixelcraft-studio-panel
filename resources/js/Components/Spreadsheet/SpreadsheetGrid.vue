<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { cellKey, indexToColLetters, normalizeRange } from "./cells.js";
import { evalCell, formatCell } from "./formula.js";

const props = defineProps({
  rows: { type: Number, required: true },
  cols: { type: Number, required: true },
  cells: { type: Object, required: true },
  anchor: { type: Object, default: null },
  focus: { type: Object, default: null },
  hiddenRows: { type: Array, default: () => [] },
  conditionalRules: { type: Array, default: () => [] },
});

const emits = defineEmits([
  "update:anchor",
  "update:focus",
  "cell-change",
  "request-clear",
  "navigate",
  "fill",
  "header-menu",
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
const filling = ref(false);
const fillTarget = ref(null);
const menu = ref(null);

const hiddenSet = computed(() => new Set(props.hiddenRows ?? []));

// Position visuelle d'une ligne (en sautant les lignes masquées au-dessus).
function visibleBefore(dataRow) {
  let count = 0;
  for (let r = 0; r < dataRow; r++) if (!hiddenSet.value.has(r)) count++;
  return count;
}

function visibleInRange(r1, r2) {
  let count = 0;
  for (let r = r1; r <= r2; r++) if (!hiddenSet.value.has(r)) count++;
  return count;
}

function nextVisibleRow(from, dir) {
  let r = from;
  for (;;) {
    r += dir;
    if (r < 0 || r > props.rows - 1) return from;
    if (!hiddenSet.value.has(r)) return r;
  }
}

function cellAt(col, row) {
  return props.cells[cellKey(col, row)] ?? null;
}

function evaluatedDisplay(col, row) {
  const cell = cellAt(col, row);
  if (!cell) return "";
  if (cell.v == null || cell.v === "") return "";
  const result = evalCell(cell.v, props.cells, cellKey(col, row));
  return formatCell(result, cell.fmt);
}

function matchRule(val, rule) {
  const num = typeof val === "number" ? val : parseFloat(String(val).replace(",", "."));
  const rnum = parseFloat(String(rule.value).replace(",", "."));
  const isEmpty = val === "" || val == null;
  switch (rule.op) {
    case "gt":
      return !isNaN(num) && !isNaN(rnum) && num > rnum;
    case "lt":
      return !isNaN(num) && !isNaN(rnum) && num < rnum;
    case "eq":
      return String(val) === String(rule.value) || (!isNaN(num) && num === rnum);
    case "neq":
      return !(String(val) === String(rule.value) || (!isNaN(num) && num === rnum));
    case "contains":
      return String(val).toLowerCase().includes(String(rule.value).toLowerCase());
    case "empty":
      return isEmpty;
    case "notempty":
      return !isEmpty;
  }
  return false;
}

function matchedRule(col, row, cell) {
  const rules = props.conditionalRules;
  if (!rules || !rules.length) return null;
  const raw = cell?.v;
  const val =
    raw == null || raw === "" ? "" : evalCell(raw, props.cells, cellKey(col, row));
  for (const rule of rules) {
    if (matchRule(val, rule)) return rule;
  }
  return null;
}

function cellStyle(col, row) {
  const cell = cellAt(col, row);
  const style = {};
  if (cell) {
    if (cell.bg) style.backgroundColor = cell.bg;
    if (cell.fg) style.color = cell.fg;
    if (cell.b) style.fontWeight = "600";
    if (cell.i) style.fontStyle = "italic";
  }
  const rule = matchedRule(col, row, cell);
  if (rule) {
    if (rule.bg) style.backgroundColor = rule.bg;
    if (rule.fg) style.color = rule.fg;
  }
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
    top: visibleBefore(r.r1) * ROW_HEIGHT,
    width: (r.c2 - r.c1 + 1) * COL_WIDTH,
    height: visibleInRange(r.r1, r.r2) * ROW_HEIGHT,
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
  if (filling.value) {
    fillTarget.value = { col, row };
    return;
  }
  if (!dragging.value) return;
  emits("update:focus", { col, row });
}

function onPointerUp() {
  dragging.value = false;
}

// ---- Poignée de recopie ----
function onFillHandleDown(e) {
  if (e.button !== 0) return;
  e.stopPropagation();
  if (editing.value) commitEditor();
  filling.value = true;
  fillTarget.value = props.focus ?? props.anchor;
  window.addEventListener("pointerup", onFillUp, { once: true });
}

function onFillUp() {
  if (filling.value && fillTarget.value) {
    emits("fill", { col: fillTarget.value.col, row: fillTarget.value.row });
  }
  filling.value = false;
  fillTarget.value = null;
}

// ---- Sélection ligne / colonne entière + menu contextuel ----
function selectRow(row, shift) {
  if (editing.value) commitEditor();
  if (shift && props.anchor) {
    emits("update:focus", { col: props.cols - 1, row });
    return;
  }
  emits("update:anchor", { col: 0, row });
  emits("update:focus", { col: props.cols - 1, row });
}

function selectCol(col, shift) {
  if (editing.value) commitEditor();
  if (shift && props.anchor) {
    emits("update:focus", { col, row: props.rows - 1 });
    return;
  }
  emits("update:anchor", { col, row: 0 });
  emits("update:focus", { col, row: props.rows - 1 });
}

function openHeaderMenu(e, kind, index) {
  e.preventDefault();
  if (kind === "row") selectRow(index, false);
  else selectCol(index, false);
  menu.value = { x: e.clientX, y: e.clientY, kind, index };
  window.addEventListener("pointerdown", closeMenuOnOutside, true);
}

function closeMenu() {
  menu.value = null;
  window.removeEventListener("pointerdown", closeMenuOnOutside, true);
}

function closeMenuOnOutside(e) {
  if (e.target?.closest?.("[data-ss-menu]")) return;
  closeMenu();
}

function menuAction(action) {
  if (!menu.value) return;
  emits("header-menu", { kind: menu.value.kind, action, index: menu.value.index });
  closeMenu();
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
  let nr = Math.max(0, Math.min(props.rows - 1, a.row + dRow));
  if (dRow !== 0 && hiddenSet.value.has(nr)) {
    nr = nextVisibleRow(a.row, dRow > 0 ? 1 : -1);
  }
  emits("update:anchor", { col: nc, row: nr });
  emits("update:focus", { col: nc, row: nr });
  emits("navigate", { col: nc, row: nr });
}

function isTypingInField(e) {
  const el = e.target;
  if (!(el instanceof HTMLElement)) return false;
  const tag = el.tagName;
  return tag === "INPUT" || tag === "TEXTAREA" || tag === "SELECT" || el.isContentEditable;
}

function onKeyDown(e) {
  if (isTypingInField(e)) return;

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
  window.removeEventListener("pointerup", onFillUp);
  window.removeEventListener("pointerdown", closeMenuOnOutside, true);
});

const totalWidth = computed(() => ROW_HEADER_WIDTH + props.cols * COL_WIDTH);
const visibleRowCount = computed(() => props.rows - hiddenSet.value.size);
const totalHeight = computed(() => HEADER_HEIGHT + visibleRowCount.value * ROW_HEIGHT);

const colArray = computed(() => Array.from({ length: props.cols }, (_, i) => i));
const rowArray = computed(() => Array.from({ length: props.rows }, (_, i) => i));

const selRect = computed(() => selectionRect());

const fillRect = computed(() => {
  if (!filling.value || !fillTarget.value || !props.anchor) return null;
  const src = normalizeRange(props.anchor, props.focus ?? props.anchor);
  const t = fillTarget.value;
  const c1 = Math.min(src.c1, t.col);
  const c2 = Math.max(src.c2, t.col);
  const r1 = Math.min(src.r1, t.row);
  const r2 = Math.max(src.r2, t.row);
  return {
    left: c1 * COL_WIDTH,
    top: visibleBefore(r1) * ROW_HEIGHT,
    width: (c2 - c1 + 1) * COL_WIDTH,
    height: visibleInRange(r1, r2) * ROW_HEIGHT,
  };
});
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
          class="flex h-full cursor-pointer items-center justify-center border-b border-r border-border text-[11px] font-medium text-muted-foreground hover:bg-muted/70"
          :class="
            anchor &&
            ((focus ?? anchor).col >= Math.min(anchor.col, (focus ?? anchor).col) &&
              c >= Math.min(anchor.col, (focus ?? anchor).col) &&
              c <= Math.max(anchor.col, (focus ?? anchor).col))
              ? 'bg-primary/10 text-primary'
              : ''
          "
          :style="{ width: COL_WIDTH + 'px', minWidth: COL_WIDTH + 'px' }"
          @pointerdown="(e) => e.button === 0 && selectCol(c, e.shiftKey)"
          @contextmenu="(e) => openHeaderMenu(e, 'col', c)"
        >
          {{ indexToColLetters(c) }}
        </div>
      </div>

      <div
        v-for="r in rowArray"
        v-show="!hiddenSet.has(r)"
        :key="'r-' + r"
        class="flex"
        :style="{ height: ROW_HEIGHT + 'px' }"
      >
        <div
          class="sticky left-0 z-20 flex cursor-pointer items-center justify-center border-b border-r border-border bg-muted/40 text-[11px] font-medium text-muted-foreground hover:bg-muted/70"
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
          @pointerdown="(e) => e.button === 0 && selectRow(r, e.shiftKey)"
          @contextmenu="(e) => openHeaderMenu(e, 'row', r)"
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
        v-if="fillRect"
        class="pointer-events-none absolute border-2 border-dashed border-primary/60"
        :style="{
          left: ROW_HEADER_WIDTH + fillRect.left + 'px',
          top: HEADER_HEIGHT + fillRect.top + 'px',
          width: fillRect.width + 'px',
          height: fillRect.height + 'px',
        }"
      />

      <div
        v-if="selRect && !editing"
        class="absolute z-40 h-2 w-2 cursor-crosshair rounded-sm border border-background bg-primary"
        :style="{
          left: ROW_HEADER_WIDTH + selRect.left + selRect.width - 4 + 'px',
          top: HEADER_HEIGHT + selRect.top + selRect.height - 4 + 'px',
        }"
        title="Recopier (glisser)"
        @pointerdown="onFillHandleDown"
      />

      <div
        v-if="editing"
        class="absolute z-50"
        :style="{
          left: ROW_HEADER_WIDTH + editing.col * COL_WIDTH + 'px',
          top: HEADER_HEIGHT + visibleBefore(editing.row) * ROW_HEIGHT + 'px',
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

  <Teleport to="body">
    <div
      v-if="menu"
      data-ss-menu
      class="fixed z-[100] min-w-[200px] overflow-hidden rounded-md border border-border bg-popover py-1 text-xs text-foreground shadow-lg"
      :style="{ left: menu.x + 'px', top: menu.y + 'px' }"
    >
      <template v-if="menu.kind === 'col'">
        <button
          type="button"
          class="block w-full px-3 py-1.5 text-left hover:bg-muted"
          @click="menuAction('sort-asc')"
        >
          Trier la feuille A → Z
        </button>
        <button
          type="button"
          class="block w-full px-3 py-1.5 text-left hover:bg-muted"
          @click="menuAction('sort-desc')"
        >
          Trier la feuille Z → A
        </button>
        <button
          type="button"
          class="block w-full px-3 py-1.5 text-left hover:bg-muted"
          @click="menuAction('filter')"
        >
          Filtrer cette colonne…
        </button>
        <div class="my-1 h-px bg-border" />
      </template>
      <button
        type="button"
        class="block w-full px-3 py-1.5 text-left hover:bg-muted"
        @click="menuAction('insert-before')"
      >
        {{ menu.kind === "row" ? "Insérer une ligne au-dessus" : "Insérer une colonne à gauche" }}
      </button>
      <button
        type="button"
        class="block w-full px-3 py-1.5 text-left hover:bg-muted"
        @click="menuAction('insert-after')"
      >
        {{ menu.kind === "row" ? "Insérer une ligne en dessous" : "Insérer une colonne à droite" }}
      </button>
      <div class="my-1 h-px bg-border" />
      <button
        type="button"
        class="block w-full px-3 py-1.5 text-left text-rose-400 hover:bg-rose-500/10"
        @click="menuAction('delete')"
      >
        {{ menu.kind === "row" ? "Supprimer la ligne" : "Supprimer la colonne" }}
      </button>
    </div>
  </Teleport>
</template>

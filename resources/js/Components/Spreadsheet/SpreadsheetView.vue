<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import {
  Download,
  Palette,
  Plus,
  Redo2,
  Rows3,
  Columns3,
  Undo2,
  X,
} from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Dialog, DialogContent, DialogTitle } from "@/Components/ui/dialog";
import SpreadsheetGrid from "./SpreadsheetGrid.vue";
import SpreadsheetToolbar from "./SpreadsheetToolbar.vue";
import SheetTabs from "./SheetTabs.vue";
import {
  cellKey,
  indexToColLetters,
  iterSelection,
  normalizeRange,
  selectionCount,
  selectionLabel,
} from "./cells.js";
import { evalCell, formatValue } from "./formula.js";
import { parseClipboardTable, serializeCSV, serializeTable } from "./clipboard.js";
import {
  buildFillSeries,
  deleteCols,
  deleteRows,
  insertCols,
  insertRows,
  reorderRows,
} from "./mutations.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  sheets: { type: Array, default: () => [] },
  rankId: { type: Number, default: null },
});

function cloneSheets(arr) {
  return (arr ?? []).map((s) => ({
    id: s.id,
    name: s.name,
    position: s.position,
    rows: s.rows,
    cols: s.cols,
    data: s.data ? JSON.parse(JSON.stringify(s.data)) : {},
    meta: s.meta ? JSON.parse(JSON.stringify(s.meta)) : {},
  }));
}

const localSheets = ref(cloneSheets(props.sheets));
const activeId = ref(localSheets.value[0]?.id ?? null);

watch(
  () => props.sheets,
  (next) => {
    localSheets.value = cloneSheets(next);
    if (!localSheets.value.find((s) => s.id === activeId.value)) {
      activeId.value = localSheets.value[0]?.id ?? null;
    }
  },
  { deep: true },
);

const activeSheet = computed(() =>
  localSheets.value.find((s) => s.id === activeId.value),
);

const cellsMap = computed(() => activeSheet.value?.data ?? {});

const anchor = ref({ col: 0, row: 0 });
const focus = ref({ col: 0, row: 0 });

const editorValue = ref("");
const editorDirty = ref(false);
let suppressSync = false;

function syncEditorFromCell(a) {
  suppressSync = true;
  const cell = a ? cellsMap.value[cellKey(a.col, a.row)] : null;
  editorValue.value = cell?.v != null ? String(cell.v) : "";
  setTimeout(() => {
    suppressSync = false;
  }, 0);
}

watch(editorValue, () => {
  if (!suppressSync) editorDirty.value = true;
});

watch(anchor, (a, prev) => {
  if (editorDirty.value && prev) {
    writeValueToCell(prev.col, prev.row, editorValue.value);
    editorDirty.value = false;
  }
  syncEditorFromCell(a);
});

watch(
  () => activeId.value,
  () => {
    anchor.value = { col: 0, row: 0 };
    focus.value = { col: 0, row: 0 };
    editorValue.value = "";
    undoStack.value = [];
    redoStack.value = [];
    filters.value = {};
    filterEditCol.value = null;
    condDialogOpen.value = false;
  },
);

const selLabel = computed(() => selectionLabel(anchor.value, focus.value));
const selCount = computed(() => selectionCount(anchor.value, focus.value));

const anchorCell = computed(() =>
  anchor.value ? cellsMap.value[cellKey(anchor.value.col, anchor.value.row)] ?? null : null,
);
const currentBg = computed(() => anchorCell.value?.bg ?? null);
const currentFg = computed(() => anchorCell.value?.fg ?? null);

// ---- Filtres par colonne ----
const filters = ref({}); // { [col]: query }
const filterEditCol = ref(null);
const filterEditValue = ref("");
const filterInputRef = ref(null);

const activeFilters = computed(() =>
  Object.entries(filters.value)
    .filter(([, q]) => q && q.trim() !== "")
    .map(([col, q]) => ({ col: Number(col), q })),
);

const hiddenRows = computed(() => {
  const active = activeFilters.value;
  const s = activeSheet.value;
  if (!active.length || !s) return [];
  const hidden = [];
  for (let r = 0; r < s.rows; r++) {
    for (const { col, q } of active) {
      const key = cellKey(col, r);
      const raw = cellsMap.value[key]?.v;
      const disp =
        raw == null || raw === ""
          ? ""
          : formatValue(evalCell(raw, cellsMap.value, key));
      if (!String(disp).toLowerCase().includes(q.trim().toLowerCase())) {
        hidden.push(r);
        break;
      }
    }
  }
  return hidden;
});

function colLetter(col) {
  return indexToColLetters(Number(col));
}

function openFilterEditor(col) {
  filterEditCol.value = col;
  filterEditValue.value = filters.value[col] ?? "";
  nextTick(() => filterInputRef.value?.focus());
}

function commitFilterEditor() {
  if (filterEditCol.value == null) return;
  const col = filterEditCol.value;
  const q = filterEditValue.value;
  const next = { ...filters.value };
  if (q && q.trim() !== "") next[col] = q;
  else delete next[col];
  filters.value = next;
  filterEditCol.value = null;
  filterEditValue.value = "";
}

function removeFilter(col) {
  const next = { ...filters.value };
  delete next[col];
  filters.value = next;
}

function clearFilters() {
  filters.value = {};
  filterEditCol.value = null;
}

// ---- Mise en forme conditionnelle ----
function ensureMeta() {
  const s = activeSheet.value;
  if (!s) return null;
  if (!s.meta) s.meta = {};
  return s.meta;
}

const conditionalRules = computed(() => activeSheet.value?.meta?.conditional ?? []);
const condDialogOpen = ref(false);

const condDraft = ref({ op: "gt", value: "", bg: "#fee2e2", fg: "#b91c1c" });

const condOperators = [
  { value: "gt", label: "supérieur à" },
  { value: "lt", label: "inférieur à" },
  { value: "eq", label: "égal à" },
  { value: "neq", label: "différent de" },
  { value: "contains", label: "contient" },
  { value: "empty", label: "est vide" },
  { value: "notempty", label: "n'est pas vide" },
];

function addConditionalRule() {
  const meta = ensureMeta();
  if (!meta) return;
  const rule = { ...condDraft.value };
  meta.conditional = [...(meta.conditional ?? []), rule];
  condDraft.value = { op: "gt", value: "", bg: "#fee2e2", fg: "#b91c1c" };
  scheduleSave();
}

function removeConditionalRule(idx) {
  const meta = ensureMeta();
  if (!meta || !meta.conditional) return;
  meta.conditional = meta.conditional.filter((_, i) => i !== idx);
  scheduleSave();
}

function opLabel(op) {
  return condOperators.find((o) => o.value === op)?.label ?? op;
}

let saveTimer = null;
function scheduleSave() {
  if (!activeSheet.value) return;
  clearTimeout(saveTimer);
  const id = activeSheet.value.id;
  saveTimer = setTimeout(() => {
    const sheet = localSheets.value.find((s) => s.id === id);
    if (!sheet) return;
    router.put(
      route("projects.sheets.update", [props.projectSlug, id]),
      {
        data: sheet.data ?? {},
        meta: sheet.meta ?? {},
        rows: sheet.rows,
        cols: sheet.cols,
      },
      { preserveScroll: true, preserveState: true, only: ["sheets"], replace: true },
    );
  }, 600);
}

// ---- Historique annuler / rétablir ----
const undoStack = ref([]);
const redoStack = ref([]);
const HISTORY_LIMIT = 100;

function snapshot() {
  const s = activeSheet.value;
  if (!s) return null;
  return {
    id: s.id,
    data: JSON.parse(JSON.stringify(s.data ?? {})),
    rows: s.rows,
    cols: s.cols,
  };
}

function pushHistory() {
  const snap = snapshot();
  if (!snap) return;
  undoStack.value.push(snap);
  if (undoStack.value.length > HISTORY_LIMIT) undoStack.value.shift();
  redoStack.value = [];
}

function restoreSnapshot(snap) {
  const sheet = localSheets.value.find((s) => s.id === snap.id);
  if (!sheet) return;
  sheet.data = JSON.parse(JSON.stringify(snap.data));
  sheet.rows = snap.rows;
  sheet.cols = snap.cols;
  if (activeId.value !== snap.id) activeId.value = snap.id;
  clampSelection();
}

function clampSelection() {
  const s = activeSheet.value;
  if (!s) return;
  const col = Math.max(0, Math.min(anchor.value.col, s.cols - 1));
  const row = Math.max(0, Math.min(anchor.value.row, s.rows - 1));
  anchor.value = { col, row };
  focus.value = { col, row };
}

function undo() {
  if (!undoStack.value.length) return;
  const current = snapshot();
  const snap = undoStack.value.pop();
  if (current) redoStack.value.push(current);
  restoreSnapshot(snap);
  scheduleSave();
}

function redo() {
  if (!redoStack.value.length) return;
  const current = snapshot();
  const snap = redoStack.value.pop();
  if (current) undoStack.value.push(current);
  restoreSnapshot(snap);
  scheduleSave();
}

function ensureCell(col, row) {
  const sheet = activeSheet.value;
  if (!sheet) return null;
  const key = cellKey(col, row);
  if (!sheet.data) sheet.data = {};
  if (!sheet.data[key]) sheet.data[key] = {};
  return sheet.data[key];
}

function cleanCell(col, row) {
  const sheet = activeSheet.value;
  if (!sheet || !sheet.data) return;
  const key = cellKey(col, row);
  const c = sheet.data[key];
  if (!c) return;
  const isEmpty =
    (c.v == null || c.v === "") && !c.bg && !c.fg && !c.b && !c.i && !c.fmt;
  if (isEmpty) delete sheet.data[key];
}

function writeValueToCell(col, row, value) {
  pushHistory();
  ensureCell(col, row).v = value === "" ? "" : value;
  cleanCell(col, row);
  scheduleSave();
}

function onCellChange({ col, row, value }) {
  writeValueToCell(col, row, value);
  if (anchor.value && anchor.value.col === col && anchor.value.row === row) {
    suppressSync = true;
    editorValue.value = value;
    setTimeout(() => {
      suppressSync = false;
    }, 0);
    editorDirty.value = false;
  }
}

function applyToSelection(fn) {
  if (!anchor.value) return;
  pushHistory();
  for (const { col, row } of iterSelection(anchor.value, focus.value)) {
    const cell = ensureCell(col, row);
    fn(cell);
    cleanCell(col, row);
  }
  scheduleSave();
}

function applyBg(color) {
  applyToSelection((cell) => {
    if (color == null) delete cell.bg;
    else cell.bg = color;
  });
}

function applyFg(color) {
  applyToSelection((cell) => {
    if (color == null) delete cell.fg;
    else cell.fg = color;
  });
}

function toggleBold() {
  if (!anchor.value) return;
  const first = ensureCell(anchor.value.col, anchor.value.row);
  const target = !first.b;
  applyToSelection((cell) => {
    if (target) cell.b = 1;
    else delete cell.b;
  });
}

function toggleItalic() {
  if (!anchor.value) return;
  const first = ensureCell(anchor.value.col, anchor.value.row);
  const target = !first.i;
  applyToSelection((cell) => {
    if (target) cell.i = 1;
    else delete cell.i;
  });
}

function clearFormat() {
  applyToSelection((cell) => {
    delete cell.bg;
    delete cell.fg;
    delete cell.b;
    delete cell.i;
    delete cell.fmt;
  });
}

function applyNumberFormat(fmt) {
  applyToSelection((cell) => {
    if (fmt == null) delete cell.fmt;
    else cell.fmt = fmt;
  });
}

function exportCsv() {
  const s = activeSheet.value;
  if (!s) return;
  const grid = [];
  for (let r = 0; r < s.rows; r++) {
    const row = [];
    for (let c = 0; c < s.cols; c++) {
      const key = cellKey(c, r);
      const raw = cellsMap.value[key]?.v;
      row.push(
        raw == null || raw === ""
          ? ""
          : formatValue(evalCell(raw, cellsMap.value, key)),
      );
    }
    grid.push(row);
  }
  // BOM UTF-8 pour Excel + valeurs évaluées.
  const blob = new Blob(["﻿" + serializeCSV(grid)], {
    type: "text/csv;charset=utf-8;",
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `${(s.name || "feuille").replace(/[^\w.-]+/g, "_")}.csv`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

function clearValues() {
  applyToSelection((cell) => {
    cell.v = "";
  });
}

// ---- Copier / couper / coller (compatible Google Sheets & Excel) ----

// Conserve la dernière copie interne (valeurs brutes + mise en forme) afin de
// préserver formules et styles lors d'un collage au sein de l'app.
let internalClipboard = null;

function isEditingField() {
  const el = document.activeElement;
  if (!el) return false;
  const tag = el.tagName;
  return (
    tag === "INPUT" ||
    tag === "TEXTAREA" ||
    tag === "SELECT" ||
    el.isContentEditable
  );
}

function copySelection(e) {
  if (!anchor.value || isEditingField()) return;
  const target = focus.value ?? anchor.value;
  const r = normalizeRange(anchor.value, target);

  const display = [];
  const cells = [];
  for (let row = r.r1; row <= r.r2; row++) {
    const dispRow = [];
    const cellRow = [];
    for (let col = r.c1; col <= r.c2; col++) {
      const key = cellKey(col, row);
      const cell = cellsMap.value[key] ?? null;
      const v = cell?.v;
      dispRow.push(
        v == null || v === "" ? "" : formatValue(evalCell(v, cellsMap.value, key)),
      );
      cellRow.push(
        cell
          ? {
              v: cell.v ?? "",
              bg: cell.bg,
              fg: cell.fg,
              b: cell.b,
              i: cell.i,
              fmt: cell.fmt,
            }
          : null,
      );
    }
    display.push(dispRow);
    cells.push(cellRow);
  }

  const text = serializeTable(display);
  internalClipboard = { text, cells };

  if (e.clipboardData) {
    e.clipboardData.setData("text/plain", text);
    e.preventDefault();
  }
}

function cutSelection(e) {
  if (!anchor.value || isEditingField()) return;
  copySelection(e);
  pushHistory();
  for (const { col, row } of iterSelection(anchor.value, focus.value)) {
    const cell = ensureCell(col, row);
    cell.v = "";
    delete cell.bg;
    delete cell.fg;
    delete cell.b;
    delete cell.i;
    cleanCell(col, row);
  }
  scheduleSave();
}

function pasteSelection(e) {
  if (!anchor.value || isEditingField() || !activeSheet.value) return;
  e.preventDefault();

  const text = e.clipboardData ? e.clipboardData.getData("text/plain") : "";

  // Réutilise la copie interne (formules + mise en forme) tant que le
  // presse-papier système correspond à ce qui a été copié dans l'app.
  const useInternal = internalClipboard && internalClipboard.text === text;

  let block;
  if (useInternal) {
    block = internalClipboard.cells;
  } else {
    const table = parseClipboardTable(text);
    if (!table.length) return;
    block = table.map((row) => row.map((v) => ({ v })));
  }

  const height = block.length;
  const width = block.reduce((m, row) => Math.max(m, row.length), 0);
  if (!height || !width) return;

  const t = focus.value ?? anchor.value;
  const startCol = Math.min(anchor.value.col, t.col);
  const startRow = Math.min(anchor.value.row, t.row);

  pushHistory();
  const sheet = activeSheet.value;
  sheet.rows = Math.min(1000, Math.max(sheet.rows, startRow + height));
  sheet.cols = Math.min(200, Math.max(sheet.cols, startCol + width));

  for (let dr = 0; dr < height; dr++) {
    for (let dc = 0; dc < block[dr].length; dc++) {
      const col = startCol + dc;
      const row = startRow + dr;
      if (col > sheet.cols - 1 || row > sheet.rows - 1) continue;
      const src = block[dr][dc] ?? { v: "" };
      const cell = ensureCell(col, row);
      cell.v = src.v == null ? "" : src.v;
      if (useInternal) {
        if (src.bg) cell.bg = src.bg;
        else delete cell.bg;
        if (src.fg) cell.fg = src.fg;
        else delete cell.fg;
        if (src.b) cell.b = src.b;
        else delete cell.b;
        if (src.i) cell.i = src.i;
        else delete cell.i;
        if (src.fmt) cell.fmt = src.fmt;
        else delete cell.fmt;
      }
      cleanCell(col, row);
    }
  }

  anchor.value = { col: startCol, row: startRow };
  focus.value = {
    col: Math.min(sheet.cols - 1, startCol + width - 1),
    row: Math.min(sheet.rows - 1, startRow + height - 1),
  };
  scheduleSave();
}

// ---- Insérer / supprimer lignes & colonnes ----

function insertRowAt(at) {
  const s = activeSheet.value;
  if (!s) return;
  pushHistory();
  s.data = insertRows(s.data ?? {}, at, 1);
  s.rows = Math.min(1000, s.rows + 1);
  scheduleSave();
}

function deleteRowAt(at) {
  const s = activeSheet.value;
  if (!s || s.rows <= 1) return;
  pushHistory();
  s.data = deleteRows(s.data ?? {}, at, 1);
  s.rows = Math.max(1, s.rows - 1);
  clampSelection();
  scheduleSave();
}

function insertColAt(at) {
  const s = activeSheet.value;
  if (!s) return;
  pushHistory();
  s.data = insertCols(s.data ?? {}, at, 1);
  s.cols = Math.min(200, s.cols + 1);
  scheduleSave();
}

function deleteColAt(at) {
  const s = activeSheet.value;
  if (!s || s.cols <= 1) return;
  pushHistory();
  s.data = deleteCols(s.data ?? {}, at, 1);
  s.cols = Math.max(1, s.cols - 1);
  clampSelection();
  scheduleSave();
}

function sortByColumn(col, dir) {
  const s = activeSheet.value;
  if (!s) return;
  const rows = [];
  for (let r = 0; r < s.rows; r++) {
    const key = cellKey(col, r);
    const raw = cellsMap.value[key]?.v;
    const val =
      raw == null || raw === "" ? "" : evalCell(raw, cellsMap.value, key);
    rows.push({ r, val });
  }

  const isEmpty = (v) => v === "" || v == null;
  const nonEmpty = rows.filter((o) => !isEmpty(o.val));
  const empties = rows.filter((o) => isEmpty(o.val));

  nonEmpty.sort((a, b) => {
    let cmp;
    if (typeof a.val === "number" && typeof b.val === "number") {
      cmp = a.val - b.val;
    } else {
      cmp = String(a.val).localeCompare(String(b.val), "fr", { numeric: true });
    }
    return dir === "desc" ? -cmp : cmp;
  });

  const sorted = [...nonEmpty, ...empties]; // cellules vides toujours en bas
  const oldToNew = {};
  sorted.forEach((o, newIdx) => {
    oldToNew[o.r] = newIdx;
  });

  pushHistory();
  s.data = reorderRows(s.data ?? {}, oldToNew);
  scheduleSave();
}

function onHeaderMenu({ kind, action, index }) {
  if (kind === "col") {
    if (action === "sort-asc") return sortByColumn(index, "asc");
    if (action === "sort-desc") return sortByColumn(index, "desc");
    if (action === "filter") return openFilterEditor(index);
  }
  const at = action === "insert-after" ? index + 1 : index;
  if (kind === "row") {
    if (action === "delete") deleteRowAt(index);
    else insertRowAt(at);
  } else if (kind === "col") {
    if (action === "delete") deleteColAt(index);
    else insertColAt(at);
  }
}

// ---- Recopie incrémentée (poignée de remplissage) ----

function rawAt(col, row) {
  return cellsMap.value[cellKey(col, row)]?.v ?? "";
}

function setRaw(col, row, value) {
  const cell = ensureCell(col, row);
  cell.v = value == null ? "" : value;
  cleanCell(col, row);
}

function onFill(target) {
  const s = activeSheet.value;
  if (!s || !anchor.value) return;
  const src = normalizeRange(anchor.value, focus.value ?? anchor.value);

  let dir = null;
  if (target.row > src.r2) dir = "down";
  else if (target.row < src.r1) dir = "up";
  else if (target.col > src.c2) dir = "right";
  else if (target.col < src.c1) dir = "left";
  if (!dir) return;

  pushHistory();

  if (dir === "down" || dir === "up") {
    for (let col = src.c1; col <= src.c2; col++) {
      const source = [];
      for (let row = src.r1; row <= src.r2; row++) source.push(rawAt(col, row));
      if (dir === "down") {
        const length = target.row - src.r2;
        const series = buildFillSeries(source, length, { col: 0, row: 1 });
        for (let k = 0; k < length; k++) setRaw(col, src.r2 + 1 + k, series[k]);
      } else {
        const length = src.r1 - target.row;
        const series = buildFillSeries([...source].reverse(), length, { col: 0, row: -1 });
        for (let k = 0; k < length; k++) setRaw(col, src.r1 - 1 - k, series[k]);
      }
    }
  } else {
    for (let row = src.r1; row <= src.r2; row++) {
      const source = [];
      for (let col = src.c1; col <= src.c2; col++) source.push(rawAt(col, row));
      if (dir === "right") {
        const length = target.col - src.c2;
        const series = buildFillSeries(source, length, { col: 1, row: 0 });
        for (let k = 0; k < length; k++) setRaw(src.c2 + 1 + k, row, series[k]);
      } else {
        const length = src.c1 - target.col;
        const series = buildFillSeries([...source].reverse(), length, { col: -1, row: 0 });
        for (let k = 0; k < length; k++) setRaw(src.c1 - 1 - k, row, series[k]);
      }
    }
  }

  anchor.value = {
    col: Math.min(src.c1, target.col),
    row: Math.min(src.r1, target.row),
  };
  focus.value = {
    col: Math.max(src.c2, target.col),
    row: Math.max(src.r2, target.row),
  };
  scheduleSave();
}

function onGlobalKeyDown(e) {
  if (isEditingField()) return;
  if (!(e.ctrlKey || e.metaKey)) return;
  const k = e.key.toLowerCase();
  if (k === "z" && !e.shiftKey) {
    e.preventDefault();
    undo();
  } else if (k === "y" || (k === "z" && e.shiftKey)) {
    e.preventDefault();
    redo();
  }
}

onMounted(() => {
  window.addEventListener("copy", copySelection);
  window.addEventListener("cut", cutSelection);
  window.addEventListener("paste", pasteSelection);
  window.addEventListener("keydown", onGlobalKeyDown);
});

onBeforeUnmount(() => {
  window.removeEventListener("copy", copySelection);
  window.removeEventListener("cut", cutSelection);
  window.removeEventListener("paste", pasteSelection);
  window.removeEventListener("keydown", onGlobalKeyDown);
});

function commitToolbarEditor() {
  if (!anchor.value || !editorDirty.value) return;
  writeValueToCell(anchor.value.col, anchor.value.row, editorValue.value);
  editorDirty.value = false;
}

function onNavigateEditor({ dCol, dRow, cancel }) {
  if (cancel) {
    if (!anchor.value) return;
    editorDirty.value = false;
    syncEditorFromCell(anchor.value);
    return;
  }
  if (!anchor.value) return;
  const nc = Math.max(
    0,
    Math.min((activeSheet.value?.cols ?? 1) - 1, anchor.value.col + dCol),
  );
  const nr = Math.max(
    0,
    Math.min((activeSheet.value?.rows ?? 1) - 1, anchor.value.row + dRow),
  );
  anchor.value = { col: nc, row: nr };
  focus.value = { col: nc, row: nr };
}

function addRow() {
  if (!activeSheet.value) return;
  activeSheet.value.rows = Math.min(1000, activeSheet.value.rows + 1);
  scheduleSave();
}
function addCol() {
  if (!activeSheet.value) return;
  activeSheet.value.cols = Math.min(200, activeSheet.value.cols + 1);
  scheduleSave();
}

function addSheet() {
  router.post(
    route("projects.sheets.store", props.projectSlug),
    { rank_id: props.rankId },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["sheets"],
      onSuccess: () => {
        const newest = props.sheets[props.sheets.length - 1];
        if (newest) activeId.value = newest.id;
      },
    },
  );
}

function renameSheet({ id, name }) {
  const sheet = localSheets.value.find((s) => s.id === id);
  if (sheet) {
    sheet.name = name;
  }

  router.put(
    route("projects.sheets.update", [props.projectSlug, id]),
    { name },
    { preserveScroll: true, preserveState: true, only: ["sheets"] },
  );
}

function moveSheet({ id, dir }) {
  const idx = localSheets.value.findIndex((s) => s.id === id);
  if (idx < 0) return;
  const swap = idx + dir;
  if (swap < 0 || swap >= localSheets.value.length) return;
  const arr = [...localSheets.value];
  const [item] = arr.splice(idx, 1);
  arr.splice(swap, 0, item);
  router.post(
    route("projects.sheets.reorder", props.projectSlug),
    { ids: arr.map((s) => s.id), rank_id: props.rankId },
    { preserveScroll: true, preserveState: true, only: ["sheets"] },
  );
}

function deleteSheet(id) {
  if (localSheets.value.length <= 1) {
    alert("Impossible de supprimer la dernière feuille.");
    return;
  }
  if (!confirm("Supprimer cette feuille ?")) return;
  router.delete(
    route("projects.sheets.destroy", [props.projectSlug, id]),
    {
      preserveScroll: true,
      preserveState: true,
      only: ["sheets"],
      onSuccess: () => {
        if (activeId.value === id) {
          activeId.value = props.sheets[0]?.id ?? null;
        }
      },
    },
  );
}
</script>

<template>
  <div class="flex flex-col gap-3">
    <header class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <h2 class="flex items-center gap-2 text-base font-semibold text-foreground">
          <span>Tableur global</span>
        </h2>
        <p class="text-xs text-muted-foreground">
          Glissez pour sélectionner · Double-clic pour éditer · Suppr pour vider ·
          Ctrl+C / X / V pour copier-couper-coller
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          class="gap-1.5"
          title="Annuler (Ctrl+Z)"
          :disabled="!undoStack.length"
          @click="undo"
        >
          <Undo2 class="h-3.5 w-3.5" />
        </Button>
        <Button
          variant="outline"
          size="sm"
          class="gap-1.5"
          title="Rétablir (Ctrl+Y)"
          :disabled="!redoStack.length"
          @click="redo"
        >
          <Redo2 class="h-3.5 w-3.5" />
        </Button>
        <Button variant="outline" size="sm" class="gap-1.5" @click="addRow">
          <Rows3 class="h-3.5 w-3.5" />
          Ligne
        </Button>
        <Button variant="outline" size="sm" class="gap-1.5" @click="addCol">
          <Columns3 class="h-3.5 w-3.5" />
          Colonne
        </Button>
        <Button
          variant="outline"
          size="sm"
          class="gap-1.5"
          :class="conditionalRules.length ? 'border-primary/60 text-primary' : ''"
          @click="condDialogOpen = true"
        >
          <Palette class="h-3.5 w-3.5" />
          Format cond.
        </Button>
        <Button variant="outline" size="sm" class="gap-1.5" @click="exportCsv">
          <Download class="h-3.5 w-3.5" />
          CSV
        </Button>
        <Button size="sm" class="gap-1.5" @click="addSheet">
          <Plus class="h-3.5 w-3.5" />
          Feuille
        </Button>
      </div>
    </header>

    <SpreadsheetToolbar
      :selection-label="selLabel"
      :selection-count="selCount"
      :anchor-cell="anchor"
      :current-bg="currentBg"
      :current-fg="currentFg"
      v-model:editor-value="editorValue"
      @apply-bg="applyBg"
      @apply-fg="applyFg"
      @toggle-bold="toggleBold"
      @toggle-italic="toggleItalic"
      @apply-format="applyNumberFormat"
      @clear-format="clearFormat"
      @clear-values="clearValues"
      @commit-editor="commitToolbarEditor"
      @navigate-editor="onNavigateEditor"
    />

    <div
      v-if="activeFilters.length || filterEditCol !== null"
      class="flex flex-wrap items-center gap-2 rounded-lg border border-border bg-card/60 px-2.5 py-1.5 text-xs"
    >
      <span class="font-medium text-muted-foreground">Filtres :</span>
      <span
        v-for="f in activeFilters"
        :key="f.col"
        class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-primary"
      >
        {{ colLetter(f.col) }} contient « {{ f.q }} »
        <button
          type="button"
          class="hover:text-primary/70"
          @click="removeFilter(f.col)"
        >
          <X class="h-3 w-3" />
        </button>
      </span>
      <div
        v-if="filterEditCol !== null"
        class="inline-flex items-center gap-1"
      >
        <span class="text-muted-foreground">{{ colLetter(filterEditCol) }} contient</span>
        <input
          ref="filterInputRef"
          v-model="filterEditValue"
          class="h-7 w-40 rounded-md border border-input bg-background px-2 text-xs outline-none focus:ring-2 focus:ring-ring"
          placeholder="texte…"
          @keydown.enter.prevent="commitFilterEditor"
          @keydown.esc="filterEditCol = null"
          @blur="commitFilterEditor"
        />
      </div>
      <button
        v-if="activeFilters.length"
        type="button"
        class="ml-auto text-muted-foreground hover:text-foreground"
        @click="clearFilters"
      >
        Tout effacer
      </button>
    </div>

    <div class="h-[60vh] min-h-[420px]">
      <SpreadsheetGrid
        v-if="activeSheet"
        :rows="activeSheet.rows"
        :cols="activeSheet.cols"
        :cells="cellsMap"
        :hidden-rows="hiddenRows"
        :conditional-rules="conditionalRules"
        v-model:anchor="anchor"
        v-model:focus="focus"
        @cell-change="onCellChange"
        @request-clear="clearValues"
        @fill="onFill"
        @header-menu="onHeaderMenu"
      />
    </div>

    <SheetTabs
      :sheets="localSheets"
      :active-id="activeId"
      @select="(id) => (activeId = id)"
      @rename="renameSheet"
      @move="moveSheet"
      @delete="deleteSheet"
    />

    <Dialog :open="condDialogOpen" @update:open="(v) => (condDialogOpen = v)">
      <DialogContent class="max-w-lg p-5">
        <DialogTitle class="text-base font-semibold">
          Mise en forme conditionnelle
        </DialogTitle>
        <p class="mt-0.5 text-xs text-muted-foreground">
          Les règles colorent automatiquement les cellules selon leur valeur.
        </p>

        <ul v-if="conditionalRules.length" class="mt-3 flex flex-col gap-1.5">
          <li
            v-for="(rule, idx) in conditionalRules"
            :key="idx"
            class="flex items-center gap-2 rounded-md border border-border/60 px-2 py-1.5 text-xs"
          >
            <span
              class="inline-flex h-5 items-center rounded px-1.5 font-medium"
              :style="{ backgroundColor: rule.bg, color: rule.fg }"
            >
              Aa
            </span>
            <span class="flex-1">
              Valeur {{ opLabel(rule.op) }}
              <span
                v-if="!['empty', 'notempty'].includes(rule.op)"
                class="font-medium text-foreground"
              >
                « {{ rule.value }} »
              </span>
            </span>
            <button
              type="button"
              class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-rose-400"
              @click="removeConditionalRule(idx)"
            >
              <X class="h-3.5 w-3.5" />
            </button>
          </li>
        </ul>
        <p v-else class="mt-3 text-xs text-muted-foreground">Aucune règle.</p>

        <div class="mt-4 flex flex-col gap-2 rounded-md border border-border bg-muted/20 p-3">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs text-muted-foreground">Si la valeur</span>
            <select
              v-model="condDraft.op"
              class="h-8 rounded-md border border-input bg-background px-2 text-xs"
            >
              <option v-for="o in condOperators" :key="o.value" :value="o.value">
                {{ o.label }}
              </option>
            </select>
            <input
              v-if="!['empty', 'notempty'].includes(condDraft.op)"
              v-model="condDraft.value"
              class="h-8 w-28 rounded-md border border-input bg-background px-2 text-xs"
              placeholder="valeur"
            />
          </div>
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-1.5 text-xs text-muted-foreground">
              Fond
              <input v-model="condDraft.bg" type="color" class="h-7 w-9 rounded border border-input bg-background" />
            </label>
            <label class="flex items-center gap-1.5 text-xs text-muted-foreground">
              Texte
              <input v-model="condDraft.fg" type="color" class="h-7 w-9 rounded border border-input bg-background" />
            </label>
            <span
              class="ml-auto inline-flex h-7 items-center rounded px-2 text-xs font-medium"
              :style="{ backgroundColor: condDraft.bg, color: condDraft.fg }"
            >
              Aperçu
            </span>
          </div>
          <Button size="sm" class="mt-1 self-start gap-1.5" @click="addConditionalRule">
            <Plus class="h-3.5 w-3.5" />
            Ajouter la règle
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>

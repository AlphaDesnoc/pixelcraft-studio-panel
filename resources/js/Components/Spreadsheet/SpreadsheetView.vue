<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { Plus, Rows3, Columns3 } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import SpreadsheetGrid from "./SpreadsheetGrid.vue";
import SpreadsheetToolbar from "./SpreadsheetToolbar.vue";
import SheetTabs from "./SheetTabs.vue";
import { cellKey, iterSelection, selectionCount, selectionLabel } from "./cells.js";

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
  },
);

const selLabel = computed(() => selectionLabel(anchor.value, focus.value));
const selCount = computed(() => selectionCount(anchor.value, focus.value));

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
        rows: sheet.rows,
        cols: sheet.cols,
      },
      { preserveScroll: true, preserveState: true, only: ["sheets"], replace: true },
    );
  }, 600);
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
    (c.v == null || c.v === "") && !c.bg && !c.fg && !c.b && !c.i;
  if (isEmpty) delete sheet.data[key];
}

function writeValueToCell(col, row, value) {
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
  });
}

function clearValues() {
  applyToSelection((cell) => {
    cell.v = "";
  });
}

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
          Cliquez et glissez pour sélectionner · Suppr pour vider · Double-clic pour
          éditer
        </p>
      </div>
      <div class="flex items-center gap-2">
        <Button variant="outline" size="sm" class="gap-1.5" @click="addRow">
          <Rows3 class="h-3.5 w-3.5" />
          Ligne
        </Button>
        <Button variant="outline" size="sm" class="gap-1.5" @click="addCol">
          <Columns3 class="h-3.5 w-3.5" />
          Colonne
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
      v-model:editor-value="editorValue"
      @apply-bg="applyBg"
      @apply-fg="applyFg"
      @toggle-bold="toggleBold"
      @toggle-italic="toggleItalic"
      @clear-format="clearFormat"
      @clear-values="clearValues"
      @commit-editor="commitToolbarEditor"
      @navigate-editor="onNavigateEditor"
    />

    <div class="h-[60vh] min-h-[420px]">
      <SpreadsheetGrid
        v-if="activeSheet"
        :rows="activeSheet.rows"
        :cols="activeSheet.cols"
        :cells="cellsMap"
        v-model:anchor="anchor"
        v-model:focus="focus"
        @cell-change="onCellChange"
        @request-clear="clearValues"
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
  </div>
</template>

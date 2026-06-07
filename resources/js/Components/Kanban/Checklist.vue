<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { ArrowDownUp, CheckSquare, GripVertical, Trash2, X } from "lucide-vue-next";
import { VueDraggable } from "vue-draggable-plus";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { confirmDialog } from "@/composables/useConfirm.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  taskId: { type: Number, required: true },
  checklist: { type: Object, required: true },
});

const newItem = ref("");
const editingName = ref(false);
const nameDraft = ref("");
const localItems = ref([]);

watch(
  () => props.checklist.items,
  (items) => {
    localItems.value = items.map((item) => ({ ...item }));
  },
  { immediate: true, deep: true },
);

const total = computed(() => props.checklist.items.length);
const done = computed(() => props.checklist.items.filter((i) => i.is_done).length);
const percent = computed(() =>
  total.value > 0 ? Math.round((done.value / total.value) * 100) : 0,
);

const optimistic = ref(new Map());

function isItemDone(item) {
  const o = optimistic.value.get(item.id);
  return o !== undefined ? o : item.is_done;
}

function persistOrder() {
  router.post(
    route("projects.tasks.checklists.items.reorder", [
      props.projectSlug,
      props.taskId,
      props.checklist.id,
    ]),
    { order: localItems.value.map((item) => item.id) },
    { preserveScroll: true, preserveState: true, only: ["lists"] },
  );
}

function onDragEnd() {
  persistOrder();
}

function reverseItems() {
  localItems.value = [...localItems.value].reverse();
  persistOrder();
}

function toggleItem(item) {
  const next = !isItemDone(item);
  optimistic.value.set(item.id, next);
  router.put(
    route("projects.tasks.checklists.items.update", [
      props.projectSlug,
      props.taskId,
      props.checklist.id,
      item.id,
    ]),
    { is_done: next },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["lists"],
      onFinish: () => optimistic.value.delete(item.id),
    },
  );
}

function addItem() {
  const content = newItem.value.trim();
  if (!content) return;
  router.post(
    route("projects.tasks.checklists.items.store", [
      props.projectSlug,
      props.taskId,
      props.checklist.id,
    ]),
    { content },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["lists"],
      onSuccess: () => {
        newItem.value = "";
      },
    },
  );
}

async function deleteItem(item) {
  if (
    !(await confirmDialog({
      title: "Supprimer l'élément",
      message: "Cet élément de la checklist sera supprimé.",
    }))
  )
    return;
  router.delete(
    route("projects.tasks.checklists.items.destroy", [
      props.projectSlug,
      props.taskId,
      props.checklist.id,
      item.id,
    ]),
    { preserveScroll: true, preserveState: true, only: ["lists"] },
  );
}

async function deleteChecklist() {
  if (
    !(await confirmDialog({
      title: "Supprimer la checklist",
      message: "La checklist et tous ses éléments seront supprimés.",
    }))
  )
    return;
  router.delete(
    route("projects.tasks.checklists.destroy", [
      props.projectSlug,
      props.taskId,
      props.checklist.id,
    ]),
    { preserveScroll: true, preserveState: true, only: ["lists"] },
  );
}

function startEditName() {
  nameDraft.value = props.checklist.name;
  editingName.value = true;
}

function saveName() {
  const name = nameDraft.value.trim();
  editingName.value = false;
  if (!name || name === props.checklist.name) return;
  router.put(
    route("projects.tasks.checklists.update", [
      props.projectSlug,
      props.taskId,
      props.checklist.id,
    ]),
    { name },
    { preserveScroll: true, preserveState: true, only: ["lists"] },
  );
}
</script>

<template>
  <section class="flex flex-col gap-2.5">
    <header class="flex items-center gap-2">
      <CheckSquare class="h-4 w-4 text-muted-foreground" />
      <input
        v-if="editingName"
        v-model="nameDraft"
        class="flex-1 rounded-md border border-input bg-background/40 px-2 py-1 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-ring"
        @blur="saveName"
        @keydown.enter.prevent="saveName"
        @keydown.esc="editingName = false"
      />
      <h3
        v-else
        class="flex-1 cursor-text text-sm font-semibold"
        @click="startEditName"
      >
        {{ checklist.name }}
      </h3>
      <button
        v-if="total > 1"
        type="button"
        class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
        title="Inverser l'ordre"
        @click="reverseItems"
      >
        <ArrowDownUp class="h-3.5 w-3.5" />
      </button>
      <button
        type="button"
        class="rounded p-1 text-rose-400/80 hover:bg-rose-500/10 hover:text-rose-300"
        @click="deleteChecklist"
      >
        <Trash2 class="h-3.5 w-3.5" />
      </button>
    </header>

    <div v-if="total > 0" class="flex items-center gap-2">
      <span class="w-8 text-right text-[10px] text-muted-foreground">
        {{ percent }}%
      </span>
      <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
        <div
          class="h-full rounded-full bg-primary transition-all"
          :style="{ width: `${percent}%` }"
        />
      </div>
    </div>

    <VueDraggable
      v-if="total > 0"
      v-model="localItems"
      :animation="150"
      handle=".checklist-item-handle"
      tag="ul"
      class="flex flex-col gap-0.5"
      ghost-class="checklist-item-ghost"
      @end="onDragEnd"
    >
      <li
        v-for="item in localItems"
        :key="item.id"
        class="group flex items-center gap-2 rounded-md px-1.5 py-1 hover:bg-muted/40"
      >
        <button
          type="button"
          class="checklist-item-handle cursor-grab rounded p-0.5 text-muted-foreground opacity-0 transition-opacity hover:bg-muted hover:text-foreground active:cursor-grabbing group-hover:opacity-100"
          title="Glisser pour réordonner"
        >
          <GripVertical class="h-3.5 w-3.5" />
        </button>
        <input
          type="checkbox"
          :checked="isItemDone(item)"
          class="h-4 w-4 rounded border-border accent-primary"
          @change="toggleItem(item)"
        />
        <span
          class="flex-1 text-sm"
          :class="
            isItemDone(item)
              ? 'text-muted-foreground line-through'
              : 'text-foreground'
          "
        >
          {{ item.content }}
        </span>
        <button
          type="button"
          class="rounded p-1 text-muted-foreground opacity-0 transition-opacity hover:bg-muted hover:text-foreground group-hover:opacity-100"
          @click="deleteItem(item)"
        >
          <X class="h-3.5 w-3.5" />
        </button>
      </li>
    </VueDraggable>

    <form class="flex items-center gap-2" @submit.prevent="addItem">
      <Input
        v-model="newItem"
        type="text"
        placeholder="Ajouter un élément…"
        class="h-9 flex-1 text-sm"
      />
      <Button type="submit" size="sm" class="h-9">Ajouter</Button>
    </form>
  </section>
</template>

<style scoped>
.checklist-item-ghost {
  opacity: 0.4;
}
</style>

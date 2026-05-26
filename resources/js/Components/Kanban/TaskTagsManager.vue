<script setup>
import { computed, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import { Plus, Tag, X } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";

const props = defineProps({
  projectSlug: { type: String, required: true },
  task: { type: Object, required: true },
  tags: { type: Array, default: () => [] },
});

const selectedIds = ref([]);
const newTagName = ref("");
const creating = ref(false);

const taskTagIds = computed(() => new Set((props.task?.tags ?? []).map((t) => t.id)));

watch(
  () => props.task?.id,
  () => {
    selectedIds.value = (props.task?.tags ?? []).map((t) => t.id);
  },
  { immediate: true },
);

const availableToAdd = computed(() =>
  (props.tags ?? []).filter((t) => !taskTagIds.value.has(t.id)),
);

function sync() {
  router.put(
    route("projects.tasks.tags.sync", [props.projectSlug, props.task.id]),
    { tag_ids: selectedIds.value },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["lists", "tags"],
    },
  );
}

function toggleTag(tagId) {
  const set = new Set(selectedIds.value);
  if (set.has(tagId)) {
    set.delete(tagId);
  } else {
    set.add(tagId);
  }
  selectedIds.value = [...set];
  sync();
}

function removeTag(tagId) {
  selectedIds.value = selectedIds.value.filter((id) => id !== tagId);
  sync();
}

function createTag() {
  const name = newTagName.value.trim();
  if (!name || creating.value) return;
  creating.value = true;
  router.post(
    route("projects.tags.store", props.projectSlug),
    { name },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["lists", "tags"],
      onFinish: () => {
        creating.value = false;
        newTagName.value = "";
      },
    },
  );
}
</script>

<template>
  <section class="flex flex-col gap-2">
    <div class="flex items-center gap-2">
      <Tag class="h-4 w-4 text-muted-foreground" />
      <h3 class="text-sm font-semibold">Étiquettes</h3>
    </div>

    <div v-if="(task.tags ?? []).length" class="flex flex-wrap gap-1.5">
      <span
        v-for="tag in task.tags"
        :key="tag.id"
        class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium"
        :style="
          tag.color
            ? { borderColor: tag.color, color: tag.color }
            : undefined
        "
      >
        {{ tag.name }}
        <button
          type="button"
          class="rounded p-0.5 hover:bg-muted"
          aria-label="Retirer l'étiquette"
          @click="removeTag(tag.id)"
        >
          <X class="h-3 w-3" />
        </button>
      </span>
    </div>
    <p v-else class="text-xs text-muted-foreground">Aucune étiquette</p>

    <div v-if="availableToAdd.length" class="flex flex-wrap gap-1">
      <Button
        v-for="tag in availableToAdd"
        :key="tag.id"
        type="button"
        variant="outline"
        size="sm"
        class="h-7 gap-1 text-[11px]"
        @click="toggleTag(tag.id)"
      >
        <Plus class="h-3 w-3" />
        {{ tag.name }}
      </Button>
    </div>

    <div class="flex flex-col gap-2 rounded-md border border-border/60 bg-muted/15 p-2">
      <p class="text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
        Nouvelle étiquette projet
      </p>
      <div class="flex gap-2">
        <Input
          v-model="newTagName"
          type="text"
          placeholder="Nom"
          class="h-8 text-xs"
          @keydown.enter.prevent="createTag"
        />
        <Button
          type="button"
          size="sm"
          class="h-8 shrink-0"
          :disabled="creating || !newTagName.trim()"
          @click="createTag"
        >
          Créer
        </Button>
      </div>
    </div>
  </section>
</template>

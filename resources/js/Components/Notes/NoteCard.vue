<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import { Pencil, Pin, PinOff, Trash2 } from "lucide-vue-next";
import { confirmDialog } from "@/composables/useConfirm.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  note: { type: Object, required: true },
});

const emits = defineEmits(["edit"]);

function textColorFor(hex) {
  const h = (hex ?? "").replace("#", "");
  if (h.length !== 6) return "#18181b";
  const r = parseInt(h.substr(0, 2), 16);
  const g = parseInt(h.substr(2, 2), 16);
  const b = parseInt(h.substr(4, 2), 16);
  const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
  return luminance > 0.55 ? "#18181b" : "#fafafa";
}

const primaryText = computed(() => textColorFor(props.note.color));
const isDarkBg = computed(() => primaryText.value === "#fafafa");
const secondaryText = computed(() =>
  isDarkBg.value ? "rgba(250,250,250,0.7)" : "rgba(24,24,27,0.65)",
);
const actionHoverBg = computed(() =>
  isDarkBg.value ? "rgba(255,255,255,0.12)" : "rgba(0,0,0,0.08)",
);

const dateLabel = computed(() => {
  const iso = props.note.updated_at ?? props.note.created_at;
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(d);
});

function togglePin() {
  router.post(
    route("projects.notes.pin", [props.projectSlug, props.note.id]),
    {},
    { preserveScroll: true, preserveState: true, only: ["notes", "stats"] },
  );
}

async function destroy() {
  if (
    !(await confirmDialog({
      title: "Supprimer la note",
      message: "Cette note sera définitivement supprimée.",
    }))
  )
    return;
  router.delete(
    route("projects.notes.destroy", [props.projectSlug, props.note.id]),
    { preserveScroll: true, preserveState: true, only: ["notes", "stats"] },
  );
}
</script>

<template>
  <article
    class="group relative flex flex-col gap-2 rounded-xl border border-black/5 p-4 shadow-sm transition-shadow hover:shadow-md"
    :style="{ backgroundColor: note.color, color: primaryText }"
  >
    <Pin
      v-if="note.pinned"
      class="absolute right-3 top-3 h-3.5 w-3.5 rotate-45 fill-current opacity-60"
    />

    <h3 class="pr-6 text-sm font-semibold leading-tight">
      {{ note.title }}
    </h3>

    <p
      v-if="note.content"
      class="whitespace-pre-wrap text-sm leading-snug"
      :style="{ color: primaryText }"
    >
      {{ note.content }}
    </p>

    <footer class="mt-auto flex items-end justify-between gap-2 pt-2">
      <span class="text-xs" :style="{ color: secondaryText }">
        {{ note.creator?.name ?? "—" }}
      </span>
      <div class="flex items-center gap-2">
        <span class="text-xs" :style="{ color: secondaryText }">
          {{ dateLabel }}
        </span>
        <div
          class="flex items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100"
        >
          <button
            type="button"
            class="inline-flex h-6 w-6 items-center justify-center rounded-md"
            :style="{ color: primaryText }"
            :title="note.pinned ? 'Désépingler' : 'Épingler'"
            @mouseover="(e) => (e.currentTarget.style.backgroundColor = actionHoverBg)"
            @mouseleave="(e) => (e.currentTarget.style.backgroundColor = 'transparent')"
            @click="togglePin"
          >
            <PinOff v-if="note.pinned" class="h-3.5 w-3.5" />
            <Pin v-else class="h-3.5 w-3.5" />
          </button>
          <button
            type="button"
            class="inline-flex h-6 w-6 items-center justify-center rounded-md"
            :style="{ color: primaryText }"
            title="Modifier"
            @mouseover="(e) => (e.currentTarget.style.backgroundColor = actionHoverBg)"
            @mouseleave="(e) => (e.currentTarget.style.backgroundColor = 'transparent')"
            @click="emits('edit', note)"
          >
            <Pencil class="h-3.5 w-3.5" />
          </button>
          <button
            type="button"
            class="inline-flex h-6 w-6 items-center justify-center rounded-md text-rose-600"
            title="Supprimer"
            @mouseover="(e) => (e.currentTarget.style.backgroundColor = actionHoverBg)"
            @mouseleave="(e) => (e.currentTarget.style.backgroundColor = 'transparent')"
            @click="destroy"
          >
            <Trash2 class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </footer>
  </article>
</template>

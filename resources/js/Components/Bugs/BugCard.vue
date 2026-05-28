<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import { MessageSquare, Pencil, Trash2 } from "lucide-vue-next";
import { Badge } from "@/Components/ui/badge";
import ImageLightbox from "@/Components/ImageLightbox.vue";
import { useImageLightbox } from "@/composables/useImageLightbox.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  bug: { type: Object, required: true },
  canManage: { type: Boolean, default: false },
  priorities: { type: Object, required: true },
  statuses: { type: Object, required: true },
});

const emits = defineEmits(["edit", "open"]);

const canEdit = computed(
  () => Boolean(props.bug.can_manage || props.bug.can_edit),
);
const canDelete = computed(() => Boolean(props.bug.can_manage));

const {
  open: lightboxOpen,
  index: lightboxIndex,
  images: lightboxImages,
  openFromUrls: openScreenshotPreview,
} = useImageLightbox();

function previewScreenshot(src) {
  openScreenshotPreview(props.bug.screenshots ?? [], src);
}

const priorityVariant = computed(() => ({
  low: "secondary",
  medium: "default",
  high: "destructive",
  urgent: "destructive",
})[props.bug.priority] ?? "secondary");

const statusVariant = computed(() => ({
  open: "default",
  in_progress: "secondary",
  closed: "secondary",
})[props.bug.status] ?? "secondary");

const dateLabel = computed(() => {
  if (!props.bug.created_at) return "";
  const d = new Date(props.bug.created_at);
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "long",
    year: "numeric",
  }).format(d);
});

const slaInfo = computed(() => {
  if (!props.bug.sla_due_at) return null;
  const d = new Date(props.bug.sla_due_at);
  const due = new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(d);
  return {
    due,
    breached: Boolean(props.bug.is_sla_breached),
  };
});

function destroy() {
  if (!confirm("Supprimer ce bug ?")) return;
  router.delete(route("projects.bugs.destroy", [props.projectSlug, props.bug.id]), {
    preserveScroll: true,
    preserveState: true,
    only: ["bugs"],
  });
}
</script>

<template>
  <article
    class="cursor-pointer rounded-xl border border-border bg-card p-4 transition-colors hover:border-primary/30 hover:bg-card/80"
    @click="emits('open', bug)"
  >
    <header class="flex items-start justify-between gap-3">
      <div class="min-w-0 flex-1">
        <h3 class="text-sm font-semibold text-foreground">{{ bug.title }}</h3>
        <p v-if="bug.description" class="mt-1 whitespace-pre-wrap text-sm text-muted-foreground">
          {{ bug.description }}
        </p>
      </div>
      <div class="flex shrink-0 flex-wrap items-center gap-1.5">
        <Badge
          v-if="slaInfo"
          :variant="slaInfo.breached ? 'destructive' : 'outline'"
        >
          SLA {{ slaInfo.breached ? "dép." : "" }} · {{ slaInfo.due }}
        </Badge>
        <Badge :variant="statusVariant">{{ statuses[bug.status] ?? bug.status }}</Badge>
        <Badge :variant="priorityVariant">{{ priorities[bug.priority] ?? bug.priority }}</Badge>
      </div>
    </header>

    <div
      v-if="bug.screenshots && bug.screenshots.length > 0"
      class="mt-3 flex flex-wrap gap-2"
    >
      <button
        v-for="(src, idx) in bug.screenshots"
        :key="idx"
        type="button"
        class="block overflow-hidden rounded-md border border-border transition-opacity hover:opacity-90"
        @click.stop="previewScreenshot(src)"
      >
        <img :src="src" alt="" class="h-16 w-16 object-cover" />
      </button>
    </div>

    <footer class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
      <div class="flex flex-wrap items-center gap-2">
        <span v-if="bug.reporter">Par {{ bug.reporter.name }}</span>
        <span v-if="dateLabel">· {{ dateLabel }}</span>
        <span
          v-if="bug.assigned_rank"
          class="text-foreground"
        >
          · Rank {{ bug.assigned_rank.name }}
        </span>
        <span
          v-else-if="bug.assignee"
          class="text-foreground"
        >
          · Assigné à {{ bug.assignee.name }}
        </span>
        <span
          v-else-if="!bug.assigned_rank"
          class="text-amber-400"
        >
          · Non assigné à un rank
        </span>
      </div>
      <div class="flex items-center gap-1">
        <button
          type="button"
          class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted/60 hover:text-foreground"
          title="Ouvrir la discussion"
          @click.stop="emits('open', bug)"
        >
          <MessageSquare class="h-3.5 w-3.5" />
        </button>
        <template v-if="canEdit">
        <button
          type="button"
          class="inline-flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted/60 hover:text-foreground"
          title="Modifier"
          @click.stop="emits('edit', bug)"
        >
          <Pencil class="h-3.5 w-3.5" />
        </button>
        <button
          v-if="canDelete"
          type="button"
          class="inline-flex h-7 w-7 items-center justify-center rounded-md text-rose-400 hover:bg-rose-500/10"
          title="Supprimer"
          @click.stop="destroy"
        >
          <Trash2 class="h-3.5 w-3.5" />
        </button>
        </template>
      </div>
    </footer>
  </article>
  <ImageLightbox
    v-model:open="lightboxOpen"
    v-model:index="lightboxIndex"
    :images="lightboxImages"
  />
</template>

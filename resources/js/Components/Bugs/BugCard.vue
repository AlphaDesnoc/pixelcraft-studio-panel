<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";
import {
  CalendarDays,
  Clock3,
  MessageSquare,
  Paperclip,
  Pencil,
  Trash2,
} from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import { confirmDialog } from "@/composables/useConfirm.js";
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

const priorityColors = {
  low: "#10b981",
  medium: "#3b82f6",
  high: "#f97316",
  urgent: "#ef4444",
};
const priorityColor = computed(
  () => priorityColors[props.bug.priority] ?? "#3b82f6",
);

const statusColors = {
  open: "#f59e0b",
  in_progress: "#3b82f6",
  closed: "#10b981",
};
const statusColor = computed(() => statusColors[props.bug.status] ?? "#6b7280");

const screenshots = computed(() => props.bug.screenshots ?? []);

const reporterInitials = computed(() => {
  const name = props.bug.reporter?.name ?? "?";
  return name
    .split(" ")
    .map((part) => part.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();
});

const dateLabel = computed(() => {
  if (!props.bug.created_at) return "";
  const d = new Date(props.bug.created_at);
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
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

async function destroy() {
  if (
    !(await confirmDialog({
      title: "Supprimer le bug",
      message: "Ce signalement de bug sera définitivement supprimé.",
    }))
  )
    return;
  router.delete(route("projects.bugs.destroy", [props.projectSlug, props.bug.id]), {
    preserveScroll: true,
    preserveState: true,
    only: ["bugs"],
  });
}
</script>

<template>
  <article
    class="group relative cursor-pointer overflow-hidden rounded-xl border border-border bg-card pl-3 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-black/5"
    @click="emits('open', bug)"
  >
    <span
      class="absolute inset-y-0 left-0 w-1.5"
      :style="{
        background: `linear-gradient(to bottom, ${priorityColor}, ${priorityColor}66)`,
      }"
    />

    <div class="flex gap-3 p-4">
      <Avatar
        :fallback="reporterInitials"
        size="md"
        rounded="lg"
        class="mt-0.5"
      />

      <div class="flex min-w-0 flex-1 flex-col gap-2">
        <header class="flex items-start justify-between gap-3">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span
                class="shrink-0 rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] font-semibold text-muted-foreground"
              >
                #{{ bug.id }}
              </span>
              <h3 class="truncate text-[15px] font-semibold leading-tight text-foreground">
                {{ bug.title }}
              </h3>
            </div>
            <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs text-muted-foreground">
              <span v-if="bug.reporter" class="font-medium text-foreground/80">
                {{ bug.reporter.name }}
              </span>
              <span v-if="dateLabel" class="inline-flex items-center gap-1">
                <CalendarDays class="h-3 w-3" />
                {{ dateLabel }}
              </span>
              <span v-if="screenshots.length" class="inline-flex items-center gap-1">
                <Paperclip class="h-3 w-3" />
                {{ screenshots.length }}
              </span>
            </div>
          </div>

          <div class="flex shrink-0 flex-col items-end gap-1.5">
            <div class="flex flex-wrap items-center justify-end gap-1.5">
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium"
                :style="{ backgroundColor: statusColor + '1f', color: statusColor }"
              >
                <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: statusColor }" />
                {{ statuses[bug.status] ?? bug.status }}
              </span>
              <span
                class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                :style="{ backgroundColor: priorityColor + '1f', color: priorityColor }"
              >
                <span class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: priorityColor }" />
                {{ priorities[bug.priority] ?? bug.priority }}
              </span>
            </div>
            <span
              v-if="slaInfo"
              class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium"
              :class="
                slaInfo.breached
                  ? 'bg-rose-500/15 text-rose-400'
                  : 'bg-muted text-muted-foreground'
              "
            >
              <Clock3 class="h-3 w-3" />
              SLA {{ slaInfo.breached ? "dépassé" : slaInfo.due }}
            </span>
          </div>
        </header>

        <p
          v-if="bug.description"
          class="line-clamp-2 whitespace-pre-wrap text-sm leading-relaxed text-muted-foreground"
        >
          {{ bug.description }}
        </p>

        <div v-if="screenshots.length" class="flex flex-wrap gap-2">
          <button
            v-for="(src, idx) in screenshots"
            :key="idx"
            type="button"
            class="block overflow-hidden rounded-lg border border-border transition-transform hover:scale-105"
            @click.stop="previewScreenshot(src)"
          >
            <img :src="src" alt="" class="h-16 w-16 object-cover" />
          </button>
        </div>

        <footer
          class="mt-0.5 flex flex-wrap items-center justify-between gap-2 border-t border-border/60 pt-2.5"
        >
          <span
            v-if="bug.assigned_rank"
            class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
          >
            Rank {{ bug.assigned_rank.name }}
          </span>
          <span
            v-else-if="bug.assignee"
            class="inline-flex items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-foreground"
          >
            Assigné à {{ bug.assignee.name }}
          </span>
          <span
            v-else
            class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2 py-0.5 text-xs font-medium text-amber-400"
          >
            Non assigné
          </span>

          <div class="flex items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
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
      </div>
    </div>
  </article>
  <ImageLightbox
    v-model:open="lightboxOpen"
    v-model:index="lightboxIndex"
    :images="lightboxImages"
  />
</template>

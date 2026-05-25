<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import { Users } from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import { Badge } from "@/Components/ui/badge";
import { Card } from "@/Components/ui/card";
import { Progress } from "@/Components/ui/progress";

const props = defineProps({
  project: { type: Object, required: true },
});

const statusVariant = computed(
  () =>
    ({
      active: "success",
      completed: "default",
      archived: "secondary",
    })[props.project.status] ?? "secondary",
);

const statusLabel = computed(
  () =>
    ({
      active: "Actif",
      completed: "Terminé",
      archived: "Archivé",
    })[props.project.status] ?? props.project.status,
);

const initials = computed(() =>
  props.project.name
    .split(" ")
    .map((part) => part.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);

const progress = computed(() => {
  const total = props.project.tasks_total ?? 0;
  const done = props.project.tasks_done ?? 0;
  return total > 0 ? Math.round((done / total) * 100) : 0;
});
</script>

<template>
  <Link
    :href="route('projects.show', project.slug)"
    class="block transition-transform hover:-translate-y-0.5"
  >
    <Card class="p-4 hover:border-border">
      <div class="flex items-start gap-3">
        <Avatar
          :src="project.image_url ?? ''"
          :fallback="initials"
          size="lg"
          rounded="lg"
        />

        <div class="min-w-0 flex-1">
          <h3 class="truncate text-sm font-semibold leading-tight">
            {{ project.name }}
          </h3>
          <p class="mt-1 line-clamp-1 text-xs text-muted-foreground">
            {{ project.description || "Pas de description" }}
          </p>

          <div class="mt-3 flex items-center gap-3 text-[11px] text-muted-foreground">
            <Badge :variant="statusVariant">{{ statusLabel }}</Badge>

            <span class="inline-flex items-center gap-1">
              <Users class="h-3 w-3" />
              {{ project.members_count }}
            </span>

            <span>
              {{ project.tasks_done ?? 0 }}/{{ project.tasks_total ?? 0 }} tâches
            </span>
          </div>
        </div>
      </div>

      <Progress :value="progress" class="mt-4" />
    </Card>
  </Link>
</template>

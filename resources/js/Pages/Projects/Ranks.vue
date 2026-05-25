<script setup>
import { computed, ref } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import { ArrowLeft, Plus } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Avatar } from "@/Components/ui/avatar";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import RankCard from "@/Components/Ranks/RankCard.vue";
import RankFormDialog from "@/Components/Ranks/RankFormDialog.vue";
import MemberPickerDialog from "@/Components/Ranks/MemberPickerDialog.vue";

const props = defineProps({
  project: { type: Object, required: true },
  ranks: { type: Array, default: () => [] },
  members: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: false },
});

const formOpen = ref(false);
const editingRank = ref(null);

const memberPickerOpen = ref(false);
const memberPickerMode = ref("add-member");
const memberPickerRank = ref(null);

const initials = computed(() =>
  props.project.name
    .split(" ")
    .map((p) => p.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase(),
);

const statusLabel = computed(
  () =>
    ({
      active: "Actif",
      completed: "Terminé",
      archived: "Archivé",
    })[props.project.status] ?? props.project.status,
);
const statusVariant = computed(
  () =>
    ({
      active: "success",
      completed: "default",
      archived: "secondary",
    })[props.project.status] ?? "secondary",
);

function openCreate() {
  editingRank.value = null;
  formOpen.value = true;
}

function openAddMember(rank) {
  memberPickerMode.value = "add-member";
  memberPickerRank.value = rank;
  memberPickerOpen.value = true;
}

function openSetResponsible(rank) {
  memberPickerMode.value = "set-responsible";
  memberPickerRank.value = rank;
  memberPickerOpen.value = true;
}

const memberCandidates = computed(() => {
  if (!memberPickerRank.value) return [];
  if (memberPickerMode.value === "set-responsible") {
    return memberPickerRank.value.members ?? [];
  }
  const existingIds = new Set(
    (memberPickerRank.value.members ?? []).map((m) => m.id),
  );
  return props.members.filter((m) => !existingIds.has(m.id));
});
</script>

<template>
  <Head :title="`Ranks · ${project.name}`" />

  <AuthenticatedLayout>
    <div class="flex flex-col gap-5">
      <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-start gap-3">
          <Avatar
            :src="project.image_url ?? ''"
            :fallback="initials"
            size="lg"
            rounded="lg"
          />
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-2xl font-semibold tracking-tight">
                {{ project.name }}
              </h1>
              <Badge :variant="statusVariant">{{ statusLabel }}</Badge>
            </div>
            <Link
              :href="route('projects.show', project.slug)"
              class="mt-1 inline-flex items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
            >
              <ArrowLeft class="h-3 w-3" />
              Retour au projet
            </Link>
          </div>
        </div>
      </header>

      <section class="flex flex-col gap-3">
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-base font-semibold">Gestion des ranks</h2>
            <p class="text-xs text-muted-foreground">
              Chaque rank a un responsable qui gère son équipe, plus son propre
              Kanban, Gantt, notes et calendrier
            </p>
          </div>
          <Button v-if="canEdit" size="sm" class="gap-1.5" @click="openCreate">
            <Plus class="h-3.5 w-3.5" />
            Nouveau rank
          </Button>
        </div>

        <div
          v-if="ranks.length === 0"
          class="flex min-h-[120px] items-center justify-center rounded-xl border border-dashed border-border bg-card/30 px-6 py-10 text-center text-sm text-muted-foreground"
        >
          Aucun rank pour le moment.
        </div>

        <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
          <RankCard
            v-for="rank in ranks"
            :key="rank.id"
            :project-slug="project.slug"
            :rank="rank"
            :can-edit="canEdit"
            @add-member="openAddMember"
            @set-responsible="openSetResponsible"
          />
        </div>
      </section>

      <RankFormDialog
        v-model:open="formOpen"
        :project-slug="project.slug"
        :rank="editingRank"
      />

      <MemberPickerDialog
        v-model:open="memberPickerOpen"
        :project-slug="project.slug"
        :rank="memberPickerRank"
        :candidates="memberCandidates"
        :mode="memberPickerMode"
        :title="
          memberPickerMode === 'add-member'
            ? 'Ajouter un membre au rank'
            : 'Définir le responsable'
        "
        :submit-label="
          memberPickerMode === 'add-member' ? 'Ajouter' : 'Définir comme responsable'
        "
        :empty-label="
          memberPickerMode === 'add-member'
            ? 'Choisir un membre du projet'
            : 'Choisir parmi les membres du rank'
        "
      />
    </div>
  </AuthenticatedLayout>
</template>

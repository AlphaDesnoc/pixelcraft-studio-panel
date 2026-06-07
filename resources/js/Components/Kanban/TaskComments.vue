<script setup>
import { computed, ref } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { MessageSquare, Send, Trash2 } from "lucide-vue-next";
import { Avatar } from "@/Components/ui/avatar";
import { Button } from "@/Components/ui/button";
import { Textarea } from "@/Components/ui/textarea";

function initials(name) {
  return (name ?? "?")
    .split(" ")
    .map((part) => part.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();
}

const props = defineProps({
  projectSlug: { type: String, required: true },
  taskId: { type: Number, required: true },
  comments: { type: Array, default: () => [] },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const isAdmin = computed(() => Boolean(page.props.auth?.user?.is_admin));

const draft = ref("");
const submitting = ref(false);

const sortedComments = computed(() =>
  [...props.comments].sort(
    (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
  ),
);

function formatTime(iso) {
  if (!iso) return "";
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}

function canDelete(comment) {
  return comment.user?.id === currentUserId.value || isAdmin.value;
}

function submitComment() {
  const body = draft.value.trim();
  if (!body || submitting.value) return;

  submitting.value = true;
  router.post(
    route("projects.tasks.comments.store", [props.projectSlug, props.taskId]),
    { body },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["lists"],
      onFinish: () => {
        submitting.value = false;
        draft.value = "";
      },
    },
  );
}

function deleteComment(comment) {
  if (!confirm("Supprimer ce commentaire ?")) return;
  router.delete(
    route("projects.tasks.comments.destroy", [
      props.projectSlug,
      props.taskId,
      comment.id,
    ]),
    {
      preserveScroll: true,
      preserveState: true,
      only: ["lists"],
    },
  );
}
</script>

<template>
  <section class="flex flex-col gap-3">
    <div class="flex items-center gap-2">
      <MessageSquare class="h-4 w-4 text-muted-foreground" />
      <h3 class="text-sm font-semibold">Commentaires</h3>
      <span class="text-xs text-muted-foreground">({{ comments.length }})</span>
    </div>

    <div v-if="sortedComments.length === 0" class="text-sm text-muted-foreground">
      Aucun commentaire pour l'instant.
    </div>

    <ul v-else class="flex flex-col gap-2">
      <li
        v-for="comment in sortedComments"
        :key="comment.id"
        class="group rounded-md border border-border/60 bg-muted/20 px-3 py-2"
      >
        <div class="flex items-start gap-2">
          <Avatar
            class="mt-0.5 shrink-0"
            size="xs"
            :src="comment.user?.avatar_url ?? ''"
            :fallback="initials(comment.user?.name)"
          />
          <div class="min-w-0 flex-1">
            <p class="text-[11px] font-medium text-muted-foreground">
              {{ comment.user?.name ?? "Utilisateur" }}
              · {{ formatTime(comment.created_at) }}
            </p>
            <p class="mt-1 whitespace-pre-wrap text-sm text-foreground">
              {{ comment.body }}
            </p>
          </div>
          <button
            v-if="canDelete(comment)"
            type="button"
            class="rounded p-1 text-muted-foreground opacity-0 transition-opacity hover:bg-muted hover:text-rose-400 group-hover:opacity-100"
            aria-label="Supprimer le commentaire"
            @click="deleteComment(comment)"
          >
            <Trash2 class="h-3.5 w-3.5" />
          </button>
        </div>
      </li>
    </ul>

    <form class="flex items-end gap-2" @submit.prevent="submitComment">
      <Textarea
        v-model="draft"
        rows="2"
        placeholder="Écrire un commentaire…"
        class="min-h-[44px] flex-1 resize-none text-sm"
        @keydown.enter.exact.prevent="submitComment"
      />
      <Button
        type="submit"
        size="icon"
        class="h-10 w-10 shrink-0"
        :disabled="submitting || !draft.trim()"
      >
        <Send class="h-4 w-4" />
      </Button>
    </form>
  </section>
</template>

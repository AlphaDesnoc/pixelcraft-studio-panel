<script setup>
import { computed, ref, toRef, watch } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { ClipboardPlus, History, Link2, MessageSquare, Send } from "lucide-vue-next";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { Textarea } from "@/Components/ui/textarea";
import OnlineUsersBar from "@/Components/Chat/OnlineUsersBar.vue";
import WaChatBubbleShell from "@/Components/Chat/WaChatBubbleShell.vue";
import ImageLightbox from "@/Components/ImageLightbox.vue";
import { useImageLightbox } from "@/composables/useImageLightbox.js";
import { useBugChat } from "@/composables/useBugChat.js";
import { buildMessageClusters, getMessageCluster } from "@/lib/messageClusters.js";

const props = defineProps({
  open: { type: Boolean, required: true },
  projectSlug: { type: String, required: true },
  bug: { type: Object, default: null },
  priorities: { type: Object, required: true },
  statuses: { type: Object, required: true },
  taskOptions: { type: Array, default: () => [] },
  canManage: { type: Boolean, default: false },
});

const emits = defineEmits(["update:open"]);

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const bugRef = toRef(props, "bug");
const openRef = toRef(props, "open");

const draft = ref("");
const linkTaskId = ref("");
const linkingTask = ref(false);
const spawningTask = ref(false);

watch(
  () => props.bug?.id,
  () => {
    linkTaskId.value = "";
  },
);

const { messages, onlineUsers, loading, sending, send, listRef } = useBugChat(
  props.projectSlug,
  openRef,
  bugRef,
);

const {
  open: lightboxOpen,
  index: lightboxIndex,
  images: lightboxImages,
  openFromUrls: openScreenshotPreview,
} = useImageLightbox();

function previewScreenshot(src) {
  openScreenshotPreview(props.bug?.screenshots ?? [], src);
}

const priorityVariant = computed(() => ({
  low: "secondary",
  medium: "default",
  high: "destructive",
  urgent: "destructive",
})[props.bug?.priority] ?? "secondary");

const statusVariant = computed(() => ({
  open: "default",
  in_progress: "secondary",
  closed: "secondary",
})[props.bug?.status] ?? "secondary");

function formatTime(iso) {
  if (!iso) return "";
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}

function initials(name) {
  return (name ?? "?")
    .split(" ")
    .map((p) => p[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}

const bugMessageClusters = computed(() =>
  buildMessageClusters(messages.value, currentUserId.value),
);

function messageCluster(message) {
  return getMessageCluster(bugMessageClusters.value, message.id);
}

async function submitMessage() {
  if (!draft.value.trim()) return;
  const body = draft.value;
  draft.value = "";
  await send(body);
}

function linkSelectedTask() {
  if (!props.bug?.id || !linkTaskId.value || linkingTask.value) return;
  linkingTask.value = true;
  router.post(
    route("projects.bugs.link-task", [props.projectSlug, props.bug.id]),
    { task_id: Number(linkTaskId.value) },
    {
      preserveScroll: true,
      preserveState: true,
      only: ["bugs", "lists"],
      onFinish: () => {
        linkingTask.value = false;
      },
    },
  );
}

function createTaskFromBug() {
  if (!props.bug?.id || spawningTask.value) return;
  spawningTask.value = true;
  router.post(
    route("projects.bugs.create-task", [props.projectSlug, props.bug.id]),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      only: ["bugs", "lists"],
      onFinish: () => {
        spawningTask.value = false;
      },
    },
  );
}
</script>

<template>
  <Dialog :open="open && Boolean(bug)" @update:open="(v) => emits('update:open', v)">
    <DialogContent class="flex max-h-[90vh] w-full max-w-3xl flex-col gap-0 overflow-hidden p-0">
      <DialogHeader class="border-b border-border px-5 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3 pr-6">
          <div class="min-w-0 flex-1">
            <DialogTitle class="text-left">{{ bug?.title }}</DialogTitle>
            <p
              v-if="bug?.description"
              class="mt-1 whitespace-pre-wrap text-sm text-muted-foreground"
            >
              {{ bug.description }}
            </p>
          </div>
          <div v-if="bug" class="flex shrink-0 items-center gap-1.5">
            <Badge :variant="statusVariant">{{ statuses[bug.status] ?? bug.status }}</Badge>
            <Badge :variant="priorityVariant">
              {{ priorities[bug.priority] ?? bug.priority }}
            </Badge>
          </div>
        </div>

        <div
          v-if="bug?.screenshots?.length"
          class="mt-3 flex flex-wrap gap-2"
        >
          <button
            v-for="(src, idx) in bug.screenshots"
            :key="idx"
            type="button"
            class="block overflow-hidden rounded-md border border-border transition-opacity hover:opacity-90"
            @click="previewScreenshot(src)"
          >
            <img :src="src" alt="" class="h-14 w-14 object-cover" />
          </button>
        </div>

        <p v-if="bug?.reporter" class="mt-2 text-xs text-muted-foreground">
          Signalé par {{ bug.reporter.name }}
          <span v-if="bug.assignee"> · Assigné à {{ bug.assignee.name }}</span>
        </p>

        <div
          v-if="bug && (bug.can_manage || canManage)"
          class="mt-3 space-y-2 rounded-lg border border-border/60 bg-muted/15 px-3 py-2.5"
        >
          <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
            Tâche Kanban
          </p>
          <p v-if="bug.task_id" class="text-xs text-foreground">
            Lié au ticket tâche
            <span class="font-mono font-medium">#{{ bug.task_id }}</span>
          </p>
          <template v-else-if="taskOptions.length">
            <div class="flex flex-wrap items-end gap-2">
              <label class="flex min-w-[200px] flex-1 flex-col gap-1 text-[11px] text-muted-foreground">
                Associer une tâche existante
                <select
                  v-model="linkTaskId"
                  class="h-9 rounded-md border border-input bg-background px-2 text-xs text-foreground outline-none focus:ring-2 focus:ring-ring"
                >
                  <option value="">Choisir…</option>
                  <option
                    v-for="opt in taskOptions"
                    :key="opt.id"
                    :value="String(opt.id)"
                  >
                    {{ opt.title }}{{ opt.list_name ? " — " + opt.list_name : "" }}
                  </option>
                </select>
              </label>
              <Button
                type="button"
                size="sm"
                class="h-9 gap-1.5"
                :disabled="linkingTask || !linkTaskId"
                @click="linkSelectedTask"
              >
                <Link2 class="h-3.5 w-3.5" />
                {{ linkingTask ? "…" : "Lier" }}
              </Button>
              <Button
                type="button"
                variant="outline"
                size="sm"
                class="h-9 gap-1.5"
                :disabled="spawningTask"
                @click="createTaskFromBug"
              >
                <ClipboardPlus class="h-3.5 w-3.5" />
                Créer une tâche
              </Button>
            </div>
          </template>
          <template v-else>
            <Button
              type="button"
              variant="outline"
              size="sm"
              class="h-8 gap-1.5 text-xs"
              :disabled="spawningTask"
              @click="createTaskFromBug"
            >
              <ClipboardPlus class="h-3.5 w-3.5" />
              Créer une tâche depuis ce bug
            </Button>
          </template>
        </div>
      </DialogHeader>

      <section
        v-if="bug?.activity?.length"
        class="border-b border-border px-5 py-3"
      >
        <div class="mb-2 flex items-center gap-2">
          <History class="h-4 w-4 text-muted-foreground" />
          <span class="text-sm font-medium text-foreground">Historique</span>
        </div>
        <ol class="max-h-36 space-y-2 overflow-y-auto">
          <li
            v-for="entry in bug.activity"
            :key="entry.id"
            class="text-xs text-muted-foreground"
          >
            <span class="text-foreground">{{ entry.message }}</span>
            <span v-if="entry.user?.name"> · {{ entry.user.name }}</span>
            <span v-if="entry.created_at"> · {{ formatTime(entry.created_at) }}</span>
          </li>
        </ol>
      </section>

      <section class="flex min-h-0 flex-1 flex-col">
        <div class="flex items-center gap-2 border-b border-border px-5 py-2.5">
          <MessageSquare class="h-4 w-4 text-primary" />
          <span class="text-sm font-medium text-foreground">Discussion</span>
          <span class="text-xs text-muted-foreground">
            Échange entre le reporter et l'équipe de gestion
          </span>
        </div>

        <OnlineUsersBar
          :users="onlineUsers"
          :current-user-id="currentUserId"
          label="Sur ce ticket"
          :show-offline="false"
        />

        <div
          ref="listRef"
          class="wa-chat-messages min-h-[220px] flex-1 overflow-y-auto py-3"
        >
          <div
            v-if="loading"
            class="flex h-full items-center justify-center text-sm text-muted-foreground"
          >
            Chargement des messages…
          </div>
          <div
            v-else-if="messages.length === 0"
            class="flex h-full items-center justify-center text-center text-sm text-muted-foreground"
          >
            Aucun message. Démarrez la conversation avec l'équipe.
          </div>
          <WaChatBubbleShell
            v-for="message in messages"
            :key="message.id"
            :is-mine="messageCluster(message).isMine"
            :cluster-start="messageCluster(message).clusterStart"
            :cluster-end="messageCluster(message).clusterEnd"
            :sender-name="message.user?.name ?? ''"
            :show-sender-name="!messageCluster(message).isMine && messageCluster(message).clusterStart"
            :show-avatar="!messageCluster(message).isMine && messageCluster(message).clusterEnd"
            :avatar-initials="initials(message.user?.name)"
          >
            <p class="whitespace-pre-wrap">{{ message.body }}</p>
            <template #meta>
              <span class="wa-chat-time">{{ formatTime(message.created_at) }}</span>
            </template>
          </WaChatBubbleShell>
        </div>

        <form
          class="flex items-end gap-2 border-t border-border px-5 py-4"
          @submit.prevent="submitMessage"
        >
          <Textarea
            v-model="draft"
            placeholder="Écrire un message…"
            rows="2"
            class="min-h-[44px] flex-1 resize-none"
            @keydown.enter.exact.prevent="submitMessage"
          />
          <Button
            type="submit"
            size="icon"
            class="h-10 w-10 shrink-0"
            :disabled="sending || !draft.trim()"
          >
            <Send class="h-4 w-4" />
          </Button>
        </form>
      </section>
    </DialogContent>
  </Dialog>
  <ImageLightbox
    v-model:open="lightboxOpen"
    v-model:index="lightboxIndex"
    :images="lightboxImages"
  />
</template>

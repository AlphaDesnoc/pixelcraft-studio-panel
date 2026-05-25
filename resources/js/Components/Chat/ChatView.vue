<script setup>
import { computed, ref, toRef } from "vue";
import { usePage } from "@inertiajs/vue3";
import { MessageSquare, Send } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Textarea } from "@/Components/ui/textarea";
import ChatMembersPanel from "@/Components/Chat/ChatMembersPanel.vue";
import { useSpaceChat } from "@/composables/useSpaceChat.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  projectId: { type: Number, required: true },
  spaceKey: { type: String, required: true },
  spaceLabel: { type: String, default: "Global" },
  active: { type: Boolean, default: false },
  initialChatMembers: { type: Array, default: () => [] },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const activeRef = toRef(props, "active");
const spaceKeyRef = toRef(props, "spaceKey");
const initialMembersRef = toRef(props, "initialChatMembers");

const draft = ref("");
const { messages, chatMembers, loading, sending, send, listRef } = useSpaceChat(
  props.projectSlug,
  props.projectId,
  activeRef,
  spaceKeyRef,
  initialMembersRef,
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

function initials(name) {
  return (name ?? "?")
    .split(" ")
    .map((p) => p[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}

async function submitMessage() {
  if (!draft.value.trim()) return;
  const body = draft.value;
  draft.value = "";
  await send(body);
}
</script>

<template>
  <div class="flex min-h-[420px] flex-col overflow-hidden rounded-xl border border-border bg-card md:flex-row">
    <div class="flex min-w-0 flex-1 flex-col">
      <header class="flex items-center gap-2 border-b border-border px-4 py-3">
        <MessageSquare class="h-4 w-4 text-primary" />
        <div>
          <h2 class="text-sm font-semibold text-foreground">Chat — {{ spaceLabel }}</h2>
          <p class="text-xs text-muted-foreground">
            Discussion en temps réel de l'espace
          </p>
        </div>
      </header>

      <div
        ref="listRef"
        class="min-h-[280px] flex-1 space-y-3 overflow-y-auto px-4 py-4"
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
          Aucun message. Lancez la conversation avec votre équipe.
        </div>
        <div
          v-for="message in messages"
          :key="message.id"
          class="flex gap-2.5"
          :class="message.user?.id === currentUserId ? 'flex-row-reverse' : ''"
        >
          <div
            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground"
          >
            {{ initials(message.user?.name) }}
          </div>
          <div
            class="max-w-[75%] rounded-xl px-3 py-2"
            :class="
              message.user?.id === currentUserId
                ? 'bg-primary/15 text-foreground'
                : 'bg-muted/60 text-foreground'
            "
          >
            <p class="text-[11px] font-medium text-muted-foreground">
              {{ message.user?.name }}
              · {{ formatTime(message.created_at) }}
            </p>
            <p class="mt-0.5 whitespace-pre-wrap text-sm">{{ message.body }}</p>
          </div>
        </div>
      </div>

      <form
        class="flex items-end gap-2 border-t border-border px-4 py-3"
        @submit.prevent="submitMessage"
      >
        <Textarea
          v-model="draft"
          placeholder="Écrire un message à l'équipe…"
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
    </div>

    <ChatMembersPanel
      :members="chatMembers"
      :current-user-id="currentUserId"
      :loading="loading && chatMembers.length === 0"
    />
  </div>
</template>

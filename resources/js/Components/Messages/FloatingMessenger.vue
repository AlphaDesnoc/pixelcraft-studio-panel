<script setup>
import { computed, inject, onUnmounted, ref, toRef, watch } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import axios from "axios";
import {
  ArrowLeft,
  Check,
  CheckCheck,
  ExternalLink,
  MessageSquare,
  Minus,
  Paperclip,
  Plus,
  Reply,
  Send,
  X,
} from "lucide-vue-next";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Textarea } from "@/Components/ui/textarea";
import ChatAttachmentImage from "@/Components/Chat/ChatAttachmentImage.vue";
import NewMessageDialog from "@/Components/Messages/NewMessageDialog.vue";
import MentionSuggestions from "@/Components/Chat/MentionSuggestions.vue";
import { useMentionAutocomplete } from "@/composables/useMentionAutocomplete.js";
import { useMessageDraft } from "@/composables/useMessageDraft.js";
import {
  extractMentionUserIds,
  useDirectMessages,
} from "@/composables/useDirectMessages.js";
import {
  floatingMessengerOpen,
  setFloatingMessengerOpen,
  toggleFloatingMessenger,
} from "@/composables/useFloatingMessenger.js";
import {
  isUserOnline,
  setActiveConversationId,
  unreadMessages as globalUnread,
} from "@/composables/useSiteRealtime.js";
import { useSpaceChat } from "@/composables/useSpaceChat.js";
import { isImageAttachment } from "@/lib/attachments.js";
import { renderMessageBody } from "@/lib/twemojiRender.js";

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const currentUserName = computed(() => page.props.auth?.user?.name ?? "Utilisateur");

const hideWidget = computed(() => {
  try {
    return route().current("messages.*");
  } catch {
    return false;
  }
});

const open = floatingMessengerOpen;
const messengerMode = ref("dm");
const projectChatContext = inject("floatingProjectChat", null);
const hasProjectChat = computed(() => Boolean(projectChatContext?.value?.projectSlug));

const view = ref("list");
const selectedId = ref(null);
const pendingRecipientId = ref(null);
const conversations = ref([]);
const contacts = ref([]);
const loadingList = ref(false);
const newDialogOpen = ref(false);
const replyingTo = ref(null);
const draftTextareaRef = ref(null);
const fileInputRef = ref(null);

const conversationIdRef = toRef(selectedId);
const currentUserIdRef = toRef(currentUserId);
const currentUserNameRef = toRef(currentUserName);
const conversationsRef = toRef(conversations);

const projectChatActive = computed(() => open.value && messengerMode.value === "project");
const projectSpaceKeyRef = computed(() => projectChatContext?.value?.spaceKey ?? "global");
const projectMembersRef = computed(() => projectChatContext?.value?.members ?? []);
const projectChatActiveRef = computed(() => projectChatActive.value);

const {
  messages: projectChatMessages,
  loading: projectChatLoading,
  sending: projectChatSending,
  send: sendProjectChat,
  listRef: projectChatListRef,
} = useSpaceChat(
  computed(() => projectChatContext?.value?.projectSlug ?? ""),
  computed(() => projectChatContext?.value?.projectId ?? 0),
  projectChatActiveRef,
  projectSpaceKeyRef,
  projectMembersRef,
  currentUserIdRef,
);

const projectChatDraft = ref("");

async function submitProjectChat() {
  if (!projectChatDraft.value.trim()) return;
  const body = projectChatDraft.value;
  projectChatDraft.value = "";
  await sendProjectChat(body);
}

const draftStorageKey = computed(() =>
  selectedId.value ? `draft:dm:${selectedId.value}` : null,
);
const { draft, clear: clearDraft } = useMessageDraft(draftStorageKey);

const {
  messages: threadMessages,
  loading,
  sending,
  uploading,
  typingUsers,
  send,
  uploadAttachment,
  notifyTyping,
  listRef,
  start,
  leaveConversation,
} = useDirectMessages({
  conversationIdRef,
  currentUserIdRef,
  currentUserNameRef,
  conversationsRef,
});

const mentionCandidatesRef = computed(() => contacts.value ?? []);

const {
  open: draftMentionOpen,
  suggestions: draftMentionSuggestions,
  activeIndex: draftMentionIndex,
  handleInput: handleDraftMentionInput,
  handleKeydown: handleDraftMentionKeydown,
  insertMention: insertDraftMention,
} = useMentionAutocomplete({
  textRef: draft,
  textareaRef: draftTextareaRef,
  candidatesRef: mentionCandidatesRef,
  onInput: notifyTyping,
});

const badgeCount = computed(() => {
  if (open.value && selectedId.value) {
    return conversations.value.reduce(
      (sum, conv) =>
        conv.id === selectedId.value ? sum : sum + (conv.unread_count ?? 0),
      0,
    );
  }
  return globalUnread.value;
});

const activeConversation = computed(() =>
  conversations.value.find((c) => c.id === selectedId.value) ?? null,
);

const composeTargetName = computed(() => {
  if (pendingRecipientId.value) {
    const contact = contacts.value.find((c) => c.id === pendingRecipientId.value);
    return contact?.name ?? "Nouvelle conversation";
  }
  return activeConversation.value?.participant?.name ?? "Messages";
});

const typingLabel = computed(() => {
  const names = typingUsers.value.map((user) => user.name).filter(Boolean);
  if (names.length === 0) return "";
  if (names.length === 1) return `${names[0]} écrit…`;
  return `${names.join(", ")} écrivent…`;
});

const canSend = computed(
  () => Boolean(selectedId.value || pendingRecipientId.value) && draft.value.trim(),
);

watch(open, (value) => {
  if (value) {
    if (messengerMode.value === "dm") {
      loadConversations();
    }
  } else {
    closeThread();
  }
});

watch(selectedId, (id) => {
  setActiveConversationId(open.value ? id : null);
  replyingTo.value = null;
});

onUnmounted(() => {
  leaveConversation();
  setActiveConversationId(null);
});

async function loadConversations() {
  if (loadingList.value) return;
  loadingList.value = true;
  try {
    const { data } = await axios.get(route("messages.conversations.index"));
    conversations.value = data.conversations ?? [];
    contacts.value = data.contacts ?? [];
  } finally {
    loadingList.value = false;
  }
}

function toggleOpen() {
  toggleFloatingMessenger();
}

function closePanel() {
  setFloatingMessengerOpen(false);
}

function closeThread() {
  selectedId.value = null;
  pendingRecipientId.value = null;
  view.value = "list";
  leaveConversation();
  setActiveConversationId(null);
}

async function openConversation(conversationId) {
  selectedId.value = conversationId;
  pendingRecipientId.value = null;
  view.value = "thread";
  conversations.value = conversations.value.map((c) =>
    c.id === conversationId ? { ...c, unread_count: 0 } : c,
  );
  await start(conversationId, []);
}

async function handleNewMessageSelect({ conversationId, recipientId }) {
  if (conversationId) {
    await openConversation(conversationId);
    return;
  }
  pendingRecipientId.value = recipientId;
  selectedId.value = null;
  view.value = "thread";
  leaveConversation();
}

async function submitMessage() {
  if (!draft.value.trim()) return;
  const body = draft.value;
  const mentions = extractMentionUserIds(body, contacts.value);
  const replyToId = replyingTo.value?.id ?? null;
  clearDraft();
  replyingTo.value = null;

  const result = await send(body, selectedId.value, pendingRecipientId.value, {
    reply_to_id: replyToId,
    mentions,
  });

  if (result?.queued) return;

  if (result?.conversation && !selectedId.value) {
    pendingRecipientId.value = null;
    await openConversation(result.conversation.id);
  }
}

function onDraftKeydown(event) {
  if (handleDraftMentionKeydown(event)) return;
  if (event.key === "Enter" && !event.shiftKey) {
    event.preventDefault();
    submitMessage();
  }
}

function initials(name) {
  return (name ?? "?")
    .split(" ")
    .map((p) => p[0])
    .join("")
    .slice(0, 2)
    .toUpperCase();
}

function formatListTime(iso) {
  if (!iso) return "";
  const date = new Date(iso);
  return new Intl.DateTimeFormat("fr-FR", {
    hour: "2-digit",
    minute: "2-digit",
  }).format(date);
}

function formatMessageTime(iso) {
  if (!iso) return "";
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}

function previewText(conv) {
  return conv.last_message?.body ?? "Aucun message";
}

function startReply(message) {
  replyingTo.value = {
    id: message.id,
    label: message.user?.name ?? "Message",
    excerpt: (message.body ?? "").slice(0, 100),
  };
}

async function onFileSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = "";
  if (!file || !selectedId.value) return;
  await uploadAttachment(selectedId.value, file);
}
</script>

<template>
  <div v-if="!hideWidget" class="pointer-events-none fixed bottom-4 right-4 z-50 flex flex-col items-end gap-2">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-2 opacity-0 scale-95"
      enter-to-class="translate-y-0 opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="translate-y-0 opacity-100 scale-100"
      leave-to-class="translate-y-2 opacity-0 scale-95"
    >
      <div
        v-if="open"
        class="pointer-events-auto flex w-[min(100vw-2rem,380px)] flex-col overflow-hidden rounded-xl border border-border bg-card shadow-2xl shadow-black/20"
        style="height: min(520px, calc(100dvh - 6rem))"
      >
        <header class="flex shrink-0 items-center gap-2 border-b border-border bg-card/95 px-3 py-2.5">
          <button
            v-if="view === 'thread'"
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
            aria-label="Retour aux conversations"
            @click="closeThread"
          >
            <ArrowLeft class="h-4 w-4" />
          </button>
          <div class="min-w-0 flex-1">
            <h2 class="truncate text-sm font-semibold">
              {{
                view === "list"
                  ? messengerMode === "project"
                    ? "Chat projet"
                    : "Messages"
                  : composeTargetName
              }}
            </h2>
            <p v-if="view === 'thread' && activeConversation?.participant?.id" class="text-[11px] text-muted-foreground">
              <span
                v-if="isUserOnline(activeConversation.participant.id)"
                class="text-emerald-400"
              >
                En ligne
              </span>
              <span v-else>Hors ligne</span>
            </p>
          </div>
          <div v-if="view === 'list' && hasProjectChat" class="flex shrink-0 gap-0.5 rounded-md border border-border/60 p-0.5">
            <button
              type="button"
              class="rounded px-2 py-0.5 text-[10px] font-medium"
              :class="messengerMode === 'dm' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
              @click="messengerMode = 'dm'"
            >
              MP
            </button>
            <button
              type="button"
              class="rounded px-2 py-0.5 text-[10px] font-medium"
              :class="messengerMode === 'project' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'"
              @click="messengerMode = 'project'"
            >
              Projet
            </button>
          </div>
          <Link
            v-if="selectedId"
            :href="route('messages.index', { c: selectedId })"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
            title="Ouvrir en plein écran"
          >
            <ExternalLink class="h-4 w-4" />
          </Link>
          <button
            type="button"
            class="inline-flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
            aria-label="Réduire"
            @click="closePanel"
          >
            <Minus class="h-4 w-4" />
          </button>
        </header>

        <!-- Liste -->
        <div v-if="view === 'list' && messengerMode === 'project'" class="flex min-h-0 flex-1 flex-col">
          <div ref="projectChatListRef" class="min-h-0 flex-1 space-y-2 overflow-y-auto px-3 py-3">
            <div v-if="projectChatLoading" class="py-8 text-center text-sm text-muted-foreground">
              Chargement…
            </div>
            <div
              v-for="message in projectChatMessages"
              :key="message.id"
              class="rounded-lg bg-muted/50 px-2.5 py-1.5 text-sm"
            >
              <p class="text-[10px] text-muted-foreground">{{ message.user?.name }}</p>
              <div class="chat-message-body" v-html="renderMessageBody(message)" />
            </div>
          </div>
          <form class="flex shrink-0 gap-1.5 border-t border-border px-2 py-2" @submit.prevent="submitProjectChat">
            <Textarea
              v-model="projectChatDraft"
              placeholder="Message espace projet…"
              rows="1"
              class="min-h-[36px] resize-none py-2 text-sm"
            />
            <Button type="submit" size="icon" class="h-9 w-9 shrink-0" :disabled="projectChatSending || !projectChatDraft.trim()">
              <Send class="h-4 w-4" />
            </Button>
          </form>
        </div>

        <div v-else-if="view === 'list'" class="flex min-h-0 flex-1 flex-col">
          <div class="flex items-center justify-between border-b border-border/60 px-3 py-2">
            <span class="text-xs text-muted-foreground">Conversations</span>
            <Button
              type="button"
              size="sm"
              variant="outline"
              class="h-7 gap-1 px-2 text-xs"
              @click="newDialogOpen = true"
            >
              <Plus class="h-3.5 w-3.5" />
              Nouveau
            </Button>
          </div>
          <div class="min-h-0 flex-1 overflow-y-auto">
            <div
              v-if="loadingList"
              class="flex h-32 items-center justify-center text-sm text-muted-foreground"
            >
              Chargement…
            </div>
            <div
              v-else-if="!conversations.length"
              class="px-4 py-8 text-center text-sm text-muted-foreground"
            >
              Aucune conversation.
            </div>
            <button
              v-for="conv in conversations"
              :key="conv.id"
              type="button"
              class="flex w-full items-center gap-3 border-b border-border/40 px-3 py-3 text-left transition-colors hover:bg-muted/40"
              @click="openConversation(conv.id)"
            >
              <span
                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/15 text-xs font-semibold text-primary"
              >
                {{ initials(conv.participant?.name) }}
              </span>
              <div class="min-w-0 flex-1">
                <div class="flex items-center justify-between gap-2">
                  <span class="truncate text-sm font-medium">{{ conv.participant?.name }}</span>
                  <span class="shrink-0 text-[10px] text-muted-foreground">
                    {{ formatListTime(conv.last_message_at) }}
                  </span>
                </div>
                <p class="truncate text-xs text-muted-foreground">{{ previewText(conv) }}</p>
              </div>
              <Badge
                v-if="conv.unread_count > 0"
                variant="default"
                class="h-5 min-w-5 shrink-0 justify-center px-1.5 text-[10px]"
              >
                {{ conv.unread_count }}
              </Badge>
            </button>
          </div>
        </div>

        <!-- Fil -->
        <template v-else>
          <div
            ref="listRef"
            class="min-h-0 flex-1 space-y-2 overflow-y-auto overscroll-y-contain px-3 py-3"
          >
            <div
              v-if="loading"
              class="flex h-full items-center justify-center text-sm text-muted-foreground"
            >
              Chargement…
            </div>
            <div
              v-else-if="!threadMessages.length"
              class="flex h-full items-center justify-center text-center text-sm text-muted-foreground"
            >
              Écrivez le premier message.
            </div>
            <div
              v-for="message in threadMessages"
              :key="message.id"
              class="group flex gap-2"
              :class="message.user?.id === currentUserId ? 'flex-row-reverse' : ''"
            >
              <div
                class="max-w-[85%] rounded-xl px-2.5 py-1.5"
                :class="
                  message.user?.id === currentUserId
                    ? 'bg-primary/15'
                    : 'bg-muted/60'
                "
              >
                <p class="flex items-center gap-1 text-[10px] text-muted-foreground">
                  <span>{{ formatMessageTime(message.created_at) }}</span>
                  <CheckCheck
                    v-if="message.user?.id === currentUserId && message.is_read"
                    class="h-3 w-3 text-primary"
                  />
                  <Check
                    v-else-if="message.user?.id === currentUserId"
                    class="h-3 w-3 text-muted-foreground/70"
                  />
                </p>
                <div
                  v-if="message.reply_preview"
                  class="mb-1 rounded border border-border/50 bg-background/40 px-2 py-1 text-[10px] text-muted-foreground"
                >
                  {{ message.reply_preview.body?.slice(0, 80) }}
                </div>
                <div
                  v-if="message.body?.trim()"
                  class="chat-message-body text-sm"
                  v-html="renderMessageBody(message)"
                />
                <div v-if="message.attachments?.length" class="mt-1 space-y-1">
                  <template v-for="attachment in message.attachments" :key="attachment.id">
                    <ChatAttachmentImage
                      v-if="isImageAttachment(attachment)"
                      :attachment="attachment"
                    />
                    <a
                      v-else
                      :href="attachment.url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="text-xs text-primary hover:underline"
                    >
                      {{ attachment.original_name }}
                    </a>
                  </template>
                </div>
                <button
                  type="button"
                  class="mt-0.5 inline-flex items-center gap-0.5 text-[10px] text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100 hover:text-foreground"
                  @click="startReply(message)"
                >
                  <Reply class="h-3 w-3" />
                  Répondre
                </button>
              </div>
            </div>
          </div>

          <p v-if="typingLabel" class="shrink-0 px-3 pb-1 text-[11px] italic text-muted-foreground">
            {{ typingLabel }}
          </p>

          <div
            v-if="replyingTo"
            class="flex items-center justify-between gap-2 border-t border-border/60 bg-muted/20 px-3 py-1.5 text-[11px]"
          >
            <div class="min-w-0 truncate text-muted-foreground">
              Réponse à {{ replyingTo.label }} — {{ replyingTo.excerpt }}
            </div>
            <button type="button" @click="replyingTo = null">
              <X class="h-3.5 w-3.5" />
            </button>
          </div>

          <form
            class="flex shrink-0 items-end gap-1.5 border-t border-border px-2 py-2"
            @submit.prevent="submitMessage"
          >
            <input ref="fileInputRef" type="file" class="hidden" @change="onFileSelected" />
            <Button
              v-if="selectedId"
              type="button"
              size="icon"
              variant="ghost"
              class="h-9 w-9 shrink-0"
              :disabled="uploading"
              @click="fileInputRef?.click()"
            >
              <Paperclip class="h-4 w-4" />
            </Button>
            <div class="relative min-w-0 flex-1">
              <Textarea
                ref="draftTextareaRef"
                v-model="draft"
                placeholder="Message… (@pseudo)"
                rows="1"
                class="min-h-[36px] max-h-24 resize-none py-2 text-sm"
                @input="handleDraftMentionInput"
                @keydown="onDraftKeydown"
              />
              <MentionSuggestions
                v-if="draftMentionOpen && draftMentionSuggestions.length"
                :suggestions="draftMentionSuggestions"
                :active-index="draftMentionIndex"
                @select="insertDraftMention"
              />
            </div>
            <Button
              type="submit"
              size="icon"
              class="h-9 w-9 shrink-0"
              :disabled="sending || !canSend"
            >
              <Send class="h-4 w-4" />
            </Button>
          </form>
        </template>
      </div>
    </Transition>

    <button
      type="button"
      class="pointer-events-auto relative inline-flex h-14 w-14 items-center justify-center rounded-full border border-border bg-primary text-primary-foreground shadow-lg shadow-primary/25 transition-transform hover:scale-105 active:scale-95"
      :aria-label="open ? 'Fermer les messages' : 'Ouvrir les messages'"
      @click="toggleOpen"
    >
      <MessageSquare class="h-6 w-6" />
      <span
        v-if="badgeCount > 0 && !open"
        class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"
      >
        {{ badgeCount > 99 ? "99+" : badgeCount }}
      </span>
    </button>

    <NewMessageDialog
      v-model:open="newDialogOpen"
      :contacts="contacts"
      :conversations="conversations"
      @select="handleNewMessageSelect"
    />
  </div>
</template>

<style scoped>
.chat-message-body :deep(.twemoji) {
  margin: 0 0.05em;
}
</style>

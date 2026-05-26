<script setup>
import { computed, ref, toRef, watch } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { Mail, MessageSquare, Paperclip, Plus, Reply, Search, Send, Smile, SmilePlus, X } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Textarea } from "@/Components/ui/textarea";
import EmojiPickerPopover from "@/Components/Chat/EmojiPickerPopover.vue";
import TwemojiIcon from "@/Components/Chat/TwemojiIcon.vue";
import NewMessageDialog from "@/Components/Messages/NewMessageDialog.vue";
import MentionSuggestions from "@/Components/Chat/MentionSuggestions.vue";
import { useMentionAutocomplete } from "@/composables/useMentionAutocomplete.js";
import { insertTextAtCursor } from "@/lib/insertTextAtCursor.js";
import { renderMessageBody } from "@/lib/twemojiRender.js";
import {
  extractMentionUserIds,
  useDirectMessages,
} from "@/composables/useDirectMessages.js";
import {
  onlineUsers as siteOnlineUsers,
  siteLive,
  setActiveConversationId,
  setUnreadCount,
  isUserOnline,
  unreadMessages as sidebarUnread,
} from "@/composables/useSiteRealtime.js";

const props = defineProps({
  conversations: { type: Array, default: () => [] },
  contacts: { type: Array, default: () => [] },
  selectedConversationId: { type: Number, default: null },
  selectedConversation: { type: Object, default: null },
  messages: { type: Array, default: () => [] },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const currentUserName = computed(() => page.props.auth?.user?.name ?? "Utilisateur");

const search = ref("");
const draft = ref("");
const draftTextareaRef = ref(null);
const fileInputRef = ref(null);
const draftEmojiOpen = ref(false);
const draftEmojiTriggerRef = ref(null);
const reactionPickerOpen = ref(false);
const reactionPickerMessageId = ref(null);
const reactionTriggerRef = ref(null);
const replyingTo = ref(null);
const newDialogOpen = ref(false);
const pendingRecipientId = ref(null);

const selectedId = ref(props.selectedConversationId);
const localConversations = ref([...props.conversations]);

watch(
  () => props.conversations,
  (incoming) => {
    const byId = new Map(localConversations.value.map((c) => [c.id, c]));
    localConversations.value = incoming.map((conv) => ({
      ...conv,
      unread_count: byId.get(conv.id)?.unread_count ?? conv.unread_count ?? 0,
    }));
  },
);

const conversationIdRef = toRef(selectedId);
const currentUserIdRef = toRef(currentUserId);
const currentUserNameRef = toRef(currentUserName);

const {
  messages: threadMessages,
  loading,
  sending,
  uploading,
  live,
  highlightedIds,
  typingUsers,
  send,
  uploadAttachment,
  notifyTyping,
  listRef,
  start,
  leaveConversation,
  toggleReaction,
} = useDirectMessages({
  conversationIdRef,
  currentUserIdRef,
  currentUserNameRef,
  conversationsRef: localConversations,
});

const mentionCandidatesRef = computed(() => props.contacts ?? []);

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

watch(
  () => props.selectedConversationId,
  (id) => {
    selectedId.value = id;
    pendingRecipientId.value = null;
    if (id) {
      start(id, props.messages);
    }
  },
  { immediate: true },
);

const totalUnread = computed(() =>
  localConversations.value.reduce((sum, c) => sum + (c.unread_count ?? 0), 0),
);

watch(
  totalUnread,
  (count) => setUnreadCount(count),
  { immediate: true },
);

watch(
  selectedId,
  (id) => {
    replyingTo.value = null;
    setActiveConversationId(id);
  },
  { immediate: true },
);

const filteredConversations = computed(() => {
  const q = search.value.trim().toLowerCase();
  const list = localConversations.value;
  if (!q) {
    return list;
  }
  return list.filter((conv) => {
    const p = conv.participant;
    if (!p) return false;
    return (
      p.name.toLowerCase().includes(q) ||
      p.email.toLowerCase().includes(q) ||
      conv.last_message?.body?.toLowerCase().includes(q)
    );
  });
});

const headerUnread = computed(() =>
  Math.max(totalUnread.value, sidebarUnread.value),
);

const activeConversation = computed(() => {
  if (selectedId.value) {
    return localConversations.value.find((c) => c.id === selectedId.value) ?? props.selectedConversation;
  }
  return props.selectedConversation;
});

const onlineCount = computed(() => {
  const ids = new Set(siteOnlineUsers.value.map((u) => u.id));
  return Math.max(0, ids.size - (ids.has(currentUserId.value) ? 1 : 0));
});

const isOtherOnline = computed(() =>
  isUserOnline(activeConversation.value?.participant?.id),
);

const typingLabel = computed(() => {
  const names = typingUsers.value.map((user) => user.name).filter(Boolean);
  if (names.length === 0) return "";
  if (names.length === 1) return `${names[0]} est en train d'écrire…`;
  return `${names.join(", ")} sont en train d'écrire…`;
});

function onDraftInput() {
  handleDraftMentionInput();
}

function onDraftKeydown(event) {
  if (handleDraftMentionKeydown(event)) {
    return;
  }
  if (event.key === "Enter" && !event.shiftKey) {
    event.preventDefault();
    submitMessage();
    return;
  }
  if ((event.ctrlKey || event.metaKey) && event.key === "Enter") {
    event.preventDefault();
    submitMessage();
  }
}

function reactionActive(reaction) {
  if (reaction.me !== undefined) {
    return Boolean(reaction.me);
  }
  const name = currentUserName.value;
  return Boolean(name && reaction.users?.includes(name));
}

function reactionTitle(reaction) {
  const users = reaction.users ?? [];
  if (users.length === 0) {
    return `Réagir avec ${reaction.emoji}`;
  }
  if (users.length <= 3) {
    return `${users.join(", ")} · ${reaction.emoji}`;
  }
  return `${users.slice(0, 3).join(", ")} et ${users.length - 3} autres · ${reaction.emoji}`;
}

async function onToggleReaction(message, emoji) {
  await toggleReaction(message.id, emoji);
}

function openReactionPicker(message, event) {
  reactionPickerMessageId.value = message.id;
  reactionTriggerRef.value = event.currentTarget;
  reactionPickerOpen.value = true;
}

async function onReactionEmojiSelected(emoji) {
  if (!reactionPickerMessageId.value) {
    return;
  }
  await toggleReaction(reactionPickerMessageId.value, emoji);
  reactionPickerMessageId.value = null;
}

function isEmojiOnly(body) {
  const trimmed = body?.trim() ?? "";
  if (!trimmed) {
    return false;
  }
  return !/[\p{L}\p{N}]/u.test(trimmed);
}

function toggleDraftEmojiPicker() {
  draftEmojiOpen.value = !draftEmojiOpen.value;
}

async function onDraftEmojiSelected(emoji) {
  await insertTextAtCursor(draftTextareaRef.value, emoji, draft);
  notifyTyping();
}

function replyPreviewText(preview) {
  if (!preview) return "";
  return (
    preview.body ??
    preview.excerpt ??
    preview.body_snippet ??
    preview.text ??
    ""
  );
}

function replyPreviewAuthor(preview) {
  if (!preview) return "";
  return (
    preview.author_name ??
    preview.user?.name ??
    preview.from_user?.name ??
    ""
  );
}

function isImageAttachment(attachment) {
  if (attachment.mime_type?.startsWith("image/")) {
    return true;
  }
  return /\.(png|jpe?g|gif|webp|bmp|svg)$/i.test(attachment.original_name ?? "");
}

function shouldShowMessageBody(message) {
  const body = message.body?.trim() ?? "";
  if (!body) {
    return false;
  }
  if (message.attachments?.length && body.startsWith("📎 ")) {
    return false;
  }
  return true;
}

function startReply(message) {
  replyingTo.value = {
    id: message.id,
    label: message.user?.name ?? "Message",
    excerpt: (message.body ?? "").slice(0, 140),
  };
}

function clearReply() {
  replyingTo.value = null;
}

function openFilePicker() {
  fileInputRef.value?.click();
}

async function onFileSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = "";
  if (!file || !selectedId.value) return;
  await uploadAttachment(selectedId.value, file);
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
  const now = new Date();
  const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
  if (diffDays === 0) {
    return new Intl.DateTimeFormat("fr-FR", {
      hour: "2-digit",
      minute: "2-digit",
    }).format(date);
  }
  if (diffDays === 1) {
    return "Hier";
  }
  if (diffDays < 7) {
    return new Intl.DateTimeFormat("fr-FR", { weekday: "short" }).format(date);
  }
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
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
  const body = conv.last_message?.body;
  if (!body) return "Aucun message";
  const prefix =
    conv.last_message.user_id === currentUserId.value ? "Vous : " : "";
  return `${prefix}${body}`;
}

function isHighlighted(messageId) {
  return highlightedIds.value.has(messageId);
}

function selectConversation(conversationId) {
  pendingRecipientId.value = null;
  localConversations.value = localConversations.value.map((c) =>
    c.id === conversationId ? { ...c, unread_count: 0 } : c,
  );
  router.get(
    route("messages.index"),
    { c: conversationId },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
      only: ["selectedConversationId", "selectedConversation", "messages"],
    },
  );
}

function handleNewMessageSelect({ conversationId, recipientId }) {
  if (conversationId) {
    selectConversation(conversationId);
    return;
  }
  pendingRecipientId.value = recipientId;
  selectedId.value = null;
  leaveConversation();
  threadMessages.value = [];
}

async function submitMessage() {
  if (!draft.value.trim()) return;
  const body = draft.value;
  const mentions = extractMentionUserIds(body, props.contacts);
  const replyToId = replyingTo.value?.id ?? null;
  draft.value = "";
  clearReply();

  const conversationId = selectedId.value;
  const recipientId = pendingRecipientId.value;

  const result = await send(body, conversationId, recipientId, {
    reply_to_id: replyToId,
    mentions,
  });

  if (result?.conversation && !conversationId) {
    pendingRecipientId.value = null;
    selectedId.value = result.conversation.id;
    await start(result.conversation.id, [result.message]);
    router.get(
      route("messages.index"),
      { c: result.conversation.id },
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ["selectedConversationId", "selectedConversation"],
      },
    );
  }
}

const canSend = computed(
  () => Boolean(selectedId.value || pendingRecipientId.value) && draft.value.trim(),
);

const canAttach = computed(() => Boolean(selectedId.value) && !uploading.value);

const composeTargetName = computed(() => {
  if (activeConversation.value?.participant?.name) {
    return activeConversation.value.participant.name;
  }
  if (pendingRecipientId.value) {
    const contact = props.contacts.find((c) => c.id === pendingRecipientId.value);
    return contact?.name ?? "Contact";
  }
  return null;
});
</script>

<template>
  <Head title="Messages privés" />

  <AuthenticatedLayout>
    <div class="flex min-h-[calc(100vh-4rem)] flex-col gap-4">
      <header class="flex flex-wrap items-start justify-between gap-3">
        <div class="flex items-start gap-3">
          <span
            class="mt-0.5 flex h-10 w-10 items-center justify-center rounded-xl bg-primary/12 text-primary ring-1 ring-primary/20"
          >
            <Mail class="h-5 w-5" />
          </span>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-xl font-semibold tracking-tight">Messages privés</h1>
              <Badge
                v-if="headerUnread > 0"
                class="h-5 min-w-5 rounded-full px-1.5 text-[11px]"
              >
                {{ headerUnread > 9 ? "9+" : headerUnread }}
              </Badge>
            </div>
            <p class="mt-0.5 text-sm text-muted-foreground">
              Conversations privées avec les membres de vos projets
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2">
          <Badge
            variant="outline"
            class="gap-1.5 border-emerald-500/30 bg-emerald-500/10 text-emerald-400"
          >
            <span
              class="h-1.5 w-1.5 rounded-full transition-colors"
              :class="live || siteLive ? 'animate-pulse bg-emerald-400' : 'bg-muted-foreground'"
            />
            En direct
          </Badge>
          <Button class="gap-1.5" @click="newDialogOpen = true">
            <Plus class="h-4 w-4" />
            Nouveau
          </Button>
        </div>
      </header>

      <div
        class="grid min-h-[560px] flex-1 overflow-hidden rounded-xl border border-border bg-card lg:grid-cols-[320px_1fr]"
      >
        <aside class="flex flex-col border-b border-border lg:border-b-0 lg:border-r">
          <div class="border-b border-border px-4 py-3">
            <div class="flex items-center justify-between gap-2">
              <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
                Conversations
              </p>
              <span class="flex items-center gap-1 text-[11px] text-emerald-400">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" />
                {{ onlineCount }} en ligne
              </span>
            </div>
            <div class="relative mt-2">
              <Search
                class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
              />
              <input
                v-model="search"
                type="search"
                placeholder="Rechercher…"
                class="h-9 w-full rounded-md border border-input bg-background pl-8 pr-3 text-sm text-foreground outline-none focus:ring-2 focus:ring-ring"
              />
            </div>
          </div>

          <div class="flex-1 overflow-y-auto p-2">
            <div
              v-if="!filteredConversations.length"
              class="px-2 py-8 text-center text-sm text-muted-foreground"
            >
              Aucune conversation pour le moment.
            </div>
            <button
              v-for="conv in filteredConversations"
              :key="conv.id"
              type="button"
              class="conv-item flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left transition-all"
              :class="[
                selectedId === conv.id
                  ? 'bg-primary/10 ring-1 ring-primary/20'
                  : 'hover:bg-muted/50',
                conv.unread_count > 0 && selectedId !== conv.id
                  ? 'bg-primary/5'
                  : '',
              ]"
              @click="selectConversation(conv.id)"
            >
              <span
                class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/15 text-sm font-semibold text-primary"
              >
                {{ initials(conv.participant?.name) }}
                <span
                  v-if="isUserOnline(conv.participant?.id)"
                  class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-card bg-emerald-400"
                />
              </span>
              <span class="min-w-0 flex-1">
                <span class="flex items-center justify-between gap-2">
                  <span
                    class="truncate text-sm text-foreground"
                    :class="conv.unread_count > 0 ? 'font-semibold' : 'font-medium'"
                  >
                    {{ conv.participant?.name }}
                  </span>
                  <span
                    class="shrink-0 text-[11px]"
                    :class="conv.unread_count > 0 ? 'font-semibold text-primary' : 'text-muted-foreground'"
                  >
                    {{ formatListTime(conv.last_message?.created_at ?? conv.last_message_at) }}
                  </span>
                </span>
                <span class="mt-0.5 flex items-center justify-between gap-2">
                  <span
                    class="block min-w-0 truncate text-xs"
                    :class="
                      conv.unread_count > 0
                        ? 'font-medium text-foreground'
                        : 'text-muted-foreground'
                    "
                  >
                    {{ previewText(conv) }}
                  </span>
                  <span
                    v-if="conv.unread_count > 0"
                    class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-bold text-primary-foreground"
                  >
                    {{ conv.unread_count > 9 ? "9+" : conv.unread_count }}
                  </span>
                </span>
              </span>
            </button>
          </div>
        </aside>

        <section class="flex min-h-[420px] flex-col">
          <template v-if="activeConversation || pendingRecipientId">
            <header class="flex items-center gap-3 border-b border-border px-4 py-3">
              <span
                class="flex h-9 w-9 items-center justify-center rounded-full bg-primary/15 text-xs font-semibold text-primary"
              >
                {{ initials(composeTargetName) }}
              </span>
              <div class="min-w-0 flex-1">
                <h2 class="truncate text-sm font-semibold text-foreground">
                  {{ composeTargetName }}
                </h2>
                <p class="text-xs text-muted-foreground">
                  <template v-if="isOtherOnline">
                    <span class="inline-flex items-center gap-1 text-emerald-400">
                      <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-400" />
                      En ligne
                    </span>
                  </template>
                  <template v-else-if="pendingRecipientId">Nouvelle conversation</template>
                  <template v-else>Hors ligne</template>
                </p>
              </div>
            </header>

            <div
              ref="listRef"
              class="min-h-[320px] flex-1 space-y-3 overflow-y-auto px-4 py-4"
            >
              <div
                v-if="loading"
                class="flex h-full items-center justify-center text-sm text-muted-foreground"
              >
                Chargement des messages…
              </div>
              <div
                v-else-if="!threadMessages.length"
                class="flex h-full items-center justify-center text-center text-sm text-muted-foreground"
              >
                Aucun message. Envoyez le premier !
              </div>
              <div
                v-for="message in threadMessages"
                :key="message.id"
                class="message-row group flex gap-2.5"
                :class="[
                  message.user?.id === currentUserId ? 'flex-row-reverse' : '',
                  isHighlighted(message.id) ? 'message-row--new' : '',
                ]"
              >
                <div
                  class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground"
                >
                  {{ initials(message.user?.name) }}
                </div>
                <div
                  class="message-bubble relative max-w-[75%] rounded-xl px-3 py-2"
                  :class="[
                    message.user?.id === currentUserId
                      ? 'bg-primary/15 text-foreground'
                      : 'bg-muted/60 text-foreground',
                    isHighlighted(message.id) ? 'message-bubble--new' : '',
                  ]"
                >
                  <div
                    class="flex items-start gap-2"
                    :class="message.user?.id === currentUserId ? 'flex-row-reverse text-right' : ''"
                  >
                    <div class="min-w-0 flex-1 space-y-0.5">
                      <p class="text-[11px] font-medium text-muted-foreground">
                        {{ message.user?.name }} · {{ formatMessageTime(message.created_at) }}
                      </p>
                      <div
                        v-if="message.reply_preview"
                        class="rounded-md border border-border/60 bg-background/40 px-2 py-1.5 text-left"
                      >
                        <p class="text-[10px] font-medium text-muted-foreground">
                          {{ replyPreviewAuthor(message.reply_preview) }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                          {{ replyPreviewText(message.reply_preview) }}
                        </p>
                      </div>
                    </div>
                    <button
                      v-if="selectedId"
                      type="button"
                      class="shrink-0 rounded p-0.5 opacity-0 transition-opacity hover:bg-muted/80 hover:text-foreground group-hover:opacity-100"
                      title="Ajouter une réaction"
                      aria-label="Ajouter une réaction"
                      @click.stop="openReactionPicker(message, $event)"
                    >
                      <SmilePlus class="h-3.5 w-3.5" />
                    </button>
                    <button
                      v-if="selectedId"
                      type="button"
                      class="shrink-0 rounded p-0.5 opacity-0 transition-opacity hover:bg-muted/80 hover:text-foreground group-hover:opacity-100"
                      title="Répondre"
                      @click.stop="startReply(message)"
                    >
                      <Reply class="h-3.5 w-3.5" />
                    </button>
                  </div>
                  <div
                    v-if="shouldShowMessageBody(message)"
                    class="dm-message-body mt-0.5 text-left"
                    :class="isEmojiOnly(message.body) ? 'dm-message-body--emoji-only' : 'text-sm'"
                    v-html="renderMessageBody(message)"
                  />

                  <div
                    v-if="message.attachments?.length"
                    class="mt-2 flex flex-col gap-2"
                  >
                    <template v-for="attachment in message.attachments" :key="attachment.id">
                      <a
                        v-if="!isImageAttachment(attachment)"
                        :href="attachment.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        download
                        class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                      >
                        <Paperclip class="h-3 w-3" />
                        {{ attachment.original_name }}
                      </a>
                      <a
                        v-else
                        :href="attachment.url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block overflow-hidden rounded-md border border-border/60"
                      >
                        <img
                          :src="attachment.url"
                          :alt="attachment.original_name"
                          class="max-h-48 max-w-full object-cover"
                        />
                      </a>
                    </template>
                  </div>

                  <div
                    v-if="selectedId"
                    class="mt-1.5 flex flex-wrap items-center gap-1 opacity-80 transition-opacity group-hover:opacity-100"
                  >
                    <button
                      v-for="reaction in message.reactions ?? []"
                      :key="`${message.id}-${reaction.emoji}`"
                      type="button"
                      class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-sm leading-none transition-colors hover:bg-muted/80"
                      :class="
                        reactionActive(reaction)
                          ? 'border-primary/40 bg-primary/15'
                          : 'border-border/60 bg-background/40'
                      "
                      :title="reactionTitle(reaction)"
                      @click="onToggleReaction(message, reaction.emoji)"
                    >
                      <TwemojiIcon :emoji="reaction.emoji" size="reaction" />
                      <span
                        v-if="(reaction.count ?? reaction.users?.length ?? 0) > 1"
                        class="text-[10px] font-semibold tabular-nums text-muted-foreground"
                      >
                        {{ reaction.count ?? reaction.users?.length }}
                      </span>
                    </button>
                    <button
                      type="button"
                      class="inline-flex h-7 w-7 items-center justify-center rounded-full border border-dashed border-border/70 text-muted-foreground hover:border-border hover:bg-muted/60 hover:text-foreground"
                      aria-label="Ajouter une réaction"
                      title="Ajouter une réaction"
                      @click="openReactionPicker(message, $event)"
                    >
                      <SmilePlus class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <p
              v-if="typingLabel && selectedId"
              class="shrink-0 px-4 pb-1 text-xs italic text-muted-foreground"
            >
              {{ typingLabel }}
            </p>

            <div
              v-if="replyingTo && selectedId"
              class="flex items-center justify-between gap-2 border-t border-border/60 bg-muted/20 px-4 py-2 text-xs"
            >
              <div class="min-w-0">
                <p class="font-medium text-foreground">Réponse à {{ replyingTo.label }}</p>
                <p class="truncate text-muted-foreground">{{ replyingTo.excerpt }}</p>
              </div>
              <button
                type="button"
                class="rounded p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label="Annuler la réponse"
                @click="clearReply"
              >
                <X class="h-4 w-4" />
              </button>
            </div>

            <form
              class="flex items-end gap-2 border-t border-border px-4 py-3"
              @submit.prevent="submitMessage"
            >
              <input
                ref="fileInputRef"
                type="file"
                class="hidden"
                @change="onFileSelected"
              />
              <Button
                type="button"
                size="icon"
                variant="outline"
                class="h-10 w-10 shrink-0"
                :disabled="!canAttach"
                aria-label="Joindre un fichier"
                @click="openFilePicker"
              >
                <Paperclip class="h-4 w-4" />
              </Button>
              <span ref="draftEmojiTriggerRef" class="inline-flex shrink-0">
                <Button
                  type="button"
                  size="icon"
                  variant="outline"
                  class="h-10 w-10"
                  aria-label="Insérer un emoji"
                  title="Emoji"
                  @click="toggleDraftEmojiPicker"
                >
                  <Smile class="h-4 w-4" />
                </Button>
              </span>
              <div class="relative min-w-0 flex-1">
                <Textarea
                  ref="draftTextareaRef"
                  v-model="draft"
                  placeholder="Écrire un message… (@pseudo pour mentionner)"
                  rows="2"
                  class="min-h-[44px] w-full resize-none"
                  @input="onDraftInput"
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
                class="h-10 w-10 shrink-0"
                :disabled="sending || !canSend"
              >
                <Send class="h-4 w-4" />
              </Button>
            </form>
          </template>

          <div
            v-else
            class="flex flex-1 flex-col items-center justify-center px-6 py-12 text-center"
          >
            <span
              class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-muted/40 text-muted-foreground"
            >
              <MessageSquare class="h-8 w-8" />
            </span>
            <p class="text-sm font-medium text-foreground">
              Aucune conversation sélectionnée
            </p>
            <p class="mt-1 max-w-sm text-xs text-muted-foreground">
              Choisissez un contact dans la liste ou démarrez une nouvelle conversation
            </p>
            <Button
              variant="outline"
              class="mt-4 gap-1.5"
              @click="newDialogOpen = true"
            >
              <MessageSquare class="h-4 w-4" />
              Nouveau message
            </Button>
          </div>
        </section>
      </div>
    </div>

    <NewMessageDialog
      v-model:open="newDialogOpen"
      :contacts="contacts"
      :conversations="localConversations"
      @select="handleNewMessageSelect"
    />

    <EmojiPickerPopover
      v-model:open="draftEmojiOpen"
      :trigger-ref="draftEmojiTriggerRef"
      placement="top"
      @select="onDraftEmojiSelected"
    />
    <EmojiPickerPopover
      v-model:open="reactionPickerOpen"
      :trigger-ref="reactionTriggerRef"
      placement="top"
      @select="onReactionEmojiSelected"
    />
  </AuthenticatedLayout>
</template>

<style scoped>
.message-row--new {
  animation: message-slide-in 0.32s ease-out;
}

.message-bubble--new {
  animation: message-glow 2.6s ease-out;
}

@keyframes message-slide-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes message-glow {
  0% {
    box-shadow: 0 0 0 0 rgb(124 92 255 / 0.45);
  }
  15% {
    box-shadow: 0 0 0 4px rgb(124 92 255 / 0.25);
  }
  100% {
    box-shadow: 0 0 0 0 rgb(124 92 255 / 0);
  }
}

.dm-message-body :deep(span.rounded) {
  display: inline;
}

.dm-message-body :deep(.twemoji) {
  margin: 0 0.05em;
}

.dm-message-body--emoji-only :deep(.twemoji) {
  height: 2rem;
  width: 2rem;
}

.conv-item {
  transition:
    background-color 0.2s ease,
    transform 0.2s ease;
}
</style>

<script setup>
import { computed, ref, toRef } from "vue";
import { usePage } from "@inertiajs/vue3";
import {
  MessageSquare,
  Paperclip,
  Pencil,
  Pin,
  PinOff,
  Reply,
  Send,
  Smile,
  SmilePlus,
  Trash2,
  X,
} from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Textarea } from "@/Components/ui/textarea";
import ChatChannelsSidebar from "@/Components/Chat/ChatChannelsSidebar.vue";
import ChatMembersPanel from "@/Components/Chat/ChatMembersPanel.vue";
import ChatAttachmentImage from "@/Components/Chat/ChatAttachmentImage.vue";
import ChatMediaAttachment from "@/Components/Chat/ChatMediaAttachment.vue";
import ChatSearchBar from "@/Components/Chat/ChatSearchBar.vue";
import EmojiPickerPopover from "@/Components/Chat/EmojiPickerPopover.vue";
import MentionSuggestions from "@/Components/Chat/MentionSuggestions.vue";
import TwemojiIcon from "@/Components/Chat/TwemojiIcon.vue";
import WaChatBubbleShell from "@/Components/Chat/WaChatBubbleShell.vue";
import ImageLightbox from "@/Components/ImageLightbox.vue";
import { useImageLightbox } from "@/composables/useImageLightbox.js";
import { useMessageDraft } from "@/composables/useMessageDraft.js";
import { useMentionAutocomplete } from "@/composables/useMentionAutocomplete.js";
import { useSpaceChat } from "@/composables/useSpaceChat.js";
import { isImageAttachment, isPdfAttachment, isVideoAttachment } from "@/lib/attachments.js";
import { insertTextAtCursor } from "@/lib/insertTextAtCursor.js";
import { buildMessageClusters, getMessageCluster } from "@/lib/messageClusters.js";
import { escapeHtml, parseEmojis, renderMessageBody } from "@/lib/twemojiRender.js";

const props = defineProps({
  projectSlug: { type: String, required: true },
  projectId: { type: Number, required: true },
  spaceKey: { type: String, required: true },
  spaceLabel: { type: String, default: "Global" },
  active: { type: Boolean, default: false },
  initialChatMembers: { type: Array, default: () => [] },
  chatRankMentions: { type: Array, default: () => [] },
  channels: { type: Array, default: () => [] },
});

const emit = defineEmits(["select-channel"]);

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const currentUserName = computed(() => page.props.auth?.user?.name ?? "");
const activeRef = toRef(props, "active");
const spaceKeyRef = toRef(props, "spaceKey");
const initialMembersRef = toRef(props, "initialChatMembers");

const draftStorageKey = computed(
  () => `draft:chat:${props.projectSlug}:${props.spaceKey}`,
);
const { draft, clear: clearDraft } = useMessageDraft(draftStorageKey);

const chatSearch = ref({});
const editingMessageId = ref(null);
const editDraft = ref("");
const fileInputRef = ref(null);
const draftTextareaRef = ref(null);
const editTextareaRef = ref(null);
const replyingTo = ref(null);
const draftEmojiOpen = ref(false);
const draftEmojiTriggerRef = ref(null);
const reactionPickerOpen = ref(false);
const reactionPickerMessageId = ref(null);
const reactionTriggerRef = ref(null);

const {
  messages,
  chatMembers,
  loading,
  sending,
  uploading,
  typingUsers,
  send,
  updateMessage,
  deleteMessage,
  uploadAttachment,
  notifyTyping,
  applySearchFilters,
  listRef,
  toggleReaction,
  pinMessage,
} = useSpaceChat(
  props.projectSlug,
  props.projectId,
  activeRef,
  spaceKeyRef,
  initialMembersRef,
  currentUserId,
);

const {
  open: lightboxOpen,
  index: lightboxIndex,
  images: lightboxImages,
  openFromMessages: openImagePreview,
} = useImageLightbox();

const chatMembersRef = computed(() => chatMembers.value);

const mentionCandidatesRef = computed(() => {
  const members = (chatMembersRef.value ?? []).map((member) => ({
    ...member,
    type: "user",
  }));

  if (props.spaceKey !== "global") {
    return members;
  }

  const ranks = (props.chatRankMentions ?? []).map((rank) => ({
    type: "rank",
    id: rank.id,
    slug: rank.slug,
    name: rank.name,
    color: rank.color,
  }));

  return [...ranks, ...members];
});

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

const {
  open: editMentionOpen,
  suggestions: editMentionSuggestions,
  activeIndex: editMentionIndex,
  handleInput: handleEditMentionInput,
  handleKeydown: handleEditMentionKeydown,
  insertMention: insertEditMention,
} = useMentionAutocomplete({
  textRef: editDraft,
  textareaRef: editTextareaRef,
  candidatesRef: mentionCandidatesRef,
});

const typingLabel = computed(() => {
  const names = typingUsers.value.map((user) => user.name).filter(Boolean);
  if (names.length === 0) return "";
  if (names.length === 1) return `${names[0]} est en train d'écrire…`;
  if (names.length === 2) {
    return `${names[0]} et ${names[1]} sont en train d'écrire…`;
  }
  return `${names.length} personnes sont en train d'écrire…`;
});

const pinnedMessages = computed(() =>
  messages.value.filter((message) => message.pinned_at),
);

const regularMessages = computed(() =>
  messages.value.filter((message) => !message.pinned_at),
);

const regularMessageClusters = computed(() =>
  buildMessageClusters(regularMessages.value, currentUserId.value),
);

const pinnedMessageClusters = computed(() =>
  buildMessageClusters(pinnedMessages.value, currentUserId.value),
);

function messageCluster(message, pinned = false) {
  const clusters = pinned ? pinnedMessageClusters.value : regularMessageClusters.value;
  return getMessageCluster(clusters, message.id);
}

const draftPreviewHtml = computed(() => {
  const text = draft.value.trim();
  if (!text) return "";
  return parseEmojis(escapeHtml(text).replace(/\n/g, "<br>"));
});

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

function previewAttachment(message, attachment) {
  openImagePreview(messages.value, attachment);
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

function isEmojiOnly(body) {
  const trimmed = body?.trim() ?? "";
  if (!trimmed) {
    return false;
  }
  return !/[\p{L}\p{N}]/u.test(trimmed);
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

function replyPreviewText(preview) {
  if (!preview) return "";
  return preview.body ?? preview.excerpt ?? preview.body_snippet ?? preview.text ?? "";
}

function replyPreviewAuthor(preview) {
  if (!preview) return "";
  return preview.author_name ?? preview.user?.name ?? preview.from_user?.name ?? "";
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

async function onToggleReaction(message, emoji) {
  await toggleReaction(message.id, emoji);
}

async function onPinMessage(message) {
  await pinMessage(message.id);
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

async function onDraftEmojiSelected(emoji) {
  await insertTextAtCursor(draftTextareaRef.value, emoji, draft);
  notifyTyping();
}

function toggleDraftEmojiPicker() {
  draftEmojiOpen.value = !draftEmojiOpen.value;
}

async function submitMessage() {
  if (!draft.value.trim()) return;
  const body = draft.value;
  const replyId = replyingTo.value?.id ?? null;
  clearDraft();
  clearReply();
  await send(body, replyId);
}

function onSearch(filters) {
  applySearchFilters(filters);
}

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

function onEditKeydown(event) {
  if (handleEditMentionKeydown(event)) {
    return;
  }
}

function startEdit(message) {
  editingMessageId.value = message.id;
  editDraft.value = message.body ?? "";
}

function cancelEdit() {
  editingMessageId.value = null;
  editDraft.value = "";
}

async function saveEdit(message) {
  const body = editDraft.value.trim();
  if (!body) return;
  await updateMessage(message.id, body);
  cancelEdit();
}

async function confirmDelete(message) {
  if (!confirm("Supprimer ce message ?")) return;
  await deleteMessage(message.id);
}

function openFilePicker() {
  fileInputRef.value?.click();
}

async function onFileSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = "";
  if (!file) return;
  await uploadAttachment(file);
}
</script>

<template>
  <div class="flex h-[min(620px,calc(100dvh-12rem))] min-h-[420px] flex-col overflow-hidden rounded-xl border border-border bg-card">
    <header class="shrink-0 border-b border-border px-4 py-3">
      <div class="flex items-center gap-2">
        <MessageSquare class="h-4 w-4 text-primary" />
        <div>
          <h2 class="text-sm font-semibold text-foreground">Chat — {{ spaceLabel }}</h2>
          <p class="text-xs text-muted-foreground">
            Discussion en temps réel de l'espace
          </p>
        </div>
      </div>
    </header>

    <div class="flex min-h-0 flex-1 overflow-hidden">
      <ChatChannelsSidebar
        v-if="channels.length > 1"
        :channels="channels"
        :active-key="spaceKey"
        @select="emit('select-channel', $event)"
      />
      <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <ChatSearchBar
          v-model="chatSearch"
          :members="chatMembers"
          @search="onSearch"
        />

        <div
          ref="listRef"
          class="wa-chat-messages min-h-0 flex-1 overflow-y-auto overscroll-y-contain py-3"
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

          <section
            v-if="pinnedMessages.length"
            class="mx-2 mb-2 space-y-0.5 rounded-lg border border-amber-500/20 bg-amber-500/5 p-2"
          >
            <p class="flex items-center gap-1 px-1 pb-1 text-[10px] font-semibold uppercase tracking-wide text-amber-400">
              <Pin class="h-3 w-3" />
              Messages épinglés
            </p>
            <WaChatBubbleShell
              v-for="message in pinnedMessages"
              :key="`pinned-${message.id}`"
              :is-mine="messageCluster(message, true).isMine"
              :cluster-start="messageCluster(message, true).clusterStart"
              :cluster-end="messageCluster(message, true).clusterEnd"
              :sender-name="message.user?.name ?? ''"
              :show-sender-name="!messageCluster(message, true).isMine && messageCluster(message, true).clusterStart"
              :show-avatar="!messageCluster(message, true).isMine && messageCluster(message, true).clusterEnd"
              :avatar-initials="initials(message.user?.name)"
              pinned
            >
              <div
                v-if="shouldShowMessageBody(message)"
                class="chat-message-body"
                v-html="renderMessageBody(message)"
              />
              <template #meta>
                <span class="wa-chat-time">{{ formatTime(message.created_at) }}</span>
                <button
                  type="button"
                  class="rounded p-0.5 text-muted-foreground hover:text-foreground"
                  title="Désépingler"
                  @click="onPinMessage(message)"
                >
                  <PinOff class="h-3 w-3" />
                </button>
              </template>
            </WaChatBubbleShell>
          </section>

          <WaChatBubbleShell
            v-for="message in regularMessages"
            :key="message.id"
            :is-mine="messageCluster(message).isMine"
            :cluster-start="messageCluster(message).clusterStart"
            :cluster-end="messageCluster(message).clusterEnd"
            :sender-name="message.user?.name ?? ''"
            :show-sender-name="!messageCluster(message).isMine && messageCluster(message).clusterStart"
            :show-avatar="!messageCluster(message).isMine && messageCluster(message).clusterEnd"
            :avatar-initials="initials(message.user?.name)"
          >
            <template #toolbar>
              <button
                type="button"
                class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label="Ajouter une réaction"
                title="Ajouter une réaction"
                @click="openReactionPicker(message, $event)"
              >
                <SmilePlus class="h-3 w-3" />
              </button>
              <button
                type="button"
                class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                aria-label="Répondre"
                title="Répondre"
                @click="startReply(message)"
              >
                <Reply class="h-3 w-3" />
              </button>
              <button
                type="button"
                class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                :aria-label="message.pinned_at ? 'Désépingler' : 'Épingler'"
                :title="message.pinned_at ? 'Désépingler' : 'Épingler'"
                @click="onPinMessage(message)"
              >
                <PinOff v-if="message.pinned_at" class="h-3 w-3" />
                <Pin v-else class="h-3 w-3" />
              </button>
              <template v-if="message.can_edit && editingMessageId !== message.id">
                <button
                  type="button"
                  class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                  aria-label="Modifier"
                  @click="startEdit(message)"
                >
                  <Pencil class="h-3 w-3" />
                </button>
                <button
                  type="button"
                  class="rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-rose-400"
                  aria-label="Supprimer"
                  @click="confirmDelete(message)"
                >
                  <Trash2 class="h-3 w-3" />
                </button>
              </template>
            </template>

            <template
              v-if="message.reply_preview && editingMessageId !== message.id"
              #reply
            >
              <div class="wa-chat-reply">
                <p class="wa-chat-reply-author">
                  {{ replyPreviewAuthor(message.reply_preview) }}
                </p>
                <p class="wa-chat-reply-body">
                  {{ replyPreviewText(message.reply_preview) }}
                </p>
              </div>
            </template>

            <div v-if="editingMessageId === message.id" class="space-y-2">
              <div class="relative">
                <Textarea
                  :ref="
                    editingMessageId === message.id
                      ? (el) => (editTextareaRef = el)
                      : undefined
                  "
                  v-model="editDraft"
                  rows="2"
                  class="min-h-[44px] resize-none text-sm"
                  @input="handleEditMentionInput"
                  @keydown="onEditKeydown"
                />
                <MentionSuggestions
                  v-if="editMentionOpen && editMentionSuggestions.length"
                  :suggestions="editMentionSuggestions"
                  :active-index="editMentionIndex"
                  @select="insertEditMention"
                />
              </div>
              <div class="flex items-center gap-2">
                <Button
                  type="button"
                  size="sm"
                  class="h-7"
                  :disabled="sending || !editDraft.trim()"
                  @click="saveEdit(message)"
                >
                  Enregistrer
                </Button>
                <button
                  type="button"
                  class="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                  @click="cancelEdit"
                >
                  <X class="h-3 w-3" />
                  Annuler
                </button>
              </div>
            </div>
            <div
              v-else-if="shouldShowMessageBody(message)"
              class="chat-message-body"
              :class="isEmojiOnly(message.body) ? 'chat-message-body--emoji-only' : ''"
              v-html="renderMessageBody(message)"
            />

            <div v-if="message.attachments?.length" class="mt-1.5 flex flex-col gap-2">
              <template v-for="attachment in message.attachments" :key="attachment.id">
                <ChatMediaAttachment
                  v-if="!isImageAttachment(attachment) && (isVideoAttachment(attachment) || isPdfAttachment(attachment))"
                  :attachment="attachment"
                />
                <a
                  v-else-if="!isImageAttachment(attachment)"
                  :href="attachment.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  download
                  class="inline-flex items-center gap-1 text-xs text-primary hover:underline"
                >
                  <Paperclip class="h-3 w-3" />
                  {{ attachment.original_name }}
                </a>
                <ChatAttachmentImage
                  v-else
                  :attachment="attachment"
                  @preview="previewAttachment(message, $event)"
                />
              </template>
            </div>

            <template v-if="editingMessageId !== message.id" #meta>
              <span v-if="message.edited_at" class="wa-chat-edited">modifié</span>
              <span class="wa-chat-time">{{ formatTime(message.created_at) }}</span>
            </template>

            <template v-if="editingMessageId !== message.id" #after>
              <button
                v-for="reaction in message.reactions ?? []"
                :key="`${message.id}-${reaction.emoji}`"
                type="button"
                class="inline-flex items-center gap-1 rounded-full border border-border/60 bg-card/90 px-2 py-0.5 text-sm leading-none shadow-sm transition-colors hover:bg-muted/80"
                :class="reactionActive(reaction) ? 'border-primary/40 bg-primary/10' : ''"
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
            </template>
          </WaChatBubbleShell>
        </div>

        <p
          v-if="typingLabel"
          class="shrink-0 px-4 pb-1 text-xs italic text-muted-foreground"
        >
          {{ typingLabel }}
        </p>

        <div
          v-if="replyingTo"
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
          class="flex shrink-0 items-end gap-2 border-t border-border px-4 py-3"
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
            :disabled="uploading"
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
              :placeholder="
                spaceKey === 'global'
                  ? 'Écrire un message… Markdown (**gras**, *italique*) · @pseudo ou @rank · Ctrl+Entrée pour envoyer'
                  : 'Écrire un message… Markdown (**gras**, *italique*) · @pseudo · Ctrl+Entrée pour envoyer'
              "
              rows="2"
              class="min-h-[44px] w-full resize-none"
              @input="onDraftInput"
              @keydown="onDraftKeydown"
            />
            <div
              v-if="draftPreviewHtml"
              class="chat-draft-preview mt-1 rounded-md border border-border/60 bg-muted/20 px-2 py-1.5 text-left text-sm text-foreground"
              v-html="draftPreviewHtml"
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
    <ImageLightbox
      v-model:open="lightboxOpen"
      v-model:index="lightboxIndex"
      :images="lightboxImages"
    />
  </div>
</template>

<style scoped>
.chat-message-body :deep(span.rounded) {
  display: inline;
}

.chat-message-body :deep(.twemoji) {
  margin: 0 0.05em;
}

.chat-message-body--emoji-only :deep(.twemoji) {
  height: 2rem;
  width: 2rem;
}

.chat-draft-preview :deep(.twemoji) {
  margin: 0 0.05em;
}
</style>

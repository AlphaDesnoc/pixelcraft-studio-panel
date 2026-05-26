<script setup>
import { computed, ref, toRef } from "vue";
import { usePage } from "@inertiajs/vue3";
import {
  MessageSquare,
  Paperclip,
  Pencil,
  Reply,
  Send,
  Smile,
  SmilePlus,
  Trash2,
  X,
} from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Textarea } from "@/Components/ui/textarea";
import ChatMembersPanel from "@/Components/Chat/ChatMembersPanel.vue";
import EmojiPickerPopover from "@/Components/Chat/EmojiPickerPopover.vue";
import MentionSuggestions from "@/Components/Chat/MentionSuggestions.vue";
import TwemojiIcon from "@/Components/Chat/TwemojiIcon.vue";
import { useMentionAutocomplete } from "@/composables/useMentionAutocomplete.js";
import { useSpaceChat } from "@/composables/useSpaceChat.js";
import { insertTextAtCursor } from "@/lib/insertTextAtCursor.js";
import { renderMessageBody } from "@/lib/twemojiRender.js";

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
const currentUserName = computed(() => page.props.auth?.user?.name ?? "");
const activeRef = toRef(props, "active");
const spaceKeyRef = toRef(props, "spaceKey");
const initialMembersRef = toRef(props, "initialChatMembers");

const draft = ref("");
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
  listRef,
  toggleReaction,
} = useSpaceChat(
  props.projectSlug,
  props.projectId,
  activeRef,
  spaceKeyRef,
  initialMembersRef,
  currentUserId,
);

const chatMembersRef = computed(() => chatMembers.value);

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
  candidatesRef: chatMembersRef,
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
  candidatesRef: chatMembersRef,
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
  draft.value = "";
  clearReply();
  await send(body, replyId);
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
  <div class="flex h-[620px] flex-col overflow-hidden rounded-xl border border-border bg-card">
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
      <div class="flex min-h-0 min-w-0 flex-1 flex-col overflow-hidden">
        <div
          ref="listRef"
          class="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4"
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
            class="group flex gap-2.5"
            :class="message.user?.id === currentUserId ? 'flex-row-reverse' : ''"
          >
            <div
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold text-muted-foreground"
            >
              {{ initials(message.user?.name) }}
            </div>
            <div
              class="relative max-w-[75%] rounded-xl px-3 py-2"
              :class="
                message.user?.id === currentUserId
                  ? 'bg-primary/15 text-foreground'
                  : 'bg-muted/60 text-foreground'
              "
            >
              <div
                class="flex items-start gap-2"
                :class="message.user?.id === currentUserId ? 'flex-row-reverse' : ''"
              >
                <div class="min-w-0 flex-1 space-y-1">
                  <p class="text-[11px] font-medium text-muted-foreground">
                    {{ message.user?.name }}
                    · {{ formatTime(message.created_at) }}
                    <span v-if="message.edited_at" class="italic">(modifié)</span>
                  </p>
                  <div
                    v-if="message.reply_preview && editingMessageId !== message.id"
                    class="rounded-md border border-border/60 bg-background/35 px-2 py-1.5"
                  >
                    <p class="text-[10px] font-medium text-muted-foreground">
                      {{ replyPreviewAuthor(message.reply_preview) }}
                    </p>
                    <p class="line-clamp-2 text-xs text-muted-foreground">
                      {{ replyPreviewText(message.reply_preview) }}
                    </p>
                  </div>
                </div>
                <div
                  class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity group-hover:opacity-100"
                >
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
                </div>
              </div>

              <div v-if="editingMessageId === message.id" class="mt-1 space-y-2">
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
                class="chat-message-body mt-0.5 text-left"
                :class="isEmojiOnly(message.body) ? 'chat-message-body--emoji-only' : 'text-sm'"
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
                v-if="editingMessageId !== message.id"
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
              placeholder="Écrire un message à l'équipe… (@pseudo pour mentionner)"
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
</style>

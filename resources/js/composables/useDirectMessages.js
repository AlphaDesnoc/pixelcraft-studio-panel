import { nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import axios from "axios";
import { bindPresenceHandlers } from "@/lib/presence.js";
import { onDirectMessage } from "@/composables/useSiteRealtime.js";
import { memberPseudo } from "@/composables/useMentionAutocomplete.js";
import {
  enqueuePendingMessage,
  isOfflineError,
  listPendingMessages,
} from "@/lib/offlineMessageQueue.js";

const POLL_MS = 2000;
const HIGHLIGHT_MS = 2600;
const TYPING_TTL_MS = 3000;
const WHISPER_DEBOUNCE_MS = 400;

function sortMessages(list) {
  return [...list].sort(
    (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
  );
}

function sortConversations(list) {
  return [...list].sort((a, b) => {
    const aTime = new Date(a.last_message?.created_at ?? a.last_message_at ?? 0).getTime();
    const bTime = new Date(b.last_message?.created_at ?? b.last_message_at ?? 0).getTime();
    return bTime - aTime;
  });
}

function messagesForConversation(list, conversationId) {
  if (!conversationId) {
    return [];
  }
  return (list ?? []).filter(
    (message) =>
      Number(message.direct_conversation_id) === Number(conversationId),
  );
}

function mergeMessages(existing, incoming, conversationId) {
  const scopedExisting = messagesForConversation(existing, conversationId);
  const scopedIncoming = messagesForConversation(incoming, conversationId);

  if (scopedIncoming.length === 0) {
    return scopedExisting;
  }

  const lastExisting = scopedExisting[scopedExisting.length - 1]?.id;
  const lastIncoming = scopedIncoming[scopedIncoming.length - 1]?.id;
  if (
    scopedExisting.length === scopedIncoming.length &&
    lastExisting === lastIncoming
  ) {
    return scopedExisting;
  }

  const byId = new Map(scopedExisting.map((m) => [m.id, m]));
  for (const message of scopedIncoming) {
    byId.set(message.id, message);
  }

  return sortMessages([...byId.values()]);
}

function participantFromInbox(inbox, currentUserId) {
  return (
    inbox.participants?.find((p) => p.id !== currentUserId) ??
    inbox.participant ??
    null
  );
}

/** @returns {number[]} unique user IDs for @pseudo matches in contacts */
export function extractMentionUserIds(text, contacts) {
  const byPseudo = new Map();
  for (const c of contacts ?? []) {
    const p = memberPseudo({ email: c.email, pseudo: c.pseudo })
      .trim()
      .toLowerCase();
    if (p) {
      byPseudo.set(p, c.id);
    }
  }
  const ids = new Set();
  const re = /@([a-z0-9._-]+)/gi;
  let m;
  while ((m = re.exec(text))) {
    const id = byPseudo.get(m[1].toLowerCase());
    if (id) {
      ids.add(id);
    }
  }
  return [...ids];
}

export function useDirectMessages({
  conversationIdRef,
  currentUserIdRef,
  currentUserNameRef,
  conversationsRef,
}) {
  const messages = ref([]);
  const onlineUsers = ref([]);
  const typingUsers = ref([]);
  const loading = ref(false);
  const sending = ref(false);
  const uploading = ref(false);
  const live = ref(false);
  const highlightedIds = ref(new Set());
  const pendingOutbound = ref([]);
  const listRef = ref(null);
  let pollTimer = null;
  let channel = null;
  let activeConversationId = null;
  let unsubscribeInbox = null;
  let whisperTimer = null;
  const searchFilters = ref({});
  const highlightTimers = new Map();
  const typingTimeouts = new Map();

  function scrollToBottom(smooth = true) {
    if (!listRef.value) return;
    listRef.value.scrollTo({
      top: listRef.value.scrollHeight,
      behavior: smooth ? "smooth" : "auto",
    });
  }

  function highlightMessage(messageId) {
    if (!messageId) return;
    highlightedIds.value = new Set([...highlightedIds.value, messageId]);
    if (highlightTimers.has(messageId)) {
      clearTimeout(highlightTimers.get(messageId));
    }
    highlightTimers.set(
      messageId,
      setTimeout(() => {
        const next = new Set(highlightedIds.value);
        next.delete(messageId);
        highlightedIds.value = next;
        highlightTimers.delete(messageId);
      }, HIGHLIGHT_MS),
    );
  }

  function clearTypingState() {
    if (whisperTimer) {
      clearTimeout(whisperTimer);
      whisperTimer = null;
    }
    for (const timeout of typingTimeouts.values()) {
      clearTimeout(timeout);
    }
    typingTimeouts.clear();
    typingUsers.value = [];
  }

  function addTypingUser(user) {
    const currentUserId = currentUserIdRef.value;
    if (!user?.id || user.id === currentUserId) {
      return;
    }

    typingUsers.value = [
      ...typingUsers.value.filter((entry) => entry.id !== user.id),
      { id: user.id, name: user.name ?? "Quelqu'un" },
    ];

    if (typingTimeouts.has(user.id)) {
      clearTimeout(typingTimeouts.get(user.id));
    }

    typingTimeouts.set(
      user.id,
      setTimeout(() => {
        typingUsers.value = typingUsers.value.filter(
          (entry) => entry.id !== user.id,
        );
        typingTimeouts.delete(user.id);
      }, TYPING_TTL_MS),
    );
  }

  function whisperTyping() {
    if (!channel || !currentUserIdRef?.value) {
      return;
    }

    channel.whisper("typing", {
      user: {
        id: currentUserIdRef.value,
        name: currentUserNameRef?.value ?? "Utilisateur",
      },
    });
  }

  function notifyTyping() {
    if (!activeConversationId) {
      return;
    }
    if (whisperTimer) {
      clearTimeout(whisperTimer);
    }
    whisperTimer = setTimeout(() => {
      whisperTyping();
      whisperTimer = null;
    }, WHISPER_DEBOUNCE_MS);
  }

  function leaveConversation() {
    if (channel && window.Echo && activeConversationId) {
      window.Echo.leave(`direct.${activeConversationId}`);
      channel = null;
    }
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
    activeConversationId = null;
    onlineUsers.value = [];
    clearTypingState();
  }

  function upsertConversation(inbox, { incrementUnread = false } = {}) {
    if (!inbox?.id || !conversationsRef?.value) return;

    const currentUserId = currentUserIdRef.value;
    const participant =
      inbox.participant ?? participantFromInbox(inbox, currentUserId);
    const existing = conversationsRef.value.find((c) => c.id === inbox.id);
    const isActive = conversationIdRef.value === inbox.id;
    const fromSelf = inbox.last_message?.user_id === currentUserId;

    const unreadCount = (() => {
      if (isActive) return 0;
      if (incrementUnread && !fromSelf) {
        return (existing?.unread_count ?? 0) + 1;
      }
      return existing?.unread_count ?? 0;
    })();

    const next = {
      id: inbox.id,
      last_message_at: inbox.last_message_at,
      last_message: inbox.last_message,
      participant: participant ?? existing?.participant ?? null,
      unread_count: unreadCount,
    };

    const list = existing
      ? conversationsRef.value.map((c) => (c.id === inbox.id ? { ...c, ...next } : c))
      : [...conversationsRef.value, next];

    conversationsRef.value = sortConversations(list);
  }

  function otherParticipantId() {
    const conv = conversationsRef?.value?.find(
      (c) => c.id === conversationIdRef.value,
    );
    return conv?.participant?.id ?? null;
  }

  function handleMessagesRead(event) {
    const conversationId = event?.conversation_id;
    const readerId = event?.reader_id;
    const readAt = event?.read_at;
    if (
      !conversationId ||
      !readAt ||
      conversationIdRef.value !== conversationId ||
      readerId !== otherParticipantId()
    ) {
      return;
    }

    const readTime = new Date(readAt).getTime();
    messages.value = messages.value.map((message) => {
      if (message.user?.id !== currentUserIdRef.value) {
        return message;
      }
      if (new Date(message.created_at).getTime() <= readTime) {
        return { ...message, is_read: true, read_at: readAt };
      }
      return message;
    });
  }

  function appendMessage(message, { highlight = true, scroll = true } = {}) {
    if (
      !message?.id ||
      Number(message.direct_conversation_id) !== Number(activeConversationId)
    ) {
      return false;
    }
    if (messages.value.some((m) => m.id === message.id)) {
      return false;
    }
    const normalized =
      message.user?.id === currentUserIdRef.value && message.is_read === undefined
        ? { ...message, is_read: false, read_at: null }
        : message;
    messages.value = sortMessages([...messages.value, normalized]);
    if (highlight) {
      highlightMessage(message.id);
    }
    if (scroll) {
      nextTick(() => scrollToBottom(true));
    }
    return true;
  }

  function handleIncoming(event, { fromInbox = false, skipUnreadIncrement = false } = {}) {
    const message = event?.message ?? event;
    const inbox = event?.inbox;
    const currentUserId = currentUserIdRef.value;
    const conversationId = message?.direct_conversation_id ?? inbox?.id;
    const isActive = conversationIdRef.value === conversationId;
    const fromOther = message?.user?.id && message.user.id !== currentUserId;

    if (inbox) {
      upsertConversation(inbox, {
        incrementUnread: fromInbox && !skipUnreadIncrement && !isActive && fromOther,
      });
    }

    if (isActive && message?.id) {
      appendMessage(message, { highlight: fromOther, scroll: true });
      if (fromOther) {
        markRead(conversationId);
      }
    }
  }

  async function markRead(conversationId) {
    if (!conversationId) return;
    try {
      await axios.post(route("messages.conversations.read", conversationId));
      if (conversationsRef?.value) {
        conversationsRef.value = conversationsRef.value.map((c) =>
          c.id === conversationId ? { ...c, unread_count: 0 } : c,
        );
      }
    } catch {
      // ignore
    }
  }

  async function fetchMessages(conversationId, { scroll = false, replace = false } = {}) {
    if (Number(conversationId) !== Number(activeConversationId)) {
      return;
    }

    const params = {};
    for (const [key, value] of Object.entries(searchFilters.value)) {
      if (value != null && value !== "") {
        params[key] = value;
      }
    }

    const { data } = await axios.get(
      route("messages.conversations.messages", conversationId),
      { params },
    );
    const incoming = data.messages ?? [];
    const searching = Boolean(params.q || params.author_id || params.from || params.to);
    const merged = searching || replace
      ? sortMessages(messagesForConversation(incoming, conversationId))
      : mergeMessages(messages.value, incoming, conversationId);
    if (merged !== messages.value) {
      const previousIds = new Set(messages.value.map((m) => m.id));
      messages.value = merged;
      for (const message of merged) {
        if (!previousIds.has(message.id) && message.user?.id !== currentUserIdRef.value) {
          highlightMessage(message.id);
        }
      }
      if (scroll) {
        await nextTick();
        scrollToBottom(false);
      }
    }
  }

  async function applySearchFilters(filters) {
    searchFilters.value = filters ?? {};
    if (activeConversationId) {
      loading.value = true;
      try {
        await fetchMessages(activeConversationId, { replace: true });
      } finally {
        loading.value = false;
      }
    }
  }

  function subscribe(conversationId) {
    if (!window.Echo || !conversationId) {
      return;
    }

    channel = window.Echo.join(`direct.${conversationId}`);
    bindPresenceHandlers(channel, onlineUsers);
    channel
      .listen(".DirectMessageSent", (event) => handleIncoming(event, { fromInbox: false }))
      .listen("DirectMessageSent", (event) => handleIncoming(event, { fromInbox: false }))
      .listen(".DirectMessagesRead", handleMessagesRead)
      .listen("DirectMessagesRead", handleMessagesRead)
      .listenForWhisper("typing", (event) => {
        addTypingUser(event?.user);
      })
      .error((error) => {
        console.warn("[direct-messages] Echo subscription error", error);
      });
    live.value = true;
  }

  function startPolling(conversationId) {
    pollTimer = setInterval(() => {
      fetchMessages(conversationId).catch(() => {});
    }, POLL_MS);
  }

  async function start(conversationId, initialMessages = []) {
    leaveConversation();
    activeConversationId = conversationId;
    loading.value = true;
    messages.value = sortMessages(
      messagesForConversation(initialMessages, conversationId),
    );

    try {
      await fetchMessages(conversationId, { scroll: true });
      subscribe(conversationId);
      startPolling(conversationId);
      await markRead(conversationId);
    } finally {
      loading.value = false;
    }
  }

  async function refreshPendingOutbound() {
    pendingOutbound.value = await listPendingMessages();
  }

  async function send(body, conversationId, recipientId = null, extras = {}) {
    const trimmed = body?.trim();
    if (!trimmed || sending.value) {
      return null;
    }

    sending.value = true;
    try {
      const payload = { body: trimmed };
      if (conversationId) {
        payload.conversation_id = conversationId;
      } else if (recipientId) {
        payload.recipient_id = recipientId;
      } else {
        return null;
      }

      if (extras.reply_to_id) {
        payload.reply_to_id = extras.reply_to_id;
      }
      if (extras.mentions?.length) {
        payload.mentions = extras.mentions;
      }

      const { data } = await axios.post(route("messages.store"), payload);
      appendMessage(data.message, { highlight: false, scroll: true });
      if (data.conversation) {
        upsertConversation({
          id: data.conversation.id,
          last_message_at: data.conversation.last_message_at,
          last_message: data.conversation.last_message,
          participant: data.conversation.participant,
        });
      }
      await refreshPendingOutbound();
      return data;
    } catch (error) {
      if (isOfflineError(error)) {
        const queued = {
          id: crypto.randomUUID(),
          body: trimmed,
          conversation_id: conversationId,
          recipient_id: recipientId,
          reply_to_id: extras.reply_to_id ?? null,
          mentions: extras.mentions ?? [],
          created_at: new Date().toISOString(),
        };
        await enqueuePendingMessage(queued);
        pendingOutbound.value = await listPendingMessages();
        return { queued: true, pending: queued };
      }
      throw error;
    } finally {
      sending.value = false;
    }
  }

  async function toggleReaction(messageId, emoji) {
    if (!activeConversationId || !emoji || !messageId) {
      return;
    }

    try {
      const { data } = await axios.post(
        route("messages.reactions.toggle", messageId),
        { emoji },
      );
      const prev = messages.value.find((m) => m.id === messageId);
      if (prev && data?.reactions) {
        const byId = new Map(messages.value.map((m) => [m.id, m]));
        byId.set(messageId, { ...prev, reactions: data.reactions });
        messages.value = sortMessages([...byId.values()]);
      }
    } catch {
      /* ignore */
    }
  }

  async function uploadAttachment(conversationId, file) {
    if (!conversationId || !file || uploading.value) {
      return null;
    }

    uploading.value = true;
    try {
      const formData = new FormData();
      formData.append("file", file);
      const { data } = await axios.post(
        route("messages.attachments.store", conversationId),
        formData,
        { headers: { "Content-Type": "multipart/form-data" } },
      );
      const message = data.message;
      if (message?.id) {
        appendMessage(message, {
          highlight: message.user?.id !== currentUserIdRef.value,
          scroll: true,
        });
      }
      return data;
    } finally {
      uploading.value = false;
    }
  }

  watch(
    conversationIdRef,
    (conversationId, previousId) => {
      if (!conversationId) {
        leaveConversation();
        messages.value = [];
        loading.value = false;
      }
    },
    { immediate: false },
  );

  onMounted(() => {
    unsubscribeInbox = onDirectMessage((event, opts) =>
      handleIncoming(event, { fromInbox: true, skipUnreadIncrement: opts?.skipUnreadIncrement }),
    );
    refreshPendingOutbound().catch(() => {});
  });

  onUnmounted(() => {
    leaveConversation();
    unsubscribeInbox?.();
    highlightTimers.forEach((timer) => clearTimeout(timer));
    highlightTimers.clear();
  });

  return {
    messages,
    onlineUsers,
    typingUsers,
    loading,
    sending,
    live,
    highlightedIds,
    send,
    toggleReaction,
    uploadAttachment,
    uploading,
    notifyTyping,
    listRef,
    pendingOutbound,
    refreshPendingOutbound,
    start,
    leaveConversation,
    markRead,
    upsertConversation,
    searchFilters,
    applySearchFilters,
  };
}

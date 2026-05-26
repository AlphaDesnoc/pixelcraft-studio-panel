import { nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import axios from "axios";
import { bindPresenceHandlers } from "@/lib/presence.js";
import { onDirectMessage } from "@/composables/useSiteRealtime.js";
import { memberPseudo } from "@/composables/useMentionAutocomplete.js";

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

function mergeMessages(existing, incoming) {
  if (incoming.length === 0) {
    return existing;
  }

  const lastExisting = existing[existing.length - 1]?.id;
  const lastIncoming = incoming[incoming.length - 1]?.id;
  if (existing.length === incoming.length && lastExisting === lastIncoming) {
    return existing;
  }

  const byId = new Map(existing.map((m) => [m.id, m]));
  for (const message of incoming) {
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
  const listRef = ref(null);
  let pollTimer = null;
  let channel = null;
  let activeConversationId = null;
  let unsubscribeInbox = null;
  let whisperTimer = null;
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

  function appendMessage(message, { highlight = true, scroll = true } = {}) {
    if (!message?.id || messages.value.some((m) => m.id === message.id)) {
      return false;
    }
    messages.value = sortMessages([...messages.value, message]);
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

  async function fetchMessages(conversationId, { scroll = false } = {}) {
    const { data } = await axios.get(
      route("messages.conversations.messages", conversationId),
    );
    const incoming = data.messages ?? [];
    const merged = mergeMessages(messages.value, incoming);
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

  function subscribe(conversationId) {
    if (!window.Echo || !conversationId) {
      return;
    }

    channel = window.Echo.join(`direct.${conversationId}`);
    bindPresenceHandlers(channel, onlineUsers);
    channel
      .listen(".DirectMessageSent", (event) => handleIncoming(event, { fromInbox: false }))
      .listen("DirectMessageSent", (event) => handleIncoming(event, { fromInbox: false }))
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
    messages.value = sortMessages(initialMessages);

    try {
      await fetchMessages(conversationId, { scroll: true });
      subscribe(conversationId);
      startPolling(conversationId);
      await markRead(conversationId);
    } finally {
      loading.value = false;
    }
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
      return data;
    } finally {
      sending.value = false;
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
      if (conversationId && conversationId !== previousId) {
        start(conversationId);
        return;
      }
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
    uploadAttachment,
    uploading,
    notifyTyping,
    listRef,
    start,
    leaveConversation,
    markRead,
    upsertConversation,
  };
}

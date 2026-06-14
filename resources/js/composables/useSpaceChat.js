import { nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import axios from "axios";
import { sortPresenceUsers } from "@/lib/presence.js";

const POLL_MS = 2000;
const PRESENCE_POLL_MS = 10000;
const TYPING_TTL_MS = 3000;
const WHISPER_DEBOUNCE_MS = 400;

function sortMessages(list) {
  return [...list].sort(
    (a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime(),
  );
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

function normalizeMembers(members) {
  return sortPresenceUsers(
    (members ?? []).map((member) => ({
      id: member.id,
      name: member.name,
      pseudo: member.pseudo ?? member.email?.split("@")[0] ?? "",
      is_online: Boolean(member.is_online),
    })),
  );
}

function applyEchoPresence(members, echoUsers) {
  if (!members.length) {
    return members;
  }

  const echoIds = new Set(echoUsers.map((user) => user.id));

  return members.map((member) => ({
    ...member,
    is_online: member.is_online || echoIds.has(member.id),
  }));
}

async function pingPresence() {
  try {
    await axios.post(route("realtime.heartbeat"));
  } catch {
    // ignore
  }
}

export function useSpaceChat(
  projectSlug,
  projectId,
  activeRef,
  spaceKeyRef,
  initialMembersRef = null,
  currentUserIdRef = null,
) {
  const messages = ref([]);
  const chatMembers = ref([]);
  const loading = ref(false);
  const sending = ref(false);
  const uploading = ref(false);
  const typingUsers = ref([]);
  const listRef = ref(null);
  const atBottom = ref(true);
  const unreadCount = ref(0);
  const NEAR_BOTTOM_PX = 120;
  let pollTimer = null;
  let presenceTimer = null;
  let channel = null;
  let activeSpace = null;
  let echoOnlineUsers = [];
  let whisperTimer = null;
  const searchFilters = ref({});
  const typingTimeouts = new Map();

  function scrollToBottom() {
    if (listRef.value) {
      listRef.value.scrollTop = listRef.value.scrollHeight;
    }
  }

  function isNearBottom() {
    const el = listRef.value;
    if (!el) return true;
    return el.scrollHeight - el.scrollTop - el.clientHeight <= NEAR_BOTTOM_PX;
  }

  // À brancher sur l'événement @scroll de la liste : suit si l'utilisateur est
  // « collé » en bas pour décider d'auto-scroller ou d'afficher le compteur.
  function handleScroll() {
    atBottom.value = isNearBottom();
    if (atBottom.value) {
      unreadCount.value = 0;
    }
  }

  function jumpToPresent() {
    scrollToBottom();
    atBottom.value = true;
    unreadCount.value = 0;
  }

  // Quand on revient sur l'onglet/la fenêtre : si on était en bas, on rattrape
  // les messages arrivés pendant que le websocket était throttlé hors focus.
  function handleVisibility() {
    if (document.visibilityState === "visible" && atBottom.value) {
      nextTick(scrollToBottom);
    }
  }

  function markSelfOnline(members) {
    const currentUserId = currentUserIdRef?.value;
    if (!currentUserId) {
      return members;
    }

    return members.map((member) =>
      member.id === currentUserId ? { ...member, is_online: true } : member,
    );
  }

  function setMembers(members) {
    chatMembers.value = markSelfOnline(
      applyEchoPresence(normalizeMembers(members), echoOnlineUsers),
    );
  }

  function refreshMembersFromEcho() {
    chatMembers.value = markSelfOnline(
      applyEchoPresence(chatMembers.value, echoOnlineUsers),
    );
  }

  function refreshTypingUsers() {
    const currentUserId = currentUserIdRef?.value;
    typingUsers.value = typingUsers.value.filter(
      (user) => user.id !== currentUserId,
    );
  }

  function addTypingUser(user) {
    const currentUserId = currentUserIdRef?.value;
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

    const member = chatMembers.value.find(
      (entry) => entry.id === currentUserIdRef.value,
    );

    channel.whisper("typing", {
      user: {
        id: currentUserIdRef.value,
        name: member?.name ?? "Utilisateur",
      },
    });
  }

  function notifyTyping() {
    if (whisperTimer) {
      clearTimeout(whisperTimer);
    }
    whisperTimer = setTimeout(() => {
      whisperTyping();
      whisperTimer = null;
    }, WHISPER_DEBOUNCE_MS);
  }

  function unsubscribe() {
    if (channel && window.Echo && activeSpace && projectId) {
      window.Echo.leave(`project-chat.${projectId}.${activeSpace}`);
      channel = null;
    }
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
    if (presenceTimer) {
      clearInterval(presenceTimer);
      presenceTimer = null;
    }
    if (whisperTimer) {
      clearTimeout(whisperTimer);
      whisperTimer = null;
    }
    for (const timeout of typingTimeouts.values()) {
      clearTimeout(timeout);
    }
    typingTimeouts.clear();
    activeSpace = null;
    echoOnlineUsers = [];
    chatMembers.value = [];
    typingUsers.value = [];
  }

  function appendMessage(message) {
    if (!message?.id || messages.value.some((m) => m.id === message.id)) {
      return false;
    }
    messages.value = sortMessages([...messages.value, message]);
    const isMine =
      currentUserIdRef?.value && message.user?.id === currentUserIdRef.value;
    if (isMine || atBottom.value) {
      nextTick(() => {
        scrollToBottom();
        atBottom.value = true;
        unreadCount.value = 0;
      });
    } else {
      unreadCount.value += 1;
    }
    return true;
  }

  function replaceMessage(message) {
    if (!message?.id) return;
    const index = messages.value.findIndex((m) => m.id === message.id);
    if (index === -1) {
      appendMessage(message);
      return;
    }
    // Les payloads temps réel omettent les drapeaux par-viewer (mentions_me,
    // can_edit) : on conserve la valeur déjà connue plutôt que de l'effacer.
    const prev = messages.value[index];
    const merged = { ...message };
    if (merged.mentions_me === undefined && prev.mentions_me !== undefined) {
      merged.mentions_me = prev.mentions_me;
    }
    if (merged.can_edit === undefined && prev.can_edit !== undefined) {
      merged.can_edit = prev.can_edit;
    }
    messages.value = messages.value.map((m) => (m.id === message.id ? merged : m));
  }

  function removeMessage(messageId) {
    messages.value = messages.value.filter((m) => m.id !== messageId);
  }

  function handleIncoming(event) {
    const message = event?.message ?? event;
    appendMessage(message);
  }

  function handleUpdated(event) {
    const message = event?.message;
    if (message) {
      replaceMessage(message);
    }
  }

  function handleDeleted(event) {
    const messageId = event?.message_id ?? event?.messageId;
    if (messageId) {
      removeMessage(messageId);
    }
  }

  async function fetchMessages(spaceKey, { scroll = false, replace = false } = {}) {
    const params = { space: spaceKey };
    for (const [key, value] of Object.entries(searchFilters.value)) {
      if (value != null && value !== "") {
        params[key] = value;
      }
    }

    const { data } = await axios.get(route("projects.chat.messages.index", projectSlug), {
      params,
    });
    const incoming = data.messages ?? [];
    const searching = Boolean(params.q || params.author_id || params.from || params.to);
    const prevLength = messages.value.length;
    const merged = searching || replace
      ? sortMessages(incoming)
      : mergeMessages(messages.value, incoming);
    if (merged !== messages.value) {
      const added = merged.length - prevLength;
      messages.value = merged;
      if (scroll || replace) {
        await nextTick();
        scrollToBottom();
        atBottom.value = true;
        unreadCount.value = 0;
      } else if (added > 0) {
        if (atBottom.value) {
          await nextTick();
          scrollToBottom();
        } else {
          unreadCount.value += added;
        }
      }
    }
  }

  async function applySearchFilters(filters) {
    searchFilters.value = filters ?? {};
    if (activeSpace) {
      loading.value = true;
      try {
        await fetchMessages(activeSpace, { replace: true });
      } finally {
        loading.value = false;
      }
    }
  }

  async function fetchPresence(spaceKey) {
    try {
      const { data } = await axios.get(route("projects.chat.presence", projectSlug), {
        params: { space: spaceKey },
      });
      setMembers(data.members ?? []);
    } catch (error) {
      console.warn("[space-chat] presence fetch failed", error);
    }
  }

  function handleReactionUpdated(event) {
    const messageId = event?.message_id ?? event?.messageId;
    const reactions = event?.reactions;
    if (!messageId || reactions === undefined) {
      return;
    }

    const prev = messages.value.find((m) => m.id === messageId);
    if (prev) {
      replaceMessage({ ...prev, reactions });
    }
  }

  function subscribe(spaceKey) {
    if (!window.Echo || !spaceKey || !projectId) {
      return;
    }

    channel = window.Echo.join(`project-chat.${projectId}.${spaceKey}`);

    channel
      .here((users) => {
        echoOnlineUsers = sortPresenceUsers(users);
        refreshMembersFromEcho();
      })
      .joining((user) => {
        if (!echoOnlineUsers.some((entry) => entry.id === user.id)) {
          echoOnlineUsers = sortPresenceUsers([...echoOnlineUsers, user]);
          refreshMembersFromEcho();
        }
      })
      .leaving((user) => {
        echoOnlineUsers = echoOnlineUsers.filter((entry) => entry.id !== user.id);
        fetchPresence(spaceKey).catch(() => {});
      })
      .listen(".ChatMessageSent", handleIncoming)
      .listen("ChatMessageSent", handleIncoming)
      .listen(".ChatMessageUpdated", handleUpdated)
      .listen("ChatMessageUpdated", handleUpdated)
      .listen(".ChatMessageDeleted", handleDeleted)
      .listen("ChatMessageDeleted", handleDeleted)
      .listen(".ChatReactionUpdated", handleReactionUpdated)
      .listen("ChatReactionUpdated", handleReactionUpdated)
      .listenForWhisper("typing", (event) => {
        addTypingUser(event?.user);
      })
      .error((error) => {
        console.warn("[space-chat] Echo subscription error", error);
      });
  }

  function startPolling(spaceKey) {
    pollTimer = setInterval(() => {
      fetchMessages(spaceKey).catch(() => {});
    }, POLL_MS);

    presenceTimer = setInterval(() => {
      pingPresence();
      fetchPresence(spaceKey).catch(() => {});
    }, PRESENCE_POLL_MS);
  }

  async function start(spaceKey) {
    unsubscribe();
    activeSpace = spaceKey;
    loading.value = true;
    messages.value = [];
    atBottom.value = true;
    unreadCount.value = 0;
    setMembers(initialMembersRef?.value ?? []);

    try {
      await pingPresence();
      await fetchMessages(spaceKey, { scroll: true });
      await fetchPresence(spaceKey);
      subscribe(spaceKey);
      startPolling(spaceKey);
    } finally {
      loading.value = false;
    }
  }

  async function send(body, replyToId = null) {
    const trimmed = body?.trim();
    if (!trimmed || !activeSpace || sending.value) {
      return;
    }

    sending.value = true;
    try {
      const payload = { body: trimmed };
      if (replyToId) {
        payload.reply_to_id = replyToId;
      }
      const { data } = await axios.post(
        route("projects.chat.messages.store", projectSlug),
        payload,
        { params: { space: activeSpace } },
      );
      appendMessage(data.message);
    } finally {
      sending.value = false;
    }
  }

  async function createChapter(title) {
    const trimmed = title?.trim();
    if (!trimmed || !activeSpace || sending.value) {
      return;
    }

    sending.value = true;
    try {
      const { data } = await axios.post(
        route("projects.chat.chapters.store", projectSlug),
        { title: trimmed },
        { params: { space: activeSpace } },
      );
      appendMessage(data.message);
    } finally {
      sending.value = false;
    }
  }

  async function toggleReaction(messageId, emoji) {
    if (!activeSpace || !emoji || !messageId) {
      return;
    }

    try {
      const { data } = await axios.post(
        route("projects.chat.reactions.toggle", [projectSlug, messageId]),
        { emoji },
        { params: { space: activeSpace } },
      );
      if (data?.reactions) {
        const prev = messages.value.find((m) => m.id === messageId);
        if (prev) {
          replaceMessage({ ...prev, reactions: data.reactions });
        }
      }
    } catch {
      /* ignore */
    }
  }

  async function pinMessage(messageId) {
    if (!activeSpace || !messageId) {
      return;
    }

    try {
      const { data } = await axios.post(
        route("projects.chat.messages.pin", [projectSlug, messageId]),
        {},
        { params: { space: activeSpace } },
      );
      if (data?.message) {
        replaceMessage(data.message);
      }
    } catch {
      /* ignore */
    }
  }

  async function updateMessage(messageId, body) {
    const trimmed = body?.trim();
    if (!trimmed || !activeSpace || sending.value) {
      return;
    }

    sending.value = true;
    try {
      const { data } = await axios.put(
        route("projects.chat.messages.update", [projectSlug, messageId]),
        { body: trimmed },
        { params: { space: activeSpace } },
      );
      replaceMessage(data.message);
    } finally {
      sending.value = false;
    }
  }

  async function deleteMessage(messageId) {
    if (!activeSpace || sending.value) {
      return;
    }

    sending.value = true;
    try {
      await axios.delete(
        route("projects.chat.messages.destroy", [projectSlug, messageId]),
        { params: { space: activeSpace } },
      );
      removeMessage(messageId);
    } finally {
      sending.value = false;
    }
  }

  async function uploadAttachment(file) {
    if (!file || !activeSpace || uploading.value) {
      return;
    }

    uploading.value = true;
    try {
      const formData = new FormData();
      formData.append("file", file);
      formData.append("space", activeSpace);

      const { data } = await axios.post(
        route("projects.chat.attachments.store", projectSlug),
        formData,
        {
          headers: { "Content-Type": "multipart/form-data" },
        },
      );
      appendMessage(data.message);
    } finally {
      uploading.value = false;
    }
  }

  watch(
    initialMembersRef,
    (members) => {
      if (!activeSpace || !members?.length) {
        return;
      }
      if (chatMembers.value.length === 0) {
        setMembers(members);
      }
    },
    { deep: true },
  );

  watch(
    () => [activeRef.value, spaceKeyRef.value],
    ([active, spaceKey]) => {
      if (active && spaceKey && spaceKey !== "full") {
        start(spaceKey);
        return;
      }
      unsubscribe();
      messages.value = [];
      chatMembers.value = [];
      loading.value = false;
    },
    { immediate: true },
  );

  onMounted(() => {
    document.addEventListener("visibilitychange", handleVisibility);
    window.addEventListener("focus", handleVisibility);
  });

  onUnmounted(() => {
    document.removeEventListener("visibilitychange", handleVisibility);
    window.removeEventListener("focus", handleVisibility);
    unsubscribe();
  });

  return {
    messages,
    chatMembers,
    loading,
    sending,
    uploading,
    typingUsers,
    searchFilters,
    send,
    createChapter,
    toggleReaction,
    pinMessage,
    updateMessage,
    deleteMessage,
    uploadAttachment,
    notifyTyping,
    applySearchFilters,
    listRef,
    atBottom,
    unreadCount,
    handleScroll,
    jumpToPresent,
  };
}

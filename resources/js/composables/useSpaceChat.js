import { nextTick, onUnmounted, ref, watch } from "vue";
import axios from "axios";
import { sortPresenceUsers } from "@/lib/presence.js";

const POLL_MS = 2000;
const PRESENCE_POLL_MS = 10000;

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
  const listRef = ref(null);
  let pollTimer = null;
  let presenceTimer = null;
  let channel = null;
  let activeSpace = null;
  let echoOnlineUsers = [];

  function scrollToBottom() {
    if (listRef.value) {
      listRef.value.scrollTop = listRef.value.scrollHeight;
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
    activeSpace = null;
    echoOnlineUsers = [];
    chatMembers.value = [];
  }

  function appendMessage(message) {
    if (!message?.id || messages.value.some((m) => m.id === message.id)) {
      return false;
    }
    messages.value = sortMessages([...messages.value, message]);
    nextTick(() => scrollToBottom());
    return true;
  }

  function handleIncoming(event) {
    const message = event?.message ?? event;
    appendMessage(message);
  }

  async function fetchMessages(spaceKey, { scroll = false } = {}) {
    const { data } = await axios.get(route("projects.chat.messages.index", projectSlug), {
      params: { space: spaceKey },
    });
    const incoming = data.messages ?? [];
    const merged = mergeMessages(messages.value, incoming);
    if (merged !== messages.value) {
      messages.value = merged;
      if (scroll) {
        await nextTick();
        scrollToBottom();
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

  async function send(body) {
    const trimmed = body?.trim();
    if (!trimmed || !activeSpace || sending.value) {
      return;
    }

    sending.value = true;
    try {
      const { data } = await axios.post(
        route("projects.chat.messages.store", projectSlug),
        { body: trimmed },
        { params: { space: activeSpace } },
      );
      appendMessage(data.message);
    } finally {
      sending.value = false;
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

  onUnmounted(unsubscribe);

  return { messages, chatMembers, loading, sending, send, listRef };
}

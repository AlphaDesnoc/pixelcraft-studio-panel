import { nextTick, onUnmounted, ref, watch } from "vue";
import axios from "axios";
import { bindPresenceHandlers } from "@/lib/presence.js";

const POLL_MS = 2000;

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

export function useSpaceChat(projectSlug, projectId, activeRef, spaceKeyRef) {
  const messages = ref([]);
  const onlineUsers = ref([]);
  const loading = ref(false);
  const sending = ref(false);
  const listRef = ref(null);
  let pollTimer = null;
  let channel = null;
  let activeSpace = null;

  function scrollToBottom() {
    if (listRef.value) {
      listRef.value.scrollTop = listRef.value.scrollHeight;
    }
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
    activeSpace = null;
    onlineUsers.value = [];
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

  function subscribe(spaceKey) {
    if (!window.Echo || !spaceKey || !projectId) {
      return;
    }

    channel = window.Echo.join(`project-chat.${projectId}.${spaceKey}`);
    bindPresenceHandlers(channel, onlineUsers);
    channel
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
  }

  async function start(spaceKey) {
    unsubscribe();
    activeSpace = spaceKey;
    loading.value = true;
    messages.value = [];

    try {
      await fetchMessages(spaceKey, { scroll: true });
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
    () => [activeRef.value, spaceKeyRef.value],
    ([active, spaceKey]) => {
      if (active && spaceKey && spaceKey !== "full") {
        start(spaceKey);
        return;
      }
      unsubscribe();
      messages.value = [];
      onlineUsers.value = [];
      loading.value = false;
    },
    { immediate: true },
  );

  onUnmounted(unsubscribe);

  return { messages, onlineUsers, loading, sending, send, listRef };
}

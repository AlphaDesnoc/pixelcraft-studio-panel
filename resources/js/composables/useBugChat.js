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

export function useBugChat(projectSlug, openRef, bugRef) {
  const messages = ref([]);
  const onlineUsers = ref([]);
  const loading = ref(false);
  const sending = ref(false);
  const listRef = ref(null);
  let pollTimer = null;
  let channel = null;
  let activeBugId = null;

  function scrollToBottom() {
    if (listRef.value) {
      listRef.value.scrollTop = listRef.value.scrollHeight;
    }
  }

  function unsubscribe() {
    if (channel && window.Echo && activeBugId) {
      window.Echo.leave(`bug.${activeBugId}`);
      channel = null;
    }
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = null;
    }
    activeBugId = null;
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

  async function fetchMessages(bugId, { scroll = false } = {}) {
    const { data } = await axios.get(
      route("projects.bugs.messages.index", [projectSlug, bugId]),
    );
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

  function subscribe(bugId) {
    if (!window.Echo || !bugId) {
      return;
    }

    channel = window.Echo.join(`bug.${bugId}`);
    bindPresenceHandlers(channel, onlineUsers);

    channel
      .listen(".BugMessageSent", handleIncoming)
      .listen("BugMessageSent", handleIncoming)
      .error((error) => {
        console.warn("[bug-chat] Echo subscription error", error);
      });
  }

  function startPolling(bugId) {
    pollTimer = setInterval(() => {
      fetchMessages(bugId).catch(() => {});
    }, POLL_MS);
  }

  async function start(bugId) {
    unsubscribe();
    activeBugId = bugId;
    loading.value = true;
    messages.value = [];

    try {
      await fetchMessages(bugId, { scroll: true });
      subscribe(bugId);
      startPolling(bugId);
    } finally {
      loading.value = false;
    }
  }

  async function send(body) {
    const trimmed = body?.trim();
    if (!trimmed || !activeBugId || sending.value) {
      return;
    }

    sending.value = true;
    try {
      const { data } = await axios.post(
        route("projects.bugs.messages.store", [projectSlug, activeBugId]),
        { body: trimmed },
      );
      appendMessage(data.message);
    } finally {
      sending.value = false;
    }
  }

  watch(
    () => [openRef.value, bugRef.value?.id],
    ([open, bugId]) => {
      if (open && bugId) {
        start(bugId);
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

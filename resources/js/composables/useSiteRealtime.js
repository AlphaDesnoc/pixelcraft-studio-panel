import { ref } from "vue";
import axios from "axios";
import { bindPresenceHandlers } from "@/lib/presence.js";

export const onlineUsers = ref([]);
export const unreadMessages = ref(0);
export const unreadNotifications = ref(0);
export const siteLive = ref(false);

const POLL_MS = 3000;
const HEARTBEAT_MS = 25000;

const activeConversationId = ref(null);
const inboxHandlers = new Set();

let initializedUserId = null;
let presenceChannel = null;
let inboxChannel = null;
let pollTimer = null;
let heartbeatTimer = null;
let lastSyncAt = null;
let echoConnected = false;

export function setActiveConversationId(id) {
  activeConversationId.value = id ?? null;
}

export function setUnreadCount(count) {
  unreadMessages.value = Math.max(0, Number(count) || 0);
}

export function setUnreadNotificationsCount(count) {
  unreadNotifications.value = Math.max(0, Number(count) || 0);
}

export function isUserOnline(userId) {
  if (!userId) return false;
  return onlineUsers.value.some((u) => u.id === userId);
}

export function onDirectMessage(handler) {
  inboxHandlers.add(handler);
  return () => inboxHandlers.delete(handler);
}

function dispatchInbox(event, { skipUnreadIncrement = false } = {}) {
  for (const handler of inboxHandlers) {
    try {
      handler(event, { skipUnreadIncrement });
    } catch (error) {
      console.warn("[site-realtime] inbox handler error", error);
    }
  }
}

function handleInboxEvent(event, { skipUnreadIncrement = false } = {}) {
  const message = event?.message;
  const inbox = event?.inbox;
  const conversationId = message?.direct_conversation_id ?? inbox?.id;
  const senderId = message?.user?.id ?? inbox?.last_message?.user_id;

  dispatchInbox(event, { skipUnreadIncrement });

  if (skipUnreadIncrement || !senderId || senderId === initializedUserId) {
    return;
  }

  if (activeConversationId.value !== conversationId) {
    unreadMessages.value += 1;
  }
}

async function sendHeartbeat() {
  try {
    await axios.post(route("realtime.heartbeat"));
  } catch {
    // ignore
  }
}

async function pollSync() {
  try {
    const { data } = await axios.get(route("realtime.sync"), {
      params: lastSyncAt ? { since: lastSyncAt } : {},
    });

    lastSyncAt = data.server_time ?? new Date().toISOString();
    unreadMessages.value = data.unread_count ?? unreadMessages.value;
    if (typeof data.unread_notifications === "number") {
      unreadNotifications.value = data.unread_notifications;
    }

    if (!echoConnected && Array.isArray(data.online_users)) {
      onlineUsers.value = data.online_users;
    }

    for (const event of data.events ?? []) {
      handleInboxEvent(event, { skipUnreadIncrement: true });
    }

    siteLive.value = echoConnected || true;
  } catch {
    if (!echoConnected) {
      siteLive.value = false;
    }
  }
}

function startPolling() {
  if (pollTimer) return;
  pollSync();
  pollTimer = setInterval(pollSync, POLL_MS);
}

function startHeartbeat() {
  if (heartbeatTimer) return;
  sendHeartbeat();
  heartbeatTimer = setInterval(sendHeartbeat, HEARTBEAT_MS);
}

function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer);
    pollTimer = null;
  }
  if (heartbeatTimer) {
    clearInterval(heartbeatTimer);
    heartbeatTimer = null;
  }
}

function subscribeEcho(userId) {
  if (!window.Echo || !userId) {
    return;
  }

  if (!presenceChannel) {
    presenceChannel = window.Echo.join("site-presence");
    bindPresenceHandlers(presenceChannel, onlineUsers);
    presenceChannel.error((error) => {
      console.warn("[site-presence] Echo error, polling actif", error);
      echoConnected = false;
    });
  }

  if (!inboxChannel) {
    inboxChannel = window.Echo.private(`App.Models.User.${userId}`);
    inboxChannel
      .listen(".DirectMessageSent", (event) => handleInboxEvent(event))
      .listen("DirectMessageSent", (event) => handleInboxEvent(event))
      .error((error) => {
        console.warn("[site-inbox] Echo error, polling actif", error);
        echoConnected = false;
      });
  }

  const connection = window.Echo?.connector?.pusher?.connection;
  if (connection) {
    connection.bind("connected", () => {
      echoConnected = true;
      siteLive.value = true;
    });
    connection.bind("disconnected", () => {
      echoConnected = false;
    });
    connection.bind("unavailable", () => {
      echoConnected = false;
    });
    if (connection.state === "connected") {
      echoConnected = true;
    }
  }
}

export function initSiteRealtime(userId, initialUnread = 0) {
  if (!userId) {
    return;
  }

  if (initializedUserId !== userId) {
    teardownSiteRealtime();
    initializedUserId = userId;
  }

  unreadMessages.value = initialUnread;
  lastSyncAt = new Date().toISOString();

  startPolling();
  startHeartbeat();
  subscribeEcho(userId);
}

export function teardownSiteRealtime() {
  stopPolling();

  if (presenceChannel && window.Echo) {
    window.Echo.leave("site-presence");
    presenceChannel = null;
  }
  if (inboxChannel && window.Echo && initializedUserId) {
    window.Echo.leave(`App.Models.User.${initializedUserId}`);
    inboxChannel = null;
  }

  onlineUsers.value = [];
  siteLive.value = false;
  echoConnected = false;
  initializedUserId = null;
  lastSyncAt = null;
}

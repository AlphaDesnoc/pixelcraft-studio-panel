import { ref } from "vue";
import axios from "axios";
import {
  setUnreadNotificationsCount,
  unreadNotifications,
} from "@/composables/useSiteRealtime.js";

export const notifications = ref([]);
export const loadingNotifications = ref(false);

let initializedUserId = null;
let notificationChannel = null;

export async function fetchNotifications() {
  loadingNotifications.value = true;
  try {
    const { data } = await axios.get(route("notifications.index"));
    notifications.value = data.notifications ?? [];
    if (typeof data.unread_count === "number") {
      setUnreadNotificationsCount(data.unread_count);
    }
    return data;
  } finally {
    loadingNotifications.value = false;
  }
}

export async function markNotificationRead(notificationId) {
  await axios.post(route("notifications.read", notificationId));

  const item = notifications.value.find((n) => n.id === notificationId);
  if (item && !item.read_at) {
    item.read_at = new Date().toISOString();
    setUnreadNotificationsCount(Math.max(0, unreadNotifications.value - 1));
  }
}

export async function markAllNotificationsRead() {
  await axios.post(route("notifications.read-all"));
  for (const item of notifications.value) {
    if (!item.read_at) {
      item.read_at = new Date().toISOString();
    }
  }
  setUnreadNotificationsCount(0);
}

function prependNotification(payload) {
  if (!payload?.id) return;
  notifications.value = [
    payload,
    ...notifications.value.filter((n) => n.id !== payload.id),
  ].slice(0, 30);
}

function subscribeEcho(userId) {
  if (!window.Echo || !userId || notificationChannel) {
    return;
  }

  notificationChannel = window.Echo.private(`App.Models.User.${userId}`);
  notificationChannel
    .listen(".UserNotificationSent", handleNotificationEvent)
    .listen("UserNotificationSent", handleNotificationEvent)
    .error((error) => {
      console.warn("[notifications] Echo error", error);
    });
}

function handleNotificationEvent(event) {
  const payload = event?.notification;
  if (payload) {
    prependNotification(payload);
  }
  if (typeof event?.unread_count === "number") {
    setUnreadNotificationsCount(event.unread_count);
  } else if (payload && !payload.read_at) {
    setUnreadNotificationsCount(unreadNotifications.value + 1);
  }
}

export function initNotifications(userId, initialUnread = 0) {
  if (!userId) return;

  if (initializedUserId !== userId) {
    teardownNotifications();
    initializedUserId = userId;
  }

  setUnreadNotificationsCount(initialUnread);
  subscribeEcho(userId);
}

export function teardownNotifications() {
  if (notificationChannel) {
    notificationChannel.stopListening(".UserNotificationSent");
    notificationChannel.stopListening("UserNotificationSent");
    notificationChannel = null;
  }
  initializedUserId = null;
  notifications.value = [];
}

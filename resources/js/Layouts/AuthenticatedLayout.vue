<script setup>
import { onMounted, onUnmounted, watch } from "vue";
import { usePage } from "@inertiajs/vue3";
import AppSidebar from "@/Components/AppSidebar.vue";
import NotificationBell from "@/Components/Notifications/NotificationBell.vue";
import {
  initSiteRealtime,
  setUnreadCount,
  setUnreadNotificationsCount,
} from "@/composables/useSiteRealtime.js";
import {
  initNotifications,
  teardownNotifications,
} from "@/composables/useNotifications.js";

const page = usePage();

function bootstrapRealtime() {
  const user = page.props.auth?.user;
  if (!user?.id) return;
  initSiteRealtime(user.id, page.props.sidebar?.unread_messages ?? 0);
  initNotifications(user.id, page.props.sidebar?.unread_notifications ?? 0);
}

onMounted(bootstrapRealtime);

onUnmounted(() => {
  teardownNotifications();
});

watch(
  () => page.props.auth?.user?.id,
  () => bootstrapRealtime(),
);

watch(
  () => page.props.sidebar?.unread_messages,
  (count) => {
    if (typeof count === "number") {
      setUnreadCount(count);
    }
  },
);

watch(
  () => page.props.sidebar?.unread_notifications,
  (count) => {
    if (typeof count === "number") {
      setUnreadNotificationsCount(count);
    }
  },
);
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <AppSidebar />

    <div
      class="fixed inset-x-0 top-0 z-30 flex h-14 items-center justify-end border-b border-border/60 bg-background/95 px-6 backdrop-blur md:left-64"
    >
      <NotificationBell />
    </div>

    <div class="md:pl-64">
      <div class="h-14" aria-hidden="true" />

      <header v-if="$slots.header" class="px-6 pt-8 sm:px-8">
        <slot name="header" />
      </header>

      <main class="px-6 pb-12 pt-6 sm:px-8">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { onMounted, onUnmounted, watch, ref } from "vue";
import { usePage } from "@inertiajs/vue3";
import { Moon, Search, Sun, Monitor } from "lucide-vue-next";
import AppSidebar from "@/Components/AppSidebar.vue";
import NotificationBell from "@/Components/Notifications/NotificationBell.vue";
import GlobalSearchModal from "@/Components/Search/GlobalSearchModal.vue";
import {
  initSiteRealtime,
  setUnreadCount,
  setUnreadNotificationsCount,
  unreadMessages,
  unreadNotifications,
} from "@/composables/useSiteRealtime.js";
import {
  initNotifications,
  teardownNotifications,
} from "@/composables/useNotifications.js";
import { setDesktopBadge } from "@/composables/useDesktop.js";
import { useTheme } from "@/composables/useTheme.js";
import { useKeyboardShortcuts, onEscape } from "@/composables/useKeyboardShortcuts.js";

const page = usePage();
const { preference, cycleTheme } = useTheme();

const searchOpen = ref(false);

const themeIcon = {
  light: Sun,
  dark: Moon,
  system: Monitor,
};

const themeLabel = {
  light: "Clair",
  dark: "Sombre",
  system: "Système",
};

function onGlobalKeydown(e) {
  if ((e.ctrlKey || e.metaKey) && (e.key === "k" || e.key === "K")) {
    e.preventDefault();
    searchOpen.value = true;
  }
}

function handleEscape() {
  if (searchOpen.value) {
    searchOpen.value = false;
    return;
  }
  const openDialog = document.querySelector('[data-state="open"][role="dialog"]');
  if (openDialog) {
    const closeBtn = openDialog.querySelector('[data-dialog-close], button[aria-label="Close"]');
    closeBtn?.click();
  }
}

useKeyboardShortcuts({ onEscape: handleEscape });

function bootstrapRealtime() {
  const user = page.props.auth?.user;
  if (!user?.id) return;
  initSiteRealtime(user.id, page.props.sidebar?.unread_messages ?? 0);
  initNotifications(user.id, page.props.sidebar?.unread_notifications ?? 0);
}

let removeEscapeListener = null;

onMounted(() => {
  bootstrapRealtime();
  window.addEventListener("keydown", onGlobalKeydown);
  removeEscapeListener = onEscape(handleEscape);
});

onUnmounted(() => {
  teardownNotifications();
  window.removeEventListener("keydown", onGlobalKeydown);
  removeEscapeListener?.();
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

watch(
  [unreadMessages, unreadNotifications],
  ([messages, notifications]) => {
    setDesktopBadge((messages ?? 0) + (notifications ?? 0));
  },
  { immediate: true },
);
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <AppSidebar />

    <div
      class="fixed inset-x-0 top-0 z-30 flex h-14 items-center justify-end gap-2 border-b border-border/60 bg-background/95 px-4 backdrop-blur sm:gap-3 sm:px-6 md:left-64"
    >
      <button
        type="button"
        class="inline-flex h-9 items-center gap-2 rounded-full border border-border/80 bg-muted/40 px-3 text-[11px] font-medium text-muted-foreground shadow-sm backdrop-blur transition-colors hover:bg-muted hover:text-foreground"
        aria-label="Rechercher"
        title="Recherche globale (Ctrl + K)"
        @click="searchOpen = true"
      >
        <Search class="h-4 w-4 shrink-0" />
        <span class="hidden sm:inline">Rechercher</span>
        <kbd
          class="hidden rounded-md border border-border bg-background px-1.5 py-0.5 text-[10px] font-semibold uppercase text-muted-foreground sm:inline-flex"
        >
          Ctrl+K
        </kbd>
      </button>

      <button
        type="button"
        class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-border/80 bg-muted/40 text-muted-foreground shadow-sm transition-colors hover:bg-muted hover:text-foreground"
        :title="`Thème : ${themeLabel[preference] ?? preference}`"
        :aria-label="`Changer le thème (${themeLabel[preference] ?? preference})`"
        @click="cycleTheme"
      >
        <component :is="themeIcon[preference] ?? Monitor" class="h-4 w-4" />
      </button>

      <NotificationBell />
    </div>

    <div class="md:pl-64">
      <div class="h-14" aria-hidden="true" />

      <header v-if="$slots.header" class="px-4 pt-6 sm:px-6 sm:pt-8 md:px-8">
        <slot name="header" />
      </header>

      <main class="px-4 pb-10 pt-4 sm:px-6 sm:pb-12 sm:pt-6 md:px-8">
        <slot />
      </main>
    </div>

    <GlobalSearchModal v-model:open="searchOpen" />
  </div>
</template>

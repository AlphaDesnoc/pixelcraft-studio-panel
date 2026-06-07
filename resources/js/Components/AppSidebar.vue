<script setup>
import { computed } from "vue";
import { Link, usePage } from "@inertiajs/vue3";
import {
  LayoutDashboard,
  MessageSquare,
  UserCircle2,
  ShieldCheck,
  LogOut,
  Folder,
  CheckSquare,
} from "lucide-vue-next";
import ApplicationLogo from "@/Components/ApplicationLogo.vue";
import SidebarItem from "@/Components/SidebarItem.vue";
import DesktopAppPromo from "@/Components/DesktopAppPromo.vue";
import { Avatar } from "@/Components/ui/avatar";
import { unreadMessages } from "@/composables/useSiteRealtime.js";

const page = usePage();

const user = computed(() => page.props.auth?.user);

const isAdmin = computed(() => Boolean(user.value?.is_admin));

const projects = computed(() => page.props.sidebar?.projects ?? []);

const currentRoute = computed(() => page.props.ziggy?.location ?? "");

const isCurrent = (name, params = null) => {
  try {
    return route().current(name, params);
  } catch {
    return false;
  }
};

const userInitials = computed(() => {
  if (!user.value) return "";
  return user.value.name
    .split(" ")
    .map((part) => part.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();
});
</script>

<template>
  <aside
    class="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col border-r border-border/60 bg-background/95 backdrop-blur md:flex"
  >
    <div class="flex items-center gap-2 px-4 py-4">
      <ApplicationLogo :show-text="false" class="[&_img]:h-7" />
      <span class="text-sm font-semibold tracking-tight">
        PixelCraft Studio
      </span>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 pb-4">
      <p
        class="px-2.5 pb-2 pt-2 text-[10px] font-semibold uppercase tracking-[0.18em] text-muted-foreground/70"
      >
        Menu
      </p>
      <div class="flex flex-col gap-1">
        <SidebarItem
          :href="route('dashboard')"
          :active="isCurrent('dashboard')"
        >
          <template #icon><LayoutDashboard /></template>
          Dashboard
        </SidebarItem>
        <SidebarItem
          :href="route('messages.index')"
          :active="isCurrent('messages.*')"
          :badge="unreadMessages > 0 ? unreadMessages : null"
        >
          <template #icon><MessageSquare /></template>
          Messages
        </SidebarItem>
        <SidebarItem
          :href="route('my-tasks.index')"
          :active="isCurrent('my-tasks.*')"
        >
          <template #icon><CheckSquare /></template>
          Mes tâches
        </SidebarItem>
        <SidebarItem
          :href="route('profile.edit')"
          :active="isCurrent('profile.*')"
        >
          <template #icon><UserCircle2 /></template>
          Mon compte
        </SidebarItem>
        <SidebarItem
          v-if="isAdmin"
          :href="route('admin.users.index')"
          :active="isCurrent('admin.*')"
        >
          <template #icon><ShieldCheck /></template>
          Administration
        </SidebarItem>
        <DesktopAppPromo variant="sidebar" />
      </div>

      <p
        class="px-2.5 pb-2 pt-5 text-[10px] font-semibold uppercase tracking-[0.18em] text-muted-foreground/70"
      >
        Projets
      </p>
      <div class="flex flex-col gap-1">
        <SidebarItem
          v-for="project in projects"
          :key="project.id"
          :href="route('projects.show', project.slug)"
          :active="isCurrent('projects.show', { project: project.slug })"
        >
          <template #icon>
            <Avatar
              :src="project.image_url ?? ''"
              :fallback="project.name.charAt(0).toUpperCase()"
              size="xs"
              rounded="md"
            />
          </template>
          {{ project.name }}
        </SidebarItem>
        <p
          v-if="projects.length === 0"
          class="px-2.5 py-1 text-xs text-muted-foreground/70"
        >
          Aucun projet
        </p>
      </div>
    </nav>

    <div class="border-t border-border/60 px-3 py-3">
      <div class="flex items-center gap-2.5 px-1 py-1.5">
        <Avatar :src="user?.avatar_url ?? ''" :fallback="userInitials" size="sm" />
        <div class="min-w-0 flex-1">
          <p class="truncate text-xs font-semibold leading-tight">
            {{ user?.name }}
          </p>
          <p class="truncate text-[11px] leading-tight text-muted-foreground">
            {{ user?.email }}
          </p>
        </div>
      </div>
      <Link
        :href="route('logout')"
        method="post"
        as="button"
        class="mt-1 flex w-full items-center gap-2 rounded-md px-2.5 py-2 text-[13px] text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
      >
        <LogOut class="h-4 w-4" />
        Déconnexion
      </Link>
    </div>
  </aside>
</template>

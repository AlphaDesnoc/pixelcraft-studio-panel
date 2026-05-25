<script setup>
import { Link } from "@inertiajs/vue3";
import { cn } from "@/lib/utils";

const isCurrent = (name) => {
  try {
    return route().current(name);
  } catch {
    return false;
  }
};

const tabs = [
  { name: "Utilisateurs", route: "admin.users.index" },
  { name: "Projets", route: "admin.projects.index" },
];
</script>

<template>
  <nav
    class="flex items-center gap-1 rounded-lg border border-border/60 bg-card/60 p-1"
  >
    <Link
      v-for="tab in tabs"
      :key="tab.route"
      :href="route(tab.route)"
      :class="
        cn(
          'inline-flex h-8 items-center justify-center rounded-md px-3 text-sm font-medium transition-colors',
          isCurrent(tab.route)
            ? 'bg-primary/15 text-foreground ring-1 ring-primary/30'
            : 'text-muted-foreground hover:bg-muted hover:text-foreground',
        )
      "
    >
      {{ tab.name }}
    </Link>
  </nav>
</template>

<script setup>
import { Head } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AdminTabs from "@/Components/AdminTabs.vue";
import { Card } from "@/Components/ui/card";

defineProps({
  logs: { type: Array, default: () => [] },
});

function formatDate(iso) {
  if (!iso) return "—";
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}
</script>

<template>
  <Head title="Journal d'audit" />

  <AuthenticatedLayout>
    <template #header>
      <div>
        <h1 class="text-xl font-semibold tracking-tight">Journal d'audit</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          Historique des actions administratives et sensibles
        </p>
      </div>
    </template>

    <AdminTabs class="mb-4" />

    <Card class="overflow-hidden p-0">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border/60 text-left text-muted-foreground">
              <th class="px-5 py-3 text-xs font-medium">Date</th>
              <th class="px-5 py-3 text-xs font-medium">Utilisateur</th>
              <th class="px-5 py-3 text-xs font-medium">Action</th>
              <th class="px-5 py-3 text-xs font-medium">Message</th>
              <th class="px-5 py-3 text-xs font-medium">IP</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="log in logs"
              :key="log.id"
              class="border-b border-border/40 last:border-b-0 hover:bg-muted/30"
            >
              <td class="px-5 py-4 whitespace-nowrap text-muted-foreground">
                {{ formatDate(log.created_at) }}
              </td>
              <td class="px-5 py-4">
                <div class="font-medium text-foreground">
                  {{ log.user?.name ?? "Système" }}
                </div>
                <div v-if="log.user?.email" class="text-xs text-muted-foreground">
                  {{ log.user.email }}
                </div>
              </td>
              <td class="px-5 py-4">
                <code class="rounded bg-muted px-1.5 py-0.5 text-xs">
                  {{ log.action }}
                </code>
              </td>
              <td class="px-5 py-4 text-foreground">
                {{ log.message }}
              </td>
              <td class="px-5 py-4 text-muted-foreground">
                {{ log.ip_address ?? "—" }}
              </td>
            </tr>

            <tr v-if="logs.length === 0">
              <td colspan="5" class="px-5 py-10 text-center text-muted-foreground">
                Aucune entrée d'audit pour l'instant.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>
  </AuthenticatedLayout>
</template>

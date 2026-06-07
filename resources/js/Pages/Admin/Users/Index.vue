<script setup>
import { computed, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { Pencil, Plus, Trash2 } from "lucide-vue-next";
import { confirmDialog } from "@/composables/useConfirm.js";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AdminTabs from "@/Components/AdminTabs.vue";
import UserFormDialog from "@/Components/Admin/UserFormDialog.vue";
import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { Card } from "@/Components/ui/card";
import { Switch } from "@/Components/ui/switch";

defineProps({
  users: { type: Array, required: true },
  roles: { type: Object, required: true },
  emailDomain: { type: String, required: true },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);

const dialogOpen = ref(false);
const editingUser = ref(null);
const togglingUserId = ref(null);

const openCreate = () => {
  editingUser.value = null;
  dialogOpen.value = true;
};

const openEdit = (user) => {
  editingUser.value = user;
  dialogOpen.value = true;
};

const dateFormatter = new Intl.DateTimeFormat("fr-FR", {
  day: "numeric",
  month: "short",
  year: "numeric",
});

const formatDate = (iso) => {
  if (!iso) return "—";
  return dateFormatter.format(new Date(iso));
};

const confirmDelete = async (user) => {
  if (user.id === currentUserId.value) {
    window.alert("Vous ne pouvez pas supprimer votre propre compte.");
    return;
  }
  if (
    !(await confirmDialog({
      title: "Supprimer le compte",
      message: `Le compte de ${user.name} sera définitivement supprimé.`,
    }))
  ) {
    return;
  }
  router.delete(route("admin.users.destroy", user.id), {
    preserveScroll: true,
  });
};

const roleVariant = (role) => (role === "admin" ? "default" : "secondary");

const toggleActive = (user, isActive) => {
  if (user.id === currentUserId.value) {
    return;
  }

  togglingUserId.value = user.id;

  router.patch(
    route("admin.users.toggle-active", user.id),
    { is_active: isActive },
    {
      preserveScroll: true,
      onFinish: () => {
        togglingUserId.value = null;
      },
    },
  );
};
</script>

<template>
  <Head title="Utilisateurs" />

  <AuthenticatedLayout>
    <template #header>
      <div class="flex items-start justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold tracking-tight">Utilisateurs</h1>
          <p class="mt-1 text-sm text-muted-foreground">
            Créer et gérer les comptes
          </p>
        </div>

        <Button class="gap-1.5" @click="openCreate">
          <Plus class="h-4 w-4" />
          Nouvel utilisateur
        </Button>
      </div>
    </template>

    <AdminTabs class="mb-4" />

    <Card class="overflow-hidden p-0">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-border/60 text-left text-muted-foreground">
              <th class="px-5 py-3 text-xs font-medium">Nom</th>
              <th class="px-5 py-3 text-xs font-medium">Email</th>
              <th class="px-5 py-3 text-xs font-medium">Rôle</th>
              <th class="px-5 py-3 text-xs font-medium">Projets</th>
              <th class="px-5 py-3 text-xs font-medium">Tâches</th>
              <th class="px-5 py-3 text-xs font-medium">Actif</th>
              <th class="px-5 py-3 text-xs font-medium">Créé le</th>
              <th class="px-5 py-3 text-right text-xs font-medium"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="user in users"
              :key="user.id"
              class="border-b border-border/40 last:border-b-0 hover:bg-muted/30"
              :class="{ 'opacity-60': !user.is_active }"
            >
              <td class="px-5 py-4 font-semibold text-foreground">
                {{ user.name }}
              </td>
              <td class="px-5 py-4 text-muted-foreground">
                {{ user.email }}
              </td>
              <td class="px-5 py-4">
                <Badge :variant="roleVariant(user.role)">
                  {{ roles[user.role] ?? user.role }}
                </Badge>
              </td>
              <td class="px-5 py-4 text-muted-foreground">
                {{ user.projects_count }}
              </td>
              <td class="px-5 py-4 text-muted-foreground">
                {{ user.tasks_count }}
              </td>
              <td class="px-5 py-4">
                <Switch
                  :model-value="user.is_active"
                  :disabled="
                    user.id === currentUserId || togglingUserId === user.id
                  "
                  :aria-label="
                    user.is_active
                      ? `Désactiver ${user.name}`
                      : `Activer ${user.name}`
                  "
                  @update:model-value="toggleActive(user, $event)"
                />
              </td>
              <td class="px-5 py-4 text-muted-foreground">
                {{ formatDate(user.created_at) }}
              </td>
              <td class="px-5 py-4">
                <div class="flex items-center justify-end gap-3 text-muted-foreground">
                  <button
                    type="button"
                    class="rounded-md p-1 transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Modifier"
                    @click="openEdit(user)"
                  >
                    <Pencil class="h-4 w-4" />
                  </button>
                  <button
                    type="button"
                    class="rounded-md p-1 text-rose-400 transition-colors hover:bg-rose-500/10 hover:text-rose-300 disabled:opacity-40"
                    :disabled="user.id === currentUserId"
                    :aria-label="
                      user.id === currentUserId
                        ? 'Impossible de supprimer votre compte'
                        : 'Supprimer'
                    "
                    @click="confirmDelete(user)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </button>
                </div>
              </td>
            </tr>

            <tr v-if="users.length === 0">
              <td colspan="8" class="px-5 py-10 text-center text-muted-foreground">
                Aucun utilisateur pour l'instant.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </Card>

    <UserFormDialog
      v-model:open="dialogOpen"
      :user="editingUser"
      :roles="roles"
      :email-domain="emailDomain"
    />
  </AuthenticatedLayout>
</template>

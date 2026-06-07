<script setup>
import { computed, ref } from "vue";
import { Head, router, usePage } from "@inertiajs/vue3";
import { Camera, Trash2 } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";
import NotificationPreferencesForm from "./Partials/NotificationPreferencesForm.vue";
import TwoFactorSetup from "./Partials/TwoFactorSetup.vue";
import DesktopAppPromo from "@/Components/DesktopAppPromo.vue";
import { Avatar } from "@/Components/ui/avatar";
import { Button } from "@/Components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";

const page = usePage();
const user = computed(() => page.props.auth?.user);

defineProps({
  notificationPreferences: { type: Object, default: () => ({}) },
  notificationTypes: { type: Array, default: () => [] },
  two_factor_enabled: { type: Boolean, default: false },
});

const initials = computed(() => {
  if (!user.value) return "";
  return user.value.name
    .split(" ")
    .map((part) => part.charAt(0))
    .slice(0, 2)
    .join("")
    .toUpperCase();
});

const avatarInput = ref(null);
const uploadingAvatar = ref(false);

function pickAvatar() {
  avatarInput.value?.click();
}

function onAvatarSelected(event) {
  const file = event.target.files?.[0];
  event.target.value = "";
  if (!file || uploadingAvatar.value) return;

  uploadingAvatar.value = true;
  router.post(
    route("profile.avatar.update"),
    { avatar: file },
    {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => {
        uploadingAvatar.value = false;
      },
    },
  );
}

function removeAvatar() {
  if (!confirm("Supprimer votre photo de profil ?")) return;
  router.delete(route("profile.avatar.destroy"), { preserveScroll: true });
}
</script>

<template>
  <Head title="Mon compte" />

  <AuthenticatedLayout>
    <template #header>
      <h1 class="text-xl font-semibold tracking-tight">Mon compte</h1>
      <p class="mt-1 text-sm text-muted-foreground">
        Consultez vos informations et modifiez votre mot de passe.
      </p>
    </template>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <Card>
        <CardHeader>
          <CardTitle>Informations</CardTitle>
          <CardDescription>
            Vos identifiants sont gérés par un administrateur.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div class="flex items-center gap-4">
            <div class="group relative shrink-0">
              <Avatar
                :src="user?.avatar_url ?? ''"
                :fallback="initials"
                size="xl"
                rounded="full"
              />
              <button
                type="button"
                class="absolute inset-0 flex items-center justify-center rounded-full bg-black/50 text-white opacity-0 transition-opacity group-hover:opacity-100 disabled:opacity-100"
                :disabled="uploadingAvatar"
                title="Changer la photo"
                @click="pickAvatar"
              >
                <Camera class="h-5 w-5" />
              </button>
            </div>

            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold leading-tight">
                {{ user?.name }}
              </p>
              <p class="truncate text-xs text-muted-foreground">
                {{ user?.email }}
              </p>
              <div class="mt-2 flex flex-wrap items-center gap-2">
                <Button
                  size="sm"
                  variant="outline"
                  class="h-7 gap-1.5 text-xs"
                  :disabled="uploadingAvatar"
                  @click="pickAvatar"
                >
                  <Camera class="h-3.5 w-3.5" />
                  {{ uploadingAvatar ? "Envoi…" : "Changer la photo" }}
                </Button>
                <Button
                  v-if="user?.avatar_url"
                  size="sm"
                  variant="ghost"
                  class="h-7 gap-1.5 text-xs text-rose-400 hover:text-rose-300"
                  :disabled="uploadingAvatar"
                  @click="removeAvatar"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                  Supprimer
                </Button>
              </div>
            </div>
          </div>

          <input
            ref="avatarInput"
            type="file"
            accept="image/png,image/jpeg,image/webp,image/gif"
            class="hidden"
            @change="onAvatarSelected"
          />

          <dl class="mt-5 grid grid-cols-1 gap-3 text-sm">
            <div class="flex items-center justify-between gap-3 border-t border-border/60 pt-3">
              <dt class="text-muted-foreground">Pseudo</dt>
              <dd class="font-medium">{{ user?.name }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-border/60 pt-3">
              <dt class="text-muted-foreground">Email</dt>
              <dd class="font-medium">{{ user?.email }}</dd>
            </div>
          </dl>
        </CardContent>
      </Card>

      <Card class="lg:col-span-2">
        <CardHeader>
          <CardTitle>Notifications</CardTitle>
          <CardDescription>
            Choisissez les alertes affichées dans la cloche du panel.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <NotificationPreferencesForm
            :preferences="notificationPreferences"
            :types="notificationTypes"
          />
        </CardContent>
      </Card>

      <Card class="lg:col-span-2">
        <CardHeader>
          <CardTitle>Mot de passe</CardTitle>
          <CardDescription>
            Choisissez un mot de passe long et unique.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <UpdatePasswordForm />
        </CardContent>
      </Card>

      <div class="lg:col-span-2">
        <TwoFactorSetup :enabled="two_factor_enabled" />
      </div>

      <div class="lg:col-span-2">
        <DesktopAppPromo variant="card" />
      </div>
    </div>
  </AuthenticatedLayout>
</template>

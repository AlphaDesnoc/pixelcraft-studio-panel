<script setup>
import { computed } from "vue";
import { Head, usePage } from "@inertiajs/vue3";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm.vue";
import { Avatar } from "@/Components/ui/avatar";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";

const page = usePage();
const user = computed(() => page.props.auth?.user);

const initials = computed(() => {
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
          <div class="flex items-center gap-3">
            <Avatar :fallback="initials" size="lg" />
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold leading-tight">
                {{ user?.name }}
              </p>
              <p class="truncate text-xs text-muted-foreground">
                {{ user?.email }}
              </p>
            </div>
          </div>

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

      <Card>
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
    </div>
  </AuthenticatedLayout>
</template>

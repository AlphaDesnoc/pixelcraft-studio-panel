<script setup>
import GuestLayout from "@/Layouts/GuestLayout.vue";
import InputError from "@/Components/InputError.vue";
import { Button } from "@/Components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Head, useForm } from "@inertiajs/vue3";

defineProps({
  status: {
    type: String,
    default: "",
  },
});

const form = useForm({
  email: "",
  password: "",
  remember: false,
});

const submit = () => {
  form.post(route("login"), {
    onFinish: () => form.reset("password"),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Connexion" />

    <Card>
      <CardHeader>
        <CardTitle>Connexion</CardTitle>
        <CardDescription>Accédez à vos projets et équipes</CardDescription>
      </CardHeader>

      <CardContent>
        <p
          v-if="status"
          class="mb-4 rounded-md border border-emerald-500/30 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-300"
        >
          {{ status }}
        </p>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
          <div class="flex flex-col gap-2">
            <Label for="email">Email</Label>
            <Input
              id="email"
              v-model="form.email"
              type="email"
              placeholder="vous@exemple.com"
              required
              autofocus
              autocomplete="username"
            />
            <InputError :message="form.errors.email" />
          </div>

          <div class="flex flex-col gap-2">
            <Label for="password">Mot de passe</Label>
            <Input
              id="password"
              v-model="form.password"
              type="password"
              placeholder="••••••••"
              required
              autocomplete="current-password"
            />
            <InputError :message="form.errors.password" />
          </div>

          <Button
            type="submit"
            class="mt-2 h-10 w-full text-sm font-medium"
            :disabled="form.processing"
            :class="{ 'opacity-60': form.processing }"
          >
            {{ form.processing ? "Connexion…" : "Se connecter" }}
          </Button>
        </form>
      </CardContent>
    </Card>
  </GuestLayout>
</template>

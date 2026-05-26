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

const form = useForm({
  code: "",
});

const submit = () => {
  form.post(route("two-factor.login"), {
    onFinish: () => form.reset("code"),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Double authentification" />

    <Card>
      <CardHeader>
        <CardTitle>Double authentification</CardTitle>
        <CardDescription>
          Saisissez le code à six chiffres de votre application d'authentification,
          ou un code de récupération.
        </CardDescription>
      </CardHeader>

      <CardContent>
        <form class="flex flex-col gap-4" @submit.prevent="submit">
          <div class="flex flex-col gap-2">
            <Label for="code">Code ou code de récupération</Label>
            <Input
              id="code"
              v-model="form.code"
              type="text"
              maxlength="32"
              autocomplete="one-time-code"
              placeholder="123456 ou xxxx-xxxx-xxxx"
              class="font-mono"
              required
              autofocus
            />
            <InputError :message="form.errors.code" />
          </div>

          <Button
            type="submit"
            class="mt-2 h-10 w-full text-sm font-medium"
            :disabled="form.processing"
            :class="{ 'opacity-60': form.processing }"
          >
            {{ form.processing ? "Vérification…" : "Valider" }}
          </Button>
        </form>
      </CardContent>
    </Card>
  </GuestLayout>
</template>

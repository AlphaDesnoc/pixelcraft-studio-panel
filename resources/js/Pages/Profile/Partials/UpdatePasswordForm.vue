<script setup>
import { ref } from "vue";
import InputError from "@/Components/InputError.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { useForm } from "@inertiajs/vue3";

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
  current_password: "",
  password: "",
  password_confirmation: "",
});

const updatePassword = () => {
  form.put(route("password.update"), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
    onError: () => {
      if (form.errors.password) {
        form.reset("password", "password_confirmation");
        passwordInput.value?.$el?.focus?.();
      }
      if (form.errors.current_password) {
        form.reset("current_password");
        currentPasswordInput.value?.$el?.focus?.();
      }
    },
  });
};
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="updatePassword">
    <div class="flex flex-col gap-2">
      <Label for="current_password">Mot de passe actuel</Label>
      <Input
        id="current_password"
        ref="currentPasswordInput"
        v-model="form.current_password"
        type="password"
        autocomplete="current-password"
      />
      <InputError :message="form.errors.current_password" />
    </div>

    <div class="flex flex-col gap-2">
      <Label for="password">Nouveau mot de passe</Label>
      <Input
        id="password"
        ref="passwordInput"
        v-model="form.password"
        type="password"
        autocomplete="new-password"
      />
      <InputError :message="form.errors.password" />
    </div>

    <div class="flex flex-col gap-2">
      <Label for="password_confirmation">Confirmer le mot de passe</Label>
      <Input
        id="password_confirmation"
        v-model="form.password_confirmation"
        type="password"
        autocomplete="new-password"
      />
      <InputError :message="form.errors.password_confirmation" />
    </div>

    <div class="flex items-center gap-3">
      <Button type="submit" :disabled="form.processing">Mettre à jour</Button>
      <Transition
        enter-active-class="transition ease-in-out"
        enter-from-class="opacity-0"
        leave-active-class="transition ease-in-out"
        leave-to-class="opacity-0"
      >
        <p
          v-if="form.recentlySuccessful"
          class="text-sm text-muted-foreground"
        >
          Enregistré.
        </p>
      </Transition>
    </div>
  </form>
</template>

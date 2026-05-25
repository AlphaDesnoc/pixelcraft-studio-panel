<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import InputError from "@/Components/InputError.vue";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Select } from "@/Components/ui/select";

const props = defineProps({
  open: { type: Boolean, required: true },
  user: { type: Object, default: null },
  roles: { type: Object, required: true },
  emailDomain: { type: String, required: true },
});

const emits = defineEmits(["update:open", "saved"]);

const isEdit = computed(() => Boolean(props.user));

const form = useForm({
  name: "",
  pseudo: "",
  password: "",
  role: "member",
});

const reset = () => {
  if (isEdit.value && props.user) {
    form.name = props.user.name ?? "";
    form.pseudo = props.user.pseudo ?? "";
    form.password = "";
    form.role = props.user.role ?? "member";
  } else {
    form.name = "";
    form.pseudo = "";
    form.password = "";
    form.role = "member";
  }
  form.clearErrors();
};

watch(
  () => [props.open, props.user?.id],
  ([open]) => {
    if (open) reset();
  },
);

const submit = () => {
  const onSuccess = () => {
    emits("saved");
    emits("update:open", false);
  };

  if (isEdit.value) {
    form.put(route("admin.users.update", props.user.id), {
      preserveScroll: true,
      onSuccess,
    });
  } else {
    form.post(route("admin.users.store"), {
      preserveScroll: true,
      onSuccess,
    });
  }
};

const close = () => emits("update:open", false);
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>
          <template v-if="isEdit"> Modifier — {{ user?.name }} </template>
          <template v-else> Nouvel utilisateur </template>
        </DialogTitle>
        <DialogDescription v-if="!isEdit">
          Renseignez les informations du compte à créer.
        </DialogDescription>
      </DialogHeader>

      <form class="flex flex-col gap-3.5" @submit.prevent="submit">
        <div class="flex flex-col gap-1.5">
          <Label for="user-name" class="sr-only">
            {{ isEdit ? "Nom" : "Nom complet" }}
          </Label>
          <Input
            id="user-name"
            v-model="form.name"
            type="text"
            :placeholder="isEdit ? 'Nom' : 'Nom complet'"
            required
            autofocus
          />
          <InputError :message="form.errors.name" />
        </div>

        <div class="flex flex-col gap-1.5">
          <Label for="user-pseudo" class="text-xs text-muted-foreground">
            Pseudo
          </Label>
          <div
            class="flex h-10 items-center rounded-md border border-input bg-background/40 text-sm shadow-sm focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2 focus-within:ring-offset-background"
          >
            <input
              id="user-pseudo"
              v-model="form.pseudo"
              type="text"
              placeholder="pseudo"
              required
              class="h-full min-w-0 flex-1 rounded-md bg-transparent px-3 text-foreground placeholder:text-muted-foreground focus:outline-none"
            />
            <span class="shrink-0 pr-3 text-xs text-muted-foreground">
              @{{ emailDomain }}
            </span>
          </div>
          <InputError :message="form.errors.pseudo" />
        </div>

        <div class="flex flex-col gap-1.5">
          <Label for="user-password" class="sr-only">Mot de passe</Label>
          <Input
            id="user-password"
            v-model="form.password"
            type="password"
            :placeholder="
              isEdit
                ? 'Nouveau mot de passe (laisser vide pour ne pas changer)'
                : 'Mot de passe'
            "
            :required="!isEdit"
            autocomplete="new-password"
          />
          <InputError :message="form.errors.password" />
        </div>

        <div class="flex flex-col gap-1.5">
          <Label for="user-role" class="sr-only">Rôle</Label>
          <Select id="user-role" v-model="form.role">
            <option v-for="(label, key) in roles" :key="key" :value="key">
              {{ label }}
            </option>
          </Select>
          <InputError :message="form.errors.role" />
        </div>

        <Button
          type="submit"
          class="mt-1 h-10 w-full"
          :disabled="form.processing"
        >
          {{
            form.processing
              ? "Enregistrement…"
              : isEdit
                ? "Enregistrer"
                : "Créer"
          }}
        </Button>
      </form>
    </DialogContent>
  </Dialog>
</template>

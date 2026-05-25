<script setup>
import { computed, ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import { ImagePlus } from "lucide-vue-next";
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
import { Textarea } from "@/Components/ui/textarea";

const props = defineProps({
  open: { type: Boolean, required: true },
  project: { type: Object, default: null },
  statuses: { type: Object, required: true },
});

const emits = defineEmits(["update:open", "saved"]);

const isEdit = computed(() => Boolean(props.project));

const fileInput = ref(null);
const previewUrl = ref(null);

const form = useForm({
  name: "",
  description: "",
  image: null,
  remove_image: false,
  status: "active",
  start_date: "",
});

const reset = () => {
  previewUrl.value = null;
  if (fileInput.value) fileInput.value.value = "";

  if (isEdit.value && props.project) {
    form.name = props.project.name ?? "";
    form.description = props.project.description ?? "";
    form.image = null;
    form.remove_image = false;
    form.status = props.project.status ?? "active";
    form.start_date = props.project.start_date
      ? String(props.project.start_date).substring(0, 10)
      : "";
  } else {
    form.name = "";
    form.description = "";
    form.image = null;
    form.remove_image = false;
    form.status = "active";
    form.start_date = "";
  }
  form.clearErrors();
};

watch(
  () => [props.open, props.project?.id],
  ([open]) => {
    if (open) reset();
  },
);

const onFileChange = (event) => {
  const file = event.target.files?.[0] ?? null;
  form.image = file;
  form.remove_image = false;
  previewUrl.value = file ? URL.createObjectURL(file) : null;
};

const currentImageUrl = computed(() => {
  if (previewUrl.value) return previewUrl.value;
  if (isEdit.value && props.project?.image_url && !form.remove_image) {
    return props.project.image_url;
  }
  return null;
});

const fileLabel = computed(() => {
  if (form.image?.name) return form.image.name;
  if (currentImageUrl.value && isEdit.value) return "Image actuelle";
  return "Aucun fichier choisi";
});

const submit = () => {
  const onSuccess = () => {
    emits("saved");
    emits("update:open", false);
  };

  if (isEdit.value) {
    form.post(route("admin.projects.update", props.project.slug), {
      preserveScroll: true,
      forceFormData: true,
      onSuccess,
    });
  } else {
    form.post(route("admin.projects.store"), {
      preserveScroll: true,
      forceFormData: true,
      onSuccess,
    });
  }
};

const removeImage = () => {
  form.image = null;
  form.remove_image = true;
  previewUrl.value = null;
  if (fileInput.value) fileInput.value.value = "";
};
</script>

<template>
  <Dialog :open="open" @update:open="(v) => emits('update:open', v)">
    <DialogContent>
      <DialogHeader>
        <DialogTitle>
          <template v-if="isEdit"> Modifier — {{ project?.name }} </template>
          <template v-else> Nouveau projet </template>
        </DialogTitle>
        <DialogDescription v-if="!isEdit">
          Renseignez les informations du projet à créer.
        </DialogDescription>
      </DialogHeader>

      <form class="flex flex-col gap-3.5" @submit.prevent="submit">
        <div class="flex flex-col gap-1.5">
          <Label for="project-name" class="sr-only">Nom du projet</Label>
          <Input
            id="project-name"
            v-model="form.name"
            type="text"
            placeholder="Nom du projet"
            required
            autofocus
          />
          <InputError :message="form.errors.name" />
        </div>

        <div class="flex flex-col gap-1.5">
          <Label for="project-description" class="sr-only">Description</Label>
          <Textarea
            id="project-description"
            v-model="form.description"
            placeholder="Description"
            rows="3"
          />
          <InputError :message="form.errors.description" />
        </div>

        <div class="flex flex-col gap-1.5">
          <Label class="text-xs text-muted-foreground">Image du projet</Label>
          <div class="flex items-center gap-2">
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-md border border-border/60 bg-muted/40 text-muted-foreground"
            >
              <img
                v-if="currentImageUrl"
                :src="currentImageUrl"
                alt=""
                class="h-full w-full object-cover"
              />
              <ImagePlus v-else class="h-4 w-4" />
            </div>

            <label
              class="flex h-10 min-w-0 flex-1 cursor-pointer items-center gap-2 rounded-md border border-input bg-background/40 px-3 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            >
              <span class="rounded bg-muted px-2 py-1 text-xs font-medium text-foreground">
                Choisir un fichier
              </span>
              <span class="truncate">{{ fileLabel }}</span>
              <input
                ref="fileInput"
                type="file"
                class="hidden"
                accept="image/jpeg,image/png,image/gif,image/webp"
                @change="onFileChange"
              />
            </label>

            <button
              v-if="isEdit && currentImageUrl"
              type="button"
              class="text-xs text-rose-300 hover:text-rose-200"
              @click="removeImage"
            >
              Retirer
            </button>
          </div>
          <p class="text-[11px] text-muted-foreground">
            JPEG, PNG, GIF ou WebP — max 5 Mo
          </p>
          <InputError :message="form.errors.image" />
        </div>

        <div class="flex flex-col gap-1.5">
          <Label for="project-status" class="text-xs text-muted-foreground">
            Statut
          </Label>
          <Select id="project-status" v-model="form.status">
            <option v-for="(label, key) in statuses" :key="key" :value="key">
              {{ label }}
            </option>
          </Select>
          <InputError :message="form.errors.status" />
        </div>

        <div class="flex flex-col gap-1.5">
          <Label for="project-start-date" class="text-xs text-muted-foreground">
            Date de début (optionnel)
          </Label>
          <Input
            id="project-start-date"
            v-model="form.start_date"
            type="date"
          />
          <InputError :message="form.errors.start_date" />
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
                : "Créer le projet"
          }}
        </Button>
      </form>
    </DialogContent>
  </Dialog>
</template>

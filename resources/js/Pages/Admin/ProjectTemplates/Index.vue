<script setup>
import { ref } from "vue";
import { router, useForm } from "@inertiajs/vue3";
import { Plus, Trash2 } from "lucide-vue-next";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout.vue";
import AdminTabs from "@/Components/AdminTabs.vue";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Textarea } from "@/Components/ui/textarea";

defineProps({
  templates: { type: Array, default: () => [] },
});

const showForm = ref(false);
const form = useForm({ name: "", description: "", payload: { lists: [], ranks: [] } });
const payloadJson = ref('{"lists":[],"ranks":[]}');

function submit() {
  try {
    form.payload = JSON.parse(payloadJson.value || "{}");
  } catch {
    form.setError("payload", "JSON invalide");
    return;
  }
  form.post(route("admin.project-templates.store"), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      payloadJson.value = '{"lists":[],"ranks":[]}';
      showForm.value = false;
    },
  });
}

function removeTemplate(id) {
  if (!confirm("Supprimer ce modèle ?")) return;
  router.delete(route("admin.project-templates.destroy", id), { preserveScroll: true });
}
</script>

<template>
  <AuthenticatedLayout>
    <div class="mx-auto max-w-4xl space-y-6 p-4 sm:p-6">
      <AdminTabs />
      <header class="flex items-center justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold">Modèles de projet</h1>
          <p class="text-sm text-muted-foreground">Structure préconfigurée pour nouveaux projets</p>
        </div>
        <Button type="button" size="sm" @click="showForm = !showForm"><Plus class="h-4 w-4" /> Nouveau</Button>
      </header>

      <form v-if="showForm" class="space-y-3 rounded-xl border border-border bg-card p-4" @submit.prevent="submit">
        <Input v-model="form.name" placeholder="Nom" required />
        <Textarea v-model="form.description" placeholder="Description" rows="2" />
        <Textarea v-model="payloadJson" placeholder='Payload JSON (lists, ranks…)' rows="4" />
        <p v-if="form.errors.payload" class="text-xs text-destructive">{{ form.errors.payload }}</p>
        <Button type="submit" size="sm" :disabled="form.processing">Créer</Button>
      </form>

      <ul class="divide-y divide-border rounded-xl border border-border bg-card">
        <li v-for="tpl in templates" :key="tpl.id" class="flex items-center justify-between px-4 py-3">
          <div>
            <p class="font-medium">{{ tpl.name }}</p>
            <p class="text-sm text-muted-foreground">{{ tpl.description }}</p>
          </div>
          <Button type="button" size="sm" variant="ghost" @click="removeTemplate(tpl.id)"><Trash2 class="h-4 w-4" /></Button>
        </li>
      </ul>
    </div>
  </AuthenticatedLayout>
</template>

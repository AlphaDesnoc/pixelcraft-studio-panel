<script setup>
import { computed, ref } from "vue";
import axios from "axios";
import { router } from "@inertiajs/vue3";
import { Loader2, ShieldCheck } from "lucide-vue-next";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/Components/ui/card";

const props = defineProps({
  enabled: { type: Boolean, default: false },
});

const otpauthUri = ref("");
const code = ref("");
const recoveryCodes = ref([]);
const loadingSetup = ref(false);
const confirming = ref(false);
const disabling = ref(false);
const error = ref("");

const qrUrl = computed(() =>
  otpauthUri.value
    ? `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(
        otpauthUri.value,
      )}`
    : "",
);

async function setup() {
  error.value = "";
  loadingSetup.value = true;
  try {
    const { data } = await axios.post(route("profile.two-factor.setup"));
    otpauthUri.value = data.otpauth_uri ?? "";
    code.value = "";
  } catch (e) {
    error.value =
      e.response?.data?.message ??
      "Impossible de générer les paramètres 2FA pour le moment.";
    otpauthUri.value = "";
  } finally {
    loadingSetup.value = false;
  }
}

async function confirm() {
  if (!code.value.trim()) return;
  error.value = "";
  confirming.value = true;
  try {
    const { data } = await axios.post(route("profile.two-factor.confirm"), {
      code: code.value.trim(),
    });
    recoveryCodes.value = data.recovery_codes ?? [];
    if (!recoveryCodes.value.length) {
      await router.reload();
    }
  } catch (e) {
    error.value = e.response?.data?.message ?? "Code invalide.";
  } finally {
    confirming.value = false;
  }
}

async function disable2fa() {
  if (!window.confirm("Désactiver la double authentification ?")) return;
  error.value = "";
  disabling.value = true;
  try {
    await axios.delete(route("profile.two-factor.disable"));
    otpauthUri.value = "";
    code.value = "";
    await router.reload();
  } catch (e) {
    error.value = e.response?.data?.message ?? "Échec de la désactivation.";
  } finally {
    disabling.value = false;
  }
}
</script>

<template>
  <Card>
    <CardHeader class="gap-2">
      <div class="flex items-center gap-2">
        <ShieldCheck class="h-5 w-5 text-primary" />
        <CardTitle class="text-base">Double authentification</CardTitle>
      </div>
      <CardDescription>
        Sécurisez votre compte avec un code depuis une app d'authentification
        (Google Authenticator, 1Password, etc.).
      </CardDescription>
    </CardHeader>
    <CardContent class="flex flex-col gap-4">
      <p v-if="props.enabled" class="text-sm text-emerald-500">
        • L'A2F est active sur ce compte.
      </p>

      <p v-if="error" class="text-sm text-rose-400">
        {{ error }}
      </p>

      <div v-if="!props.enabled" class="flex flex-wrap gap-2">
        <Button
          type="button"
          variant="outline"
          :disabled="loadingSetup"
          class="gap-2"
          @click="setup"
        >
          <Loader2 v-if="loadingSetup" class="h-4 w-4 animate-spin" />
          {{ otpauthUri ? "Régénérer QR" : "Configurer l'A2F" }}
        </Button>
      </div>

      <div v-if="!props.enabled && otpauthUri" class="rounded-lg border border-border/60 bg-muted/20 p-3">
        <p class="text-xs font-medium text-foreground">
          Scanner le QR puis saisir le code à six chiffres
        </p>
        <div class="mt-3 flex flex-wrap items-start gap-4">
          <img
            v-if="qrUrl"
            :src="qrUrl"
            alt=""
            width="180"
            height="180"
            class="rounded-md border border-border bg-background p-2"
          />
          <div class="min-w-[200px] flex-1 space-y-2">
            <p class="break-all font-mono text-[11px] text-muted-foreground">
              {{ otpauthUri }}
            </p>
            <div class="flex flex-wrap gap-2">
              <Input
                v-model="code"
                type="text"
                maxlength="10"
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="123456"
                class="max-w-[9rem]"
              />
              <Button
                type="button"
                :disabled="confirming || !code.trim()"
                class="gap-2"
                @click="confirm"
              >
                <Loader2 v-if="confirming" class="h-4 w-4 animate-spin" />
                Valider
              </Button>
            </div>
          </div>
        </div>
      </div>

      <div v-if="recoveryCodes.length" class="rounded-lg border border-emerald-500/30 bg-emerald-500/10 p-4">
        <p class="text-sm font-medium text-emerald-400">
          A2F activée — conservez ces codes de récupération
        </p>
        <p class="mt-1 text-xs text-muted-foreground">
          Chaque code ne peut être utilisé qu'une seule fois si vous perdez l'accès à votre app.
        </p>
        <ul class="mt-3 grid gap-2 sm:grid-cols-2">
          <li
            v-for="recoveryCode in recoveryCodes"
            :key="recoveryCode"
            class="rounded-md border border-border/60 bg-background/60 px-3 py-2 font-mono text-sm"
          >
            {{ recoveryCode }}
          </li>
        </ul>
        <Button type="button" class="mt-4" @click="router.reload()">
          Terminer
        </Button>
      </div>

      <Button
        v-if="props.enabled"
        type="button"
        variant="destructive"
        class="max-w-fit"
        :disabled="disabling"
        @click="disable2fa"
      >
        <Loader2 v-if="disabling" class="h-4 w-4 animate-spin" />
        Désactiver la 2FA
      </Button>
    </CardContent>
  </Card>
</template>

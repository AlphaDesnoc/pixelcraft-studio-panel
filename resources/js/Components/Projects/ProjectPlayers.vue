<script setup>
import { computed, ref } from "vue";
import { router } from "@inertiajs/vue3";
import {
  Check,
  Copy,
  Gamepad2,
  KeyRound,
  RefreshCw,
  Search,
  Trash2,
  Users,
} from "lucide-vue-next";
import { Button } from "@/Components/ui/button";

const props = defineProps({
  projectSlug: { type: String, required: true },
  server: { type: Object, default: null },
  players: { type: Array, default: () => [] },
});

const search = ref("");
const copied = ref("");

const panelUrl = computed(() =>
  typeof window !== "undefined" ? window.location.origin : "",
);
const apiBase = computed(() => `${panelUrl.value}/api/v1/plugin`);

const onlineCount = computed(
  () => props.players.filter((p) => p.online).length,
);

const filteredPlayers = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return props.players;
  return props.players.filter(
    (p) =>
      p.name?.toLowerCase().includes(q) ||
      p.uuid?.toLowerCase().includes(q) ||
      p.ip?.toLowerCase().includes(q),
  );
});

async function copy(value, key) {
  try {
    await navigator.clipboard.writeText(value);
    copied.value = key;
    setTimeout(() => {
      if (copied.value === key) copied.value = "";
    }, 1500);
  } catch {
    /* clipboard indisponible */
  }
}

function regenerateToken() {
  if (
    !window.confirm(
      "Régénérer l'identifiant ? L'ancien cessera de fonctionner et le serveur devra être relié à nouveau.",
    )
  )
    return;
  router.post(
    route("projects.minecraft.regenerate-token", props.projectSlug),
    {},
    { preserveScroll: true },
  );
}

function removePlayer(player) {
  if (!window.confirm(`Supprimer ${player.name} de la liste ?`)) return;
  router.delete(
    route("projects.minecraft.players.destroy", [props.projectSlug, player.id]),
    { preserveScroll: true },
  );
}

function clearOffline() {
  if (!window.confirm("Supprimer tous les joueurs actuellement hors-ligne ?"))
    return;
  router.delete(
    route("projects.minecraft.players.clear-offline", props.projectSlug),
    { preserveScroll: true },
  );
}

function formatDate(iso) {
  if (!iso) return "—";
  return new Intl.DateTimeFormat("fr-FR", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(iso));
}
</script>

<template>
  <div class="flex flex-col gap-4">
    <!-- Carte de liaison du serveur -->
    <div class="rounded-xl border border-border bg-card">
      <div class="flex items-center gap-2 border-b border-border px-4 py-3">
        <KeyRound class="h-4 w-4 text-primary" />
        <h3 class="text-sm font-semibold text-foreground">Lier un serveur</h3>
        <span
          v-if="server?.linked_at"
          class="ml-2 inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-[11px] font-medium text-emerald-500"
        >
          <Check class="h-3 w-3" /> Relié
        </span>
        <span
          v-else
          class="ml-2 rounded-full bg-amber-500/10 px-2 py-0.5 text-[11px] font-medium text-amber-500"
        >
          En attente de liaison
        </span>
      </div>

      <div class="space-y-4 px-4 py-4">
        <ol class="space-y-1 text-xs text-muted-foreground">
          <li>
            <span class="font-medium text-foreground">1.</span> Installez le plugin
            <span class="font-mono text-foreground">PixelCraftLink</span> sur votre
            serveur Spigot/Paper.
          </li>
          <li>
            <span class="font-medium text-foreground">2.</span> Renseignez une fois
            l'URL du panel dans <span class="font-mono text-foreground">config.yml</span>
            (champ <span class="font-mono text-foreground">panel-url</span>).
          </li>
          <li>
            <span class="font-medium text-foreground">3.</span> En console (ou en jeu en
            tant qu'opérateur), exécutez&nbsp;:
            <span class="font-mono text-foreground">/pixellink &lt;identifiant&gt;</span>
          </li>
        </ol>

        <div class="grid gap-3 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
              Identifiant de liaison
            </label>
            <div class="flex items-center gap-1">
              <code
                class="flex-1 truncate rounded-md border border-border bg-muted/40 px-2 py-1.5 font-mono text-sm font-semibold tracking-wider text-foreground"
              >
                {{ server?.link_code ?? "—" }}
              </code>
              <Button
                type="button"
                size="icon"
                variant="outline"
                class="h-8 w-8 shrink-0"
                @click="copy(server?.link_code ?? '', 'code')"
              >
                <Check v-if="copied === 'code'" class="h-3.5 w-3.5 text-emerald-500" />
                <Copy v-else class="h-3.5 w-3.5" />
              </Button>
            </div>
          </div>

          <div>
            <label class="mb-1 block text-[11px] font-medium text-muted-foreground">
              URL du panel (config.yml)
            </label>
            <div class="flex items-center gap-1">
              <code
                class="flex-1 truncate rounded-md border border-border bg-muted/40 px-2 py-1.5 font-mono text-xs"
              >
                {{ apiBase }}
              </code>
              <Button
                type="button"
                size="icon"
                variant="outline"
                class="h-8 w-8 shrink-0"
                @click="copy(apiBase, 'url')"
              >
                <Check v-if="copied === 'url'" class="h-3.5 w-3.5 text-emerald-500" />
                <Copy v-else class="h-3.5 w-3.5" />
              </Button>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-1">
          <code
            class="flex-1 truncate rounded-md border border-border bg-muted/40 px-2 py-1.5 font-mono text-xs"
          >
            /pixellink {{ server?.link_code ?? "<identifiant>" }}
          </code>
          <Button
            type="button"
            size="icon"
            variant="outline"
            class="h-8 w-8 shrink-0"
            @click="copy(`/pixellink ${server?.link_code ?? ''}`, 'cmd')"
          >
            <Check v-if="copied === 'cmd'" class="h-3.5 w-3.5 text-emerald-500" />
            <Copy v-else class="h-3.5 w-3.5" />
          </Button>
        </div>

        <p class="text-[11px] text-muted-foreground">
          Astuce&nbsp;: pour relier sans toucher au
          <span class="font-mono">config.yml</span>, utilisez
          <span class="font-mono text-foreground">/pixellink {{ apiBase }} {{ server?.link_code ?? "<identifiant>" }}</span>.
        </p>

        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
          <p class="text-[11px] text-muted-foreground">
            Dernière synchro&nbsp;: {{ formatDate(server?.last_synced_at) }}
            <span v-if="server?.last_ip"> · IP serveur {{ server.last_ip }}</span>
          </p>
          <Button
            type="button"
            size="sm"
            variant="outline"
            class="h-8"
            @click="regenerateToken"
          >
            <RefreshCw class="mr-1 h-3.5 w-3.5" />
            Régénérer l'identifiant
          </Button>
        </div>
      </div>
    </div>

    <!-- Liste des joueurs -->
    <div class="rounded-xl border border-border bg-card">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
        <div class="flex items-center gap-2">
          <Users class="h-4 w-4 text-primary" />
          <h3 class="text-sm font-semibold text-foreground">Joueurs</h3>
          <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">
            {{ players.length }} total · {{ onlineCount }} en ligne
          </span>
        </div>
        <div class="flex items-center gap-2">
          <div class="relative">
            <Search class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
            <input
              v-model="search"
              type="text"
              placeholder="Rechercher pseudo, UUID, IP…"
              class="h-8 w-56 rounded-md border border-border bg-background pl-7 pr-2 text-xs outline-none focus:ring-1 focus:ring-primary"
            />
          </div>
          <Button
            type="button"
            size="sm"
            variant="outline"
            class="h-8"
            @click="clearOffline"
          >
            Purger hors-ligne
          </Button>
        </div>
      </div>

      <div v-if="filteredPlayers.length" class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead>
            <tr class="border-b border-border/60 text-[11px] uppercase tracking-wide text-muted-foreground">
              <th class="px-4 py-2 font-medium">Joueur</th>
              <th class="px-4 py-2 font-medium">UUID</th>
              <th class="px-4 py-2 font-medium">IP</th>
              <th class="px-4 py-2 font-medium">Connexions</th>
              <th class="px-4 py-2 font-medium">Vu pour la 1re fois</th>
              <th class="px-4 py-2 font-medium">Vu récemment</th>
              <th class="px-4 py-2"></th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="player in filteredPlayers"
              :key="player.id"
              class="border-b border-border/40 last:border-0 hover:bg-muted/30"
            >
              <td class="px-4 py-2">
                <div class="flex items-center gap-2">
                  <span
                    class="h-2 w-2 shrink-0 rounded-full"
                    :class="player.online ? 'bg-emerald-500' : 'bg-muted-foreground/40'"
                    :title="player.online ? 'En ligne' : 'Hors-ligne'"
                  />
                  <span class="font-medium text-foreground">{{ player.name }}</span>
                </div>
              </td>
              <td class="px-4 py-2">
                <button
                  type="button"
                  class="font-mono text-xs text-muted-foreground hover:text-foreground"
                  title="Copier l'UUID"
                  @click="copy(player.uuid, `uuid-${player.id}`)"
                >
                  {{ player.uuid }}
                  <Check
                    v-if="copied === `uuid-${player.id}`"
                    class="ml-1 inline h-3 w-3 text-emerald-500"
                  />
                </button>
              </td>
              <td class="px-4 py-2">
                <button
                  type="button"
                  class="font-mono text-xs text-muted-foreground hover:text-foreground"
                  title="Copier l'IP"
                  @click="copy(player.ip ?? '', `ip-${player.id}`)"
                >
                  {{ player.ip ?? "—" }}
                  <Check
                    v-if="copied === `ip-${player.id}`"
                    class="ml-1 inline h-3 w-3 text-emerald-500"
                  />
                </button>
              </td>
              <td class="px-4 py-2 text-muted-foreground">{{ player.join_count }}</td>
              <td class="px-4 py-2 text-xs text-muted-foreground">
                {{ formatDate(player.first_seen_at) }}
              </td>
              <td class="px-4 py-2 text-xs text-muted-foreground">
                {{ formatDate(player.last_seen_at) }}
              </td>
              <td class="px-4 py-2 text-right">
                <Button
                  type="button"
                  size="icon"
                  variant="ghost"
                  class="h-7 w-7 text-muted-foreground hover:text-destructive"
                  title="Supprimer"
                  @click="removePlayer(player)"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </Button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div
        v-else
        class="flex flex-col items-center justify-center gap-2 px-4 py-12 text-center"
      >
        <Gamepad2 class="h-8 w-8 text-muted-foreground/50" />
        <p class="text-sm text-muted-foreground">
          {{
            players.length
              ? "Aucun joueur ne correspond à la recherche."
              : "Aucun joueur enregistré pour l'instant. Reliez votre serveur pour les voir apparaître."
          }}
        </p>
      </div>
    </div>
  </div>
</template>

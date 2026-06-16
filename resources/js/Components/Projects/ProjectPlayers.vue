<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { router } from "@inertiajs/vue3";
import {
  Activity,
  BarChart3,
  Check,
  ChevronDown,
  Copy,
  Gamepad2,
  Globe,
  KeyRound,
  MapPin,
  RefreshCw,
  Search,
  Server,
  Trash2,
  TrendingUp,
  UserPlus,
  Users,
  Wifi,
} from "lucide-vue-next";
import { Button } from "@/Components/ui/button";

const props = defineProps({
  projectSlug: { type: String, required: true },
  server: { type: Object, default: null },
  players: { type: Array, default: () => [] },
});

const search = ref("");
const copied = ref("");

// Copie réactive locale : alimentée par les props au départ puis rafraîchie
// via fetch sans recharger la page.
const liveServer = ref(props.server);
const livePlayers = ref(props.players);
const refreshing = ref(false);
const autoRefresh = ref(true);
const lastRefreshedAt = ref(null);
let pollTimer = null;

// Si Inertia recharge la page (navigation, action), on resynchronise.
watch(
  () => props.players,
  (val) => {
    livePlayers.value = val;
  },
);
watch(
  () => props.server,
  (val) => {
    liveServer.value = val;
  },
);

async function refresh() {
  if (refreshing.value) return;
  refreshing.value = true;
  try {
    const res = await fetch(
      route("projects.minecraft.players.index", props.projectSlug),
      {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      },
    );
    if (!res.ok) return;
    const data = await res.json();
    livePlayers.value = data.players ?? [];
    liveServer.value = data.server ?? liveServer.value;
    lastRefreshedAt.value = new Date();
  } catch {
    /* réseau indisponible : on garde les données courantes */
  } finally {
    refreshing.value = false;
  }
}

onMounted(() => {
  pollTimer = setInterval(() => {
    if (autoRefresh.value && document.visibilityState === "visible") {
      refresh();
    }
  }, 10000);
});

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer);
});

const panelUrl = computed(() =>
  typeof window !== "undefined" ? window.location.origin : "",
);
const apiBase = computed(() => `${panelUrl.value}/api/v1/plugin`);

const onlineCount = computed(
  () => livePlayers.value.filter((p) => p.online).length,
);

const showStats = ref(true);

// Indicateurs globaux (non filtrés par la recherche).
const stats = computed(() => {
  const players = livePlayers.value;
  const now = Date.now();
  const day = 86_400_000;
  const within = (iso, ms) => !!iso && now - new Date(iso).getTime() <= ms;
  return {
    total: players.length,
    online: players.filter((p) => p.online).length,
    connections: players.reduce((sum, p) => sum + (p.join_count || 0), 0),
    newWeek: players.filter((p) => within(p.first_seen_at, 7 * day)).length,
    activeDay: players.filter((p) => within(p.last_seen_at, day)).length,
    activeWeek: players.filter((p) => within(p.last_seen_at, 7 * day)).length,
    geoKnown: players.filter((p) => p.geo?.country).length,
    proxy: players.filter((p) => p.geo?.proxy).length,
    hosting: players.filter((p) => p.geo?.hosting).length,
    mobile: players.filter((p) => p.geo?.mobile).length,
  };
});

// Drapeau emoji à partir d'un code pays ISO-3166 alpha-2 (ex. "FR" → 🇫🇷).
function flagEmoji(code) {
  if (!code || code.length !== 2) return "";
  const base = 127397; // 0x1F1E6 - 'A'.codePointAt(0)
  return String.fromCodePoint(
    ...[...code.toUpperCase()].map((c) => c.charCodeAt(0) + base),
  );
}

// Construit un classement [{ label, count, pct }] à partir d'un accesseur.
// `source` permet de restreindre l'échantillon (ex. joueurs en ligne).
function topBreakdown(accessor, { limit = 6, source = null } = {}) {
  const list = source ?? livePlayers.value;
  const counts = new Map();
  for (const p of list) {
    const raw = accessor(p);
    const key = raw && String(raw).trim() ? String(raw).trim() : "Inconnu";
    counts.set(key, (counts.get(key) ?? 0) + 1);
  }
  const total = list.length || 1;
  return [...counts.entries()]
    .sort((a, b) => b[1] - a[1])
    .slice(0, limit)
    .map(([label, count]) => ({
      label,
      count,
      pct: Math.round((count / total) * 100),
    }));
}

const byCountry = computed(() => topBreakdown((p) => p.geo?.country));
const byIsp = computed(() => topBreakdown((p) => p.geo?.isp));
const byCity = computed(() => topBreakdown((p) => p.geo?.city));
const byServer = computed(() =>
  topBreakdown((p) => p.current_server, {
    source: livePlayers.value.filter((p) => p.online),
  }),
);

const topPlayers = computed(() =>
  [...livePlayers.value]
    .sort((a, b) => (b.join_count || 0) - (a.join_count || 0))
    .slice(0, 5)
    .filter((p) => (p.join_count || 0) > 0),
);

const filteredPlayers = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return livePlayers.value;
  return livePlayers.value.filter(
    (p) =>
      p.name?.toLowerCase().includes(q) ||
      p.uuid?.toLowerCase().includes(q) ||
      p.ip?.toLowerCase().includes(q) ||
      p.geo?.city?.toLowerCase().includes(q) ||
      p.geo?.postal?.toLowerCase().includes(q) ||
      p.geo?.country?.toLowerCase().includes(q) ||
      p.geo?.isp?.toLowerCase().includes(q),
  );
});

// Localisation lisible : "Ville (CP), Pays", en omettant les parties absentes.
function formatLocation(geo) {
  if (!geo) return null;
  const cityZip = [geo.city, geo.postal].filter(Boolean).join(" ");
  const parts = [cityZip || null, geo.country].filter(Boolean);
  return parts.length ? parts.join(", ") : null;
}

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
        <h3 class="text-sm font-semibold text-foreground">Lier le proxy</h3>
        <span
          v-if="liveServer?.linked_at"
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
            <span class="font-medium text-foreground">1.</span> Déposez le plugin
            <span class="font-mono text-foreground">PixelCraftLink</span> dans le dossier
            <span class="font-mono text-foreground">plugins/</span> de votre proxy
            <span class="font-mono text-foreground">Velocity</span>, puis démarrez-le.
          </li>
          <li>
            <span class="font-medium text-foreground">2.</span> Renseignez une fois
            l'URL du panel dans
            <span class="font-mono text-foreground">plugins/pixelcraftlink/config.toml</span>
            (champ <span class="font-mono text-foreground">panel-url</span>).
          </li>
          <li>
            <span class="font-medium text-foreground">3.</span> Dans la console du proxy,
            exécutez&nbsp;:
            <span class="font-mono text-foreground">pixellink &lt;identifiant&gt;</span>
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
                {{ liveServer?.link_code ?? "—" }}
              </code>
              <Button
                type="button"
                size="icon"
                variant="outline"
                class="h-8 w-8 shrink-0"
                @click="copy(liveServer?.link_code ?? '', 'code')"
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
            pixellink {{ liveServer?.link_code ?? "<identifiant>" }}
          </code>
          <Button
            type="button"
            size="icon"
            variant="outline"
            class="h-8 w-8 shrink-0"
            @click="copy(`pixellink ${liveServer?.link_code ?? ''}`, 'cmd')"
          >
            <Check v-if="copied === 'cmd'" class="h-3.5 w-3.5 text-emerald-500" />
            <Copy v-else class="h-3.5 w-3.5" />
          </Button>
        </div>

        <p class="text-[11px] text-muted-foreground">
          Astuce&nbsp;: pour relier sans toucher au
          <span class="font-mono">config.toml</span>, utilisez
          <span class="font-mono text-foreground">pixellink {{ apiBase }} {{ liveServer?.link_code ?? "<identifiant>" }}</span>.
        </p>

        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
          <p class="text-[11px] text-muted-foreground">
            Dernière synchro&nbsp;: {{ formatDate(liveServer?.last_synced_at) }}
            <span v-if="liveServer?.last_ip"> · IP serveur {{ liveServer.last_ip }}</span>
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

    <!-- Statistiques -->
    <div class="rounded-xl border border-border bg-card">
      <button
        type="button"
        class="flex w-full items-center gap-2 border-b border-border px-4 py-3 text-left"
        :class="showStats ? '' : 'border-b-transparent'"
        @click="showStats = !showStats"
      >
        <BarChart3 class="h-4 w-4 text-primary" />
        <h3 class="text-sm font-semibold text-foreground">Statistiques</h3>
        <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">
          {{ stats.geoKnown }}/{{ stats.total }} géolocalisés
        </span>
        <ChevronDown
          class="ml-auto h-4 w-4 text-muted-foreground transition-transform"
          :class="showStats ? 'rotate-180' : ''"
        />
      </button>

      <div v-if="showStats && stats.total" class="space-y-4 px-4 py-4">
        <!-- KPI -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
          <div class="rounded-lg border border-border bg-muted/30 px-3 py-2">
            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
              <Users class="h-3.5 w-3.5" /> Total
            </div>
            <p class="mt-0.5 text-lg font-semibold text-foreground">{{ stats.total }}</p>
          </div>
          <div class="rounded-lg border border-border bg-muted/30 px-3 py-2">
            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
              <span class="h-2 w-2 rounded-full bg-emerald-500" /> En ligne
            </div>
            <p class="mt-0.5 text-lg font-semibold text-emerald-500">{{ stats.online }}</p>
          </div>
          <div class="rounded-lg border border-border bg-muted/30 px-3 py-2">
            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
              <TrendingUp class="h-3.5 w-3.5" /> Connexions
            </div>
            <p class="mt-0.5 text-lg font-semibold text-foreground">{{ stats.connections }}</p>
          </div>
          <div class="rounded-lg border border-border bg-muted/30 px-3 py-2">
            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
              <UserPlus class="h-3.5 w-3.5" /> Nouveaux 7 j
            </div>
            <p class="mt-0.5 text-lg font-semibold text-foreground">{{ stats.newWeek }}</p>
          </div>
          <div class="rounded-lg border border-border bg-muted/30 px-3 py-2">
            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
              <Activity class="h-3.5 w-3.5" /> Actifs 24 h
            </div>
            <p class="mt-0.5 text-lg font-semibold text-foreground">{{ stats.activeDay }}</p>
          </div>
          <div class="rounded-lg border border-border bg-muted/30 px-3 py-2">
            <div class="flex items-center gap-1.5 text-[11px] text-muted-foreground">
              <Activity class="h-3.5 w-3.5" /> Actifs 7 j
            </div>
            <p class="mt-0.5 text-lg font-semibold text-foreground">{{ stats.activeWeek }}</p>
          </div>
        </div>

        <!-- Répartitions -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <div class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground">
              <Globe class="h-3.5 w-3.5 text-primary" /> Top pays
            </div>
            <ul v-if="byCountry.length" class="space-y-1.5">
              <li v-for="row in byCountry" :key="row.label" class="space-y-0.5">
                <div class="flex items-center justify-between text-xs">
                  <span class="truncate text-foreground">{{ row.label }}</span>
                  <span class="shrink-0 pl-2 text-muted-foreground">{{ row.count }} · {{ row.pct }}%</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                  <div class="h-full rounded-full bg-primary" :style="{ width: row.pct + '%' }" />
                </div>
              </li>
            </ul>
            <p v-else class="text-xs text-muted-foreground">Aucune donnée.</p>
          </div>

          <div>
            <div class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground">
              <Wifi class="h-3.5 w-3.5 text-primary" /> Top opérateurs
            </div>
            <ul v-if="byIsp.length" class="space-y-1.5">
              <li v-for="row in byIsp" :key="row.label" class="space-y-0.5">
                <div class="flex items-center justify-between text-xs">
                  <span class="truncate text-foreground" :title="row.label">{{ row.label }}</span>
                  <span class="shrink-0 pl-2 text-muted-foreground">{{ row.count }} · {{ row.pct }}%</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                  <div class="h-full rounded-full bg-primary" :style="{ width: row.pct + '%' }" />
                </div>
              </li>
            </ul>
            <p v-else class="text-xs text-muted-foreground">Aucune donnée.</p>
          </div>

          <div>
            <div class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground">
              <MapPin class="h-3.5 w-3.5 text-primary" /> Top villes
            </div>
            <ul v-if="byCity.length" class="space-y-1.5">
              <li v-for="row in byCity" :key="row.label" class="space-y-0.5">
                <div class="flex items-center justify-between text-xs">
                  <span class="truncate text-foreground">{{ row.label }}</span>
                  <span class="shrink-0 pl-2 text-muted-foreground">{{ row.count }} · {{ row.pct }}%</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                  <div class="h-full rounded-full bg-primary" :style="{ width: row.pct + '%' }" />
                </div>
              </li>
            </ul>
            <p v-else class="text-xs text-muted-foreground">Aucune donnée.</p>
          </div>

          <div>
            <div class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground">
              <Server class="h-3.5 w-3.5 text-primary" /> Serveurs (en ligne)
            </div>
            <ul v-if="byServer.length" class="space-y-1.5">
              <li v-for="row in byServer" :key="row.label" class="space-y-0.5">
                <div class="flex items-center justify-between text-xs">
                  <span class="truncate text-foreground">{{ row.label }}</span>
                  <span class="shrink-0 pl-2 text-muted-foreground">{{ row.count }} · {{ row.pct }}%</span>
                </div>
                <div class="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                  <div class="h-full rounded-full bg-emerald-500" :style="{ width: row.pct + '%' }" />
                </div>
              </li>
            </ul>
            <p v-else class="text-xs text-muted-foreground">Aucun joueur en ligne.</p>
          </div>

          <div>
            <div class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground">
              <Wifi class="h-3.5 w-3.5 text-primary" /> Signaux réseau
            </div>
            <ul class="space-y-1.5">
              <li class="flex items-center justify-between text-xs">
                <span class="flex items-center gap-1.5 text-foreground">
                  <span class="h-2 w-2 rounded-full bg-red-500" /> VPN / proxy
                </span>
                <span class="text-muted-foreground">{{ stats.proxy }}</span>
              </li>
              <li class="flex items-center justify-between text-xs">
                <span class="flex items-center gap-1.5 text-foreground">
                  <span class="h-2 w-2 rounded-full bg-amber-500" /> Datacenter
                </span>
                <span class="text-muted-foreground">{{ stats.hosting }}</span>
              </li>
              <li class="flex items-center justify-between text-xs">
                <span class="flex items-center gap-1.5 text-foreground">
                  <span class="h-2 w-2 rounded-full bg-sky-500" /> Mobile
                </span>
                <span class="text-muted-foreground">{{ stats.mobile }}</span>
              </li>
            </ul>
            <p class="mt-2 text-[10px] text-muted-foreground">
              Sur {{ stats.geoKnown }} IP géolocalisée{{ stats.geoKnown > 1 ? "s" : "" }}.
            </p>
          </div>

          <div class="sm:col-span-2 lg:col-span-3">
            <div class="mb-2 flex items-center gap-1.5 text-xs font-medium text-foreground">
              <TrendingUp class="h-3.5 w-3.5 text-primary" /> Joueurs les plus actifs
            </div>
            <ul v-if="topPlayers.length" class="space-y-1">
              <li
                v-for="(player, i) in topPlayers"
                :key="player.id"
                class="flex items-center gap-2 text-xs"
              >
                <span class="w-4 shrink-0 text-right font-mono text-muted-foreground">{{ i + 1 }}</span>
                <span
                  class="h-2 w-2 shrink-0 rounded-full"
                  :class="player.online ? 'bg-emerald-500' : 'bg-muted-foreground/40'"
                />
                <span class="truncate font-medium text-foreground">{{ player.name }}</span>
                <span class="ml-auto shrink-0 text-muted-foreground">
                  {{ player.join_count }} connexion{{ player.join_count > 1 ? "s" : "" }}
                </span>
              </li>
            </ul>
            <p v-else class="text-xs text-muted-foreground">Aucune donnée.</p>
          </div>
        </div>
      </div>

      <div
        v-else-if="showStats"
        class="px-4 py-8 text-center text-sm text-muted-foreground"
      >
        Aucune donnée à analyser pour l'instant.
      </div>
    </div>

    <!-- Liste des joueurs -->
    <div class="rounded-xl border border-border bg-card">
      <div class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
        <div class="flex items-center gap-2">
          <Users class="h-4 w-4 text-primary" />
          <h3 class="text-sm font-semibold text-foreground">Joueurs</h3>
          <span class="rounded-full bg-muted px-2 py-0.5 text-[11px] text-muted-foreground">
            {{ livePlayers.length }} total · {{ onlineCount }} en ligne
          </span>
        </div>
        <div class="flex items-center gap-2">
          <div class="relative">
            <Search class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground" />
            <input
              v-model="search"
              type="text"
              placeholder="Rechercher pseudo, UUID, IP, ville, opérateur…"
              class="h-8 w-56 rounded-md border border-border bg-background pl-7 pr-2 text-xs outline-none focus:ring-1 focus:ring-primary"
            />
          </div>
          <Button
            type="button"
            size="sm"
            variant="outline"
            class="h-8"
            :disabled="refreshing"
            title="Rafraîchir la liste"
            @click="refresh"
          >
            <RefreshCw
              class="mr-1 h-3.5 w-3.5"
              :class="refreshing ? 'animate-spin' : ''"
            />
            Rafraîchir
          </Button>
          <label
            class="flex select-none items-center gap-1.5 text-[11px] text-muted-foreground"
            title="Rafraîchit automatiquement toutes les 10 secondes"
          >
            <input
              v-model="autoRefresh"
              type="checkbox"
              class="h-3.5 w-3.5 rounded border-border accent-primary"
            />
            Auto
          </label>
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

      <div v-if="filteredPlayers.length" class="h-[480px] overflow-auto">
        <table class="w-full text-left text-sm">
          <thead class="sticky top-0 z-10 bg-card">
            <tr class="border-b border-border/60 text-[11px] uppercase tracking-wide text-muted-foreground">
              <th class="px-4 py-2 font-medium">Joueur</th>
              <th class="px-4 py-2 font-medium">Serveur</th>
              <th class="px-4 py-2 font-medium">UUID</th>
              <th class="px-4 py-2 font-medium">IP</th>
              <th class="px-4 py-2 font-medium">Localisation</th>
              <th class="px-4 py-2 font-medium">Opérateur</th>
              <th class="px-4 py-2 font-medium">Réseau</th>
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
                <span
                  v-if="player.online && player.current_server"
                  class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-medium text-primary"
                >
                  {{ player.current_server }}
                </span>
                <span v-else class="text-xs text-muted-foreground">—</span>
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
              <td class="px-4 py-2">
                <span
                  v-if="formatLocation(player.geo)"
                  class="text-xs text-foreground"
                  :title="player.geo?.region || ''"
                >
                  <span v-if="player.geo?.country_code" class="mr-1">{{ flagEmoji(player.geo.country_code) }}</span>
                  {{ formatLocation(player.geo) }}
                </span>
                <span v-else class="text-xs text-muted-foreground">—</span>
              </td>
              <td class="px-4 py-2">
                <span class="text-xs text-muted-foreground" :title="player.geo?.as || ''">
                  {{ player.geo?.isp || "—" }}
                </span>
              </td>
              <td class="px-4 py-2">
                <div class="flex flex-wrap items-center gap-1">
                  <span
                    v-if="player.geo?.proxy"
                    class="rounded-full bg-red-500/10 px-1.5 py-0.5 text-[10px] font-medium text-red-500"
                    title="IP détectée comme VPN / proxy / Tor"
                  >
                    VPN
                  </span>
                  <span
                    v-if="player.geo?.hosting"
                    class="rounded-full bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-500"
                    title="IP d'hébergeur / datacenter"
                  >
                    Datacenter
                  </span>
                  <span
                    v-if="player.geo?.mobile"
                    class="rounded-full bg-sky-500/10 px-1.5 py-0.5 text-[10px] font-medium text-sky-500"
                    title="Connexion mobile (4G/5G)"
                  >
                    Mobile
                  </span>
                  <span
                    v-if="!player.geo?.proxy && !player.geo?.hosting && !player.geo?.mobile"
                    class="text-xs text-muted-foreground"
                  >
                    —
                  </span>
                </div>
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
            livePlayers.length
              ? "Aucun joueur ne correspond à la recherche."
              : "Aucun joueur enregistré pour l'instant. Reliez votre serveur pour les voir apparaître."
          }}
        </p>
      </div>
    </div>
  </div>
</template>

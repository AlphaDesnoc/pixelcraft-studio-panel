# Application bureau — PixelCraft Studio Panel

L'application desktop charge **le panel web complet** dans une fenêtre native Electron. Ce n'est pas une version réduite : c'est exactement la même application (Kanban, bugs, tableur, fichiers, chat projet, messages privés, calendrier, notes, admin, 2FA, etc.).

```
┌─────────────────────────────────────┐
│  PixelCraft Panel (Electron)        │
│  ┌───────────────────────────────┐  │
│  │  panel.pixelcraft-studios.fr  │  │
│  │  (Inertia + Vue — 100 % web)  │  │
│  └───────────────────────────────┘  │
│  + notifications Windows/macOS      │
│  + badge messages/non-lus           │
│  + téléchargements natifs           │
└─────────────────────────────────────┘
```

L'API REST (`/api/v1`) reste disponible pour un futur client mobile ou une UI native partielle, mais **le logiciel desktop n'en a pas besoin** pour avoir toutes les fonctionnalités.

---

## Fonctionnalités incluses

Identiques au navigateur :

| Module | Disponible |
|--------|------------|
| Dashboard & widgets | ✅ |
| Mes tâches | ✅ |
| Projets (Kanban, listes, tags) | ✅ |
| Chat projet & présence | ✅ |
| Messages privés & réactions | ✅ |
| Bugs par rank | ✅ |
| Tableur (feuilles) | ✅ |
| Fichiers & pièces jointes | ✅ |
| Calendrier & notes | ✅ |
| Recherche globale (Ctrl+K) | ✅ |
| Notifications temps réel (Reverb) | ✅ |
| Profil, thème, 2FA | ✅ |
| Administration | ✅ |

Améliorations desktop :

- Notifications système (MP + notifications panel) quand la fenêtre n'est pas au premier plan
- Badge sur l'icône (messages + notifications non lus)
- Liens externes ouverts dans le navigateur, navigation interne dans l'app
- Boîte de dialogue native pour les téléchargements
- Session persistante (cookies conservés entre les lancements)
- Une seule instance (relancer l'app focus la fenêtre existante)

---

## Installation & lancement

### Prérequis

- Node.js 20+
- npm

### Configuration

Copier l'exemple de config (optionnel) :

```bash
cd desktop
copy config.example.json config.json
```

`config.json` :

```json
{
  "panelUrl": "https://panel.pixelcraft-studios.fr"
}
```

Variables d'environnement alternatives : `PANEL_URL`, `DESKTOP_APP_URL`.

### Lancer

```bash
cd desktop
npm install
npm start
```

Panel local (avec `php artisan serve` + `npm run dev` actifs) :

```bash
npm run start:local
```

---

## Build installateur

```bash
cd desktop
npm install
npm run build:win     # Windows (.exe)
npm run build:mac     # macOS (.dmg)
npm run build:linux   # Linux (AppImage)
```

Installateurs générés dans `desktop/dist/`.

Pour distribuer à l'équipe : builder le `.exe`, le partager, chaque utilisateur se connecte avec son compte panel habituel.

---

## Détection desktop côté web

L'app Electron envoie :

- En-tête `X-PixelCraft-Desktop: 1` sur toutes les requêtes vers le panel
- User-Agent suffixé `PixelCraftPanel/1.0`

Inertia expose `desktop.isDesktop` pour d'éventuels ajustements UI futurs.

---

## API REST (optionnelle)

Base : `{APP_URL}/api/v1` — voir les endpoints dans la section précédente du fichier ou `routes/api.php`.

Utile pour mobile / intégrations, pas requis pour le logiciel desktop actuel.

---

## Dépannage

| Problème | Piste |
|----------|-------|
| Écran blanc au lancement | Vérifier `panelUrl` et la connexion internet |
| Temps réel inactif | Reverb doit être configuré sur le serveur (identique au web) |
| 2FA | Fonctionne comme sur le web (TOTP ou codes de récupération) |
| Fichiers / uploads | Même limites que le panel web |

---

## Évolutions

- Icône applicative personnalisée dans `desktop/build/`
- NativePHP quand compatible Laravel 13

---

## Auto-déploiement

Deux pipelines distincts :

| Composant | Déclencheur | Résultat |
|-----------|-------------|----------|
| **Panel web** | Push sur `main` | Déploiement VPS via SSH (`.github/workflows/deploy.yml`) |
| **App desktop** | Push sur `main` modifiant `desktop/` | Build Windows + GitHub Release (`.github/workflows/desktop-release.yml`) |

### Panel web (déjà en place)

Chaque merge sur `main` déploie automatiquement sur le VPS (`panel.pixelcraft-studios.fr`).

### App desktop (nouveau)

Quand tu pushes sur `main` avec des changements dans `desktop/` :

1. GitHub Actions build l'installateur Windows (`.exe`)
2. Une **GitHub Release** est créée/mise à jour avec l'installateur + `latest.yml`
3. Les utilisateurs qui ont déjà l'app reçoivent la **mise à jour automatique** au lancement (`electron-updater`)

Déclenchement manuel possible : **Actions → Desktop release → Run workflow**.

### Versioning automatique

En CI, la version devient `1.0.{numéro de run}` (ex. `1.0.42`) pour garantir des releases uniques sans bump manuel.

Pour une version majeure (ex. `1.1.0`), modifie `desktop/package.json` avant le push.

### Première installation pour l'équipe

1. Via le panel web : **sidebar → App desktop** ou **Mon compte → Application desktop**
2. Ou directement sur [GitHub Releases](https://github.com/AlphaDesnoc/pixelcraft-studio-panel/releases)
3. Télécharge le `.exe` le plus récent et installe — les MAJ suivantes arrivent seules

### Prérequis GitHub

Aucun secret supplémentaire : `GITHUB_TOKEN` suffit (permission `contents: write` déjà configurée dans le workflow).

Le dépôt doit être **public** ou le plan GitHub doit autoriser les Releases sur repo privé.

### Flux recommandé

```
dev (travail) → merge main → panel web déployé sur VPS
                          → app desktop buildée + release GitHub
                          → utilisateurs desktop mis à jour auto
```

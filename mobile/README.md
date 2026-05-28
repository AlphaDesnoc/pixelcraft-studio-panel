# PixelCraft Panel — Mobile (Flutter)

Application mobile **iOS / Android** avec **UI 100 % native**.

## Fonctionnalités

| Zone | Statut |
|------|--------|
| Login + 2FA | Natif |
| Dashboard, mes tâches, recherche | Natif |
| Messages privés, notifications | Natif |
| Projet : Kanban, chat, notes, calendrier, bugs, fichiers | Natif |
| Tableur (grille éditable, autosave) | Natif |
| Équipe projet | Natif |
| Ranks + dashboard stats | Natif |
| Admin (users, projects) | Natif (si `is_admin`) |
| Préférences notifications | Natif |
| Temps réel | WebSocket Reverb + polling `/realtime/sync` (3 s) |
| Notifications | **Android/iOS natif** (`flutter_local_notifications`) — pas de Firebase requis |

## Lancer

```bash
cd mobile
flutter pub get
flutter run
```

Émulateur Android + panel local :

```bash
flutter run --dart-define=PANEL_BASE_URL=http://10.0.2.2:8000
```

## Mises à jour automatiques (Android)

Au démarrage, l’app lit un manifeste texte et compare le **build** installé :

```ini
version=1.0.7
build=7
apk=https://github.com/.../pixelcraft-panel-mobile-1.0.7.apk
```

| Source | URL |
|--------|-----|
| **Défaut (CI)** | `releases/latest/download/update-manifest.txt` sur GitHub |
| **Gist / serveur** | `--dart-define=UPDATE_MANIFEST_URL=https://gist.githubusercontent.com/.../raw/update.txt` |

Flux : dialogue « Mise à jour disponible » → téléchargement APK → instructions d’installation (dont conflit de signature) → écran d’installation Android.

Si Android refuse l’install avec *« conflit avec un package déjà present »*, désinstallez l’app puis réinstallez (signatures debug/release différentes).

Voir `update-manifest.example.txt`. La CI `mobile-release.yml` publie `update-manifest.txt` à chaque release.

## Backend requis

Après déploiement, exécuter les migrations :

```bash
php artisan migrate --force
```

Variables optionnelles (web uniquement, push serveur → navigateur) :

```env
PIXELCRAFT_FCM_SERVER_KEY=...   # push Firebase côté backend (non utilisé par l'app mobile)
```

### Notifications Android

L'app affiche les alertes **directement dans la barre système Android** via `flutter_local_notifications` :

- permission `POST_NOTIFICATIONS` demandée à la connexion (Android 13+)
- canaux **Messages** et **Alertes** (importance haute, vibration)
- déclenchées par le WebSocket Reverb ou le polling toutes les 3 s

> **Limite** : si l'app est **complètement fermée** (tuée par le système), les notifs s'arrêtent tant que l'app n'est pas relancée. Pour du push avec app tuée, il faudrait FCM ou un service au premier plan Android.

## Architecture

```
Flutter (Material 3)
    ↓ Bearer Sanctum
/api/v1/*  (workspace, CRUD, admin, realtime, push-tokens)
```

Pas de WebView dans le flux principal.

## CI/CD (GitHub Actions)

| Workflow | Déclencheur | Rôle |
|----------|-------------|------|
| `mobile-ci.yml` | Push/PR sur `mobile/**` | `flutter analyze` + `flutter test` |
| `mobile-release.yml` | Push `main` sur `mobile/**` | Build APK + [GitHub Release](https://github.com/AlphaDesnoc/pixelcraft-studio-panel/releases) |

Release manuelle : **Actions → Mobile release → Run workflow**.

### Secrets requis (signature release)

| Secret | Description |
|--------|-------------|
| `ANDROID_KEYSTORE_BASE64` | Keystore `.jks` encodé base64 |
| `ANDROID_KEYSTORE_PASSWORD` | Mot de passe du keystore |
| `ANDROID_KEY_ALIAS` | Alias de la clé |
| `ANDROID_KEY_PASSWORD` | Mot de passe de la clé |

Sans ces secrets, le workflow **Mobile release** échoue (pas de fallback clé debug). Toutes les APK release doivent partager la **même** signature pour que les mises à jour in-app fonctionnent.

Build release local : créer `mobile/android/key.properties` (voir ci-dessous) ou utiliser `flutter run` (debug).

```properties
storePassword=...
keyPassword=...
keyAlias=...
storeFile=app/release.keystore
```

### Tag des releases

`mobile-v1.0.{run_number}` — ex. `mobile-v1.0.42`

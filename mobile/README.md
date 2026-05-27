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
| Temps réel | Polling `/realtime/sync` (3 s) + notifications locales |
| Push FCM | Backend prêt (`PIXELCRAFT_FCM_SERVER_KEY`) + enregistrement token |

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

## Backend requis

Après déploiement, exécuter les migrations :

```bash
php artisan migrate --force
```

Variables optionnelles :

```env
PIXELCRAFT_FCM_SERVER_KEY=...   # push Firebase (legacy API)
```

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

### Secrets optionnels (signature Play Store)

| Secret | Description |
|--------|-------------|
| `ANDROID_KEYSTORE_BASE64` | Keystore `.jks` encodé base64 |
| `ANDROID_KEYSTORE_PASSWORD` | Mot de passe du keystore |
| `ANDROID_KEY_ALIAS` | Alias de la clé |
| `ANDROID_KEY_PASSWORD` | Mot de passe de la clé |

Sans ces secrets, l'APK est signé avec la clé debug (installable en sideload, pas pour le Play Store).

### Tag des releases

`mobile-v1.0.{run_number}` — ex. `mobile-v1.0.42`

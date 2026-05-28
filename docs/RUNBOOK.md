# Runbook production — PixelCraft Studio Panel

## Déploiement

1. Mettre l'application en maintenance si nécessaire : `php artisan down`
2. Récupérer le code et installer les dépendances :
   - `composer install --no-dev --optimize-autoloader`
   - `npm ci && npm run build`
3. Migrations : `php artisan migrate --force`
4. Caches : `php artisan config:cache && php artisan route:cache && php artisan view:cache`
5. Relancer les workers / services (voir ci-dessous)
6. `php artisan up`

## Scheduler (obligatoire)

Ajouter au cron du serveur (utilisateur web) :

```cron
* * * * * cd /chemin/vers/panel && php artisan schedule:run >> /dev/null 2>&1
```

Commandes planifiées :

| Commande | Fréquence | Rôle |
|----------|-----------|------|
| `calendar:send-reminders` | Chaque minute | Rappels calendrier (récurrence incluse) |
| `bugs:notify-sla` | Toutes les 15 min | Alertes SLA bugs dépassés |
| `panel:due-reminders` | 08:00 | Échéances tâches J/J-1 |
| `panel:recurring-tasks` | 07:00 | Tâches récurrentes |
| `panel:auto-archive-tasks` | 03:00 | Archivage auto |

Vérifier : `php artisan schedule:list`

## Reverb / temps réel

- Reverb doit tourner en service (systemd, Supervisor, etc.)
- Si Reverb est down, le panel bascule sur le **polling** ; le badge « Hors ligne (live) » s'affiche en haut à droite
- Vérifier `REVERB_*` et `VITE_REVERB_*` dans `.env`

## Migrations récentes (vérifier après deploy)

- Exceptions / rappels calendrier : `2026_05_29_100000_calendar_exceptions_reminders_and_audit.php`
- Toute migration en attente : `php artisan migrate:status`

## Santé

- HTTP : `GET /up`
- Logs : `storage/logs/laravel.log`
- Queue (si utilisée) : `php artisan queue:work` doit être supervisé

## Rollback

1. `php artisan down`
2. Revenir au commit précédent
3. `php artisan migrate:rollback --step=1` **uniquement** si la migration du deploy le nécessite
4. Rebuild front + caches
5. `php artisan up`

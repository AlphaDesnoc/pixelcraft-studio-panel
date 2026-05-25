# PixelCraft Studio Panel

Panel de gestion de projets (Laravel + Inertia + Vue) pour PixelCraft Studio — espaces par rank, kanban, messagerie privée, chat d'équipe, bugs, etc.

---

## Installation

```bash
composer setup
```

Ou manuellement :

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

---

## Lancer le projet en local

### Option 1 — Simple (sans Reverb, recommandé au quotidien)

Une seule commande, **sans WebSocket**. Le temps réel passe par du **polling HTTP** (~3 secondes).

```bash
composer dev:simple
```

Équivalent à `php artisan serve` + `npm run dev`.

### Option 2 — Complet (avec Reverb, temps réel instantané)

Lance Laravel, Vite, Reverb, la queue et les logs :

```bash
composer dev
```

### Option 3 — Manuel (3 terminaux)

```bash
# Terminal 1 — Laravel
php artisan serve

# Terminal 2 — Vite
npm run dev

# Terminal 3 — Reverb (optionnel, pour l'instantané)
php artisan reverb:start --port=8080
```

---

## Temps réel : comment ça marche

Contrairement à Next.js où le serveur Node gère HTTP **et** WebSockets dans un seul processus, Laravel sépare :

| Composant | Rôle |
|-----------|------|
| `php artisan serve` / PHP-FPM | Pages, API, Inertia |
| `php artisan reverb:start` | WebSockets (messages instantanés) |

### Deux modes côté client

Le panel utilise **toujours** les deux mécanismes en parallèle :

1. **Polling HTTP** (fallback, actif même sans Reverb)
   - `GET /realtime/sync` toutes les **3 s** — non-lus, présence, nouveaux messages
   - `POST /realtime/heartbeat` toutes les **25 s** — « je suis connecté sur le site »

2. **Reverb + Laravel Echo** (instantané si Reverb tourne)
   - Canal `site-presence` — qui est en ligne sur tout le panel
   - Canal privé `App.Models.User.{id}` — nouveaux messages privés
   - Canal `direct.{conversationId}` — fil de conversation ouvert
   - Canaux projet : chat d'espace, bugs, etc.

Si Reverb n'est pas démarré, le polling prend le relais automatiquement. Pas besoin de configuration supplémentaire.

---

## Messagerie privée

### Fonctionnalités

- Conversations 1-à-1 entre membres d'un **même projet**
- Badge **non-lus** dans la sidebar (mis à jour en temps réel)
- Statut **en ligne** dès qu'un utilisateur est connecté **n'importe où** sur le site
- Animations sur les nouveaux messages reçus
- Conversations remontées en tête à chaque nouveau message

### Routes API

```
GET  /messages                              — page principale (?c=123 pour ouvrir une conv)
GET  /messages/conversations/{id}/messages  — historique (JSON)
POST /messages                              — envoyer un message
POST /messages/conversations/{id}/read      — marquer comme lu
GET  /realtime/sync                         — sync polling (non-lus, présence, événements)
POST /realtime/heartbeat                    — heartbeat présence
```

### Accès

- Un utilisateur ne peut écrire qu'aux membres avec qui il **partage au moins un projet**
- Les admins peuvent contacter tous les utilisateurs du panel

---

## Configuration Reverb (local)

Variables dans `.env` :

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=684852
REVERB_APP_KEY=shlxpywtl9kjyoxi30aa
REVERB_APP_SECRET=e4qs6sjeyptswaycllrc

# Adresse vue par le navigateur
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Processus Reverb (écoute interne)
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

# Injectées dans le JS au build Vite
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Générer de nouvelles clés si besoin :

```bash
php artisan reverb:install
```

> **Important :** Reverb doit tourner sur le port **8080**, pas 8000 (réservé à `php artisan serve`).

---

## Déploiement en production

Reverb est un **processus séparé** de PHP-FPM, comme une queue Laravel. Quatre étapes :

### 1. Variables `.env` production

```env
APP_URL=https://panel.tondomaine.fr

BROADCAST_CONNECTION=reverb

REVERB_APP_ID=684852
REVERB_APP_KEY=ta_cle
REVERB_APP_SECRET=ton_secret

# Adresse publique vue par le navigateur
REVERB_HOST=panel.tondomaine.fr
REVERB_PORT=443
REVERB_SCHEME=https

# Processus Reverb (interne, écoute en local)
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="${REVERB_HOST}"
VITE_REVERB_PORT="${REVERB_PORT}"
VITE_REVERB_SCHEME="${REVERB_SCHEME}"
```

Les variables `VITE_*` sont lues **au build**, pas au runtime. Le `.env` de prod doit être en place **avant** `npm run build`.

### 2. Supervisor — garder Reverb actif

Fichier `/etc/supervisor/conf.d/reverb.conf` :

```ini
[program:reverb]
process_name=%(program_name)s
command=php /chemin/vers/pixelcraft-studio-panel/artisan reverb:start --host=127.0.0.1 --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/chemin/vers/pixelcraft-studio-panel/storage/logs/reverb.log
stopwaitsecs=3600
```

Activation :

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb
```

Prévoir aussi un worker queue si tu utilises des jobs (`php artisan queue:work`).

### 3. Nginx — proxy WebSocket

Reverb écoute en interne sur le port 8080. Nginx expose le WebSocket sur ton domaine :

```nginx
# Site Laravel classique
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

# WebSocket Reverb
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
}
```

Avec HTTPS (Let's Encrypt), le navigateur se connecte en `wss://panel.tondomaine.fr/app/...` — d'où `REVERB_PORT=443` et `REVERB_SCHEME=https`.

### 4. Ordre de déploiement

```bash
git pull
composer install --no-dev --optimize-autoloader
# Éditer .env avec les valeurs de prod
php artisan migrate --force
php artisan config:cache
php artisan route:cache
npm ci && npm run build
sudo supervisorctl restart reverb
sudo supervisorctl restart laravel-worker   # si queue
sudo systemctl reload php8.3-fpm
sudo systemctl reload nginx
```

### Schéma production

```
Navigateur
   ├─ HTTPS → Nginx → PHP-FPM (Laravel, pages + API)
   └─ WSS   → Nginx → Reverb :8080 (temps réel)

Reverb ← Laravel broadcast (DirectMessageSent, chat, bugs…)
```

### Sans Reverb en prod ?

Le polling HTTP (`/realtime/sync`) reste actif. Ça fonctionne, mais avec un délai d'environ 3 secondes. Pour une messagerie vraiment instantanée, Reverb (ou un service type Pusher) est recommandé en production.

---

## Checklist production Reverb

| Étape | |
|-------|---|
| `BROADCAST_CONNECTION=reverb` | ☐ |
| Clés `REVERB_APP_*` renseignées | ☐ |
| `REVERB_HOST` = domaine public | ☐ |
| `REVERB_SCHEME=https` + `REVERB_PORT=443` | ☐ |
| `npm run build` exécuté **après** configuration du `.env` | ☐ |
| `php artisan reverb:start` sous Supervisor | ☐ |
| Nginx proxy `/app` → `127.0.0.1:8080` | ☐ |
| Certificat HTTPS actif | ☐ |

---

## Équipes et accès projets

- Seuls les **membres** d'un projet y ont accès (middleware `project.member`)
- La sidebar ne liste que les projets assignés
- Onglet **Équipe** (espace Global) : ajout/retrait de membres, rôles `owner` / `manager` / `member`
- Les admins peuvent accéder à tous les projets mais voient dans la sidebar uniquement leurs projets assignés

---

## Stack technique

- **Backend :** Laravel 13, Inertia, Sanctum
- **Frontend :** Vue 3, Vite, Tailwind, shadcn-vue
- **Temps réel :** Laravel Reverb, Laravel Echo, polling HTTP de secours
- **Base :** SQLite (dev) / MySQL ou PostgreSQL (prod recommandé)

---

## Commandes utiles

```bash
composer dev          # Dev complet (Reverb + queue + logs + Vite)
composer dev:simple   # Dev simple (Laravel + Vite, polling)
composer test         # Tests PHPUnit
npm run build         # Build assets production
php artisan migrate   # Migrations
php artisan reverb:start --port=8080   # Reverb seul
```

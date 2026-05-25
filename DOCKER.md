# Déploiement Docker + Portainer

Guide complet pour faire tourner **PixelCraft Studio Panel** sur un serveur **Debian 12** avec **Docker** et **Portainer**.

---

## Ce que Docker va lancer

| Conteneur | Rôle |
|-----------|------|
| **db** | MariaDB 11 — base de données |
| **app** | PHP 8.3-FPM — Laravel (API, pages, Inertia) |
| **nginx** | Serveur web interne + proxy WebSocket Reverb |
| **reverb** | Temps réel (messages instantanés) |
| **worker** | Queue Laravel (jobs en arrière-plan) |

Stack séparée **`reverse-proxy`** (optionnelle mais recommandée) :

| Conteneur | Rôle |
|-----------|------|
| **reverse-proxy** | Nginx public — ports 80/443, HTTPS, domaine |
| **certbot** | Renouvellement automatique Let's Encrypt |

Volumes persistants :
- `db_data` — données MariaDB
- `storage_data` — uploads, logs, fichiers projets

---

## Partie 1 — Préparer le serveur Debian 12

Connecte-toi en SSH :

```bash
ssh root@ton-serveur
```

Mise à jour :

```bash
apt update && apt upgrade -y
apt install -y ca-certificates curl git
```

---

## Partie 2 — Installer Docker

```bash
curl -fsSL https://get.docker.com | sh
systemctl enable docker
systemctl start docker
```

Vérification :

```bash
docker --version
docker compose version
```

*(Optionnel)* Ajouter ton utilisateur au groupe docker :

```bash
usermod -aG docker ton_user
```

---

## Partie 3 — Installer Portainer

```bash
docker volume create portainer_data

docker run -d \
  -p 8000:8000 \
  -p 9443:9443 \
  --name portainer \
  --restart=unless-stopped \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v portainer_data:/data \
  portainer/portainer-ce:latest
```

Ouvre dans le navigateur :

```
https://IP-DU-SERVEUR:9443
```

Crée le compte administrateur Portainer au premier lancement.

> **Ports :** `9443` = interface Portainer (HTTPS). Le panel Laravel utilisera le port `80` (ou `443` via un reverse proxy).

---

## Partie 4 — Préparer le projet sur le serveur

### 4.1 Cloner le dépôt

```bash
mkdir -p /opt/pixelcraft
cd /opt/pixelcraft
git clone <URL-DE-TON-REPO> pixelcraft-studio-panel
cd pixelcraft-studio-panel
```

### 4.2 Créer le fichier `.env`

```bash
cp .env.docker.example .env
nano .env
```

### 4.3 Variables obligatoires à renseigner

#### Application

```env
APP_NAME="PixelCraft Studio"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://panel.tondomaine.fr
APP_KEY=base64:XXXXXXXX   # voir ci-dessous
```

**Générer `APP_KEY`** (sur ton PC en local dans le projet, ou temporairement sur le serveur) :

```bash
php artisan key:generate --show
```

Copie la valeur `base64:...` dans `.env`.

#### Base de données

```env
DB_DATABASE=pixelcraft
DB_USERNAME=pixelcraft
DB_PASSWORD=mot_de_passe_solide
DB_ROOT_PASSWORD=mot_de_passe_root_solide
```

> `DB_HOST=db` est déjà correct — c’est le nom du service Docker, pas `127.0.0.1`.

#### Reverb (temps réel)

Génère les clés **en local** (une fois) :

```bash
php artisan reverb:install
```

Copie dans `.env` :

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...

# Domaine public vu par le navigateur
REVERB_HOST=panel.tondomaine.fr
REVERB_PORT=443
REVERB_SCHEME=https
```

> **Important :** `REVERB_HOST`, `REVERB_PORT` et `REVERB_SCHEME` sont compilés dans le JS au **build Docker**. Si tu les modifies, il faut **rebuild** la stack.

> **Important :** `REVERB_HOST`, `REVERB_PORT` et `REVERB_SCHEME` sont compilés dans le JS au **build Docker**. Si tu les modifies, il faut **rebuild** la stack.

---

## Partie 5 — Réseau Docker partagé

Le reverse proxy et le panel communiquent via un réseau externe `web` :

```bash
docker network create web
```

À faire **une seule fois** sur le serveur, avant de déployer les stacks.

---

## Partie 6 — Déployer avec Portainer

### Stack 1 — `pixelcraft-panel`

1. Édite `docker/nginx-proxy/conf.d/panel.conf` → remplace `panel.tondomaine.fr` par ton domaine
2. Portainer → **Stacks** → **Add stack** → nom : `pixelcraft-panel`
3. Colle `docker-compose.yml` ou pointe vers le repo Git
4. Le fichier `.env` doit être présent sur le serveur dans le dossier du projet
5. **Deploy the stack**

```bash
cd /opt/pixelcraft/pixelcraft-studio-panel
docker compose up -d --build
```

### Stack 2 — `reverse-proxy`

1. Portainer → **Stacks** → **Add stack** → nom : `reverse-proxy`
2. Colle `docker-compose.proxy.yml`
3. **Deploy the stack**

```bash
docker compose -f docker-compose.proxy.yml up -d
```

> Le conteneur `pixelcraft-nginx` **n'expose plus le port 80** sur l'hôte. Tout passe par `reverse-proxy`.

---

## Partie 7 — HTTPS (Let's Encrypt)

### 7.1 DNS

Pointe `panel.tondomaine.fr` vers l'IP publique du serveur (enregistrement A).

### 7.2 Premier certificat

La config `panel.conf` est en **HTTP** par défaut (nécessaire pour le challenge Let's Encrypt).

```bash
cd /opt/pixelcraft/pixelcraft-studio-panel
chmod +x docker/nginx-proxy/init-letsencrypt.sh
./docker/nginx-proxy/init-letsencrypt.sh panel.tondomaine.fr admin@tondomaine.fr
```

### 7.3 Activer HTTPS

```bash
cp docker/nginx-proxy/conf.d/panel.ssl.conf.example docker/nginx-proxy/conf.d/panel.conf
# Édite panel.conf si le domaine diffère
docker compose -f docker-compose.proxy.yml restart reverse-proxy
```

Le conteneur **certbot** renouvelle le certificat automatiquement toutes les 12 h.

### 7.4 Rebuild le panel (Reverb en HTTPS)

Après HTTPS actif, vérifie le `.env` :

```env
APP_URL=https://panel.tondomaine.fr
REVERB_HOST=panel.tondomaine.fr
REVERB_PORT=443
REVERB_SCHEME=https
```

Puis rebuild la stack panel (les variables Vite sont compilées dans le JS) :

```bash
docker compose up -d --build
```

---

## Partie 8 — Vérifier que tout tourne

Dans **Portainer** → **Stacks** → `pixelcraft-panel` → clique sur chaque conteneur :

| Conteneur | Statut attendu |
|-----------|----------------|
| db | running |
| app | running (healthy) |
| nginx | running |
| reverb | running |
| worker | running |

Test navigateur :

```
https://panel.tondomaine.fr
```

(Avant le certificat SSL : `http://panel.tondomaine.fr`)

Logs utiles (Portainer → conteneur → **Logs**) :
- `app` — migrations, erreurs Laravel
- `reverb` — connexions WebSocket
- `nginx` — requêtes HTTP

Commande shell dans le conteneur `app` (Portainer → **Exec console**) :

```bash
php artisan migrate:status
```

---

## Partie 9 — Créer le premier admin

### Option A — Seeder (données de démo)

Dans le conteneur **app** (Portainer → Exec console) :

```bash
php artisan db:seed --force
```

Crée notamment `alphadmin@pixelcraftstudio.fr` avec le mot de passe `password` (à changer immédiatement en prod).

### Option B — Compte manuel via Tinker

Portainer → conteneur **app** → **Exec console** :

```bash
php artisan tinker
```

```php
\App\Models\User::create([
    'name' => 'Admin',
    'email' => 'admin@pixelcraftstudio.fr',
    'password' => bcrypt('mot_de_passe_solide'),
    'role' => 'admin',
    'email_verified_at' => now(),
]);
```

---

## Partie 10 — Mises à jour du projet

### Manuellement (SSH)

Sur le serveur :

```bash
cd /opt/pixelcraft/pixelcraft-studio-panel
./scripts/deploy.sh
```

Équivalent :

```bash
git pull
docker compose up -d --build
```

Ordre automatique au rebuild :
1. Build frontend (Vite) avec les variables `REVERB_*`
2. `composer install --no-dev`
3. Migrations au démarrage de `app`
4. Restart reverb + worker

### CI/CD GitHub Actions (auto sur `main`)

Chaque **push sur `main`** (y compris après merge d'une PR) déclenche le déploiement via SSH.

Fichiers :
- `.github/workflows/deploy.yml` — workflow GitHub
- `scripts/deploy.sh` — script exécuté sur le VPS

#### 1. Préparer le serveur (une fois)

```bash
# Utilisateur dédié (recommandé)
adduser deploy
usermod -aG docker deploy

# Cloner le repo si ce n'est pas déjà fait
mkdir -p /opt/pixelcraft
git clone git@github.com:TON_ORG/pixelcraft-studio-panel.git /opt/pixelcraft/pixelcraft-studio-panel
cd /opt/pixelcraft/pixelcraft-studio-panel
cp .env.docker.example .env   # puis éditer .env

# Clé pour que le serveur puisse git pull depuis GitHub
sudo -u deploy ssh-keygen -t ed25519 -f /home/deploy/.ssh/github_deploy -N ""
sudo -u deploy cat /home/deploy/.ssh/github_deploy.pub
```

Sur GitHub → repo → **Settings → Deploy keys → Add deploy key** : colle la clé publique (lecture seule suffit).

```bash
chown -R deploy:deploy /opt/pixelcraft/pixelcraft-studio-panel
# chmod +x optionnel — le workflow utilise « bash scripts/deploy.sh »
```

#### 2. Clé SSH pour GitHub Actions → serveur

Sur **ta machine locale** (ou le serveur) :

```bash
ssh-keygen -t ed25519 -f github_actions_deploy -N "" -C "github-actions-deploy"
```

- **`github_actions_deploy.pub`** → sur le serveur, dans `/home/deploy/.ssh/authorized_keys`
- **`github_actions_deploy`** (privée) → secret GitHub `SSH_PRIVATE_KEY`

Test :

```bash
ssh -i github_actions_deploy deploy@217.154.194.232
```

#### 3. Secrets GitHub

Repo → **Settings → Secrets and variables → Actions → New repository secret** :

| Secret | Exemple |
|--------|---------|
| `SSH_HOST` | `217.154.194.232` ou `panel.pixelcraft-studios.fr` |
| `SSH_USER` | `deploy` |
| `SSH_PRIVATE_KEY` | contenu de `github_actions_deploy` (clé privée) |
| `DEPLOY_PATH` | `/opt/pixelcraft/pixelcraft-studio-panel` |
| `SSH_PORT` | `22` (optionnel) |

*(Optionnel)* Crée un environnement **production** dans **Settings → Environments** pour exiger une approbation manuelle avant déploiement.

#### 4. Déclenchement

- Merge d'une PR sur `main` → déploiement auto
- Push direct sur `main` → déploiement auto
- Manuel : onglet **Actions** → **Deploy production** → **Run workflow**

Le `.env` reste **uniquement sur le serveur** (ignoré par git) — il n'est jamais écrasé par le déploiement.

---

## Partie 11 — Sans Reverb (plus simple)

Si tu veux éviter le WebSocket au début :

```env
BROADCAST_CONNECTION=log
```

Tu peux arrêter le conteneur `reverb` dans Portainer. Le **polling HTTP** (~3 s) continue de fonctionner pour la messagerie.

---

## Schéma réseau

```
Internet
   │
   ▼
reverse-proxy:80/443  (stack reverse-proxy, réseau "web")
   │
   ▼
pixelcraft-nginx:80   (stack pixelcraft-panel, réseau "web")
   │
   ├─ app:9000      (PHP-FPM)
   ├─ reverb:8080   (WebSocket /app)
   └─ public/       (assets statiques)

app / reverb / worker ──► db:3306 (MariaDB, réseau interne "pixelcraft")
```

---

## Checklist complète

| Étape | |
|-------|---|
| Docker installé | ☐ |
| Portainer accessible sur `:9443` | ☐ |
| Réseau `web` créé (`docker network create web`) | ☐ |
| Repo cloné dans `/opt/pixelcraft/...` | ☐ |
| `.env` rempli (`APP_KEY`, DB, Reverb) | ☐ |
| Stack `pixelcraft-panel` (5 conteneurs running) | ☐ |
| Stack `reverse-proxy` (reverse-proxy + certbot) | ☐ |
| Domaine DNS → IP serveur | ☐ |
| Certificat Let's Encrypt obtenu | ☐ |
| `panel.ssl.conf` activé + rebuild panel | ☐ |
| Premier admin créé | ☐ |

---

## Dépannage

### Page blanche / 502
- Vérifie que `app` est **healthy**
- Logs conteneur `app` et `nginx`

### WebSocket / messagerie pas instantanée
- Conteneur `reverb` running ?
- `REVERB_HOST` = domaine public (pas `localhost`)
- Rebuild après changement des `VITE_REVERB_*`
- Nginx proxy `/app` → reverb (déjà configuré dans `docker/nginx/default.conf`)

### Erreur base de données
- `DB_PASSWORD` et `DB_ROOT_PASSWORD` définis ?
- Conteneur `db` healthy ?

### Assets JS/CSS manquants
- Rebuild la stack (`docker compose up -d --build`)
- Les assets Vite sont compilés **dans l’image**, pas au runtime

### Permission storage
- Le volume `storage_data` est monté sur `/var/www/html/storage`
- L’entrypoint exécute `chown www-data` au démarrage de `app`

---

## Fichiers Docker du projet

```
docker/
  Dockerfile                    # Build multi-étapes (Node + PHP + Nginx)
  nginx/default.conf            # Nginx interne du panel + proxy Reverb
  nginx-proxy/
    nginx.conf                  # Nginx reverse proxy public
    conf.d/panel.conf           # Config HTTP (défaut)
    conf.d/panel.ssl.conf.example  # Config HTTPS après Let's Encrypt
    init-letsencrypt.sh         # Script premier certificat
  php/entrypoint.sh             # Wait DB, migrate, cache
docker-compose.yml              # Stack panel
docker-compose.proxy.yml        # Stack reverse proxy + certbot
.env.docker.example             # Modèle .env prod panel
.env.proxy.example              # Domaine + email certbot
```

---

## Commandes utiles

```bash
# État des conteneurs
docker compose ps

# Logs en direct
docker compose logs -f app reverb nginx

# Rebuild complet
docker compose up -d --build

# Shell dans l'app
docker compose exec app bash

# Arrêter tout
docker compose down

# Arrêter + supprimer les volumes (⚠️ efface la BDD)
docker compose down -v
```

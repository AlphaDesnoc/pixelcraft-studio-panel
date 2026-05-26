#!/usr/bin/env bash
# Déploiement production — exécuté sur le VPS (manuellement ou via GitHub Actions).
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

BRANCH="${DEPLOY_BRANCH:-main}"

echo "==> Répertoire : $ROOT_DIR"
echo "==> Branche    : $BRANCH"

if [[ -f "${HOME}/.ssh/github_deploy" ]]; then
  export GIT_SSH_COMMAND="ssh -i ${HOME}/.ssh/github_deploy -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new"
fi

PANEL_CONF="docker/nginx-proxy/conf.d/panel.conf"
PANEL_BACKUP=""

# panel.conf est propre au serveur (domaine, SSL) — ne doit pas bloquer le déploiement.
if [[ -f "$PANEL_CONF" ]]; then
  PANEL_BACKUP="$(mktemp)"
  cp "$PANEL_CONF" "$PANEL_BACKUP"
  echo "==> panel.conf sauvegardé (config locale reverse-proxy)"
fi

echo "==> git fetch + reset..."
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git reset --hard "origin/$BRANCH"

if [[ -n "$PANEL_BACKUP" && -f "$PANEL_BACKUP" ]]; then
  cp "$PANEL_BACKUP" "$PANEL_CONF"
  rm -f "$PANEL_BACKUP"
  echo "==> panel.conf local restauré"
elif [[ ! -f "$PANEL_CONF" ]]; then
  echo "==> panel.conf absent — copie depuis panel.http.conf.example"
  cp docker/nginx-proxy/conf.d/panel.http.conf.example "$PANEL_CONF"
  echo "    Édite server_name dans $PANEL_CONF si besoin."
fi

echo "==> docker compose up -d --build..."
docker compose up -d --build

if [[ -f docker-compose.proxy.yml ]]; then
  echo "==> reverse-proxy..."
  docker network inspect web >/dev/null 2>&1 || docker network create web
  docker compose -f docker-compose.proxy.yml up -d
fi

echo "==> État des conteneurs"
docker compose ps

echo "==> Déploiement terminé."

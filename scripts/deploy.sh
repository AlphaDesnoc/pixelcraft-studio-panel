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

echo "==> git pull..."
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull --ff-only origin "$BRANCH"

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

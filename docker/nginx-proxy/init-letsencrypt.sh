#!/bin/sh
# Premier certificat Let's Encrypt pour le reverse proxy.
# Usage (sur le serveur, depuis la racine du projet) :
#   chmod +x docker/nginx-proxy/init-letsencrypt.sh
#   ./docker/nginx-proxy/init-letsencrypt.sh panel.tondomaine.fr admin@tondomaine.fr

set -e

DOMAIN="${1:?Usage: init-letsencrypt.sh <domaine> <email>}"
EMAIL="${2:?Usage: init-letsencrypt.sh <domaine> <email>}"

echo "Obtention du certificat pour ${DOMAIN}..."

docker compose -f docker-compose.proxy.yml run --rm certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email "${EMAIL}" \
    --agree-tos \
    --no-eff-email \
    -d "${DOMAIN}"

echo "Certificat obtenu. Active la config HTTPS :"
echo "  cp docker/nginx-proxy/conf.d/panel.ssl.conf.example docker/nginx-proxy/conf.d/panel.conf"
echo "  docker compose -f docker-compose.proxy.yml restart reverse-proxy"

#!/usr/bin/env bash
#
# Lance la suite navigateur dans le conteneur Playwright epingle.
#
# Le script refuse de demarrer tant qu'il n'a pas verifie que le conteneur
# applicatif sert bien CE depot : un test de mise en page execute contre une
# autre copie du code ne prouve rien.
#
# Variables surchargeables :
#   APP_CONTAINER          conteneur qui sert l'application   (defaut 3omar-browser-app)
#   BROWSER_BASE_URL       URL de l'application               (defaut http://127.0.0.1:49222)
#   PLAYWRIGHT_IMAGE       image Playwright epinglee
#   NODE_MODULES_VOLUME    volume docker des dependances npm
#   CAPTURE_DIR            sortie des captures, chemin vu depuis le conteneur (/work/...)
#
# Tout argument supplementaire est transmis a `playwright test`.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APP_CONTAINER="${APP_CONTAINER:-3omar-browser-app}"
BROWSER_BASE_URL="${BROWSER_BASE_URL:-http://127.0.0.1:49222}"
PLAYWRIGHT_IMAGE="${PLAYWRIGHT_IMAGE:-mcr.microsoft.com/playwright:v1.61.1-noble}"
NODE_MODULES_VOLUME="${NODE_MODULES_VOLUME:-3omar_browser_node_modules}"

fail() { echo "ERREUR: $*" >&2; exit 1; }

# 1. Le conteneur applicatif doit exister et tourner.
docker inspect "${APP_CONTAINER}" >/dev/null 2>&1 \
    || fail "conteneur ${APP_CONTAINER} introuvable. Voir docs/DEVELOPPEMENT.md, section \"Tests navigateur\"."

[ "$(docker inspect -f '{{.State.Running}}' "${APP_CONTAINER}")" = "true" ] \
    || fail "conteneur ${APP_CONTAINER} arrete."

# 2. Il doit monter CE depot, et pas une autre copie du code.
MOUNT_SOURCE="$(docker inspect -f \
    '{{range .Mounts}}{{if eq .Destination "/var/www/html"}}{{.Source}}{{end}}{{end}}' \
    "${APP_CONTAINER}")"

[ "${MOUNT_SOURCE}" = "${REPO_ROOT}" ] \
    || fail "le conteneur ${APP_CONTAINER} sert ${MOUNT_SOURCE:-<rien>} alors que ce depot est ${REPO_ROOT}."

# 3. L'application doit repondre.
STATUS="$(curl -s -o /dev/null -w '%{http_code}' "${BROWSER_BASE_URL}/calculateur" || true)"
[ "${STATUS}" = "200" ] \
    || fail "${BROWSER_BASE_URL}/calculateur a repondu ${STATUS} au lieu de 200."

echo "Depot        : ${REPO_ROOT}"
echo "Application  : ${BROWSER_BASE_URL} (conteneur ${APP_CONTAINER})"
echo "Navigateur   : ${PLAYWRIGHT_IMAGE}"
echo

# 4. Suite navigateur, binaire local epingle (jamais un telechargement npx).
exec docker run --rm --init --network host \
    --label owner=3omar-browser \
    -e BROWSER_BASE_URL="${BROWSER_BASE_URL}" \
    -e CAPTURE_DIR="${CAPTURE_DIR:-}" \
    -e CI="${CI:-}" \
    -v "${REPO_ROOT}":/work \
    -v "${NODE_MODULES_VOLUME}":/work/node_modules \
    -w /work \
    "${PLAYWRIGHT_IMAGE}" \
    ./node_modules/.bin/playwright test --config=tests/browser/playwright.config.js "$@"

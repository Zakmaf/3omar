#!/usr/bin/env bash
# Tests de la vérification post-publication des tags OCI (#288), contre un vrai
# registre local (service registry de la CI, ou `docker run -p 5000:5000 registry:3`).
# Rien n'est poussé ailleurs que sur ce registre : aucune image GHCR n'est lue
# ni modifiée, aucun workflow de publication n'est déclenché.
#
# Usage : .github/scripts/test-verifier-tags-image.sh <registre>   (ex. localhost:5000)
#
# Chaque scénario part d'un dépôt neuf dans le registre, déjà peuplé comme après
# la stable v3.6.0 (latest, v3, v3.6, v3.6.0 sur l'image A) et une pré-release
# antérieure (dev sur l'image P0). Il capture les alias protégés, simule une
# publication (conforme ou fautive), puis exige le verdict attendu de
# .github/scripts/verifier-tags-image.sh.
set -euo pipefail

registre="${1:?Usage : $0 <registre>}"
script="$(cd "$(dirname "$0")" && pwd)/verifier-tags-image.sh"
travail=$(mktemp -d)
trap 'rm -rf "$travail"' EXIT

echoue=0
numero=0

# Image minimale distincte par nom : FROM scratch + un fichier marqueur.
construire() {
    local nom="$1"
    mkdir -p "$travail/ctx-$nom"
    printf '%s\n' "$nom" > "$travail/ctx-$nom/marqueur"
    printf 'FROM scratch\nCOPY marqueur /marqueur\n' > "$travail/ctx-$nom/Dockerfile"
    docker build -q -t "3omar-test-tags:$nom" "$travail/ctx-$nom" > /dev/null
}

# publier <dépôt> <image locale> <tag>... : pousse la même image sous chaque tag.
publier() {
    local depot="$1" source="$2" t
    shift 2
    for t in "$@"; do
        docker tag "3omar-test-tags:$source" "$depot:$t"
        docker push -q "$depot:$t" > /dev/null
    done
}

digest_de() {
    docker buildx imagetools inspect "$1" --format '{{json .Manifest}}' | jq -r '.digest'
}

# Nouveau dépôt peuplé : stable v3.6.0 et dev antérieure.
nouveau_depot() {
    numero=$((numero + 1))
    depot="$registre/test-tags-$numero"
    publier "$depot" A latest v3 v3.6 v3.6.0
    publier "$depot" P0 dev v3.6.1-dev.1
}

# attendre <succes|echec> <description> <commande>...
attendre() {
    local attendu="$1" description="$2" sortie code
    shift 2
    set +e
    sortie=$("$@" 2>&1)
    code=$?
    set -e
    echo "== ${description} (code ${code})"
    printf '%s\n' "$sortie" | sed 's/^::error::/  [attendu] /; s/^/  /'
    if [ "$attendu" = succes ] && [ "$code" -ne 0 ]; then
        echo "::error::${description} : la vérification aurait dû réussir"
        echoue=1
    elif [ "$attendu" = echec ] && [ "$code" -eq 0 ]; then
        echo "::error::${description} : la vérification aurait dû échouer"
        echoue=1
    fi
}

if [ ! -f "$script" ]; then
    echo "::error::${script} absent : la vérification post-publication n'existe pas"
    exit 1
fi

for nom in A P0 B C; do
    construire "$nom"
done

capture="$travail/capture"

# ---- Pré-release v3.7.0-dev.1 ----------------------------------------------

nouveau_depot
bash "$script" capturer "$depot" v3.7.0-dev.1 true "$capture"
publier "$depot" B v3.7.0-dev.1 dev
attendre succes "pré-release conforme : tag exact et dev sur le même digest, alias stables inchangés" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

nouveau_depot
bash "$script" capturer "$depot" v3.7.0-dev.1 true "$capture"
publier "$depot" B 3.7.0-dev.1 dev
attendre echec "pré-release #288 : tag publié sans v, tag exact absent" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:dev")"

nouveau_depot
bash "$script" capturer "$depot" v3.7.0-dev.1 true "$capture"
publier "$depot" B v3.7.0-dev.1
attendre echec "pré-release : dev non déplacé" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

nouveau_depot
bash "$script" capturer "$depot" v3.7.0-dev.1 true "$capture"
publier "$depot" B v3.7.0-dev.1 dev latest
attendre echec "pré-release : latest déplacé" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

nouveau_depot
bash "$script" capturer "$depot" v3.7.0-dev.1 true "$capture"
publier "$depot" B v3.7.0-dev.1 dev v3
attendre echec "pré-release : vMAJEUR déplacé" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

nouveau_depot
bash "$script" capturer "$depot" v3.7.0-dev.1 true "$capture"
publier "$depot" B v3.7.0-dev.1 dev v3.7
attendre echec "pré-release : vMAJEUR.MINEUR créé alors qu'il était absent" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

nouveau_depot
bash "$script" capturer "$depot" v3.7.0-dev.1 true "$capture"
publier "$depot" B v3.7.0-dev.1 dev
attendre echec "pré-release : digest attendu différent du digest publié" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.6.0")"

nouveau_depot
bash "$script" capturer "$depot" v3.7.0-dev.1 true "$capture"
publier "$depot" B v3.7.0-dev.1 dev
attendre echec "pré-release : capture faite pour un autre tag" \
    bash "$script" verifier "$depot" v3.7.0-dev.2 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

# ---- Stable v3.7.0 ------------------------------------------------------------

nouveau_depot
bash "$script" capturer "$depot" v3.7.0 false "$capture"
publier "$depot" C latest v3 v3.7 v3.7.0
attendre succes "stable conforme : latest, v3, v3.7, v3.7.0 sur le même digest, dev inchangé" \
    bash "$script" verifier "$depot" v3.7.0 false "$capture" "$(digest_de "$depot:v3.7.0")"

nouveau_depot
bash "$script" capturer "$depot" v3.7.0 false "$capture"
publier "$depot" C latest v3.7 v3.7.0
attendre echec "stable : vMAJEUR non déplacé" \
    bash "$script" verifier "$depot" v3.7.0 false "$capture" "$(digest_de "$depot:v3.7.0")"

nouveau_depot
bash "$script" capturer "$depot" v3.7.0 false "$capture"
publier "$depot" C v3 v3.7 v3.7.0
attendre echec "stable : latest non déplacé" \
    bash "$script" verifier "$depot" v3.7.0 false "$capture" "$(digest_de "$depot:v3.7.0")"

nouveau_depot
bash "$script" capturer "$depot" v3.7.0 false "$capture"
publier "$depot" C latest v3 v3.7 v3.7.0 dev
attendre echec "stable : dev déplacé" \
    bash "$script" verifier "$depot" v3.7.0 false "$capture" "$(digest_de "$depot:v3.7.0")"

nouveau_depot
bash "$script" capturer "$depot" v3.7.0 false "$capture"
publier "$depot" C latest v3 v3.7 v3.7.0 3.7.0
attendre echec "stable : tag sans v créé" \
    bash "$script" verifier "$depot" v3.7.0 false "$capture" "$(digest_de "$depot:v3.7.0")"

# ---- Couples tag / mode refusés -------------------------------------------------

nouveau_depot
attendre echec "tag de pré-release annoncé stable" \
    bash "$script" capturer "$depot" v3.7.0-dev.1 false "$capture"
attendre echec "tag stable annoncé pré-release" \
    bash "$script" capturer "$depot" v3.7.0 true "$capture"
attendre echec "suffixe hors convention" \
    bash "$script" capturer "$depot" v3.7.0-rc.1 true "$capture"
attendre echec "tag sans v" \
    bash "$script" capturer "$depot" 3.7.0 false "$capture"

if [ "$echoue" -ne 0 ]; then
    echo "Au moins un scénario n'a pas donné le verdict attendu."
    exit 1
fi
echo "Tous les scénarios de vérification post-publication donnent le verdict attendu."

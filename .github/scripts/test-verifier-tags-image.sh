#!/usr/bin/env bash
# Tests de la vérification post-publication des tags OCI (#288), contre de vrais
# registres locaux. Rien n'est poussé ailleurs que sur ces registres : aucune
# image GHCR n'est lue ni modifiée, aucun workflow de publication n'est déclenché.
#
# Usage : .github/scripts/test-verifier-tags-image.sh <registre> <registre protégé>
#   <registre>          registre local ouvert (service registry de la CI, ou
#                       `docker run -d -p 5000:5000 registry:3`), ex. localhost:5000
#   <registre protégé>  registre local exigeant une authentification que le test
#                       ne fournit pas (voir le job CI « Vérification
#                       post-publication des tags »), ex. localhost:5001
#
# Chaque scénario exige le code de sortie EXACT de
# .github/scripts/verifier-tags-image.sh : 0 conforme, 1 non conforme,
# 2 lecture du registre impossible. Un 2 ne satisfait jamais un scénario non
# conforme, et inversement.
#
# Scénarios de verdict : chaque cas part d'un dépôt neuf dans le registre ouvert,
# déjà peuplé comme après la stable v3.6.0 (latest, v3, v3.6, v3.6.0 sur
# l'image A) et une pré-release antérieure (dev sur l'image P0). Il capture les
# alias protégés, simule une publication (conforme ou fautive), puis vérifie.
#
# Scénarios de lecture impossible : vraies erreurs réseau et d'authentification,
# sans remplacer docker ni imagetools. Le script doit sortir en 2 par le chemin
# « Lecture du registre impossible », sans jamais conclure qu'un tag est absent.
set -euo pipefail

registre="${1:?Usage : $0 <registre> <registre protégé>}"
registre_protege="${2:?Usage : $0 <registre> <registre protégé>}"
# Port local sur lequel rien n'écoute : connexion refusée, réellement.
registre_injoignable=localhost:1
script="$(cd "$(dirname "$0")" && pwd)/verifier-tags-image.sh"
travail=$(mktemp -d)
trap 'rm -rf "$travail"' EXIT

echoue=0
numero=0
derniere_sortie=

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

signification() {
    case "$1" in
        0) echo conforme ;;
        1) echo "non conforme" ;;
        2) echo "lecture du registre impossible" ;;
        *) echo "code inattendu" ;;
    esac
}

# attendre <code> <description> <commande>... : exige exactement ce code de
# sortie. La sortie de la commande reste dans $derniere_sortie.
attendre() {
    local attendu="$1" description="$2" code
    shift 2
    set +e
    derniere_sortie=$("$@" 2>&1)
    code=$?
    set -e
    echo "== ${description} (code ${code}, attendu ${attendu} : $(signification "$attendu"))"
    printf '%s\n' "$derniere_sortie" | sed 's/^::error::/[erreur signalée] /; s/^/  /'
    if [ "$code" -ne "$attendu" ]; then
        echo "::error::${description} : code ${code} ($(signification "$code")), attendu exactement ${attendu} ($(signification "$attendu"))"
        echoue=1
    fi
}

# exiger_message <motif ERE> <description> : la dernière sortie doit le contenir.
exiger_message() {
    if ! grep -qiE -- "$1" <<< "$derniere_sortie"; then
        echo "::error::$2 : la sortie ne contient pas « $1 »"
        echoue=1
    fi
}

# refuser_message <motif ERE> <description> : la dernière sortie ne doit pas le contenir.
refuser_message() {
    if grep -qiE -- "$1" <<< "$derniere_sortie"; then
        echo "::error::$2 : la sortie contient « $1 »"
        echoue=1
    fi
}

# capturer <tag> <prerelease> : capture préalable, qui doit réussir (code 0).
capturer() {
    attendre 0 "capture avant publication de $1 dans $depot" \
        bash "$script" capturer "$depot" "$1" "$2" "$capture"
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
capturer v3.7.0-dev.1 true
publier "$depot" B v3.7.0-dev.1 dev
attendre 0 "pré-release conforme : tag exact et dev sur le même digest, alias stables inchangés" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

nouveau_depot
capturer v3.7.0-dev.1 true
publier "$depot" B 3.7.0-dev.1 dev
attendre 1 "pré-release #288 : tag publié sans v, tag exact absent" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:dev")"

nouveau_depot
capturer v3.7.0-dev.1 true
publier "$depot" B v3.7.0-dev.1
attendre 1 "pré-release : dev non déplacé" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

nouveau_depot
capturer v3.7.0-dev.1 true
publier "$depot" B v3.7.0-dev.1 dev latest
attendre 1 "pré-release : latest déplacé" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

nouveau_depot
capturer v3.7.0-dev.1 true
publier "$depot" B v3.7.0-dev.1 dev v3
attendre 1 "pré-release : vMAJEUR déplacé" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

nouveau_depot
capturer v3.7.0-dev.1 true
publier "$depot" B v3.7.0-dev.1 dev v3.7
attendre 1 "pré-release : vMAJEUR.MINEUR créé alors qu'il était absent" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

nouveau_depot
capturer v3.7.0-dev.1 true
publier "$depot" B v3.7.0-dev.1 dev
attendre 1 "pré-release : digest attendu différent du digest publié" \
    bash "$script" verifier "$depot" v3.7.0-dev.1 true "$capture" "$(digest_de "$depot:v3.6.0")"

nouveau_depot
capturer v3.7.0-dev.1 true
publier "$depot" B v3.7.0-dev.1 dev
attendre 1 "pré-release : capture faite pour un autre tag" \
    bash "$script" verifier "$depot" v3.7.0-dev.2 true "$capture" "$(digest_de "$depot:v3.7.0-dev.1")"

# ---- Stable v3.7.0 ------------------------------------------------------------

nouveau_depot
capturer v3.7.0 false
publier "$depot" C latest v3 v3.7 v3.7.0
attendre 0 "stable conforme : latest, v3, v3.7, v3.7.0 sur le même digest, dev inchangé" \
    bash "$script" verifier "$depot" v3.7.0 false "$capture" "$(digest_de "$depot:v3.7.0")"

nouveau_depot
capturer v3.7.0 false
publier "$depot" C latest v3.7 v3.7.0
attendre 1 "stable : vMAJEUR non déplacé" \
    bash "$script" verifier "$depot" v3.7.0 false "$capture" "$(digest_de "$depot:v3.7.0")"

nouveau_depot
capturer v3.7.0 false
publier "$depot" C v3 v3.7 v3.7.0
attendre 1 "stable : latest non déplacé" \
    bash "$script" verifier "$depot" v3.7.0 false "$capture" "$(digest_de "$depot:v3.7.0")"

nouveau_depot
capturer v3.7.0 false
publier "$depot" C latest v3 v3.7 v3.7.0 dev
attendre 1 "stable : dev déplacé" \
    bash "$script" verifier "$depot" v3.7.0 false "$capture" "$(digest_de "$depot:v3.7.0")"

nouveau_depot
capturer v3.7.0 false
publier "$depot" C latest v3 v3.7 v3.7.0 3.7.0
attendre 1 "stable : tag sans v créé" \
    bash "$script" verifier "$depot" v3.7.0 false "$capture" "$(digest_de "$depot:v3.7.0")"

# ---- Couples tag / mode refusés (non conformes : code 1) -------------------------

nouveau_depot
attendre 1 "tag de pré-release annoncé stable" \
    bash "$script" capturer "$depot" v3.7.0-dev.1 false "$capture"
attendre 1 "tag stable annoncé pré-release" \
    bash "$script" capturer "$depot" v3.7.0 true "$capture"
attendre 1 "suffixe hors convention" \
    bash "$script" capturer "$depot" v3.7.0-rc.1 true "$capture"
attendre 1 "tag sans v" \
    bash "$script" capturer "$depot" 3.7.0 false "$capture"
attendre 1 "drapeau pré-release ni true ni false" \
    bash "$script" capturer "$depot" v3.7.0 oui "$capture"

# ---- Registre illisible : lecture impossible (code 2), jamais « absent » ---------
#
# Pour chaque registre illisible, `capturer` (pré-release) puis `verifier`
# (stable, avec une capture cohérente préparée à la main et un digest bien
# formé) doivent sortir en 2 par le chemin « Lecture du registre impossible »,
# la cause réelle doit apparaître dans le journal, aucun tag ne doit être déclaré
# absent, et une capture en échec ne doit laisser aucun fichier.

digest_fictif="sha256:$(printf '%064d' 0)"
capture_stable="$travail/capture-stable-coherente"
printf '%s absent\n' dev 3.7.0 > "$capture_stable"

# lecture_impossible <registre> <libellé> <motif ERE de la cause>
lecture_impossible() {
    local reg="$1" libelle="$2" cause="$3" depot_illisible capture_echec
    depot_illisible="$reg/test-tags-illisible"
    capture_echec="$travail/capture-$libelle"

    attendre 2 "capturer sur registre ${libelle} (${reg})" \
        bash "$script" capturer "$depot_illisible" v3.7.0-dev.1 true "$capture_echec"
    exiger_message 'Lecture du registre impossible' "capturer sur registre ${libelle}"
    exiger_message "$cause" "capturer sur registre ${libelle} (cause réelle)"
    refuser_message '= absent' "capturer sur registre ${libelle} (pris pour un tag absent)"
    if [ -e "$capture_echec" ]; then
        echo "::error::capturer sur registre ${libelle} : une capture a été écrite malgré la lecture impossible"
        echoue=1
    fi

    attendre 2 "verifier sur registre ${libelle} (${reg})" \
        bash "$script" verifier "$depot_illisible" v3.7.0 false "$capture_stable" "$digest_fictif"
    exiger_message 'Lecture du registre impossible' "verifier sur registre ${libelle}"
    exiger_message "$cause" "verifier sur registre ${libelle} (cause réelle)"
    refuser_message 'absent après publication|= absent' "verifier sur registre ${libelle} (pris pour un tag absent)"
    refuser_message 'non conformes' "verifier sur registre ${libelle} (verdict non conforme au lieu d'une erreur de lecture)"
}

lecture_impossible "$registre_injoignable" injoignable 'connection refused|dial tcp'
lecture_impossible "$registre_protege" protege 'unauthorized|401'

if [ "$echoue" -ne 0 ]; then
    echo "Au moins un scénario n'a pas donné le verdict attendu."
    exit 1
fi
echo "Tous les scénarios de vérification post-publication donnent le code de sortie et le chemin attendus."

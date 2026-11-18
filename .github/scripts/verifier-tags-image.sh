#!/usr/bin/env bash
# Vérification des tags OCI d'une release (#288), lue dans le registre lui-même.
#
# Usage :
#   verifier-tags-image.sh capturer IMAGE TAG PRERELEASE FICHIER
#   verifier-tags-image.sh verifier IMAGE TAG PRERELEASE FICHIER DIGEST
#
# IMAGE      dépôt OCI, ex. ghcr.io/zakmaf/3omar
# TAG        tag de la release GitHub, vX.Y.Z (stable) ou vX.Y.Z-dev.N (pré-release)
# PRERELEASE true ou false, drapeau pré-release de la release GitHub
# FICHIER    état des tags protégés, écrit par `capturer`, relu par `verifier`
# DIGEST     digest réellement poussé (sortie digest de docker/build-push-action)
#
# `capturer` s'exécute avant le push : il note le digest de chaque tag protégé,
# ou « absent ». `verifier` s'exécute après le push et exige :
#
#   mode         tags publiés (= DIGEST)            tags protégés (inchangés)
#   pré-release  vX.Y.Z-dev.N, dev                  latest, vX, vX.Y, X.Y.Z-dev.N
#   stable       vX.Y.Z, vX.Y, vX, latest           dev, X.Y.Z
#
# Un tag protégé absent avant le push doit l'être encore après. Les formes sans
# préfixe v (X.Y.Z-dev.N, X.Y.Z) sont protégées : c'est le défaut de #288.
#
# Codes de sortie : 0 conforme ; 1 non conforme (tag publié absent ou sur un
# autre digest, tag protégé modifié, couple tag/mode refusé ou PRERELEASE ni
# true ni false, capture absente ou incohérente) ; 2 nombre d'arguments ou
# action invalide, digest poussé invalide, lecture du registre impossible. Une
# erreur de lecture (authentification, réseau) n'est jamais prise pour un tag
# absent ; .github/scripts/test-verifier-tags-image.sh l'exerce réellement.
set -euo pipefail

usage() {
    echo "Usage : $0 capturer IMAGE TAG PRERELEASE FICHIER | verifier IMAGE TAG PRERELEASE FICHIER DIGEST" >&2
    exit 2
}

[ $# -ge 5 ] || usage
action="$1"
image="${2,,}"
tag="$3"
prerelease="$4"
fichier="$5"
digest_attendu="${6:-}"

case "$action" in
    capturer) [ $# -eq 5 ] || usage ;;
    verifier) [ $# -eq 6 ] || usage ;;
    *) usage ;;
esac

case "$prerelease" in
    true) motif='^v([0-9]+)\.([0-9]+)\.([0-9]+)-dev\.[1-9][0-9]*$' ;;
    false) motif='^v([0-9]+)\.([0-9]+)\.([0-9]+)$' ;;
    *)
        echo "::error::PRERELEASE doit valoir true ou false (reçu : ${prerelease})"
        exit 1
        ;;
esac
if ! [[ $tag =~ $motif ]]; then
    echo "::error::Tag ${tag} invalide pour une release (pré-release : ${prerelease}). Attendu : vX.Y.Z (stable) ou vX.Y.Z-dev.N (pré-release)."
    exit 1
fi
majeur="${BASH_REMATCH[1]}"
mineur="${BASH_REMATCH[2]}"

if [ "$prerelease" = true ]; then
    publies=("$tag" dev)
    proteges=(latest "v${majeur}" "v${majeur}.${mineur}" "${tag#v}")
else
    publies=("$tag" "v${majeur}.${mineur}" "v${majeur}" latest)
    proteges=(dev "${tag#v}")
fi

# Digest du tag, ou « absent » si le registre répond que le tag n'existe pas.
# Toute autre erreur arrête le script (code 2).
resoudre() {
    local ref="${image}:$1" sortie digest
    if sortie=$(docker buildx imagetools inspect "$ref" --format '{{json .Manifest}}' 2>&1 < /dev/null); then
        digest=$(printf '%s\n' "$sortie" | jq -r '.digest' 2>/dev/null || true)
        if [[ ! $digest =~ ^sha256:[0-9a-f]{64}$ ]]; then
            echo "::error::Réponse illisible du registre pour ${ref} : ${sortie}" >&2
            return 2
        fi
        printf '%s\n' "$digest"
    # Chaîne passée en here-string, pas par un tube : sous pipefail, un
    # `printf | grep -q` peut valoir 141 (SIGPIPE) et inverser la négation.
    elif grep -qiE '(: not found$|manifest unknown)' <<< "$sortie" \
        && ! grep -qiE 'unauthorized|denied|forbidden|timeout|dial tcp|connection refused|no such host|tls' <<< "$sortie"; then
        echo absent
    else
        echo "::error::Lecture du registre impossible pour ${ref} (ni présent ni absent) : ${sortie}" >&2
        return 2
    fi
}

if [ "$action" = capturer ]; then
    temporaire=$(mktemp)
    for t in "${proteges[@]}"; do
        d=$(resoudre "$t") || exit 2
        printf '%s %s\n' "$t" "$d" >> "$temporaire"
        echo "Avant publication : ${image}:${t} = ${d}"
    done
    mv "$temporaire" "$fichier"
    exit 0
fi

if [[ ! $digest_attendu =~ ^sha256:[0-9a-f]{64}$ ]]; then
    echo "::error::Digest poussé invalide ou vide : « ${digest_attendu} »"
    exit 2
fi
if [ ! -f "$fichier" ]; then
    echo "::error::Capture ${fichier} absente : les tags protégés n'ont pas été relevés avant le push"
    exit 1
fi
attendus_captures=$(printf '%s\n' "${proteges[@]}")
captures=$(cut -d' ' -f1 "$fichier")
if [ "$captures" != "$attendus_captures" ]; then
    echo "::error::Capture faite pour d'autres tags (${captures//$'\n'/ }) que ceux de ${tag} (${attendus_captures//$'\n'/ })"
    exit 1
fi

echoue=0
rapport=()

for t in "${publies[@]}"; do
    d=$(resoudre "$t") || exit 2
    if [ "$d" = absent ]; then
        echo "::error::${image}:${t} absent après publication (attendu ${digest_attendu})"
        rapport+=("| \`${t}\` | publié | absent | KO |")
        echoue=1
    elif [ "$d" != "$digest_attendu" ]; then
        echo "::error::${image}:${t} pointe sur ${d} au lieu du digest poussé ${digest_attendu}"
        rapport+=("| \`${t}\` | publié | \`${d}\` | KO |")
        echoue=1
    else
        echo "Publié : ${image}:${t} = ${d}"
        rapport+=("| \`${t}\` | publié | \`${d}\` | OK |")
    fi
done

while read -r t avant; do
    apres=$(resoudre "$t") || exit 2
    if [ "$apres" != "$avant" ]; then
        echo "::error::${image}:${t} a changé pendant la publication de ${tag} : avant ${avant}, après ${apres}"
        rapport+=("| \`${t}\` | protégé | ${avant} puis ${apres} | KO |")
        echoue=1
    else
        echo "Inchangé : ${image}:${t} = ${apres}"
        rapport+=("| \`${t}\` | protégé | ${apres} | OK |")
    fi
done < "$fichier"

if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
    {
        echo "### Tags OCI de ${tag} (pré-release : ${prerelease})"
        echo
        echo "Digest poussé : \`${digest_attendu}\`"
        echo
        echo "| Tag | Rôle | Digest | Verdict |"
        echo "|-----|------|--------|---------|"
        printf '%s\n' "${rapport[@]}"
    } >> "$GITHUB_STEP_SUMMARY"
fi

if [ "$echoue" -ne 0 ]; then
    echo "Tags OCI non conformes pour ${tag}."
    exit 1
fi
echo "Tags OCI conformes pour ${tag}."

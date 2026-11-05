#!/usr/bin/env bash
# Vérifie la règle rédactionnelle du dépôt (AGENTS.md, CONTRIBUTING.md) sur les
# seules lignes ajoutées ou modifiées entre <base> et HEAD : pas de tiret
# cadratin (U+2014), pas d'emoji. Les lignes existantes non touchées ne sont
# pas contrôlées.
#
# Usage : .github/scripts/style-redactionnel.sh <base>
#
# Exceptions, explicites et limitées :
# - composer.lock et package-lock.json, générés par les outils et recopiant les
#   descriptions des paquets tiers ;
# - dans AGENTS.md, CLAUDE.md et CONTRIBUTING.md, les lignes qui énoncent la
#   règle elle-même (elles mentionnent « cadratin » ou « emoji » et peuvent
#   citer le caractère).
set -euo pipefail
export LC_ALL=C.UTF-8

base="${1:-}"
if [ -z "$base" ] || [ -z "${base//0/}" ]; then
    echo "Aucune base de comparaison (premier push d'une branche) : rien à vérifier."
    exit 0
fi

# Tiret cadratin, symboles divers et dingbats, étoile et cercle emoji, sélecteur
# de variante emoji, plans emoji (pictogrammes, visages, drapeaux, etc.).
motif='[\x{2014}\x{2600}-\x{27BF}\x{2B50}\x{2B55}\x{FE0F}\x{1F000}-\x{1FAFF}]'

lignes=$(mktemp)
fautives=$(mktemp)
trap 'rm -f "$lignes" "$fautives"' EXIT

# Une ligne par ajout : fichier<TAB>numéro de ligne<TAB>texte. La comparaison part
# de l'ancêtre commun : en local, une base `origin/main` en avance ne compte pas.
depart=$(git merge-base "$base" HEAD)
git -c core.quotepath=false diff --unified=0 --no-color --no-ext-diff "$depart" HEAD \
    | awk '
        /^diff --git / { entete = 1; fichier = ""; next }
        entete && /^\+\+\+ / { fichier = substr($0, 7); next }
        /^@@ / { entete = 0; split($3, a, ","); n = substr(a[1], 2) + 0; next }
        entete { next }
        /^\+/ { print fichier "\t" n "\t" substr($0, 2); n++ }
    ' > "$lignes"

# Seul le texte (après le deuxième TAB) est cherché, pas le nom du fichier.
grep -P "^[^\t]*\t[0-9]+\t.*${motif}" "$lignes" > "$fautives" || true

echoue=0
while IFS=$'\t' read -r fichier ligne texte; do
    case "$fichier" in
        composer.lock | package-lock.json) continue ;;
        AGENTS.md | CLAUDE.md | CONTRIBUTING.md)
            if printf '%s\n' "$texte" | grep -q -i -E 'cadratin|emoji|émoji'; then
                continue
            fi
            ;;
    esac
    echo "::error file=${fichier},line=${ligne}::Tiret cadratin (U+2014) ou emoji dans une ligne modifiée"
    echo "${fichier}:${ligne}: ${texte:0:200}"
    echoue=1
done < "$fautives"

if [ "$echoue" -ne 0 ]; then
    echo "Règle rédactionnelle : remplacer le tiret cadratin par une virgule, deux-points, parenthèses ou un tiret simple, et retirer les emojis (AGENTS.md, CONTRIBUTING.md)."
    exit 1
fi
echo "Aucun tiret cadratin ni emoji dans les $(wc -l < "$lignes") lignes ajoutées ou modifiées."

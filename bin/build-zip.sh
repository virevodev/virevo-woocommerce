#!/usr/bin/env bash
#
# Construit un ZIP d'extension propre et installable (wp-admin → Extensions →
# Ajouter → Téléverser). Le ZIP contient un dossier racine `virevo-for-woocommerce/`
# et exclut les fichiers de dev (cf. .gitattributes export-ignore).
#
# Usage :
#   bash bin/build-zip.sh            → paquet d'AUTO-DISTRIBUTION (pilotes)
#   bash bin/build-zip.sh --wporg    → paquet pour le DÉPÔT WordPress.org
#
# La différence tient à un seul fichier, `includes/class-virevo-updater.php`,
# qui va chercher les mises à jour sur les releases GitHub. C'est indispensable
# quand on auto-distribue un ZIP, et INTERDIT sur WordPress.org : la directive 8
# du dépôt officiel proscrit de servir des mises à jour depuis un autre serveur
# que WordPress.org. Le paquet `--wporg` le retire donc, et le chargeur du
# plugin teste son existence avant de l'utiliser.
set -euo pipefail

cd "$(dirname "$0")/.."

WPORG=0
if [ "${1:-}" = "--wporg" ]; then
	WPORG=1
elif [ -n "${1:-}" ]; then
	echo "Option inconnue : $1 (attendu : --wporg)" >&2
	exit 2
fi

VERSION=$(grep -m1 "Version:" virevo-for-woocommerce.php \
	| sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r[:space:]')

# Le `Stable tag` du readme.txt commande la version RÉELLEMENT servie par
# WordPress.org. S'il diverge de l'en-tête du plugin, le dépôt publie l'ancienne
# version sans prévenir. On refuse de construire dans ce cas.
STABLE=$(grep -m1 "^Stable tag:" readme.txt \
	| sed -E 's/^Stable tag:[[:space:]]*//' | tr -d '\r[:space:]')
if [ "$VERSION" != "$STABLE" ]; then
	echo "❌ Incohérence de version : plugin $VERSION, readme.txt Stable tag $STABLE." >&2
	echo "   Aligner les deux avant de construire." >&2
	exit 1
fi

mkdir -p dist

if [ "$WPORG" -eq 1 ]; then
	OUT="dist/virevo-for-woocommerce-${VERSION}-wporg.zip"
else
	OUT="dist/virevo-for-woocommerce-${VERSION}.zip"
fi
rm -f "$OUT"

# git archive respecte .gitattributes (export-ignore) et produit une structure
# propre avec le préfixe = dossier du plugin. Construit depuis HEAD (commité).
if [ "$WPORG" -eq 1 ]; then
	# On retire l'updater dans un INDEX TEMPORAIRE, puis on archive l'arbre qui
	# en résulte. Tout se fait avec git seul : ni `zip` ni `tar` ne sont
	# garantis présents (Git Bash sous Windows n'a pas `zip`), et l'arbre de
	# travail n'est jamais touché.
	TMPIDX="$(mktemp)"
	trap 'rm -f "$TMPIDX"' EXIT
	GIT_INDEX_FILE="$TMPIDX" git read-tree HEAD
	GIT_INDEX_FILE="$TMPIDX" git rm -q --cached includes/class-virevo-updater.php
	TREE="$(GIT_INDEX_FILE="$TMPIDX" git write-tree)"
	git archive --format=zip --prefix=virevo-for-woocommerce/ -o "$OUT" "$TREE"
	echo "✅ Construit : $OUT (version $VERSION, SANS l'auto-mise à jour GitHub)"
else
	git archive --format=zip --prefix=virevo-for-woocommerce/ -o "$OUT" HEAD
	echo "✅ Construit : $OUT (version $VERSION)"
fi

#!/usr/bin/env bash
#
# Construit un ZIP d'extension propre et installable (wp-admin → Extensions →
# Ajouter → Téléverser). Le ZIP contient un dossier racine `virevo-for-woocommerce/`
# et exclut les fichiers de dev (cf. .gitattributes export-ignore).
#
# Usage : bash bin/build-zip.sh   (depuis n'importe où)
set -euo pipefail

cd "$(dirname "$0")/.."

VERSION=$(grep -m1 "Version:" virevo-for-woocommerce.php \
	| sed -E 's/.*Version:[[:space:]]*//' | tr -d '\r[:space:]')

mkdir -p dist
OUT="dist/virevo-for-woocommerce-${VERSION}.zip"
rm -f "$OUT"

# git archive respecte .gitattributes (export-ignore) et produit une structure
# propre avec le préfixe = dossier du plugin. Construit depuis HEAD (commité).
git archive --format=zip --prefix=virevo-for-woocommerce/ -o "$OUT" HEAD

echo "✅ Construit : $OUT (version $VERSION)"

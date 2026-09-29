#!/usr/bin/env bash
# Envoi incrémental de dist/ vers OVH, en SFTP.
#
# Remplace wlixcc/SFTP-Deploy-Action, qui ne peut pas être incrémental : son
# entrypoint fait un `sftp -b "put -r dist/* www/"`, un envoi aveugle de tout,
# à chaque déploiement. Avec les deux vidéos, dist/ pèse 53 Mo pour 585
# fichiers, dont 28 Mo qui ne changent jamais.
#
# Principe : un manifeste d'empreintes SHA-256 vit sur le serveur, à côté des
# fichiers. On compare l'arbre local au manifeste distant et on n'envoie que ce
# qui diffère, puis le nouveau manifeste.
#
# Pourquoi des empreintes et pas les dates : le runner reconstruit tout à
# chaque fois, donc chaque fichier local est plus récent que son homologue
# distant, même quand ses octets sont identiques. Une comparaison sur la date
# renverrait tout. Sur la taille seule, un « 2-1 » devenu « 3-0 » passerait
# inaperçu. L'empreinte est le seul critère qui dit la vérité.
#
# Sûr par défaut : pas de manifeste distant (premier passage, fichier perdu,
# FORCE_FULL=true) => on envoie tout, comme avant.
#
# Ce script ne supprime rien à distance, comme l'ancienne étape : api/cache/ et
# l'état du rate limit vivent sur le serveur et ne sont pas dans dist/.
#
# Variables attendues : SFTP_HOST, SFTP_USER, SFTP_PASSWORD.
# Facultatif : DIST (défaut site/dist), REMOTE (défaut www), FORCE_FULL.
set -euo pipefail

: "${SFTP_HOST:?SFTP_HOST manquant}"
: "${SFTP_USER:?SFTP_USER manquant}"
: "${SFTP_PASSWORD:?SFTP_PASSWORD manquant}"

DIST=${DIST:-site/dist}
REMOTE=${REMOTE:-www}
FORCE_FULL=${FORCE_FULL:-false}

# Le préfixe `.ht` n'est pas décoratif : la configuration Apache par défaut
# refuse de servir ces fichiers, donc le manifeste n'est pas lisible en ligne.
MANIFEST=.ht-deploy-manifest

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

sftp_batch() {
  sshpass -p "$SFTP_PASSWORD" sftp \
    -oBatchMode=no -oStrictHostKeyChecking=no -oLogLevel=ERROR \
    -b "$1" "$SFTP_USER@$SFTP_HOST"
}

# --- 1. Ce qu'on veut en ligne -----------------------------------------------
( cd "$DIST" && find . -type f ! -name "$MANIFEST" -print0 \
    | xargs -0 -r sha256sum ) \
  | sed 's|  \./|  |' | LC_ALL=C sort -k2 > "$work/local.txt"

total=$(awk 'END { print NR }' "$work/local.txt")

# --- 2. Ce qui y est déjà -----------------------------------------------------
: > "$work/remote.txt"
if [ "$FORCE_FULL" = "true" ]; then
  echo "FORCE_FULL : envoi complet demandé."
else
  # Le `-` en tête dit à sftp d'ignorer l'échec : au premier passage le
  # manifeste n'existe pas encore, ce n'est pas une erreur.
  printf -- '-get %q %q\n' "$REMOTE/$MANIFEST" "$work/remote.txt" > "$work/fetch.sftp"
  sftp_batch "$work/fetch.sftp" || true
  if [ -s "$work/remote.txt" ]; then
    echo "Manifeste distant lu : $(awk 'END { print NR }' "$work/remote.txt") fichiers connus."
  else
    echo "Pas de manifeste distant : envoi complet."
  fi
fi

# --- 3. La différence ---------------------------------------------------------
# Comparaison ligne entière (empreinte + chemin) : couvre d'un coup le fichier
# nouveau et le fichier dont le contenu a changé.
#
# Le test porte sur FILENAME et pas sur l'idiome habituel `NR==FNR` : quand le
# premier fichier est vide — premier déploiement, manifeste perdu — `NR==FNR`
# reste vrai pendant tout le second, chaque ligne est prise pour déjà connue et
# le script n'envoie rien en croyant le serveur à jour.
awk -v connu="$work/remote.txt" \
  'FILENAME == connu { known[$0] = 1; next } !($0 in known)' \
  "$work/remote.txt" "$work/local.txt" | cut -d' ' -f3- > "$work/changed.txt"

# `grep -c` sortirait en erreur sur un fichier vide, et `set -e`
# abattrait le script exactement dans le cas « rien à envoyer ».
changed=$(awk 'END { print NR }' "$work/changed.txt")
echo "$changed fichier(s) à envoyer sur $total."

if [ "$changed" -eq 0 ]; then
  echo "Rien à faire, le serveur est déjà à jour."
  exit 0
fi

# --- 4. L'envoi ---------------------------------------------------------------
{
  # Les dossiers d'abord, du moins profond au plus profond : le `mkdir` de sftp
  # n'est pas récursif, il faut donc énumérer aussi les parents. `-mkdir` ignore
  # l'erreur quand le dossier existe déjà, ce qui est le cas courant.
  # Un fichier à la racine n'a pas de dossier — sans le filtre sur `/`, `b.css`
  # deviendrait un dossier du même nom et écraserait le fichier.
  awk -F/ '{ for (i = 1; i < NF; i++) { p = (i == 1 ? $1 : p "/" $i); print i, p } }' \
    "$work/changed.txt" | LC_ALL=C sort -u | LC_ALL=C sort -n -s -k1,1 | cut -d' ' -f2- \
    | while IFS= read -r dir; do printf -- '-mkdir %q\n' "$REMOTE/$dir"; done

  while IFS= read -r file; do
    printf 'put %q %q\n' "$DIST/$file" "$REMOTE/$file"
  done < "$work/changed.txt"

  # Le manifeste en dernier : s'il échoue avant, le prochain passage renverra
  # simplement tout, plutôt que de croire à jour ce qui ne l'est pas.
  printf 'put %q %q\n' "$work/local.txt" "$REMOTE/$MANIFEST"
} > "$work/upload.sftp"

# `wc -c` et pas `du -b` : BSD du n'a pas -b, et ce script doit pouvoir tourner
# ailleurs que sur le runner pour être mis au point (cf. deploy-sftp.test.sh).
bytes=$( ( cd "$DIST" && tr '\n' '\0' < "$work/changed.txt" | xargs -0 -r wc -c ) \
  | awk '$2 != "total" { n += $1 } END { print n + 0 }' )
echo "Volume : $(( bytes / 1024 )) Ko."

sftp_batch "$work/upload.sftp"
echo "Envoi terminé."

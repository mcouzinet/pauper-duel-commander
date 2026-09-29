#!/usr/bin/env bash
# Vérifie deploy-sftp.sh contre un faux serveur, puisqu'on ne peut pas se
# brancher sur OVH depuis un poste de dev ni depuis la CI d'une PR.
#
# Le faux `sshpass` ci-dessous rejoue le fichier de commandes sftp (get, put,
# -mkdir) dans un dossier local. Ce qui est vérifié n'est pas le transfert
# lui-même mais la seule chose qui peut se tromper en silence : quels fichiers
# sont envoyés, et lesquels sont à juste titre laissés de côté.
#
#   ./scripts/deploy-sftp.test.sh
set -euo pipefail

here=$(cd "$(dirname "$0")" && pwd)
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

mkdir -p "$tmp/bin" "$tmp/serveur" "$tmp/local/sous-dossier"

# --- Faux serveur -------------------------------------------------------------
cat > "$tmp/bin/sshpass" <<'FAKE'
#!/usr/bin/env bash
# Rejoue un batch sftp dans $FAKE_ROOT. `-xxx` en tête d'une ligne = ignorer
# l'échec, comme le vrai sftp.
set -uo pipefail
batch=""; prev=""
for arg in "$@"; do [ "$prev" = "-b" ] && batch=$arg; prev=$arg; done
: "${batch:?pas de -b}"
: > "$FAKE_ROOT/../envoyes.txt"
while IFS= read -r line; do
  line=${line#-}
  # shellcheck disable=SC2086
  eval "set -- $line"
  case $1 in
    mkdir) mkdir -p "$FAKE_ROOT/${2#www/}" ;;
    get)   src=$FAKE_ROOT/${2#www/}; [ -f "$src" ] && cp "$src" "$3" || true ;;
    put)   dst=$FAKE_ROOT/${3#www/}; mkdir -p "$(dirname "$dst")"; cp "$2" "$dst"
           echo "${3#www/}" >> "$FAKE_ROOT/../envoyes.txt" ;;
  esac
done < "$batch"
FAKE
chmod +x "$tmp/bin/sshpass"
export PATH="$tmp/bin:$PATH" FAKE_ROOT="$tmp/serveur"
export SFTP_HOST=exemple SFTP_USER=u SFTP_PASSWORD=p DIST="$tmp/local" REMOTE=www

echo 'un'    > "$tmp/local/a.html"
echo 'deux'  > "$tmp/local/b.css"
echo 'trois' > "$tmp/local/sous-dossier/c.js"
printf 'regle\n' > "$tmp/local/.htaccess"

# nombre de fichiers envoyés, hors manifeste
envoyes() { grep -cv '^\.ht-deploy-manifest$' "$tmp/envoyes.txt" || true; }
attendu() {
  local veut=$1 a=$2 obtenu; obtenu=$(envoyes)
  if [ "$obtenu" = "$veut" ]; then echo "  ok   $a : $obtenu fichier(s)"
  else echo "  ECHEC $a : $obtenu envoyé(s), $veut attendu(s)"; cat "$tmp/envoyes.txt"; exit 1; fi
}

echo '1. premier passage, serveur vide'
"$here/deploy-sftp.sh" > /dev/null
attendu 4 'tout part, dotfile compris'
[ -f "$tmp/serveur/.htaccess" ] || { echo '  ECHEC : .htaccess absent du serveur'; exit 1; }
echo '  ok   .htaccess envoyé sans geste manuel'

echo '2. rien ne change'
"$here/deploy-sftp.sh" > /dev/null
attendu 0 'aucun renvoi'

echo '3. un fichier modifié, à taille strictement identique'
# « deux » -> « cinq » : cinq octets dans les deux cas. C'est le cas qu'une
# comparaison sur la taille laisserait passer, et c'est celui qui arrive pour
# de vrai — un score « 2-1 » corrigé en « 3-0 » ne change pas la taille du HTML.
[ "$(wc -c < "$tmp/local/b.css")" -eq 5 ] || { echo '  ECHEC : le cas testé ne tient plus'; exit 1; }
echo 'cinq' > "$tmp/local/b.css"
[ "$(wc -c < "$tmp/local/b.css")" -eq 5 ] || { echo '  ECHEC : tailles différentes, le test ne prouve rien'; exit 1; }
"$here/deploy-sftp.sh" > /dev/null
attendu 1 'seul le fichier modifié repart'
grep -q '^b.css$' "$tmp/envoyes.txt" || { echo '  ECHEC : mauvais fichier envoyé'; exit 1; }
echo '  ok   vu par empreinte, là où une comparaison de taille aurait tout raté'

echo '4. nouveau fichier'
echo 'quatre' > "$tmp/local/sous-dossier/d.json"
"$here/deploy-sftp.sh" > /dev/null
attendu 1 'seul le nouveau part'

echo '5. FORCE_FULL'
FORCE_FULL=true "$here/deploy-sftp.sh" > /dev/null
attendu 5 'tout repart sur demande'

echo '6. manifeste distant perdu'
rm -f "$tmp/serveur/.ht-deploy-manifest"
"$here/deploy-sftp.sh" > /dev/null
attendu 5 'sans manifeste, envoi complet plutôt que rien'

echo 'Tout est vert.'

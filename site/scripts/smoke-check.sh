#!/usr/bin/env bash
# Contrôle qu'un déploiement a bien laissé le site debout.
#
#   ./scripts/smoke-check.sh                          # la production
#   BASE_URL=https://exemple.test ./scripts/smoke-check.sh
#
# Écrit après une panne : un `.htaccess` invalide a mis toute la production en
# 500 pendant que le workflow affichait « success ». Le build et PHPUnit
# passaient, évidemment — ils ne parlent jamais au serveur. Seul un appel réel
# voit ce genre de chose, et une configuration Apache refusée casse tout d'un
# coup, pas une page.
#
# Ce que chaque ligne protège :
#   /            la redirection racine vers /fr/ (mod_rewrite chargé)
#   /fr/ /en/ /it/   les trois langues servies, DirectoryIndex compris
#   .../inconnu  le 404 personnalisé, donc ErrorDocument
#   api/…php     le seul point d'entrée exécuté en production
#   api/lib/…    la fermeture des dossiers internes ; un 200 ici serait une
#                fuite de code source, pas un simple dérangement
#
# Ne remet rien en place : il fait échouer le job, ce qui vaut mieux qu'un
# « success » vert au-dessus d'un site mort.
set -uo pipefail

BASE_URL=${BASE_URL:-https://pauperduelcommander.fr}
TRIES=${TRIES:-3}
DELAY=${DELAY:-5}

# chemin<espace>code attendu
CASES="
/ 301
/fr/ 200
/en/ 200
/it/ 200
/fr/tournois/ 200
/fr/banlist/ 200
/fr/validateur/ 200
/fr/page-qui-nexiste-pas/ 404
/api/validate-deck.php 405
/api/lib/config.php 403
"

echo "Contrôle de $BASE_URL"
fail=0

while read -r path want; do
  [ -n "$path" ] || continue
  got=""
  for try in $(seq 1 "$TRIES"); do
    got=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$BASE_URL$path" || echo 000)
    [ "$got" = "$want" ] && break
    # Un hébergeur mutualisé hoquette ; on ne déclare l'échec qu'après insistance.
    [ "$try" -lt "$TRIES" ] && sleep "$DELAY"
  done
  if [ "$got" = "$want" ]; then
    printf '  ok     %-28s %s\n' "$path" "$got"
  else
    printf '  ECHEC  %-28s %s attendu, %s obtenu\n' "$path" "$want" "$got"
    fail=1
  fi
done <<EOF
$CASES
EOF

if [ "$fail" -ne 0 ]; then
  echo
  echo "Le site ne répond pas comme prévu. Le déploiement est parti, mais quelque"
  echo "chose ne va pas en ligne — commencer par www/.htaccess, c'est le fichier"
  echo "qui casse tout d'un coup."
  exit 1
fi

echo "Le site répond correctement."

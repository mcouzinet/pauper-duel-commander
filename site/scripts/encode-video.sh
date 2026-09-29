#!/usr/bin/env bash
# Prépare une vidéo pour le site à partir d'un master.
#
#   ./scripts/encode-video.sh <master> <nom de sortie> [miniature]
#   ./scripts/encode-video.sh ~/Downloads/Pauper-Duel-Commander.mp4 \
#       pauper-duel-commander-fr ~/Downloads/miniature-fr.jpg
#
# Produit public/video/<nom>.mp4 et public/img/<nom>-poster.webp.
#
# Les réglages ne sont pas décoratifs :
#   fps=30            un master à 60 i/s pèse 30 % de plus sans rien apporter
#                     à du motion design (compteurs, texte qui glisse)
#   crf 25 / slow     un tiers du poids du master, sans perte visible
#   +faststart        remet l'index (moov) en tête du fichier : sans lui le
#                     navigateur télécharge tout avant d'afficher la 1re image
#   yuv420p           le seul format de pixels que lisent tous les navigateurs
#
# Sans miniature, le poster est pris dans la vidéo à la première seconde. Une
# vraie miniature vaut mieux : elle est composée pour être lue à l'arrêt.
#
# La page d'accueil sert ce fichier avec preload="none" : rien ne part tant que
# personne ne clique. Le coût n'est pas l'affichage de la page, c'est la lecture.
set -euo pipefail

src=${1:?usage: encode-video.sh <master> [nom de sortie] [miniature]}
name=${2:-$(basename "${src%.*}" | tr '[:upper:] ' '[:lower:]-')}
thumb=${3:-}
root=$(cd "$(dirname "$0")/.." && pwd)

mkdir -p "$root/public/video"
ffmpeg -y -v error -i "$src" \
  -vf fps=30 -c:v libx264 -crf 25 -preset slow -profile:v high -pix_fmt yuv420p \
  -movflags +faststart -c:a aac -b:a 128k -ac 2 \
  "$root/public/video/$name.mp4"

poster="$root/public/img/$name-poster.webp"
if [ -n "$thumb" ]; then
  cwebp -quiet -q 82 -o "$poster" -- "$thumb"
else
  ffmpeg -y -v error -ss 1 -i "$src" -frames:v 1 -f image2pipe -vcodec png - \
    | cwebp -quiet -q 82 -o "$poster" -- -
fi

ls -lh "$root/public/video/$name.mp4" "$poster" | awk '{print $9, $5}'

#!/usr/bin/env bash
# Prépare une vidéo pour le site à partir d'un master.
#
#   ./scripts/encode-video.sh ~/Downloads/Pauper-Duel-Commander.mp4
#
# Produit public/video/<nom>.mp4 et public/img/<nom>-poster.webp.
#
# Les réglages ne sont pas décoratifs :
#   fps=30            un master à 60 i/s pèse 30 % de plus sans rien apporter
#                     à du motion design (compteurs, texte qui glisse)
#   crf 25 / slow     11 Mo pour 51 s de 1080p, contre 38 Mo pour le master
#   +faststart        remet l'index (moov) en tête du fichier : sans lui le
#                     navigateur télécharge tout avant d'afficher la 1re image
#   yuv420p           le seul format de pixels que lisent tous les navigateurs
#
# La page d'accueil sert ce fichier avec preload="none" : rien ne part tant que
# personne ne clique. Le coût n'est pas l'affichage de la page, c'est la lecture.
set -euo pipefail

src=${1:?usage: encode-video.sh <fichier source> [nom de sortie]}
name=${2:-$(basename "${src%.*}" | tr '[:upper:] ' '[:lower:]-')}
root=$(cd "$(dirname "$0")/.." && pwd)

mkdir -p "$root/public/video"
ffmpeg -y -v error -i "$src" \
  -vf fps=30 -c:v libx264 -crf 25 -preset slow -profile:v high -pix_fmt yuv420p \
  -movflags +faststart -c:a aac -b:a 128k -ac 2 \
  "$root/public/video/$name.mp4"

# Poster : première image lisible, affichée tant que la vidéo n'est pas lancée.
ffmpeg -y -v error -ss 1 -i "$src" -frames:v 1 -f image2pipe -vcodec png - \
  | cwebp -quiet -q 82 -o "$root/public/img/$name-poster.webp" -- -

ls -lh "$root/public/video/$name.mp4" "$root/public/img/$name-poster.webp" | awk '{print $9, $5}'

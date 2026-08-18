---
name: Pauper Duel Commander
description: Le Commander compétitif en 1 contre 1, joué avec des communes.
colors:
  orange-brasier: "#FF5722"
  orange-brasier-clair: "#FF7043"
  orange-brasier-sombre: "#E64A19"
  or-peu-commune: "#FFA500"
  or-peu-commune-clair: "#FFB84D"
  or-peu-commune-sombre: "#CC8400"
  fond-salle: "#0A0E13"
  fond-table: "#141821"
  fond-table-haute: "#1C212B"
  texte-principal: "#FFFFFF"
  texte-secondaire: "#B8BEC8"
  texte-attenue: "#8B93A1"
  mana-blanc: "#F8F6F1"
  mana-bleu: "#0E68AB"
  mana-noir: "#150B00"
  mana-rouge: "#D3202A"
  mana-vert: "#00733E"
  mana-incolore: "#BEB9B2"
typography:
  display:
    fontFamily: "Barlow Condensed, Impact, sans-serif"
    fontSize: "clamp(2.25rem, 6vw, 3.75rem)"
    fontWeight: 700
    lineHeight: 0.95
    letterSpacing: "-0.025em"
  headline:
    fontFamily: "Barlow Condensed, Impact, sans-serif"
    fontSize: "clamp(1.875rem, 5vw, 3rem)"
    fontWeight: 700
    lineHeight: 1.1
    letterSpacing: "-0.025em"
  title:
    fontFamily: "Barlow Condensed, Impact, sans-serif"
    fontSize: "clamp(1.125rem, 2.5vw, 1.5rem)"
    fontWeight: 700
    lineHeight: 1.2
    letterSpacing: "0.025em"
  body:
    fontFamily: "Inter, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.625
    letterSpacing: "normal"
  label:
    fontFamily: "Barlow Condensed, Impact, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "0.05em"
  cardName:
    fontFamily: "Beleren, Barlow Condensed, serif"
    fontSize: "0.875rem"
    fontWeight: 700
    lineHeight: 1.25
    letterSpacing: "normal"
rounded:
  sm: "0.25rem"
  md: "0.5rem"
  lg: "0.75rem"
  full: "9999px"
spacing:
  xs: "0.5rem"
  sm: "0.75rem"
  md: "1rem"
  lg: "1.25rem"
  xl: "2.5rem"
  2xl: "3.5rem"
components:
  button-primary:
    backgroundColor: "{colors.orange-brasier}"
    textColor: "{colors.texte-principal}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "1rem 2rem"
  button-primary-hover:
    backgroundColor: "{colors.orange-brasier}"
    textColor: "{colors.texte-principal}"
    rounded: "{rounded.md}"
    padding: "1rem 2rem"
  button-secondary:
    backgroundColor: "{colors.fond-table}"
    textColor: "{colors.orange-brasier}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "1rem 2rem"
  card-clickable:
    backgroundColor: "{colors.fond-table}"
    textColor: "{colors.texte-principal}"
    rounded: "{rounded.lg}"
    padding: "0"
  panel:
    backgroundColor: "{colors.fond-table}"
    textColor: "{colors.texte-secondaire}"
    rounded: "{rounded.lg}"
    padding: "1.25rem"
  badge:
    backgroundColor: "{colors.fond-table-haute}"
    textColor: "{colors.texte-secondaire}"
    rounded: "{rounded.sm}"
    padding: "0.125rem 0.5rem"
  badge-gold-solid:
    backgroundColor: "{colors.or-peu-commune}"
    textColor: "{colors.fond-salle}"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    padding: "0.125rem 0.5rem"
  stat-pill:
    backgroundColor: "{colors.fond-table-haute}"
    textColor: "{colors.texte-secondaire}"
    rounded: "{rounded.full}"
    padding: "0.375rem 0.75rem"
  input:
    backgroundColor: "{colors.fond-table}"
    textColor: "{colors.texte-principal}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "0.75rem 1rem"
---

# Design System: Pauper Duel Commander

## Overview

**Creative North Star: "La salle de tournoi"**

Une salle sombre, des tables éclairées, et l'orange comme lumière de signalisation. Le fond n'est pas noir mais presque (`#0A0E13`), texturé d'un grain imperceptible : c'est la pièce, pas l'interface. Ce qui brille, ce sont les illustrations de cartes et les quelques marqueurs orange qui disent où aller. L'interface elle-même se tait.

La tension à tenir est celle du format lui-même : le Pauper Duel Commander est **compétitif et bon marché**. Un site qui ne serait que « salle de tournoi » deviendrait intimidant ; un site qui ne serait qu'accueillant perdrait le sérieux qu'attend un joueur qui prépare un top 8. La résolution passe par la hiérarchie, pas par le ton : la densité et la précision servent le joueur expérimenté, tandis que les points d'entrée (tuiles d'accès rapide, parcours « débuter », listes taguées) restent larges et explicites pour celui qui découvre. Le site accueille par sa structure, pas en baissant son niveau d'exigence.

Deux dérives sont explicitement rejetées. **Le site « gamer » criard** — néons, dégradés partout, halos sur chaque bloc — est la pente naturelle d'un fond noir et d'un orange vif ; c'est pourquoi l'accent est rationné. **Le tableur administratif** — colonnes grises sans illustration ni hiérarchie — est la pente inverse d'un site de règles et de résultats ; c'est pourquoi l'illustration de carte est traitée comme un contenu de premier plan, jamais comme une décoration optionnelle.

**Key Characteristics:**
- Fond quasi-noir texturé, surfaces empilées en trois niveaux tonaux
- Un seul accent chaud rationné, doublé d'un or réservé au mérite et à la rareté
- Titres en capitales condensées, corps de texte neutre et lisible
- Les noms de cartes portent la police officielle de Magic (Beleren)
- Ombres permanentes et structurelles sur les objets élevés, absentes des simples conteneurs
- Rien n'est chargé depuis un CDN tiers : polices et illustrations sont maîtrisées

## Colors

Une salle sombre traversée par deux chaleurs : l'orange qui appelle à l'action, l'or qui distingue. Tout le reste est neutre et froid, pour que ces deux-là restent audibles.

### Primary
- **Orange Brasier** (`#FF5722`) : l'unique couleur d'action. Boutons primaires, liens actifs, bordure des objets cliquables, item de navigation courant, anneau de focus clavier. Sa rareté est ce qui le rend lisible : dès qu'il apparaît deux fois dans un même bloc pour deux raisons différentes, il ne signale plus rien.
- **Orange Brasier Clair** (`#FF7043`) et **Sombre** (`#E64A19`) : uniquement les extrémités du dégradé à 135° des boutons primaires et du texte en `text-magic-gradient`. Ne jamais les employer en aplat seuls.

### Secondary
- **Or Peu Commune** (`#FFA500`) : le mérite et la rareté. Places de podium, badges de résultat, surtitres (`eyebrow`), libellés des tuiles d'accès rapide. Le nom n'est pas décoratif : en Pauper Duel Commander, la rareté « peu commune » est précisément ce qui définit un général légal. L'or dit « cette chose a été distinguée ».

### Neutral
- **Fond Salle** (`#0A0E13`) : le fond de page. Porte un dégradé orange à 3 % en haut et un grain fractal à 3 % — imperceptibles isolément, mais ils empêchent le noir de paraître plat.
- **Fond Table** (`#141821`) : les surfaces posées sur la page. Cartes, panneaux, en-têtes de section.
- **Fond Table Haute** (`#1C212B`) : le niveau au-dessus. Badges, pastilles, état survolé d'une tuile.
- **Texte Principal** (`#FFFFFF`) : titres et données de premier plan.
- **Texte Secondaire** (`#B8BEC8`) : corps de texte courant.
- **Texte Atténué** (`#8B93A1`) : métadonnées, dates, légendes.

### Mana
Les six couleurs officielles de Magic — **Blanc** (`#F8F6F1`), **Bleu** (`#0E68AB`), **Noir** (`#150B00`), **Rouge** (`#D3202A`), **Vert** (`#00733E`), **Incolore** (`#BEB9B2`) — servent exclusivement aux symboles de mana et aux identités de couleur. Ce sont des données, pas une palette : elles ne colorent jamais un élément d'interface.

### Named Rules

**La Règle du Signal.** L'orange marque ce sur quoi on peut agir. Un bloc décoratif, un encadré informatif ou un conteneur non cliquable n'y ont pas droit — c'est le rôle de `panel`. La bordure orange de `magic-card` est réservée aux objets cliquables : la respecter garde à l'accent sa fonction de balise. (Principe fort, appliqué par défaut ; un écart doit être un choix conscient, pas un oubli.)

**La Règle du Plancher de Contraste.** `#8B93A1` est le gris le plus clair autorisé pour du texte : il mesure 4,9:1 sur `#141821`. Il ne se dilue jamais avec une opacité. Le gris précédent (`#6B7280`) tombait à 3,67:1 et échouait à WCAG AA partout où il servait — pieds de page, noms de joueurs, dates.

## Typography

**Display Font:** Barlow Condensed (avec Impact, sans-serif)
**Body Font:** Inter (avec system-ui, sans-serif)
**Card Name Font:** Beleren (avec Barlow Condensed, serif)

**Character:** Un condensé large-capitales qui évoque l'affiche de tournoi, posé sur une grotesque neutre qui ne fatigue pas sur un long texte de règles. Le contraste entre les deux fait tout le travail de hiérarchie : les titres crient, le corps parle normalement. Beleren, la police des noms de cartes Magic, n'est pas un choix esthétique mais sémantique — elle signale « ceci est une carte », et rend un nom immédiatement reconnaissable au milieu d'une phrase.

Les trois familles sont **auto-hébergées** en WOFF2 (`site/public/fonts/`), en trois graisses de Barlow Condensed (400, 600, 700) et une variable Inter (400–600). Aucun CDN tiers, aucune requête externe.

### Hierarchy
- **Display** (700, `clamp(2.25rem, 6vw, 3.75rem)`, interlignage 0.95) : le titre de la page d'accueil uniquement. Interlignage sous 1 pour que deux lignes capitales forment un bloc compact.
- **Headline** (700, `clamp(1.875rem, 5vw, 3rem)`, 1.1) : le `h1` de chaque page intérieure.
- **Title** (700, `clamp(1.125rem, 2.5vw, 1.5rem)`, capitales, `0.025em`) : les titres de section, précédés d'une barre orange de 4 px.
- **Body** (400, 1rem, 1.625) : tout le texte courant. Largeur limitée par `max-w-2xl` à `max-w-3xl` pour rester sous ~75 caractères par ligne.
- **Label** (700, 0.75rem, capitales, `0.05em`) : badges, surtitres, en-têtes de tableau. Les surtitres (`eyebrow`) montent à `0.14em` : à cette taille, l'interlettrage est ce qui rend les capitales lisibles.
- **Card Name** (Beleren 700, 0.875rem) : tout nom de carte Magic, systématiquement accompagné de `lang="en"` — les noms restent en anglais dans les trois langues du site.

### Named Rules

**La Règle des Capitales Condensées.** Les titres sont en capitales, condensés, avec un interlettrage serré (`-0.025em`) et une ombre portée `0 2px 8px rgba(0,0,0,0.5)`. Cette ombre est omise sur le texte en dégradé : le texte y est transparent, l'ombre ne rendrait rien tout en coûtant un repaint.

**La Règle de la Langue des Cartes.** Un nom de carte s'écrit toujours dans son orthographe canonique Scryfall, en Beleren, marqué `lang="en"`. Le texte d'interface qui l'accompagne, lui, se traduit. Les deux ne se mélangent jamais dans le même élément.

## Layout

Un conteneur unique et centré (`container mx-auto`) avec une gouttière de 1 rem qui passe à 1,5 rem au-delà de 768 px. Les blocs de lecture sont bornés par `max-w-2xl` à `max-w-5xl` selon la densité ; le contenu ne s'étale jamais sur toute la largeur d'un grand écran.

Le rythme vertical est porté par les sections : `py-10` sur mobile, `py-14` au-delà de `md` pour les en-têtes de page ; `py-8` à `py-16` pour les sections de contenu. À l'intérieur d'un bloc, l'espacement suit une échelle courte — 0.5 / 0.75 / 1 / 1.25 rem — appliquée en `gap` plutôt qu'en marges, pour que la suppression d'un élément ne laisse pas de trou.

**Mobile-first, sans exception.** Chaque grille démarre à une colonne et s'élargit par paliers. Les grilles denses en sont l'illustration : la liste des decklists passe de 1 colonne à 3 (`sm`), 4 (`md`), 5 (`lg`) puis 6 (`xl`) — c'est le **nombre de colonnes**, jamais un recadrage de l'illustration, qui règle la taille des cartes. Sous `sm`, une carte verticale devient une ligne compacte, et un rayon horizontal devient un carrousel à défilement magnétique : empiler des tuiles pleine hauteur sur un téléphone produirait des pages de plusieurs milliers de pixels.

Un en-tête fixe surplombe la page ; toute ancre porte `scroll-margin-top: 5rem` pour ne pas atterrir dessous.

## Elevation & Depth

La profondeur se construit d'abord par **superposition tonale** — trois fonds empilés, séparés par des bordures d'un pixel à très faible opacité (`white/8` à `white/10`) — puis par des **ombres structurelles et permanentes**. Une carte porte son ombre au repos : c'est ce qui la pose au-dessus de la page, indépendamment de toute interaction. Le survol ne crée pas l'ombre, il l'amplifie.

La distinction est nette : les objets **élevés** (cartes, decks, tuiles cliquables) portent une ombre permanente ; les simples **conteneurs** (`panel`) n'en portent aucune et se contentent de leur fond et de leur filet de bordure. Une ombre sur un panneau non cliquable brouillerait cette lecture.

Les ombres sont **teintées orange**, jamais neutres : elles combinent une diffusion chaude à très faible opacité et une ombre sombre porteuse. C'est ce qui les rattache à la salle éclairée plutôt qu'à un empilement de fenêtres.

### Shadow Vocabulary
- **Repos** (`0 4px 24px -2px rgba(255,87,34,0.08), 0 8px 16px -4px rgba(0,0,0,0.3)`) : toute carte ou deck au repos.
- **Survol** (`0 12px 40px -4px rgba(255,87,34,0.15), 0 16px 24px -8px rgba(0,0,0,0.4)`) : la même carte survolée. L'orange double d'intensité, l'ombre portée s'allonge.
- **Halo** (`0 0 20px rgba(255,87,34,0.15), 0 0 40px rgba(255,87,34,0.08)`) : réservé au bouton primaire, qui n'a pas d'ombre portée mais un rayonnement.
- **Halo intense** (`0 0 30px rgba(255,87,34,0.25), 0 0 60px rgba(255,110,64,0.12)`) : le même bouton survolé.

### Named Rules

**La Règle du Halo Rationné.** Le rayonnement orange appartient au bouton primaire et à lui seul. Étendu aux cartes, aux panneaux ou aux sections, il produit exactement le site « gamer » que ce système refuse.

## Shapes

Un langage de formes doux mais contenu, sur quatre rayons seulement : **4 px** pour les badges, **8 px** pour les boutons, champs et lignes de tableau, **12 px** pour les cartes, panneaux et tuiles, et le **cercle complet** pour les pastilles statistiques et les marqueurs de place.

Les bordures sont l'outil de séparation principal, et elles sont presque toujours **d'un seul pixel à faible opacité** : `white/8` pour un conteneur neutre, `card-border/25` pour une tuile, `brand-orange/20` pour un objet cliquable au repos qui monte à `/50` au survol. La seule bordure épaisse (2 px) appartient à `magic-card` et au bouton secondaire — l'épaisseur est elle aussi un signal d'interactivité.

Aucun angle vif, aucune découpe, aucune forme irrégulière : la géométrie ne cherche pas à être expressive. Ce sont les illustrations de cartes, en `aspect-[4/3]` ou `aspect-[488/680]`, qui apportent la variété visuelle.

## Components

### Buttons
- **Shape:** coins doux (8 px), capitales condensées, interlettrage `0.05em`, padding `1rem 2rem`.
- **Primary:** dégradé orange à 135° (`#FF7043` → `#FF5722` → `#E64A19`), texte blanc avec ombre portée pour tenir sur la partie claire du dégradé, halo orange en guise d'ombre.
- **Hover / Focus:** le halo passe à sa version intense sur 300 ms. Le focus clavier ajoute un contour orange de 2 px décalé de 2 px.
- **Secondary:** bordure orange de 2 px sur fond orange à 10 %, texte orange. Au survol, le fond monte à 20 % et la bordure s'éclaircit.

### Cards / Containers
- **Corner Style:** 12 px.
- **Background:** `Fond Table` (`#141821`), avec pour `magic-card` un voile en dégradé orange de 6 % à 2 %.
- **Shadow Strategy:** ombre de repos permanente, amplifiée au survol (cf. Elevation & Depth). `panel` n'en porte aucune.
- **Border:** `magic-card` porte 2 px d'orange à 20 %, portés à 50 % au survol ; `panel` se contente d'un filet `white/8`.
- **Internal Padding:** 1,25 rem pour un panneau, 0,875 rem pour une carte de deck.

### Badges
- **Style:** 4 px de rayon, capitales de 12 px, fond teinté à 15 % et bordure à 30 % de la couleur porteuse.
- **Variants:** or (résultat), orange (action), rouge (banni), vert (nouveau), gris (neutre).
- **Solid variants:** `badge--gold-solid` et `badge--new-solid` renversent le principe — fond plein, texte sombre — parce qu'ils se posent **sur une illustration de carte**. Un badge translucide y devient illisible dès que l'illustration est claire ; l'or plein sur texte `#0A0E13` mesure 9,8:1.

### Inputs / Fields
- **Style:** fond `Fond Table`, bordure `white/10`, rayon 8 px, padding `0.75rem 1rem`.
- **Focus:** anneau orange à 50 % d'opacité sur 2 px, sans déplacement du champ.

### Navigation
- **Style:** en-tête fixe, libellés en capitales condensées. L'item courant est orange ; les autres sont en texte secondaire et passent à l'orange au survol.
- **Mobile:** panneau plein écran, libellés agrandis à `text-2xl` pour rester des cibles tactiles confortables.

### Aperçu de carte au survol
Le composant signature du site. Tout nom de carte peut porter `card-hover-trigger` avec ses `data-card-name` / `data-card-image` : au survol sur pointeur fin, au tap sur tactile, au focus au clavier, une prévisualisation de la carte apparaît, accompagnée d'un lien Scryfall. Elle se replace au défilement et se ferme par Échap, clic extérieur ou bouton. C'est ce qui permet à une annonce de bannissement de nommer des cartes sans que le lecteur ait à les connaître par cœur.

## Do's and Don'ts

### Do:
- **Do** réserver l'Orange Brasier à ce qui est actionnable, et utiliser `panel` pour un conteneur qui ne l'est pas.
- **Do** poser une ombre permanente sur un objet élevé, et aucune sur un simple conteneur.
- **Do** réduire une carte en ajoutant des colonnes, jamais en recadrant son illustration.
- **Do** écrire les noms de cartes en Beleren, orthographe Scryfall, avec `lang="en"`.
- **Do** utiliser un badge plein (`--solid`) dès qu'il se pose sur une illustration.
- **Do** prévoir un repli textuel lisible quand une illustration Scryfall manque : une carte sans image doit rester présente et nommée.
- **Do** démarrer chaque grille à une colonne et l'élargir par paliers.

### Don't:
- **Don't** diluer `#8B93A1` avec une opacité : c'est le plancher de contraste, pas une base à éclaircir.
- **Don't** étendre le halo orange au-delà du bouton primaire.
- **Don't** empiler plusieurs sections décoratives avant le contenu que la page promet : les listes et tableaux doivent rester atteignables sans défilement prolongé.
- **Don't** répéter un même qualificatif sur chaque élément d'une liste — un marqueur discret et une légende unique valent mieux qu'un mot repris cinq fois.
- **Don't** charger une police ou une feuille de style depuis un CDN tiers.
- **Don't** écrire une couleur en dur dans un composant : tout passe par les tokens `@theme` de `globals.css`.
- **Don't** produire un tableau gris sans illustration ni hiérarchie — ni, à l'inverse, un empilement de néons et de dégradés.

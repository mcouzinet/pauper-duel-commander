# Refonte UX des decklists (étape 1)

## Objectif
Refondre la consultation des decklists PDC (page détail en priorité, index ensuite) en
s'inspirant des sites de référence, pour qu'un joueur puisse lire, comprendre, tester et
exporter une liste en quelques secondes, sur téléphone à côté de son tapis comme sur desktop.
Livrable : une PR non mergée sur une branche dédiée, avec captures avant/après (mobile et desktop).

## Existant (à lire avant de toucher quoi que ce soit)
- Page détail : site/src/components/pages/DecklistDetailPage.astro. En-tête (faits puis
  image du général), sommaire collant par type, CardList en 2 à 4 colonnes, DeckStats
  (courbe + 2 donuts), decks du même général, boutons copier / télécharger / valider.
- Index : DecklistIndexPage.astro + DeckCard.astro (rayons, recherche et filtres côté
  client sur les data-*, paramètre ?commander= déjà géré).
- Données : lib/deck-renderer.ts (enrichissement Scryfall au build), lib/decklists.ts,
  scripts/card-preview.ts (aperçu au survol, au tap, au clavier).
- Les commentaires en tête de ces fichiers racontent les audits précédents. Ne pas
  réintroduire ce qu'ils ont corrigé : page de 12 700 px sur mobile, art avant le titre,
  aperçu bloqué au tap.
- PRODUCT.md (utilisateurs et règle d'arbitrage : entre une page dense et une page qui
  explique, on choisit celle qui explique, sans jamais ralentir le joueur de tournoi) et
  DESIGN.md (système visuel, grilles mobile-first) à la racine du dépôt.

## Références (ouvrir les pages, ne pas travailler de mémoire)
- Moxfield : vues texte / grille / piles, regroupement (type, coût, couleur) et tri,
  courbe de mana cliquable qui filtre la liste, symboles de mana comparés aux sources des
  terrains, main de départ (7 cartes, mulligan, pioche), calcul hypergéométrique, decks
  du même général.
- Archidekt : piles visuelles par catégorie, playtest, onglet stats.
- MTGGoldfish : prix papier / MTGO, « Card Breakdown » visuel par type, decks similaires.
- EDHREC : taux d'inclusion d'une carte chez les autres listes du même général,
  regroupement par fonction.
- MTGTop8, Melee.gg : liste de tournoi compacte, export MTGO / Arena, « decks contenant
  cette carte ».
Pour chaque motif : le problème qu'il résout, sa pertinence pour PDC (format pauper, une
soixantaine de listes, surtout des résultats de tournoi), et ce qu'on écarte (comptes,
likes, commentaires, pubs) avec la raison.

## Périmètre
P1, page détail :
1. Bascule de vue Liste / Visuel (grille d'images groupée), mémorisée par visiteur
   (localStorage dans un try/catch). Les images ne se chargent qu'à l'activation du mode
   visuel. Repli texte si l'illustration manque.
2. Regrouper par type / coût / couleur, trier par coût / nom, côté client, sur le HTML
   déjà rendu.
3. En-tête : général (et partenaire) mis en avant, résultat de tournoi, auteur, date,
   couleurs, nombre de cartes. Actions principales visibles sans défiler sur mobile.
4. Stats utiles au joueur : courbe de mana dont une barre filtre ou surligne la liste,
   coût moyen avec et sans terrains, symboles de couleur comparés aux sources de mana,
   répartition par type. Retirer les donuts s'ils n'apportent rien.
5. Main de départ : tirer 7, mulligan, piocher, nouvelle main (JS client, général hors
   bibliothèque).
6. Export : copier au format MTGO / Arena / Moxfield, télécharger, envoyer au validateur
   (garder l'existant).

P2 :
7. Pour chaque carte, combien d'autres listes PDC du même général la jouent (calcul au
   build). Depuis l'index, « decks contenant cette carte » (?card=, sur le modèle de
   ?commander=).
8. Prix total indicatif (EUR et tix, Scryfall au build), daté et présenté comme une
   estimation.
9. Index : cartes de deck alignées sur la nouvelle page, recherche par carte.

Hors périmètre : tout changement du schéma JSON (sideboard, primer, catégories
manuelles), comptes / likes / commentaires, playtest complet, formulaires de soumission.

## Contraintes (cf. CLAUDE.md)
- Astro statique, TypeScript vanilla, aucun framework UI, aucune nouvelle dépendance de
  graphique (SVG / CSS).
- Tout ce qui vient de Scryfall est résolu au build, avec toujours un repli texte.
- Mobile-first. Avec JS désactivé, la liste complète reste lisible.
- i18n fr / en / it : t(), clés dans les trois catalogues, aucun littéral par langue.
  Scripts client : table locale indexée par document.documentElement.lang.
- Une page = un composant partagé. URL via route(). Classes composites de globals.css
  (magic-card réservé au cliquable, panel sinon). --color-text-muted jamais dilué.
- Accessibilité : tout au clavier, aria-pressed sur les bascules, aucune information
  portée par la couleur seule.
- Performance : pas de régression du LCP. La vue Liste par défaut ne charge pas 99 images.
- Nouvelles actions tracées via window.pdcTrack (vue, regroupement, main, export).

## Méthode
1. Benchmark dans le navigateur, résumé en tableau motif / référence / décision.
2. Maquette basse fidélité de la page détail (375 px et 1280 px). La présenter et
   attendre mon accord avant d'implémenter.
3. Implémentation par tranches verticales, chacune vérifiée dans le navigateur
   à 375 px et 1280 px.

## Terminé quand
- Le P1 fonctionne sur une liste avec partenaire (wilson-refined-grizzly-endstep-1-1er)
  et une sans (quaketusk-boar-tournoi-de-l-automne-1er), dans les trois langues.
- npm run build, npm run check (0 erreur, 0 avertissement), npm test et la
  vérification des liens internes de la CI passent.
- Lighthouse mobile sur la page détail : performance et accessibilité au moins égales
  à l'existant (mesuré avant de commencer).
- PR ouverte avec le benchmark, les captures avant/après et la liste de ce qui est
  reporté (P2 ou étape suivante).

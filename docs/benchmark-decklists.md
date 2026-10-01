# Benchmark : pages de deck (étape 1 de la refonte UX)

Six sites consultés le 1er octobre 2026 dans le navigateur, à 375 px et 1280 px :
Moxfield, Archidekt, MTGGoldfish, EDHREC, MTGTop8, Melee.gg. Chaque motif est lu à
travers PDC : un format pauper, une soixantaine de listes, presque toutes issues d'un
top 8, et la règle de PRODUCT.md (le curieux passe avant la densité, sans jamais
ralentir le joueur de tournoi).

## La page actuelle, mesurée

Liste de référence : `quaketusk-boar-tournoi-de-l-automne-1er`, en production.

- **Mobile (375 px)** : 4 850 px de haut. L'en-tête occupe 680 px ; la première carte
  de la liste arrive à 800 px, sous la ligne de flottaison.
- **Liste en 2 colonnes sur mobile** : les noms sont tronqués (« Brimstone V… »,
  « Temur Battle… », « Red Mage's… »). Le nom d'une carte est l'information à lire.
- **Quatre boutons de même poids** (copier, télécharger, valider, voir le tournoi) ;
  « Voir le tournoi » répète le badge de résultat juste au-dessus.
- **Stats** : deux donuts. Celui des couleurs compte des cartes (« Rouge : 57,
  Incolore : 42 ») et non des symboles de mana ; la légende des types reste en anglais
  dans la version française (« Creature: 41 »).
- **Aucune vue visuelle** : un curieux qui ne connaît pas les noms ne voit que
  l'illustration du général.

## Motifs

| # | Motif | Vu chez | Problème résolu | Décision pour PDC |
|---|---|---|---|---|
| 1 | Bascule Liste / Visuel | Moxfield (6 styles, dans une fenêtre), Archidekt (sélecteur dans la page), EDHREC (3 icônes), Melee (bouton « Images ») | Reconnaître une carte à son illustration | **P1.** Deux vues seulement, Liste par défaut, bascule visible dans la page. Les images ne se chargent qu'à l'activation. |
| 2 | Regrouper et trier | Moxfield (10 regroupements, 5 tris), Archidekt | Lire la liste selon la question posée | **P1.** Regrouper par type, coût ou couleur ; trier par coût ou nom. Rareté, édition, artiste : sans objet en PDC. |
| 3 | Une colonne sur mobile, noms complets, coût à droite | Moxfield (mobile) | Les noms tronqués | **P1.** Une colonne sous 640 px, deux puis trois au-delà. |
| 4 | Courbe de mana cliquable qui filtre la liste | Moxfield | Voir quelles cartes font la courbe | **P1.** Chaque barre est un bouton ; un rappel « Coût 3 · 15 cartes » permet de retirer le filtre. |
| 5 | Symboles de couleur comparés aux sources de mana | Moxfield (Color Analysis) | Vérifier que la base de mana suit les sorts | **P1.** Barres appariées par couleur ; une seule ligne pour un deck monocolore. |
| 6 | Main de départ : 7 cartes, mulligan, pioche | Moxfield, Archidekt (playtester) | « Est-ce que ça se joue ? » sans sortir les cartes | **P1.** Mulligan de Londres (règle 3.2), général hors bibliothèque. Playtest complet écarté. |
| 7 | Probabilités : hypergéométrique, « chance de jouer sa courbe » | Moxfield | Calculs de joueur expert | **Écarté.** Outil d'expert ; le nombre moyen de terrains dans une main de départ suffit. |
| 8 | Récapitulatif collant (nombre de cartes par groupe) | Moxfield (barre du bas) | S'orienter dans 99 cartes | **P1, déjà là.** Le sommaire collant reste, et suit le regroupement choisi. |
| 9 | Contexte de tournoi : place, score, événement | MTGGoldfish, MTGTop8, Melee | Savoir d'où vient la liste | **P1.** Badge or « 1er · Tournoi de l'automne · 3-0-0 » qui mène au tournoi ; le bouton en double disparaît. |
| 10 | Les autres decks du même top 8 | MTGTop8 (colonne du top 8), Melee (classement sous la liste) | Comparer les listes d'un même événement | **P2.** Bandeau « Du même top 8 » sous la liste. |
| 11 | Taux d'inclusion d'une carte | EDHREC (inclusion et synergie sur 137 decks), MTGGoldfish (« 3.8 in 79% of decks ») | Séparer le cœur d'un archétype des choix personnels | **P2, en nombres.** PDC compte 1 à 4 listes par général : un pourcentage sur deux listes ne veut rien dire. « Dans 2 des 3 listes de ce général. » |
| 12 | Prix total | MTGGoldfish (papier et tix), Moxfield, Archidekt | Le budget, argument du pauper | **P2.** Estimation EUR et tix tirée de Scryfall au build, datée. |
| 13 | Export : copier, fichier, MTGO, Arena | MTGGoldfish (téléchargement, export Arena, feuille d'inscription PDF), MTGTop8 (.dec MTGO) | Reprendre la liste ailleurs | **P1.** « Copier la liste » devient l'action principale ; « Exporter » regroupe les formats. Feuille d'inscription PDF : plus tard. |
| 14 | Filtre texte dans le deck | Archidekt (« Local filter ») | Retrouver une carte précise | **Écarté pour l'étape 1.** La recherche du navigateur suffit ; à revoir sur retour des joueurs. |
| 15 | Noms de cartes traduits | Melee (noms en français) | Lecture dans sa langue | **Écarté.** Les noms restent en anglais, orthographe Scryfall (règle de la langue des cartes). |
| 16 | Comptes, likes, vues, commentaires, achat, publicité | Moxfield, Archidekt, MTGGoldfish | Communauté, monétisation | **Écarté.** Pas de comptes ni de base de données ; le Discord prolonge le site. |

## Données disponibles au build (vérifié dans le cache Scryfall)

`prices.eur`, `prices.tix`, `produced_mana`, `rarity`, `mana_cost`, `cmc`, ainsi que la
place et le score du tournoi (`lib/decklists.ts`). Aucun motif retenu n'exige de
requête à l'exécution.

Exemples réels, tels que la page les calcule (la maquette, faite avant le code,
comptait les deux faces des cartes à aventure et classait autrement une carte
artefact-terrain, d'où quelques écarts) :

- **Quaketusk Boar** (Tournoi de l'automne, 1er, 3-0-0) : 38 terrains, courbe
  1 / 14 / 13 / 15 / 7 / 3 / 7 / 1 (coûts 0 à 7+), coût moyen 2,9 hors terrains et
  1,8 terrains compris ; 62 symboles rouges (face avant) pour 43 sources de rouge.
- **Wilson, Refined Grizzly + Far Traveler** (Endstep #1, 1er) : 35 terrains, coût
  moyen 2,0 ; 41 symboles blancs pour 20 sources, 26 symboles verts pour 21 sources
  (61 % des symboles sont blancs, 49 % des sources).

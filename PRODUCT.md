# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

**Utilisateur premier : le curieux qui découvre le format.** Il connaît Magic, pas le Pauper Duel Commander. Il veut comprendre en quelques minutes ce que c'est, pourquoi ça vaut le coup, et repartir avec une liste jouable. C'est lui qui tranche quand deux besoins s'opposent : entre une page dense et une page qui explique, on choisit celle qui explique.

**Le joueur qui prépare un tournoi.** Il connaît le format et vient vérifier un point précis — une carte est-elle bannie, son deck est-il légal, quels généraux dominent. Il consulte souvent depuis un téléphone, parfois pendant l'événement, à côté d'un tapis de jeu. L'exactitude et la rapidité d'accès priment pour lui ; il ne doit jamais être ralenti par ce qui sert le curieux.

**L'organisateur de tournoi.** Il annonce son événement et publie ses résultats. Il passe par un formulaire protégé par un code d'accès partagé. C'est un contributeur ponctuel, pas un utilisateur quotidien.

## Product Purpose

Le site est la référence consultable du format Pauper Duel Commander : les règles, la ban list et son historique, les decklists, le métagame, les tournois, et un validateur qui vérifie mécaniquement la légalité d'un deck.

Il existe parce que ces informations étaient dispersées entre annonces, messages Discord et tableurs. Le succès se mesure à deux choses : un joueur trouve la réponse à sa question sans demander à quelqu'un, et un curieux comprend le format sans avoir à le pratiquer d'abord.

## Positioning

**Site de référence communautaire, pas organe officiel.** Le comité décide ailleurs ; le site documente ses annonces fidèlement, en citant nommément la source de chacune. Il rapporte, il ne décrète pas. Cette distinction est structurante : elle interdit de présenter une interprétation comme une décision, et elle oblige à conserver la traçabilité de chaque changement de ban list.

Ce qu'un site voisin ne pourrait pas copier sans le même travail : **un validateur qui applique réellement les règles du format**, y compris celles que personne n'applique correctement de tête — notamment l'éligibilité d'un général, qui se juge sur *toutes* ses impressions papier et MTGO, Arena exclu, et non sur son édition par défaut. Le reste du site est de la documentation ; le validateur est une implémentation.

## Operating Context

Le comité publie une annonce sur le format **tous les deux mois, le premier lundi des mois pairs**. Chaque annonce modifie la ban list et devient une entrée d'historique horodatée et sourcée.

Les tournois sont organisés par des boutiques et des associations, en France, et leurs résultats arrivent après coup — soit saisis à la main, soit soumis par l'organisateur via le formulaire. Une decklist gagnante peut être publiée des semaines après l'événement.

La consultation se fait largement sur téléphone, y compris en tournoi. Le site est **entièrement pré-rendu** : « aujourd'hui » est figé au moment du build, ce qui impose une reconstruction hebdomadaire pour qu'un tournoi passé cesse d'être annoncé comme à venir.

Les données de cartes (noms, raretés, illustrations, légalité Pauper) proviennent de **Scryfall**, récupérées au build et mises en cache.

## Capabilities and Constraints

- **Trois langues** : français, anglais, italien. Le français est la langue d'origine ; l'anglais sert de repli, puis le français. Toute clé absente s'affiche brute, jamais vide.
- **Validateur de deck** : neuf règles, exposé en API. C'est la seule chose qui s'exécute à l'exécution — le reste du site n'est que des fichiers statiques.
- **La ban list n'est jamais optionnelle.** Si elle ne peut pas être chargée, le validateur échoue bruyamment plutôt que de valider un deck illégal en silence. C'est un bug corrigé, pas une hypothèse.
- **Soumissions modérées** : decklists ouvertes au public, tournois réservés aux organisateurs par code d'accès. Une soumission n'est jamais publiée directement : elle ouvre une pull request qu'un humain relit. Une decklist illégale est refusée avant d'atteindre le dépôt.
- **Une liste illégale n'annule pas un tournoi** : elle est écartée, le tournoi est publié quand même. Un résultat est un fait ; il ne dépend pas d'une faute de frappe sur l'une des huit listes.
- **Le formulaire tournoi publie des résultats, pas des annonces** : une date future est refusée.
- Le contenu est du JSON versionné, édité à la main ou par pull request. Il n'y a **pas de base de données ni d'espace d'administration**.
- Terminologie : « général » (le commandant), « peu commune » (la rareté qui le rend éligible), « ban list », « méta », « top 8 ».

## Brand Commitments

- **Nom** : Pauper Duel Commander (abrégé PDC). Le logo existe et est fourni.
- **Promesse** : « Le Commander compétitif en 1 contre 1, avec des communes. » — « 100 cartes, un général peu commun, 20 points de vie : la profondeur du Commander, l'accessibilité du Pauper. » Ces deux formulations sont la position du produit ; elles ne se réécrivent pas à la légère.
- **La mention légale Wizards of the Coast est obligatoire** en pied de page, dans toutes les langues.
- **Le Discord est le prolongement du site** (`discord.gg/4MR2sSWdms`) : ce que le site ne peut pas trancher s'y discute.
- La cadence d'annonce du comité est affichée comme un fait ; elle doit rester exacte.

## Evidence on Hand

Tout le contenu publié est réel et vérifiable :

- **18 tournois** (`site/content/tournaments/`), avec top 8, méta et nombre de participants quand ils sont connus.
- **26 decklists** (`site/content/decklists/`), la plupart rattachées à un résultat de tournoi.
- **2 annonces de ban list** (`site/content/banlist-history/`), sourcées nommément : LofiBlue (12/12/2025, liste initiale) et Na-O-H (03/08/2026).
- **17 cartes bannies** (`site/content/banlist.json`), dont la ban list de référence du validateur.
- Illustrations et données de cartes : Scryfall.

**Absences à ne jamais combler par de l'invention.** Il n'y a aucun témoignage de joueur, aucun chiffre de fréquentation, aucun partenariat, aucun classement national, aucune donnée financière. Un nom de joueur, une place ou un nombre de participants qui n'est pas dans les fichiers de contenu n'existe pas : une donnée manquante reste vide plutôt que plausible.

## Product Principles

1. **Rapporter, pas décréter.** Toute décision de format citée est attribuée à sa source et datée. Le site n'a pas d'opinion sur les règles ; il les documente.
2. **Une donnée manquante reste manquante.** Aucun résultat, participant ou nom n'est comblé par déduction. Le vide est une information ; la vraisemblance est un mensonge.
3. **Le curieux passe avant la densité.** Quand une page peut servir les deux, elle sert d'abord celui qui découvre — sans jamais retirer au joueur confirmé l'information exacte qu'il vient chercher.
4. **Ce qui est vérifiable est vérifié par la machine.** La légalité d'un deck ne se juge pas à l'œil : le validateur applique les règles, y compris celles qu'un humain applique mal de mémoire.
5. **Une contribution passe par une relecture.** Rien de ce que soumet le public n'atteint le site sans qu'un humain l'ait validé.

## Accessibility & Inclusion

Le plancher est **WCAG AA**, et il est déjà tenu : contraste minimal de 4,9:1 pour le texte atténué, focus clavier visible sur tout élément interactif, lien d'évitement vers le contenu principal, régions `aria-live` pour les résultats filtrés, et respect de `prefers-reduced-motion`.

Deux exigences viennent de l'usage réel plutôt que du standard : le site doit rester utilisable **sur un téléphone, en tournoi, dans une salle mal éclairée**, et **toute illustration de carte doit avoir un repli textuel** — une carte dont l'image ne se résout pas doit rester présente et nommée, jamais disparaître de la liste.

# Étude : decklists et tournois à 1 000 listes

Octobre 2026. Question posée : le site statique (Astro, JSON dans le dépôt, envoi SFTP
vers OVH) tiendra-t-il quand il y aura 1 000 decklists, et faut-il passer par la base
de données disponible chez OVH (la MySQL de l'époque WordPress, inutilisée) ?

## Réponse courte

| Sujet | À 1 000 decklists | Verdict |
|---|---|---|
| Build Astro | 30 s en local (6 s aujourd'hui), linéaire | Tient |
| Publier un tournoi | une trentaine de fichiers, moins d'une minute | Tient |
| Changement global (en-tête, CSS, JS) | ~4 200 fichiers à envoyer, **40 à 60 min** de SFTP | **Casse** |
| Page index des decklists | 42 000 éléments HTML, 2 029 balises image | **Casse sur téléphone** |

Le build n'est pas le problème. Ce qui casse, c'est le **transport du déploiement**
(une connexion SFTP, un fichier après l'autre) et la **page index** qui affiche toute
la collection d'un coup.

**Recommandation** : garder l'architecture statique et le JSON relu par PR, corriger le
transport (envoi parallèle, ou SSH si l'offre OVH le permet) et alléger l'index. Ne pas
migrer les decklists en base comme source de vérité : cela ne règle aucun des deux
problèmes tant que les pages restent statiques, et cela coûterait des semaines.
Garder la base pour plus tard, comme **index de recherche** dérivé du JSON, le jour où
arrivera « decks contenant cette carte ».

## 1. Ce qui a été mesuré

### Déploiements réels (GitHub Actions, workflow « Deploy to OVH »)

| Commit | Fichiers envoyés | Durée SFTP | Par fichier |
|---|---|---|---|
| fcb87c5, refonte decklists | 282 sur 337 | 152 s | 0,54 s |
| f9f7dea, correction d'une liste | 158 sur 336 | 107 s | 0,68 s |
| dc74757, un tournoi soumis | 29 sur 336 | 20 s | 0,69 s |
| d28f629, correctif GA4 | 268 sur 324 | 226 s | 0,84 s |
| 8052511, sources vidéo et docs | 268 sur 324 | 238 s | 0,89 s |
| 70c5367, vidéo d'accueil | 273 sur 324 | 173 s | 0,63 s |

L'étape « Install & build » prend 14 à 32 s. Sur les six derniers déploiements, cinq
ont renvoyé presque toutes les pages : tout changement de mise en page, de CSS, de
JavaScript ou de texte d'en-tête modifie chaque fichier HTML. C'est le cas courant, pas
l'exception.

### Build à blanc sur une collection gonflée

Copie isolée du site ; les 59 decklists et 22 tournois réels clonés K fois, chaque copie
de tournoi reliée à ses propres listes (résultats, listes voisines et rayons se
comportent comme en vrai). Build sans `dist/` ni `.astro/`, comme en CI. Machine locale.

| Decklists | Tournois | Pages | Build | Mémoire max | HTML total | Index FR |
|---|---|---|---|---|---|---|
| 59 | 22 | 278 | 6 s | 571 Mo | 26 Mo | 290 Ko |
| 236 | 88 | 1 007 | 10 s | 740 Mo | 101 Mo | 921 Ko |
| 472 | 176 | 1 979 | 15 s | 944 Mo | 202 Mo | 1,8 Mo |
| 1 003 | 374 | 4 166 | 30 s | 1,1 Go | 428 Mo | 3,7 Mo |
| 2 006 | 748 | 8 297 | 59 s | 2,5 Go | 854 Mo | 7,4 Mo |

Environ 7 ms par page, linéaire. Le runner GitHub est plus lent que la machine locale
(estimation : 1 min de build à 1 000 listes, 2 min à 2 000), et ses 16 Go de mémoire
couvrent les 2,5 Go mesurés à 2 000.

Les copies réutilisent les mêmes cartes, donc le cache Scryfall était déjà chaud. En
vrai, de nouvelles cartes arrivent avec les listes ; leur coût est faible : le
préchauffage interroge Scryfall par lots de 75, soit environ 67 requêtes pour 5 000
cartes, et le cache de la CI ne récupère que les nouvelles.

### Page index

| Decklists | HTML brut | HTML compressé (gzip, actif en prod) | Éléments HTML | Balises image |
|---|---|---|---|---|
| 59 | 290 Ko | 20 Ko | 3 193 | 147 |
| 1 003 | 3,7 Mo | 66 Ko | 42 058 | 2 029 |

Le transfert reste léger grâce à la compression. Le coût est ailleurs : un téléphone
doit construire 42 000 éléments et le filtre côté client les parcourt tous. Chaque
carte de deck y est rendue deux fois (version compacte et version complète).

## 2. Rythme de croissance

Decklists par mois de publication : 7 en décembre 2025, 4 à 9 par mois ensuite, puis
27 en septembre 2026, depuis l'ouverture du formulaire tournoi.

- Au rythme de septembre (~30 par mois) : 1 000 listes dans **environ 2 ans et demi**.
- À 3 tournois par semaine (~100 listes par mois) : 1 000 listes dans **9 à 10 mois**.

L'horizon est donc de 1 à 3 ans : le temps de corriger, pas d'urgence.

## 3. Projections

| Événement | Fichiers envoyés | Durée SFTP estimée |
|---|---|---|
| Un tournoi et ses 8 listes, aujourd'hui | 29 (mesuré) | 20 s (mesuré) |
| Le même, à 1 000 listes | ~30 à 60 (pages du tournoi, des listes, des voisines, index, accueil, méta, sitemap) | < 1 min |
| Changement global, à 1 000 listes | ~4 200 | 38 à 62 min |
| Changement global, à 2 000 listes | ~8 300 | 75 à 123 min |

Au-delà de la durée, un envoi d'une heure multiplie les risques de coupure. Le
manifeste n'étant écrit qu'à la fin, une relance renvoie tout ce qui n'est pas confirmé :
pas de fichier perdu, mais un site à moitié à jour pendant l'incident.

## 4. Où passe le temps, par ordre d'importance

1. **Le transport SFTP séquentiel** : 0,5 à 0,9 s par fichier, quelle que soit sa
   taille. C'est de la latence (allers-retours entre le runner GitHub et OVH), pas du
   débit : 428 Mo de HTML passeraient en une minute environ sur une seule connexion
   rapide.
2. **La page index**, côté visiteur.
3. **Le build**, linéaire. Un gain facile existe : chaque page relit sur disque la fiche
   Scryfall de chacune de ses cartes (environ 80 lectures et analyses JSON par page et
   par langue). Un cache mémoire a fait passer la génération des pages de 28,3 s à
   19,4 s à 1 000 listes (−31 %, mesuré sur la copie de test).

## 5. Options

### A. Garder le statique, corriger le transport (recommandé)

- **A1. Envoi SFTP parallèle.** Répartir les fichiers modifiés en P lots envoyés par P
  connexions simultanées, après une première passe qui crée les dossiers, et les
  fichiers de `_astro/` (CSS, JS) d'abord pour qu'aucune page ne référence un fichier
  absent. Gain attendu : jusqu'à P fois plus rapide, soit 5 à 8 min à 1 000 listes avec
  8 connexions. Coût estimé : une demi-journée (`deploy-sftp.sh` et son test).
  À vérifier : le nombre de connexions simultanées accepté par l'offre OVH (commencer
  à 4).
- **A2. SSH, si l'offre le permet.** Chez OVH, le shell SSH est réservé aux offres Pro et Performance (à confirmer pour la nôtre) ; il s'ouvre
  avec les mêmes identifiants FTP-SSH. Alors `rsync` (ou une archive envoyée puis extraite
  sur place) ramène un déploiement complet à environ une minute. À vérifier dans
  l'espace client, onglet FTP-SSH.

### B. Alléger la page index (à faire avant ~300 listes)

- Rendre chaque carte de deck une seule fois, avec une mise en page responsive, au lieu
  de deux versions : éléments et balises image divisés par deux.
- Afficher les 24 à 48 listes les plus récentes en HTML et charger le reste à la demande
  depuis un index JSON (environ 300 octets par liste, soit 300 Ko à 1 000 listes) sur
  lequel tournent la recherche et les filtres. Sans JavaScript : pagination statique.
- Coût estimé : 1 à 2 jours.

### C. La base comme source de vérité, pages servies par PHP (non recommandé)

Gain : publier une liste devient une écriture en base, sans build ni déploiement, sans
limite de volume.

Coût :

- réécrire en PHP le rendu des pages decklist et tournoi, en trois langues, avec le SEO
  et le design system, en double du rendu Astro du reste du site ;
- tenir à jour en base une table des cartes Scryfall ;
- remplacer la relecture par PR par un espace d'administration : authentification,
  droits, historique, protection contre les abus, sur un hébergement mutualisé ;
- sauvegardes, migrations, cache de pages.

Coût estimé : 3 à 6 semaines. La refonte va aussi contre deux principes de PRODUCT.md :
pas de base ni d'espace d'administration, et toute contribution passe par une
relecture (aujourd'hui garantie par la PR et tracée par git).

À reconsidérer seulement si l'un de ces cas apparaît : des données écrites par les
visiteurs (comptes, votes, commentaires), plus de ~5 000 listes, ou plus de soumissions
que la relecture par PR ne peut absorber.

### D. La base comme index de recherche, dérivé du JSON (plus tard)

Pour les fonctions qui interrogent toute la collection : « decks contenant cette
carte » (P2 de la refonte), filtres croisés, statistiques de méta par carte. Le JSON du
dépôt reste la source de vérité ; l'index est régénéré à chaque déploiement.

- **SQLite plutôt que MySQL** : un seul fichier généré au build et déployé avec le site,
  lu par un petit point d'entrée PHP en lecture seule. Rien à administrer, pas d'import
  à distance. À vérifier : PDO SQLite activé sur l'offre OVH.
- **MySQL** si SQLite est indisponible, alimentée par un point d'entrée d'import protégé
  par un secret, sur le modèle des secrets existants.
- Quand : au moment de construire « decks contenant cette carte ».

### E. Changer d'hébergement pour le statique (pour mémoire)

Cloudflare Pages ou Netlify envoient en parallèle, ne transfèrent que les fichiers
nouveaux et basculent d'une version à l'autre d'un coup. L'API PHP resterait chez OVH
sur un sous-domaine (CORS à prévoir). Cela règle le transport sans toucher au code, au
prix d'une migration DNS et de deux hébergements.

## 6. Recommandation et seuils de revue

| Quand | Quoi | Coût estimé |
|---|---|---|
| Maintenant | A2 `rsync` par SSH, avec repli automatique sur A1 (SFTP parallèle) si le shell ou `rsync` manquent | ½ à 1 jour |
| Maintenant | Cache mémoire des fiches Scryfall au build | 1 h |
| Avant ~300 listes | B index léger | 1 à 2 jours |
| Avec « decks contenant cette carte » | D index de recherche SQLite | 2 à 3 jours |
| Seulement sur besoin | C base source de vérité | 3 à 6 semaines |

Revoir cette étude si l'un de ces seuils est franchi : build en CI au-delà de 3 min,
déploiement global au-delà de 10 min après A1, plus de 5 000 listes, ou besoin de
données écrites par les visiteurs.

## 7. Hébergement OVH : ce qu'on sait

| Question | Réponse | Conséquence |
|---|---|---|
| Shell SSH ? | A priori oui. OVH le réserve aux offres Pro, Performance et Agency ; Perso et Starter n'ont que FTP et SFTP ([OVHcloud, offres professionnelles](https://www.ovhcloud.com/en/web-hosting/business/)). | A2 devient l'option principale. |
| Connexions SFTP simultanées ? | Inconnu, non documenté par OVH. | Ne compte que pour le repli A1 : commencer à 4. |
| PDO SQLite ? | Inconnu. Des retours d'utilisateurs l'ont vu actif sur l'offre Perso ([forum OVHcloud](https://community.ovh.com/t/base-de-donnees-sqlite-de-2go-sur-hebergement-mutualise-perso/30404)), rien d'officiel. | Ne compte que pour l'option D, plus tard. Une commande suffira à trancher : `php -m` par SSH. |
| Contenu de la base MySQL héritée ? | Vide. | Rien à migrer ni à préserver ; elle reste disponible si l'option D l'exige. |

Plutôt que de deviner, le script de déploiement peut tester lui-même : tenter une
commande par SSH, utiliser `rsync` si elle passe, sinon basculer sur le SFTP parallèle,
et écrire dans le journal quelle voie a servi. Le premier déploiement répond alors à la
première question.

## Annexe : reproduire les mesures

Le banc de test vit hors du dépôt : une copie de `site/` dont `content/` est remplacé par
la collection clonée K fois, puis `npx astro build` à blanc (sans `dist/` ni `.astro/`)
mesuré avec `/usr/bin/time -l`. Les durées SFTP viennent des journaux du workflow
« Deploy to OVH » (`gh run view <id> --log`, ligne « N fichier(s) à envoyer »).

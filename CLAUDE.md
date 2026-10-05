# PDC - Pauper Duel Commander

## Projet
Site web pour le format Magic: The Gathering "Pauper Duel Commander" (PDC).
Gestion de règles, ban list, tournois, decklists, et validateur de deck.

Tout vit dans `site/`. La racine ne contient que la documentation, plus
`deploy-staging.sh` (prévisualisation d'une branche sur Surge, cf. `DEPLOY.md`).

## Stack Technique
- **Framework**: Astro 5, `output: 'static'` (aucun adaptateur, aucun SSR)
- **CSS**: Tailwind CSS 4 via `@tailwindcss/vite` — config CSS-first, pas de `tailwind.config.js`
- **Polices**: auto-hébergées (`site/public/fonts/`) — aucun CDN tiers
- **JS**: TypeScript vanilla, aucun framework UI
- **Contenu**: JSON dans `site/content/` (content collections Astro)
- **API**: PHP 8 standalone, sans framework : trois points d'entrée (validateur, deux formulaires)
- **Tests**: PHPUnit 9 (API)
- **API externe**: Scryfall (données cartes Magic)

Le site est entièrement pré-rendu. Le validateur de deck est **la seule chose
qui s'exécute à l'exécution** : la prod n'a besoin que de PHP, pas de Node.

> Historique : le site tournait sous WordPress (Bedrock + Timber + ACF Pro +
> Polylang). Cette stack a été supprimée ; l'historique git en garde la trace.
> La spec de migration a été retirée : elle décrivait une cible qui avait
> divergé de ce qui a été implémenté. Le code fait foi.

## Structure du Projet
```
.github/workflows/deploy.yml     # CI/CD : build + tests PHPUnit + envoi SFTP -> OVH
site/
├── astro.config.ts              # static, trailingSlash always, site, sitemap, Tailwind
├── content/                     # SOURCE DE VÉRITÉ du contenu
│   ├── banlist.json             # bannedAsCommander / bannedInDeck / cards (union)
│   ├── banlist-history/*.json   # une annonce officielle par fichier (collection)
│   ├── decklists/*.json         # decklist MTGO dans un champ texte
│   ├── matches/*.json           # appariements par tournoi (pas encore affichés)
│   └── tournaments/*.json       # top8, metaList, participants
├── public/
│   ├── .htaccess                # Redirections 301 des anciennes URLs WordPress
│   ├── robots.txt, favicon.ico
│   ├── api/                     # API PHP, déployée telle quelle
│   │   ├── validate-deck.php    # Validation d'un deck (public)
│   │   ├── submit-decklist.php  # Soumission decklist -> PR (public modéré)
│   │   ├── submit-tournament.php # Soumission tournoi -> PR (organisateurs)
│   │   ├── .htaccess            # N'autorise que ces trois points d'entrée
│   │   ├── lib/
│   │   │   ├── config.php       # Constantes, CORS, secrets, autoload
│   │   │   ├── DeckValidator.php    # Les 9 règles PDC
│   │   │   ├── DecklistParser.php   # Texte MTGO -> tableau
│   │   │   ├── ScryfallService.php  # Client Scryfall + cache fichier
│   │   │   ├── RateLimiter.php      # Fenêtre fixe, état fichier
│   │   │   ├── GitHubClient.php     # Branche + commit multi-fichiers + PR
│   │   │   ├── TurnstileVerifier.php    # Captcha, fail-closed
│   │   │   ├── DecklistSubmission.php   # Input -> JSON collection decklists
│   │   │   ├── DecklistSubmissionController.php
│   │   │   ├── TournamentSubmission.php # Input -> JSON tournoi + decklists top 8
│   │   │   └── TournamentSubmissionController.php
│   │   ├── data/                # banlist.json généré au build (gitignored)
│   │   └── cache/               # Cache Scryfall + rate limit (gitignored)
│   ├── video/                   # Vidéo d'accueil fr/en, réencodée (scripts/encode-video.sh)
│   └── img/ fonts/
├── src/
│   ├── content.config.ts        # Schémas zod : tournaments, decklists, banlistHistory
│   ├── pages/{fr,en,it}/        # Routes MINCES (slugs localisés) -> composant partagé
│   ├── pages/404.astro
│   ├── layouts/Base.astro       # Seul layout
│   ├── components/pages/        # Une page = un composant partagé, prop `locale`
│   ├── components/              # BanListGrid, CardList, ManaCurve, Top8, MetaMosaic, CommanderTable, GuildGrid...
│   ├── lib/                     # scryfall.ts, deck-renderer.ts, i18n.ts, routes.ts...
│   ├── i18n/{fr,en,it}.json
│   ├── scripts/                 # JS client (mobile-menu, card-preview, analytics, decklist-detail)
│   └── styles/globals.css       # @theme Tailwind 4 + classes composites
├── promo/reel/                  # Sources de la vidéo de présentation (cf. plus bas)
├── scripts/
│   ├── copy-banlist.mjs         # content/banlist.json -> public/api/data/
│   ├── warm-scryfall-cache.mjs  # pré-remplit le cache Scryfall avant le build
│   ├── check-commander-names.mjs # audit des généraux des tournois (nom exact, peu commune)
│   ├── subset-mana-font.mjs     # réduit la police mana aux glyphes utilisés (à la main)
│   ├── deploy-sftp.sh (+ .test.sh) # envoi incrémental de dist/ vers OVH (CI)
│   ├── smoke-check.sh           # vérifie la prod après déploiement (CI)
│   └── encode-video.sh          # master vidéo -> public/video + poster
└── tests/                       # PHPUnit + fixtures Scryfall
```

## Commandes
```bash
cd site
npm run dev       # Dev (copie la ban list + réchauffe le cache Scryfall au préalable)
npm run build     # Build -> site/dist/  (même prélude que dev)
npm run check     # astro check : propre (0 erreur, 0 avertissement, 7 hints)
npm test          # PHPUnit (API)
```

`npm run dev` ne sert pas le PHP. Pour tester le validateur en local, lancer
`php -S 127.0.0.1:8000` depuis `site/public/`.

**Déploiement** : GitHub Actions (`gh workflow run deploy.yml`) — build, tests,
puis envoi SFTP de `dist/` vers OVH `www/`. Détails dans `DEPLOY.md`.

## Contenu

Les JSON de `site/content/` sont édités à la main. Schémas dans
`src/content.config.ts` (zod) — toute modification de forme doit y être répercutée.

`banlist.json` a deux listes d'affichage (`bannedAsCommander`, `bannedInDeck`) et
`cards`, leur **union**, seule utilisée par le validateur. En ajoutant une carte,
mettre à jour `cards` aussi, sinon elle ne sera pas rejetée. `lastUpdated` et
`content/banlist-history/` alimentent la date affichée et le badge « Nouveau ».

Les decklists acceptent un champ `tags` (éditorial). Seule valeur reconnue
aujourd'hui : `"debutant"`, qui place la liste dans le rayon « Pour commencer »
de l'index. C'est un choix humain, à revoir quand la collection grossit.

**Ne jamais lire `content/banlist.json` directement depuis une page** : passer par
`lib/banlist.ts`. C'était copié dans cinq pages, chacune reconstruisant son propre
Set.

`banlist-history/` est une collection (un fichier par annonce) affichée en
historique sur la page ban list. Modèle : `date`, `source`, `kind`
(`initial`|`update`), `changes[]` (`card`, `type` = `banned`|`unbanned`|`restricted`,
`experimental?`), `notes[]` (`{fr, en, it?}`, facultatif). C'est de l'affichage :
mettre à jour `banlist.json` reste nécessaire pour que le validateur en tienne
compte.

Dans `notes[]`, `fr` et `en` sont obligatoires (ce que publie le comité) ; les
langues ajoutées ensuite sont facultatives et replient sur l'anglais via
`noteText()`, pour qu'une annonce puisse partir avant d'être traduite partout.

Noms de cartes : toujours l'orthographe canonique Scryfall (union `cards`,
`metaList`, historique) — le validateur compare des noms.

Le champ `details` d'un tournoi est une chaîne **unique, non localisée**, et
c'est **délibéré** : le texte de l'organisateur (horaires, PAF, lots) reste en
français dans les trois langues. Ne pas le passer en `{fr, en, it}` — cela
obligerait chaque organisateur à écrire trois versions de son annonce.

Un tournoi peut porter la suite de son classement final dans `standings`
(facultatif) : les places après le top 8, même forme que `top8` (`decklistSlug`
facultatif), affichées repliées sous le top 8. Un bilan compte tous les matchs joués, phase
finale comprise (une finale à 1-1 est un nul). Une decklist liée depuis
`standings` porte son résultat comme celles du top 8 (`resultsBySlug`). C'est de
l'affichage : la méta lit toujours `metaList`, qui doit compter les mêmes
généraux. Une place inconnue s'écrit `???`, comme ailleurs.

`content/matches/<tournoi>.json` garde les appariements ronde par ronde
(`matches`, schéma dans `content.config.ts`), pour une future matrice des
matchups : rien ne les affiche encore, trop peu de données. Un joueur y est sa
place finale, qui donne son général ; une exemption, ou un adversaire absent,
compte comme une victoire. Retoucher le classement d'un tournoi (une place qui
saute) oblige à renuméroter son fichier de matchs. Les bilans qu'on en déduit,
phase finale comprise, doivent être ceux du classement.

## API / Validateur

`POST /api/validate-deck.php` — `commander`, `partner` (optionnel), `decklist`,
`locale` (optionnel : `fr` par défaut, `en` et `it` acceptés ; toute autre valeur
retombe sur `fr`).
Réponse : `{success, data: {is_valid, errors[], warnings[], stats{}}}`.

Les 9 règles sont documentées en tête de `DeckValidator.php`.

### Soumissions (formulaire -> PR GitHub)

Deux endpoints ouvrent une PR que quelqu'un relit avant merge ; le merge déclenche
le déploiement. Ils partagent Turnstile (fail-closed), un honeypot, le quota
5/h/IP et `GitHubClient` (commit multi-fichiers via l'API Git Trees).

Chaque PR est étiquetée (`PDC_LABEL_DECKLIST` / `PDC_LABEL_TOURNAMENT`), pour
distinguer les deux sortes sans les ouvrir. La labellisation est un **second
appel** (GitHub ne prend pas de label à la création d'une PR) et elle est
**best-effort** : si elle échoue, `GitHubClient::label()` avale l'erreur et la
soumission reste un succès. Échouer ici signalerait une erreur pour une PR qui
existe, et le soumissionnaire renverrait tout — une PR sans label est un défaut
cosmétique, un doublon non.

`POST /api/submit-decklist.php` — form-encodé. Un deck illégal est refusé (422),
sans PR.

`POST /api/submit-tournament.php` — **JSON** (`top8` est imbriqué) : `accessCode`,
`title`, `date`, `location?`, `city?`, `participants?`, `top8[]`, `meta?`,
`locale?`. Une seule PR contient le tournoi **et** les decklists légales du top 8,
`decklistSlug` câblé entre les deux.
Réponse : `{success, pr_url, included[], rejected[]}`.

Les secrets (`GITHUB_TOKEN`, `TURNSTILE_SECRET`, `ORGANIZER_CODE`) vivent hors
`www/` et sont lus par `pdc_secret()` ; absents, l'endpoint répond **503**. Mise
en place : `docs/external/README.md`.

### Invariants à ne pas casser

- **La ban list n'est jamais optionnelle.** Si elle ne peut pas être chargée,
  `get_banned_card_names()` lève une `RuntimeException` et l'endpoint renvoie
  **503**. Ne jamais revenir à "avertir et continuer" : cela validerait des decks
  contenant des cartes bannies (c'était le bug corrigé).
- **Éligibilité du général (règle 2.4)** : Créature, Véhicule, Vaisseau Spatial ou
  Background (`COMMANDER_TYPES`), ayant été imprimé **au moins une fois** en peu
  commune. La rareté se juge sur **toutes** les impressions papier/MTGO
  (`get_all_rarities`, via `prints_search_uri`), **Arena exclu** — pas sur la seule
  impression par défaut de Scryfall. Ne pas retomber sur `$card->rarity` seul
  (bug : Baleful Strix, rare par défaut mais commandant légal, était rejeté).
- **`api/data/banlist.json` est le chemin canonique.** C'est le seul qui résout
  à l'identique dans le dépôt, dans `dist/` et en production, parce que `api/`
  est ce qui est réellement déployé. `content/` n'est pas déployé.
- **Garde-fous d'abus** : 20 req/min/IP (429), 120 cartes distinctes max (422),
  20 lookups Scryfall de secours max par requête, 50 Ko max (413). Le coût réel
  n'est pas la taille du corps mais les noms inconnus : chacun déclenche une
  requête Scryfall isolée avec 100 ms d'attente.
- **`X-Forwarded-For` est ignoré volontairement** dans `RateLimiter::client_id()`
  (falsifiable). Le lire nécessiterait une allow-list de proxys de confiance.
- **Seuls les trois points d'entrée sont joignables** (`validate-deck`,
  `submit-decklist`, `submit-tournament`). `lib/`, `cache/` et `data/` sont
  refusés par `.htaccess` (Apache) — équivalent nginx en commentaire dedans.
- **Le formulaire tournoi publie des résultats, pas des annonces.** Une date
  future est refusée (422 `date_future`) : `TournamentDetailPage` masque tout le
  bloc résultats tant que la date n'est pas passée, donc une telle soumission
  publierait une page vide de ce que l'organisateur a saisi.
- **Une decklist illégale n'annule pas le tournoi.** Elle est écartée de la PR et
  sa place garde `decklistSlug: null` (l'état normal de la collection) ; le motif
  part dans la réponse et dans le corps de la PR. Ce qui reste invariant : une
  liste illégale ne devient jamais du contenu publié. Ne pas basculer vers « tout
  rejeter » — un tournoi est un fait, il ne doit pas dépendre d'une faute de
  frappe sur l'une des huit listes.
- **Le code organisateur est vérifié APRÈS Turnstile.** L'ordre inverse serait
  moins cher mais laisserait un bot force-brute le code sans résoudre de captcha.
- **Budget de validation** (`PDC_SUBMIT_VALIDATION_BUDGET`, 20 s) : valider huit
  decks peut dépasser le `max_execution_time` d'un mutualisé. Au-delà du budget
  les listes restantes sont marquées « non vérifiées » et écartées — jamais
  publiées sans contrôle.
- **Les erreurs de forme sont des codes, pas de la prose** (`title_required`,
  `date_future`…). Le message traverse le réseau et est traduit côté navigateur
  (`submitTournament.js.formErrors`) : y écrire une phrase française la ferait
  lire à toutes les langues.

## Vidéo de présentation

La page d'accueil lit `public/video/pauper-duel-commander-{fr,en}.mp4`
(l'italien reprend l'anglaise), avec la miniature en poster. Ce sont des copies
réencodées, pas des sources :

1. `promo/reel/reel.html` est le montage : une fonction **pure** du temps, sans
   animation CSS ni horloge. `sfx.js` synthétise effets et musique sur la même
   ligne de temps. Depuis `promo/reel/`, `node render.cjs` capture l'image à
   60 i/s avec Puppeteer et produit le master 1080p dans `out/` (gitignored, ~8 min).
   `--en` pour la version anglaise, `stills 12.4 30` pour vérifier quelques
   images, `thumbnail` pour la miniature YouTube 1280x720 (`thumbnail.html`).
2. `scripts/encode-video.sh <master> <nom> [miniature]` en tire la version du
   site (30 i/s, CRF 25, `+faststart`) et le poster.

À tenir en modifiant le montage :
- **Rien de daté.** Aucun nombre de tournois, de généraux ou de decklists,
  aucune date d'annonce : la vidéo doit durer. Les seuls nombres sont ceux des règles.
- **Aucun général à la mode en vedette** : un général qui domine peut être banni.
  L'exemple est Garland, et les cartes montrées avec lui sortent de sa decklist
  d'Artefacts #7.
- Chaque scène commence sur un temps de la musique (120 BPM, `B(n)`) et ses
  horaires internes sont relatifs à son début : allonger une scène, c'est déplacer
  une borne de `R` ou `W`.
- Le français est écrit dans le balisage ; l'anglais est la table `EN` (sélecteur
  -> HTML), qui reprend les formulations de `src/i18n/en.json`.
- Les images de cartes viennent de Scryfall (URL épinglées, impression dans la
  rareté qui rend la carte légale là où elle est montrée) : le rendu a besoin du
  réseau. Les chemins de Puppeteer et de Chrome for Testing dans `render.cjs`
  sont ceux de la machine qui a produit la vidéo.

## Conventions de Code

### PHP
- Classes `PascalCase`, méthodes `snake_case` statiques
- Constantes définies avec un garde `if (!defined(...))` pour que les tests
  puissent les surcharger
- Chaque classe refuse d'être appelée directement (garde `basename()`)
- Messages d'erreur utilisateur dans `DeckValidator::MESSAGES` (fr + en + it),
  sans accents côté français — l'italien garde les siens, les retirer y ferait
  des fautes (`rarità`, pas `rarita`). Ne pas écrire de littéral dans un message :
  passer par `self::msg('id', ...)`, sinon les autres langues reparleront
  français. Un test vérifie que chaque id porte bien les trois langues.

### Tests
- **Hermétiques, jamais de réseau.** `ScryfallService` lit à travers un cache
  fichier ; `tests/bootstrap.php` le pré-remplit avec de vraies réponses Scryfall
  de `tests/fixtures/scryfall/`. Toute carte utilisée dans un test doit y avoir
  sa fixture, sinon le test tape l'API réelle.
- Les fixtures sont choisies pour isoler une règle à la fois.
- La suite lit une ban list figée (`tests/fixtures/banlist.json`), pas
  `content/banlist.json` : une annonce du comité ne doit casser aucun test (la
  légalisation de Bastion Protector, banni d'exemple, l'a fait). Seul
  `BanListTest` charge la vraie, sans y chercher de carte précise.
- Nouvelle fixture : `curl -A "PDC-Test/1.0" --get "https://api.scryfall.com/cards/named"
  --data-urlencode "exact=Nom" -o tests/fixtures/scryfall/name_<slug>.json`
  (slug = minuscules, non-alphanumériques -> `-`, cf. `pdc_sanitize_key`)

### Astro / TypeScript
- **Une page = un composant partagé** dans `src/components/pages/`, avec une prop
  `locale`. Les fichiers sous `src/pages/{fr,en,it}/` sont des **routes minces**
  qui importent ce composant et fixent `locale` (les pages `[slug]` y gardent leur
  `getStaticPaths`). Ne pas dupliquer une page par langue.
- Slugs localisés dans `src/lib/routes.ts` (table unique) : `route()` et
  `translatePath()`. Le sélecteur de langue s'en sert — ne pas faire de
  remplacement `/fr/` -> `/en/` à la main (ça 404 sur les segments traduits).
- Traductions via `t(key, locale)` de `src/lib/i18n.ts`. Repli **sur l'anglais**,
  puis sur le français : `it → en → fr`, `en → fr`. Une clé absente partout
  s'affiche brute (son nom), jamais vide.
- Ajouter une clé dans `fr.json`, `en.json` **et** `it.json` — les trois
  catalogues ont la même forme
- Ne pas écrire de littéral dans un composant en testant la langue
  (`locale === 'fr' ? … : …`) : c'est invisible pour toute langue ajoutée ensuite.
  Les titres et descriptions SEO vivent en `<namespace>.seoTitle` /
  `.seoDescription`. Les composants qui parcourent une branche entière du
  catalogue passent par `messagesFor(locale)`, jamais par un import direct du JSON.
- Un script client ne peut pas appeler `t()` : il lit `document.documentElement.lang`
  et se sert d'une table locale (cf. `card-preview.ts`, `cookieconsent-config.ts`)
- Construire les URL avec `route()` de `lib/routes.ts`, jamais par interpolation :
  c'est une URL en dur qui avait mis les 15 liens « Deck » des top 8 en 404
- Une nouvelle page = un composant dans `components/pages/`, monté par deux
  routes minces (`pages/fr/…` et `pages/en/…`)
- Un script client `is:inline` ne voit pas les variables Astro : passer les
  valeurs par des `data-*` attributs (cf. bouton d'export de decklist)
- Page decklist : chaque carte est rendue **une seule fois** (`CardList`), en
  ligne qui sert aussi de déclencheur d'aperçu. Regrouper, trier, filtrer par
  coût et la vue Visuel travaillent sur ces mêmes lignes et leurs `data-*`
  (`scripts/decklist-detail.ts`) : ne pas dupliquer la liste par mode. Les
  images de la vue Visuel sont `loading="lazy"` dans une boîte non affichée ;
  c'est ce qui évite à la vue Liste de télécharger 99 images, ne pas l'enlever.
  Les libellés du script viennent d'un bloc JSON construit avec `t()`.
- Les styles d'une page dans un fichier à part (`decklist.css`) vont dans
  `@layer components` : hors couche, ils battraient les utilitaires Tailwind
  (`lg:hidden` restait sans effet).
- Le top 8 d'un tournoi est un podium (`Top8`) : les trois premiers en cartes,
  dans l'ordre des places dans le balisage et dessinés 2-1-3 par CSS, puis les
  places suivantes en lignes compactes (`Standings`, les mêmes lignes servent au
  reste du classement), à côté du podium quand l'écran le permet. Pas de
  mosaïque ici : un top 8 est un classement, pas une part.
- Une paire (partenaires, général et background) montre ses deux cartes partout :
  en éventail sur le podium, deux vignettes superposées dans les lignes et le
  tableau (`pair-thumb`, seule la principale déclenche l'aperçu), une tuile
  coupée en deux dans la mosaïque. `CommanderStat` et `Top8Entry` portent
  `partnerImage` / `partnerCardImage`. Styles dans `results.css` : un élément dont ces
  règles fixent la mise en page n'en reçoit pas d'un utilitaire `flex`/`grid`,
  qui l'emporterait.
- Page méta : la mosaïque (`MetaMosaic`, treemap de `lib/treemap.ts`, contrôle
  `node scripts/check-treemap.mjs`) donne à chaque général une surface égale à
  sa part ; la traîne remplit la dernière case de petites vignettes. Les entrées
  sans général (`???`) ont leur propre case : fondues dans la traîne, elles
  s'affichaient « 0 autres généraux » quand il n'y en avait pas. Chaque tuile
  est un conteneur de taille : ses libellés apparaissent selon la place
  (requêtes `@container` dans `results.css`, tailles de police hors utilitaires).
  Identités en grille des guildes (`GuildGrid`), couleurs en colonnes
  (`ColorColumns`), chiffres exacts dans `CommanderTable`.
- Page d'un tournoi : son plateau suit le même schéma (mosaïque, tableau, guildes,
  couleurs) ; la meilleure place de chaque général au top 8 est marquée sur sa
  tuile (`places`, clé sur le nom complet et sur la carte principale, car un top 8
  peut écrire une paire sans son partenaire).
- Une barre de part (`share-bar`) mesure sa part sur 100 %, jamais par rapport au
  plus grand de sa liste, et se lit à côté de son nombre imprimé. Avec une échelle
  par graphique, 6 % dessinait une barre pleine à côté d'une couleur à 51 % en
  demi-barre. Le classement des généraux n'a pas de barre : à quelques %, elle
  n'aurait été qu'un trait.
- Un contrôle qui ne marche qu'avec JavaScript porte `data-needs-js` : il est
  masqué sans JS (`globals.css`, la classe `js` est posée dans `Base.astro`).

### CSS
- Tailwind 4 : tokens dans `@theme {}` de `globals.css`, pas de fichier de config
- Mobile-first ; classes composites (`magic-card`, `btn-primary`, `page-head`,
  `panel`, `deck-card`, `badge`, `stat-pill`, `quick-tile`) en `@apply`
- `magic-card` (bordure orange) est réservée aux objets cliquables ; utiliser
  `panel` pour un simple conteneur, sinon l'orange perd sa fonction d'accent
- Une couleur lue par `var()` dans un style en ligne (barres de `DeckColors`,
  colonnes de `ColorColumns`) doit vivre dans `@theme static` : Tailwind n'émet
  que les variables qu'une classe utilise, et sans `static` ces barres
  s'affichaient sans couleur
- Pas de virgule entre deux conditions d'un `@container` : la minification du
  build (Lightning CSS) supprime la règle en silence. Écrire `or`.
- Titres en blanc (`text-text-primary`), jamais de grand titre en orange :
  `text-magic-gradient` ne sert plus qu'à « Commander » dans le titre d'accueil,
  qui reprend le logo
- `--color-text-muted` est le plancher de contraste (4,9:1) : ne pas le diluer
  avec une opacité
- Texte sur un aplat orange : `text-bg-primary` (6,1:1), jamais blanc (3,16:1,
  sous AA). Même principe que les badges pleins

## Points d'Attention
- `site/dist/`, `site/public/api/{data,cache}/` et `public/api/data/banlist.json`
  sont générés — ne pas éditer
- Les données Scryfall sont récupérées **au build**, cache 30 j dans `site/.cache/`.
  `warm-scryfall-cache.mjs` (pre-build) les pré-remplit séquentiellement : sans lui,
  le build parallèle d'Astro se fait rate-limiter par Scryfall et les cartes
  s'affichent sans illustration, en silence. Le cache expire à 30 j — le script
  rafraîchit aussi les entrées périmées.
- **`npm run check` est propre** : 0 erreur, 0 avertissement, 7 hints (variables
  inutilisées, scripts traités comme `is:inline`). `npm run lint` y ajoute
  `tsc --noEmit`, tout aussi propre (mesuré le 1er octobre 2026). C'est la
  référence : une erreur qui apparaît est une régression du changement en cours,
  pas un héritage. La dernière « erreur préexistante » connue, celle
  d'`astro.config.ts` (conflit de types entre deux majeures de Vite), a été
  neutralisée par 812c0d7 via le cast `tailwindcss() as any` ; son commentaire sur
  place dit pourquoi. Chiffre à remesurer avant de le citer : l'ancienne note
  « 42 erreurs » n'a jamais correspondu à l'état du dépôt.
- **Redirections** : `public/.htaccess` (racine) redirige en 301 les anciennes
  URLs WordPress non préfixées vers les routes `/fr/…`. Apache uniquement
  (OVH mutualisé) ; équivalent nginx en commentaire.
- **Le déploiement n'envoie que ce qui a changé** (`site/scripts/deploy-sftp.sh`) :
  un manifeste d'empreintes SHA-256 vit sur le serveur et seuls les fichiers qui
  en diffèrent partent. Sans manifeste, tout part — un raté coûte du temps, pas
  des fichiers manquants. Les dotfiles sont inclus, donc un `.htaccess` modifié
  n'a plus besoin d'être poussé à la main. Rien n'est supprimé à distance, et un
  fichier retouché directement sur le serveur ne sera pas réparé : relancer le
  workflow avec *Tout renvoyer*. Test : `site/scripts/deploy-sftp.test.sh`.
- Le site est **entièrement généré au build**, « aujourd'hui » compris : un
  tournoi passé reste « à venir » jusqu'au prochain build. D'où le rebuild
  hebdomadaire (cron dans `deploy.yml`) en plus du déploiement au push.
  `hasHappened()` (`lib/tournaments.ts`) coupe le cas le plus visible : un top 8
  rempli vaut « c'est joué », donc des résultats publiés le soir même sortent
  tout de suite des « prochains tournois ». La date seule ne suffisait pas, elle
  affichait Endstep #1 comme à venir avec son top 8 en main. Un tournoi passé
  **sans** résultats dépend toujours du build.
- Les images de cartes viennent de Scryfall : prévoir toujours un repli texte
  quand l'illustration manque (leçon de la ban list, où une carte sans image
  n'était pas rendue du tout)

# PDC - Pauper Duel Commander

## Projet
Site web pour le format Magic: The Gathering "Pauper Duel Commander" (PDC).
Gestion de règles, ban list, tournois, decklists, et validateur de deck.

Tout vit dans `site/`. La racine ne contient que la documentation.

## Stack Technique
- **Framework**: Astro 5, `output: 'static'` (aucun adaptateur, aucun SSR)
- **CSS**: Tailwind CSS 4 via `@tailwindcss/vite` — config CSS-first, pas de `tailwind.config.js`
- **Polices**: auto-hébergées (`site/public/fonts/`) — aucun CDN tiers
- **JS**: TypeScript vanilla, aucun framework UI
- **Contenu**: JSON dans `site/content/` (content collections Astro)
- **API**: PHP 8 standalone, sans framework — un seul endpoint
- **Tests**: PHPUnit 9 (API)
- **API externe**: Scryfall (données cartes Magic)

Le site est entièrement pré-rendu. Le validateur de deck est **la seule chose
qui s'exécute à l'exécution** : la prod n'a besoin que de PHP, pas de Node.

> Historique : le site tournait sous WordPress (Bedrock + Timber + ACF Pro +
> Polylang). Cette stack a été supprimée. `SPEC-MIGRATION.md` documente la
> migration ; certaines de ses sections décrivent une cible qui a divergé de ce
> qui a été réellement implémenté — le code fait foi.

## Structure du Projet
```
.github/workflows/deploy.yml     # CI/CD : build + tests PHPUnit + envoi SFTP -> OVH
site/
├── astro.config.ts              # static, trailingSlash always, site, sitemap, Tailwind
├── content/                     # SOURCE DE VÉRITÉ du contenu
│   ├── banlist.json             # bannedAsCommander / bannedInDeck / cards (union)
│   ├── banlist-history/*.json   # une annonce officielle par fichier (collection)
│   ├── decklists/*.json         # decklist MTGO dans un champ texte
│   └── tournaments/*.json       # top8, metaList, participants
├── public/
│   ├── .htaccess                # Redirections 301 des anciennes URLs WordPress
│   ├── robots.txt, favicon.ico
│   ├── api/                     # API PHP, déployée telle quelle
│   │   ├── validate-deck.php    # SEUL point d'entrée public
│   │   ├── .htaccess            # N'autorise que validate-deck.php
│   │   ├── lib/
│   │   │   ├── config.php       # Constantes, CORS, helpers, autoload
│   │   │   ├── DeckValidator.php    # Les 9 règles PDC
│   │   │   ├── DecklistParser.php   # Texte MTGO -> tableau
│   │   │   ├── ScryfallService.php  # Client Scryfall + cache fichier
│   │   │   └── RateLimiter.php      # Fenêtre fixe, état fichier
│   │   ├── data/                # banlist.json généré au build (gitignored)
│   │   └── cache/               # Cache Scryfall + rate limit (gitignored)
│   └── img/ fonts/
├── src/
│   ├── content.config.ts        # Schémas zod : tournaments, decklists, banlistHistory
│   ├── pages/{fr,en}/           # Routes MINCES (slugs localisés) -> composant partagé
│   ├── pages/404.astro
│   ├── layouts/Base.astro       # Seul layout
│   ├── components/pages/        # Une page = un composant partagé, prop `locale`
│   ├── components/              # BanListGrid, CardList, DeckStats, Top8Table...
│   ├── lib/                     # scryfall.ts, deck-renderer.ts, i18n.ts, routes.ts...
│   ├── i18n/{fr,en}.json
│   ├── scripts/                 # JS client (mobile-menu, card-hover, deck-export)
│   └── styles/globals.css       # @theme Tailwind 4 + classes composites
├── scripts/
│   ├── copy-banlist.mjs         # content/banlist.json -> public/api/data/
│   └── warm-scryfall-cache.mjs  # pré-remplit le cache Scryfall avant le build
└── tests/                       # PHPUnit + fixtures Scryfall
```

## Commandes
```bash
cd site
npm run dev       # Dev (copie la ban list + réchauffe le cache Scryfall au préalable)
npm run build     # Build -> site/dist/  (même prélude que dev)
npm run check     # astro check + tsc (voir Points d'Attention : 1 erreur préexistante)
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
`experimental?`), `notes[]` (`{fr, en}`, facultatif). C'est de l'affichage : mettre
à jour `banlist.json` reste nécessaire pour que le validateur en tienne compte.

Noms de cartes : toujours l'orthographe canonique Scryfall (union `cards`,
`metaList`, historique) — le validateur compare des noms.

## API / Validateur

`POST /api/validate-deck.php` — `commander`, `partner` (optionnel), `decklist`,
`locale` (optionnel : `fr` par défaut, `en` accepté).
Réponse : `{success, data: {is_valid, errors[], warnings[], stats{}}}`.

Les 9 règles sont documentées en tête de `DeckValidator.php`.

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
- **Seul `validate-deck.php` est joignable.** `lib/`, `cache/` et `data/` sont
  refusés par `.htaccess` (Apache) — équivalent nginx en commentaire dedans.

## Conventions de Code

### PHP
- Classes `PascalCase`, méthodes `snake_case` statiques
- Constantes définies avec un garde `if (!defined(...))` pour que les tests
  puissent les surcharger
- Chaque classe refuse d'être appelée directement (garde `basename()`)
- Messages d'erreur utilisateur dans `DeckValidator::MESSAGES` (fr + en), sans
  accents côté français. Ne pas écrire de littéral dans un message : passer par
  `self::msg('id', ...)`, sinon la version EN reparlera français.

### Tests
- **Hermétiques, jamais de réseau.** `ScryfallService` lit à travers un cache
  fichier ; `tests/bootstrap.php` le pré-remplit avec de vraies réponses Scryfall
  de `tests/fixtures/scryfall/`. Toute carte utilisée dans un test doit y avoir
  sa fixture, sinon le test tape l'API réelle.
- Les fixtures sont choisies pour isoler une règle à la fois.
- Nouvelle fixture : `curl -A "PDC-Test/1.0" --get "https://api.scryfall.com/cards/named"
  --data-urlencode "exact=Nom" -o tests/fixtures/scryfall/name_<slug>.json`
  (slug = minuscules, non-alphanumériques -> `-`, cf. `pdc_sanitize_key`)

### Astro / TypeScript
- **Une page = un composant partagé** dans `src/components/pages/`, avec une prop
  `locale`. Les fichiers sous `src/pages/{fr,en}/` sont des **routes minces** qui
  importent ce composant et fixent `locale` (les pages `[slug]` y gardent leur
  `getStaticPaths`). Ne pas dupliquer une page par langue.
- Slugs localisés dans `src/lib/routes.ts` (table unique) : `route()` et
  `translatePath()`. Le sélecteur de langue s'en sert — ne pas faire de
  remplacement `/fr/` -> `/en/` à la main (ça 404 sur les segments traduits).
- Traductions via `t(key, locale)` de `src/lib/i18n.ts`, fallback FR
- Ajouter une clé dans `fr.json` **et** `en.json` (une clé manquante s'affiche
  brute côté client)
- Construire les URL avec `route()` de `lib/routes.ts`, jamais par interpolation :
  c'est une URL en dur qui avait mis les 15 liens « Deck » des top 8 en 404
- Une nouvelle page = un composant dans `components/pages/`, monté par deux
  routes minces (`pages/fr/…` et `pages/en/…`)
- Un script client `is:inline` ne voit pas les variables Astro : passer les
  valeurs par des `data-*` attributs (cf. bouton d'export de decklist)

### CSS
- Tailwind 4 : tokens dans `@theme {}` de `globals.css`, pas de fichier de config
- Mobile-first ; classes composites (`magic-card`, `btn-primary`, `page-head`,
  `panel`, `deck-card`, `badge`, `stat-pill`, `quick-tile`) en `@apply`
- `magic-card` (bordure orange) est réservée aux objets cliquables ; utiliser
  `panel` pour un simple conteneur, sinon l'orange perd sa fonction d'accent
- `--color-text-muted` est le plancher de contraste (4,9:1) : ne pas le diluer
  avec une opacité

## Points d'Attention
- `site/dist/`, `site/public/api/{data,cache}/` et `public/api/data/banlist.json`
  sont générés — ne pas éditer
- Les données Scryfall sont récupérées **au build**, cache 30 j dans `site/.cache/`.
  `warm-scryfall-cache.mjs` (pre-build) les pré-remplit séquentiellement : sans lui,
  le build parallèle d'Astro se fait rate-limiter par Scryfall et les cartes
  s'affichent sans illustration, en silence. Le cache expire à 30 j — le script
  rafraîchit aussi les entrées périmées.
- **`npm run check` remonte 1 erreur préexistante** dans `astro.config.ts` (types
  du plugin Vite Tailwind vs Astro). Sans effet sur le build ; ne pas la traiter
  comme une régression.
- **Redirections** : `public/.htaccess` (racine) redirige en 301 les anciennes
  URLs WordPress non préfixées vers les routes `/fr/…`. Apache uniquement
  (OVH mutualisé) ; équivalent nginx en commentaire.
- Le déploiement SFTP n'envoie pas les fichiers cachés (`dist/*`) : un `.htaccess`
  modifié doit être poussé à la main une fois (cf. `DEPLOY.md`).
- Le site est **entièrement généré au build**, « aujourd'hui » compris : un
  tournoi passé reste « à venir » jusqu'au prochain build. D'où le rebuild
  hebdomadaire (cron dans `deploy.yml`) en plus du déploiement au push
- Les images de cartes viennent de Scryfall : prévoir toujours un repli texte
  quand l'illustration manque (leçon de la ban list, où une carte sans image
  n'était pas rendue du tout)

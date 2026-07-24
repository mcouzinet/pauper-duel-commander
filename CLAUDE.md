# PDC - Pauper Duel Commander

## Projet
Site web pour le format Magic: The Gathering "Pauper Duel Commander" (PDC).
Gestion de règles, ban list, tournois, decklists, et validateur de deck.

Tout vit dans `site/`. La racine ne contient que la documentation.

## Stack Technique
- **Framework**: Astro 5, `output: 'static'` (aucun adaptateur, aucun SSR)
- **CSS**: Tailwind CSS 4 via `@tailwindcss/vite` — config CSS-first, pas de `tailwind.config.js`
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
site/
├── astro.config.ts              # Minimal : static, trailingSlash always, Tailwind
├── content/                     # SOURCE DE VÉRITÉ du contenu
│   ├── banlist.json             # bannedAsCommander / bannedInDeck / cards (union)
│   ├── decklists/*.json         # decklist MTGO dans un champ texte
│   └── tournaments/*.json       # top8, metaList, participants
├── public/
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
│   ├── content.config.ts        # Schémas zod des collections
│   ├── pages/{fr,en}/           # Routes, slugs localisés
│   ├── layouts/Base.astro       # Seul layout
│   ├── components/              # BanListGrid, CardList, DeckStats, Top8Table...
│   ├── lib/                     # scryfall.ts, deck-renderer.ts, i18n.ts...
│   ├── i18n/{fr,en}.json
│   ├── scripts/                 # JS client (mobile-menu, card-hover, deck-export)
│   └── styles/globals.css       # @theme Tailwind 4 + classes composites
├── scripts/copy-banlist.mjs     # content/banlist.json -> public/api/data/
└── tests/                       # PHPUnit + fixtures Scryfall
```

## Commandes
```bash
cd site
npm run dev       # Dev (copie la ban list au préalable)
npm run build     # Build -> site/dist/
npm run check     # astro check + tsc
npm test          # PHPUnit (API)
```

`npm run dev` ne sert pas le PHP. Pour tester le validateur en local, lancer
`php -S 127.0.0.1:8000` depuis `site/public/`.

## Contenu

Les JSON de `site/content/` sont édités à la main. Schémas dans
`src/content.config.ts` (zod) — toute modification de forme doit y être répercutée.

`banlist.json` a deux listes d'affichage (`bannedAsCommander`, `bannedInDeck`) et
`cards`, leur **union**, seule utilisée par le validateur. En ajoutant une carte,
mettre à jour `cards` aussi, sinon elle ne sera pas rejetée.

## API / Validateur

`POST /api/validate-deck.php` — `commander`, `partner` (optionnel), `decklist`.
Réponse : `{success, data: {is_valid, errors[], warnings[], stats{}}}`.

Les 9 règles sont documentées en tête de `DeckValidator.php`.

### Invariants à ne pas casser

- **La ban list n'est jamais optionnelle.** Si elle ne peut pas être chargée,
  `get_banned_card_names()` lève une `RuntimeException` et l'endpoint renvoie
  **503**. Ne jamais revenir à "avertir et continuer" : cela validerait des decks
  contenant des cartes bannies (c'était le bug corrigé).
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
- Messages d'erreur utilisateur en français, sans accents (l'existant est ainsi)

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
- Pages FR et EN séparées, avec slugs localisés (`/fr/tournois/`, `/en/tournaments/`)
- `locale` est passé en prop depuis `Base.astro`
- Traductions via `t(key, locale)` de `src/lib/i18n.ts`, fallback FR
- Ajouter une clé dans `fr.json` **et** `en.json` (une clé manquante s'affiche
  brute côté client)

### CSS
- Tailwind 4 : tokens dans `@theme {}` de `globals.css`, pas de fichier de config
- Mobile-first ; classes composites (`magic-card`, `btn-primary`) en `@apply`

## Points d'Attention
- `site/dist/` et `site/public/api/{data,cache}/` sont générés — ne pas éditer
- Les données Scryfall sont récupérées **au build**, cache 30 j dans `site/.cache/`
- Les messages d'erreur du validateur PHP sont en français uniquement, y compris
  côté page EN pour ceux qui ne passent pas par `RULE_LABELS`

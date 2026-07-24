# PDC — Pauper Duel Commander

Site du format Magic: The Gathering **Pauper Duel Commander** : règles, ban list,
tournois, decklists et validateur de deck.

## Stack

Site **statique** (Astro) + une **API PHP** réduite au strict minimum.

| | |
|---|---|
| **Génération** | Astro 5 (`output: 'static'`, aucun adaptateur) |
| **CSS** | Tailwind CSS 4 via `@tailwindcss/vite` (config CSS-first dans `src/styles/globals.css`) |
| **JS** | TypeScript vanilla, aucun framework UI |
| **Contenu** | Fichiers JSON (`site/content/`) via les content collections Astro |
| **Runtime** | PHP 8 — uniquement `/api/validate-deck.php` |
| **Données cartes** | API Scryfall, appelée au *build* et mise en cache 30 jours |
| **i18n** | FR / EN, maison (`src/i18n/*.json`) |

Tout le site est pré-rendu. **Le validateur de deck est la seule chose qui
s'exécute à l'exécution** : le VPS n'a besoin que de PHP, pas de Node.

> Le site tournait auparavant sous WordPress (Bedrock + Timber + ACF Pro).
> Cette stack a été supprimée ; voir `SPEC-MIGRATION.md` et l'historique git.

## Structure

```
site/
├── astro.config.ts
├── content/                  # Source de vérité du contenu
│   ├── banlist.json
│   ├── decklists/*.json
│   └── tournaments/*.json
├── public/
│   ├── api/                  # API PHP (déployée telle quelle)
│   │   ├── validate-deck.php # Seul point d'entrée public
│   │   ├── lib/              # Classes internes (accès refusé)
│   │   ├── data/             # banlist.json généré au build
│   │   └── cache/            # Cache Scryfall + état du rate limit
│   └── img/, fonts/
├── src/
│   ├── pages/{fr,en}/        # Routes (slugs localisés)
│   ├── components/, layouts/
│   ├── lib/                  # Scryfall, parser, rendu, i18n
│   ├── i18n/{fr,en}.json
│   ├── scripts/              # JS client
│   └── styles/
├── scripts/copy-banlist.mjs  # content/banlist.json -> public/api/data/
└── tests/                    # PHPUnit (API)
```

## Développement

```bash
cd site && npm install
```

```bash
npm run dev
```

| Commande | Rôle |
|---|---|
| `npm run dev` | Serveur de dev (copie la ban list au préalable) |
| `npm run build` | Build production dans `site/dist/` |
| `npm run preview` | Prévisualise le build |
| `npm run check` | Vérification Astro + TypeScript |
| `npm test` | Tests PHPUnit de l'API |

`npm run dev` sert le site mais **pas** le PHP. Pour tester le validateur en
local, lancer PHP à côté depuis `site/public/` :

```bash
php -S 127.0.0.1:8000
```

## Tests

```bash
npm test
```

Les tests couvrent le validateur, le chargement de la ban list, le parser de
decklist et le rate limiter. Ils sont **hermétiques** : `ScryfallService` lit à
travers un cache fichier pré-rempli avec de vraies réponses Scryfall
(`tests/fixtures/scryfall/`), donc aucun test ne sort sur le réseau.

## Contenu

`site/content/` est la source de vérité, éditée à la main.

- `banlist.json` — `cards` est l'union des cartes bannies, utilisée par le
  validateur. Copiée vers `public/api/data/` au build : c'est le seul chemin qui
  résout à l'identique dans le dépôt, dans `dist/` et en production.
- `tournaments/*.json` — top 8, meta, participants (schéma dans `src/content.config.ts`)
- `decklists/*.json` — decklist au format MTGO dans un champ texte

Les données Scryfall (images, coûts de mana, types) sont récupérées au build et
mises en cache 30 jours dans `site/.cache/scryfall/`.

## Déploiement

Le build produit `site/dist/`, qui contient déjà `api/`.

```bash
cd site && npm run build && rsync -avz --delete dist/ user@vps:/var/www/pdc/
```

Le serveur doit exposer `/var/www/pdc` en DocumentRoot et exécuter le PHP de
`/api/`. Seul `validate-deck.php` doit être joignable : `lib/`, `cache/` et
`data/` sont internes. Les règles Apache sont dans `public/api/.htaccess` ;
l'équivalent nginx y est documenté en commentaire (`.htaccess` est ignoré par
nginx).

`cache/` doit être accessible en écriture par PHP (cache Scryfall + état du
rate limit).

## Validateur de deck

`POST /api/validate-deck.php` — `commander`, `partner` (optionnel), `decklist`.

Les 9 règles vérifiées sont documentées en tête de
`public/api/lib/DeckValidator.php`. Si la ban list ne peut pas être chargée,
l'API renvoie **503** plutôt que de valider un deck qu'elle n'a pas
complètement vérifié.

Garde-fous : 20 requêtes/minute/IP (429), 120 cartes distinctes maximum (422),
50 Ko de corps maximum (413).

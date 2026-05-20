# Spec: Migration PDC — WordPress → Astro + PHP

## Objective

Remplacer le site WordPress (Bedrock + ACF + Timber/Twig) par un site **statique Astro** avec un **backend PHP léger** (sans WordPress). Supprimer la dépendance à WordPress, ACF Pro, Polylang et tous les plugins tout en conservant 100% des fonctionnalités et le design existant.

**Contrainte serveur** : VPS avec PHP + MySQL uniquement. Pas de Node.js en production. Le build Astro génère du HTML/CSS/JS pur, déployé comme fichiers statiques. Le PHP ne sert qu'à l'API du validateur de deck (seule fonctionnalité runtime).

**Utilisateurs :**
- Visiteurs : consultent règles, ban list, tournois, decklists, méta, valident leurs decks
- Admins (1-2 personnes) : ajoutent tournois/decklists, mettent à jour la ban list via fichiers JSON dans le repo

**Critères de succès :**
- Toutes les pages actuelles existent et fonctionnent à l'identique
- Le validateur de deck retourne les mêmes résultats que la version PHP actuelle
- Le site est disponible en FR et EN avec le même contenu
- Temps de chargement meilleur qu'aujourd'hui (pages statiques = LCP < 1s)
- Build `npm run build` produit un dossier déployable par simple rsync/FTP
- Le backend PHP du validateur fonctionne sans aucune dépendance WordPress

---

## Tech Stack

| Composant | Choix | Version | Justification |
|---|---|---|---|
| **Frontend (build)** | Astro | 5.x | Génère du HTML statique pur. Pas de JS runtime sauf le nécessaire. i18n natif. Content Collections pour JSON/MDX |
| **Langage frontend** | TypeScript | 5.x | Type safety au build. Zero impact runtime |
| **CSS** | Tailwind CSS | 4.x | Déjà utilisé, config migrée |
| **JS interactif** | Vanilla ES6+ | — | Comme l'actuel. Pas de framework frontend (pas de React/Vue) |
| **Backend (runtime)** | PHP | 8.x | Même serveur. Seul le validateur a besoin de PHP runtime |
| **Cache Scryfall (runtime)** | Fichiers JSON sur disque | — | Remplace les transients WP. Un fichier `.json` par carte dans `cache/scryfall/` |
| **Cache Scryfall (build)** | Fichiers JSON locaux | — | Même format, dans `.cache/` (gitignored). Accélère les rebuilds |
| **Données** | Fichiers JSON + MDX | — | Tournois, decklists, ban list, contenu éditorial |
| **i18n** | Astro i18n routing | — | `/fr/`, `/en/` natif + fichiers de traduction JSON |
| **Fonts** | Barlow Condensed, Inter (Google), Beleren (local) | — | Identique |
| **Icônes mana** | mana-font (CDN) | — | Identique |
| **Déploiement** | rsync vers VPS | — | `npm run build && rsync dist/ + api/` |

### Ce qui n'est PAS sur le serveur
- Pas de Node.js
- Pas de WordPress
- Pas de MySQL (plus nécessaire — les données sont dans des fichiers JSON)
- Pas d'ACF Pro, Polylang, Wordfence, Yoast

---

## Architecture

```
┌─────────────────────────────────────────────────┐
│                    VPS (production)               │
│                                                   │
│  Apache/Nginx                                     │
│  ├── /var/www/pdc/                                │
│  │   ├── index.html          ← Astro output      │
│  │   ├── fr/                 ← Pages FR statiques │
│  │   │   ├── index.html                          │
│  │   │   ├── tournois/                           │
│  │   │   ├── decklist/                           │
│  │   │   ├── validateur/                         │
│  │   │   ├── meta/                               │
│  │   │   └── banlist/                            │
│  │   ├── en/                 ← Pages EN statiques │
│  │   ├── _astro/             ← CSS/JS bundles     │
│  │   └── api/                ← PHP (runtime)      │
│  │       ├── validate-deck.php                    │
│  │       └── lib/                                 │
│  │           ├── ScryfallService.php              │
│  │           ├── DeckValidator.php                │
│  │           ├── DecklistParser.php               │
│  │           └── config.php                       │
│  └── cache/                                       │
│      └── scryfall/           ← Cache JSON fichier │
│          ├── lightning-bolt.json                   │
│          └── ...                                   │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│               Machine dev (build time)            │
│                                                   │
│  npm run build                                    │
│  ├── Astro lit content/ (JSON, MDX)               │
│  ├── Fetche Scryfall pour enrichir les pages      │
│  ├── Génère HTML statique dans dist/              │
│  └── rsync dist/ → VPS:/var/www/pdc/              │
│                                                   │
│  Le PHP dans api/ est copié tel quel sur le VPS   │
└─────────────────────────────────────────────────┘
```

**Flux de données :**
- **Pages statiques** (tournois, decklists, meta, rules, banlist) : toutes les données Scryfall sont résolues au build. Le HTML final contient déjà les images, mana costs, stats. Zero requête Scryfall côté visiteur.
- **Validateur** : le formulaire JS envoie un POST à `/api/validate-deck.php`. Le PHP valide le deck en temps réel, avec cache Scryfall fichier sur le serveur.

---

## Commands

```bash
# Dans le dossier site/
npm install           # Installer les dépendances (dev only)
npm run dev           # Dev server Astro (localhost:4321)
npm run build         # Build production → dist/
npm run preview       # Prévisualiser le build
npm run lint          # ESLint + Astro check
npm run typecheck     # astro check (types Astro + TS)
npm run deploy        # rsync dist/ + api/ vers le VPS
```

---

## Project Structure

```
pdc/
├── web/app/themes/pdc-theme/    # ← WordPress actuel (conservé pendant la migration)
├── site/                         # ← NOUVEAU SITE
│   ├── astro.config.ts
│   ├── tailwind.config.ts        # Migré depuis la racine
│   ├── tsconfig.json
│   ├── package.json
│   │
│   ├── content/                  # Données (Content Collections Astro)
│   │   ├── tournaments/          # Un JSON par tournoi
│   │   │   ├── artefact-1.json
│   │   │   ├── artefact-2.json
│   │   │   └── ...               # 10 fichiers
│   │   ├── decklists/            # Un JSON par decklist
│   │   │   ├── crackling-drake-artefact-1.json
│   │   │   └── ...               # 32 fichiers
│   │   └── config.ts             # Schémas Zod des collections
│   │
│   ├── src/
│   │   ├── content/              # Contenu éditorial MDX
│   │   │   └── pages/
│   │   │       ├── fr/
│   │   │       │   ├── accueil.mdx
│   │   │       │   ├── banlist.mdx
│   │   │       │   └── ...
│   │   │       └── en/
│   │   │           ├── home.mdx
│   │   │           ├── banlist.mdx
│   │   │           └── ...
│   │   │
│   │   ├── pages/                # Routes Astro
│   │   │   ├── index.astro       # Redirect → /fr/
│   │   │   ├── fr/
│   │   │   │   ├── index.astro           # Homepage
│   │   │   │   ├── tournois/
│   │   │   │   │   ├── index.astro       # Archive
│   │   │   │   │   └── [slug].astro      # Détail
│   │   │   │   ├── decklist/
│   │   │   │   │   ├── index.astro       # Archive
│   │   │   │   │   └── [slug].astro      # Détail
│   │   │   │   ├── validateur.astro      # Formulaire
│   │   │   │   ├── meta.astro            # Page méta
│   │   │   │   ├── banlist.astro         # Ban list
│   │   │   │   └── [...slug].astro       # Pages MDX catch-all
│   │   │   └── en/
│   │   │       └── ...                   # Miroir FR
│   │   │
│   │   ├── layouts/
│   │   │   └── Base.astro        # HTML shell (head, header, main, footer)
│   │   │
│   │   ├── components/           # Composants Astro (.astro) = zero JS
│   │   │   ├── Header.astro
│   │   │   ├── Footer.astro
│   │   │   ├── Navigation.astro
│   │   │   ├── LanguageSwitcher.astro
│   │   │   ├── Hero.astro                # M02
│   │   │   ├── FeaturesGrid.astro        # M03
│   │   │   ├── TextImage.astro           # M04
│   │   │   ├── Callout.astro             # M05
│   │   │   ├── Steps.astro               # M06
│   │   │   ├── BanListGrid.astro         # M07
│   │   │   ├── FaqAccordion.astro        # M08
│   │   │   ├── Community.astro           # M09
│   │   │   ├── MagicCard.astro
│   │   │   ├── Button.astro
│   │   │   ├── Badge.astro
│   │   │   ├── SectionDivider.astro
│   │   │   ├── Top8Table.astro
│   │   │   ├── MetaPanel.astro
│   │   │   ├── ColorPieChart.astro       # SVG donut
│   │   │   ├── CommanderBar.astro        # Barre horizontale %
│   │   │   ├── CardList.astro            # Cartes groupées par type
│   │   │   ├── DeckStats.astro           # CMC chart, color/type donuts
│   │   │   └── DecklistCard.astro        # Carte dans l'archive
│   │   │
│   │   ├── lib/                  # Logique métier (build-time, TypeScript)
│   │   │   ├── scryfall.ts       # Fetch + cache fichier local
│   │   │   ├── deck-renderer.ts  # Enrichissement, tri, stats
│   │   │   ├── decklist-parser.ts
│   │   │   ├── tournaments.ts    # Helpers (split commander, color sort, meta aggregation)
│   │   │   ├── mana.ts           # Mana cost → HTML
│   │   │   ├── colors.ts         # Color identity utils
│   │   │   └── i18n.ts           # Helper traductions
│   │   │
│   │   ├── types/
│   │   │   ├── scryfall.ts
│   │   │   ├── tournament.ts
│   │   │   ├── decklist.ts
│   │   │   └── index.ts
│   │   │
│   │   ├── scripts/              # JS client (chargé par le navigateur)
│   │   │   ├── accordion.ts      # Init accordéons
│   │   │   ├── validator.ts      # Formulaire AJAX → /api/validate-deck.php
│   │   │   ├── decklist.ts       # Card hover preview, export buttons
│   │   │   ├── mobile-menu.ts    # Toggle menu mobile
│   │   │   └── decklist-filter.ts # Filtres archive decklists (client-side)
│   │   │
│   │   ├── styles/
│   │   │   ├── globals.css       # Tailwind layers + composants custom
│   │   │   ├── decklist.css
│   │   │   └── mana-custom.css
│   │   │
│   │   └── i18n/
│   │       ├── fr.json           # Traductions UI
│   │       └── en.json
│   │
│   ├── public/
│   │   ├── fonts/beleren/        # Beleren Bold woff/ttf
│   │   ├── img/                  # Logo, og-image, etc.
│   │   └── api/                  # ← PHP runtime (copié tel quel sur le VPS)
│   │       ├── validate-deck.php
│   │       └── lib/
│   │           ├── ScryfallService.php
│   │           ├── DeckValidator.php
│   │           ├── DecklistParser.php
│   │           └── config.php
│   │
│   └── .cache/                   # Cache Scryfall local (gitignored)
│       └── scryfall/
│
├── scripts/
│   └── export-wp-data.php        # Script migration WP → JSON (one-shot, PHP)
│
├── SPEC-MIGRATION.md             # Ce fichier
└── CLAUDE.md
```

---

## Data Models

### Tournament JSON (`content/tournaments/artefact-1.json`)

```jsonc
{
  "title": "Artefact #1",
  "date": "2026-01-15",
  "location": "Artefact Store",
  "city": "Toulon",
  "playerCount": 16,
  "actualPlayerCount": 14,
  "signupUrl": "https://...",
  "details": "Texte libre...",
  "top8": [
    {
      "place": 1,
      "playerName": "Jean",
      "commanderName": "Gut, True Soul Zealot // Agent of the Iron Throne",
      "score": "5-0",
      "decklistSlug": "gut-agent-artefact-1"
    }
  ],
  "metaList": [
    { "name": "Gut, True Soul Zealot // Agent of the Iron Throne", "count": 5 },
    { "name": "Crackling Drake", "count": 3 }
  ]
}
```

**Validation Zod (dans `content/config.ts`) :**
```typescript
const tournamentSchema = z.object({
  title: z.string(),
  date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  location: z.string(),
  city: z.string(),
  playerCount: z.number().min(2),
  actualPlayerCount: z.number().optional(),
  signupUrl: z.string().url().optional(),
  details: z.string().optional(),
  top8: z.array(z.object({
    place: z.number().min(1).max(8),
    playerName: z.string(),
    commanderName: z.string(),
    score: z.string(),
    decklistSlug: z.string().nullable(),
  })).max(8).default([]),
  metaList: z.array(z.object({
    name: z.string(),
    count: z.number().min(1),
  })).default([]),
});
```

### Decklist JSON (`content/decklists/gut-agent-artefact-1.json`)

```jsonc
{
  "title": "Gut/Agent Aggro",
  "commander": "Gut, True Soul Zealot",
  "partner": "Agent of the Iron Throne",
  "date": "2026-01-15",
  "author": "Jean",
  "archetype": "Aggro",
  "cards": "1 Lightning Bolt\n1 Chain Lightning\n1 Faithless Looting\n..."
}
```

### Ban List (`content/banlist.json`)

```jsonc
{
  "lastUpdated": "2026-05-01",
  "cards": [
    "All That Glitters",
    "Cranial Plating",
    "Mystic Sanctuary"
  ]
}
```

### Traductions (`src/i18n/fr.json`)

```jsonc
{
  "nav": {
    "rules": "Règles",
    "banlist": "Ban List",
    "tournaments": "Tournois",
    "decklists": "Decklists",
    "validator": "Validateur",
    "meta": "Méta"
  },
  "validator": {
    "title": "Validateur de Deck",
    "commanderLabel": "Commandant",
    "partnerLabel": "Partenaire / Background",
    "decklistLabel": "Decklist (format MTGO)",
    "submitButton": "Valider le deck",
    "loading": "Validation en cours...",
    "resultValid": "Deck valide !",
    "resultInvalid": "Deck invalide",
    "rules": {
      "format": "Format de la decklist",
      "commander": "Commandant",
      "commander_type": "Type du commandant",
      "commander_rarity": "Rareté du commandant",
      "deck_size": "Taille du deck",
      "not_found": "Cartes non trouvées",
      "duplicates": "Doublons",
      "rarity": "Rareté (Pauper)",
      "color_identity": "Identité de couleur",
      "ban_list": "Ban List"
    }
  },
  "common": {
    "creatures": "Créatures",
    "instants": "Éphémères",
    "sorceries": "Rituels",
    "artifacts": "Artefacts",
    "enchantments": "Enchantements",
    "lands": "Terrains",
    "other": "Autres"
  }
}
```

---

## Code Style

### TypeScript (build-time, composants Astro)

```typescript
// Naming: kebab-case fichiers, PascalCase composants/types, camelCase fonctions
// Pas de React — composants Astro (.astro) = templates HTML avec frontmatter TS

// --- Exemple : composant Astro ---
---
// src/components/Top8Table.astro
import type { EnrichedTop8Entry } from '../types';

interface Props {
  top8: EnrichedTop8Entry[];
}

const { top8 } = Astro.props;
---

<div class="space-y-4">
  {top8.map((entry) => (
    <div class="magic-card p-4 flex items-center gap-4">
      <span class="tournament-place tournament-place--{entry.place}">
        #{entry.place}
      </span>
      <img src={entry.commanderImage} alt={entry.commanderName} class="w-16 rounded" />
      <div>
        <p class="font-heading text-lg text-text-primary">{entry.playerName}</p>
        <p class="text-text-secondary">{entry.commanderName}</p>
      </div>
      <span class="ml-auto text-text-muted">{entry.score}</span>
    </div>
  ))}
</div>

// --- Exemple : lib (pure TS) ---
// src/lib/decklist-parser.ts
export interface ParsedCard {
  quantity: number;
  name: string;
}

export function parseDecklist(text: string): ParsedCard[] {
  return text
    .split('\n')
    .map(line => line.trim())
    .filter(line => line && !line.startsWith('//') && !line.startsWith('#'))
    .filter(line => !/^sideboard:?$/i.test(line))
    .map(parseLine)
    .filter((card): card is ParsedCard => card !== null);
}

function parseLine(line: string): ParsedCard | null {
  const match = line.match(/^(\d+)\s+(.+)$/);
  if (match) return { quantity: parseInt(match[1], 10), name: match[2].trim() };
  if (/^[A-Za-z]/.test(line)) return { quantity: 1, name: line };
  return null;
}
```

### PHP (runtime API)

```php
// Même conventions que l'actuel : PascalCase classes, snake_case fonctions
// MAIS sans aucune dépendance WordPress (pas de wp_remote_get, get_transient, etc.)

// --- Exemple : ScryfallService.php ---
class ScryfallService {
    private const CACHE_DIR = __DIR__ . '/../../cache/scryfall/';
    private const CACHE_DURATION = 30 * 86400; // 30 jours
    private const API_BASE = 'https://api.scryfall.com';

    public static function get_card_by_name(string $name): ?object {
        $cache_key = self::cache_key($name);
        $cached = self::read_cache($cache_key);
        if ($cached !== null) return $cached;

        $url = self::API_BASE . '/cards/named?exact=' . urlencode($name);
        $data = self::http_get($url);
        if ($data) self::write_cache($cache_key, $data);
        return $data;
    }

    private static function http_get(string $url): ?object {
        $ctx = stream_context_create(['http' => [
            'header' => "User-Agent: PDC-Site/2.0\r\n",
            'timeout' => 10,
        ]]);
        $json = @file_get_contents($url, false, $ctx);
        return $json ? json_decode($json) : null;
    }

    private static function read_cache(string $key): ?object {
        $path = self::CACHE_DIR . $key . '.json';
        if (!file_exists($path)) return null;
        if (time() - filemtime($path) > self::CACHE_DURATION) return null;
        return json_decode(file_get_contents($path));
    }

    private static function write_cache(string $key, object $data): void {
        if (!is_dir(self::CACHE_DIR)) mkdir(self::CACHE_DIR, 0755, true);
        file_put_contents(self::CACHE_DIR . $key . '.json', json_encode($data));
    }

    private static function cache_key(string $name): string {
        return preg_replace('/[^a-z0-9_-]/', '_', strtolower(trim($name)));
    }
}
```

### CSS/Tailwind

Identique à l'actuel. Mobile-first, utility classes Tailwind, composants complexes dans `@layer components {}`.

---

## Logique Métier

### Build-time (TypeScript) — enrichissement des pages statiques

Au moment du `npm run build`, Astro exécute le TypeScript pour :

1. **Lire les Content Collections** (JSON tournois/decklists)
2. **Appeler Scryfall** pour enrichir chaque carte (images, mana cost, type, couleurs)
3. **Calculer les stats** (CMC distribution, color counts, type counts)
4. **Agréger la méta** (global + top4 pour la page méta)
5. **Rendre le HTML final** avec toutes les données déjà résolues

Fichiers TS :

| Fichier | Rôle | Port de |
|---|---|---|
| `lib/scryfall.ts` | Fetch Scryfall + cache fichier local | `class-scryfall-service.php` (sans WP) |
| `lib/decklist-parser.ts` | Parse MTGO text → `ParsedCard[]` | `class-decklist-parser.php` |
| `lib/deck-renderer.ts` | Enrichit cards, trie, groupe, calcule stats | `class-deck-renderer.php` |
| `lib/tournaments.ts` | Split commander, color sort, meta aggregation | `functions.php` helpers + `page-meta.php` |
| `lib/mana.ts` | `{2}{U}{U}` → HTML `<i class="ms ms-2 ms-cost">` | `Deck_Renderer::format_mana_cost()` |
| `lib/colors.ts` | Color identity merge, sort WUBRG, labels | helpers divers |

### Runtime (PHP) — validateur de deck uniquement

Le PHP sur le serveur ne fait **qu'une chose** : valider des decks à la demande.

`public/api/validate-deck.php` :
```
POST /api/validate-deck.php
Content-Type: application/x-www-form-urlencoded

commander=Gut,+True+Soul+Zealot&partner=Agent+of+the+Iron+Throne&decklist=1+Lightning+Bolt%0A...

→ { "success": true, "data": { "is_valid": true, "errors": [], "warnings": [], "stats": {...} } }
```

Fichiers PHP :

| Fichier | Rôle | Port de |
|---|---|---|
| `api/validate-deck.php` | Point d'entrée POST, sanitize, CORS | `pdc_ajax_validate_deck()` |
| `api/lib/DeckValidator.php` | 9 règles de validation | `class-deck-validator.php` |
| `api/lib/DecklistParser.php` | Parse MTGO text | `class-decklist-parser.php` |
| `api/lib/ScryfallService.php` | Fetch + cache fichier | `class-scryfall-service.php` (sans WP) |
| `api/lib/config.php` | Ban list (lit `banlist.json`), constantes | Nouveau |

**Changement clé** : la ban list n'est plus extraite des blocs Gutenberg. Elle vient de `content/banlist.json` (lu par le PHP via chemin relatif).

### Les 9 règles du validateur (identiques à l'actuel)

1. Commander requis + trouvé sur Scryfall
2. Commander doit être une Creature (`type_line` contient "Creature")
3. Commander doit être uncommon (`rarity === "uncommon"`)
4. Taille du deck : 99 (solo) ou 98 (partner)
5. Toutes les cartes trouvées sur Scryfall
6. Pas de doublons (sauf basic lands : "Basic Land" dans type_line OU nom in `[Plains, Island, Swamp, Mountain, Forest, Wastes]`)
7. Rareté Pauper : `legalities.pauper !== "not_legal"` (accepte `legal` ET `banned`)
8. Identité de couleur : chaque carte ⊆ identité commander (+ partner)
9. Ban list : aucune carte dans `banlist.json`

---

## Pages et Routes

| Route | Source actuelle | Rendu | Données |
|---|---|---|---|
| `/` | — | Redirect → `/fr/` | — |
| `/fr/` | `page.php` + M02, M03, M09 | **Statique** | MDX + composants |
| `/fr/tournois/` | `archive-tournament.php` | **Statique** | JSON tournaments |
| `/fr/tournois/[slug]/` | `single-tournament.php` | **Statique** | JSON + Scryfall (build) |
| `/fr/decklist/` | `archive-decklist.php` | **Statique** | JSON decklists + Scryfall (build) |
| `/fr/decklist/[slug]/` | `single-decklist.php` | **Statique** | JSON + Scryfall (build) |
| `/fr/validateur/` | `page-validateur.php` | **Statique** (form JS) | JS → PHP API runtime |
| `/fr/meta/` | `page-meta.php` | **Statique** | Agrégation build-time |
| `/fr/banlist/` | page + M07 block | **Statique** | `banlist.json` + Scryfall (build) |
| `/fr/[slug]/` | `page.php` + blocs | **Statique** | MDX |
| `/en/...` | Miroir FR | **Statique** | Mêmes données, traductions EN |
| `/api/validate-deck.php` | `admin-ajax.php` | **PHP runtime** | Scryfall live + cache fichier |

**Tout est statique sauf le validateur.** Les pages tournois/decklists/meta incluent les images Scryfall résolues au build — zero API call côté visiteur.

Pour mettre à jour le contenu : éditer le JSON → `npm run build` → déployer.

---

## Migration des Données

### Volume exact (du dump SQL)

| Type | Quantité |
|---|---|
| Tournois | 10 (Artefact 1-5, Fight Club 1-2, Anim'Magic 1, Chupacabras 1, Ludotrotter 1) |
| Decklists | 32 |
| Pages FR/EN | 5 paires (accueil, banlist, validateur, meta, confidentialité) |
| Taxonomies | 5 auteurs, 5 archétypes, 18 couleurs |
| Attachments | 4 images (logos + photos) |

### Script d'export (`scripts/export-wp-data.php`)

Script PHP one-shot qui lit le dump SQL et génère les fichiers JSON/MDX.

```bash
# Charger le dump dans une DB locale temporaire
mysql -u root -e "CREATE DATABASE pdc_export"
mysql -u root pdc_export < 104dbb16-*.mysql266.*

# Exécuter le script d'export
php scripts/export-wp-data.php

# Résultat :
# → site/content/tournaments/*.json  (10 fichiers)
# → site/content/decklists/*.json    (32 fichiers)
# → site/content/banlist.json
# → site/src/i18n/fr.json + en.json
```

Le script :
1. Lit `mod17_posts` (type=tournament/decklist) + `mod17_postmeta` (ACF fields)
2. Parse les repeaters ACF (`top8_0_place`, `top8_0_player_name`, etc.)
3. Parse `tournament_meta_list` textarea → array metaList
4. Extrait les taxonomies via `mod17_term_relationships` + `mod17_terms`
5. Parse le bloc M07 dans `post_content` pour extraire la ban list
6. Extrait les traductions Polylang de `mod17_options`

Les ~5 pages de contenu éditorial seront converties en MDX **manuellement** (peu de pages, contenu simple avec des blocs ACF à transformer en composants MDX).

---

## Testing Strategy

| Niveau | Framework | Cible | Couverture |
|---|---|---|---|
| Unit TS | Vitest | `decklist-parser.ts`, `deck-renderer.ts`, `tournaments.ts`, `mana.ts` | 90%+ |
| Unit PHP | PHPUnit (léger) | `DeckValidator.php`, `DecklistParser.php`, `ScryfallService.php` | 90%+ |
| Build | `astro check` + `tsc` | Types, broken links, missing data | Toutes les pages |
| Visual | Comparaison manuelle | Design identique à l'actuel | Spot-check par page |

**Priorités :**
1. `DeckValidator.php` — chaque règle testée individuellement avec mocks Scryfall
2. `decklist-parser.ts` — edge cases (lignes vides, commentaires, split cards, sans quantité)
3. `deck-renderer.ts` — tri, groupement, stats, DFC handling
4. Build complet sans erreur — toutes les pages générées correctement

---

## Boundaries

### Always
- Typer toutes les données TS (pas de `any`)
- Sanitizer les inputs PHP (`filter_input`, `htmlspecialchars`)
- CORS sur l'endpoint PHP (même domaine uniquement)
- Respecter le rate limit Scryfall au build (100ms entre requêtes)
- Tester le validateur PHP avant de déployer
- Conserver l'accessibilité (ARIA accordion, focus management)

### Ask First
- Ajouter une dépendance npm ou un fichier PHP externe
- Modifier le schéma JSON des tournois/decklists
- Changer la structure des routes
- Modifier la ban list ou les règles de validation

### Never
- Stocker des images de cartes localement (toujours Scryfall CDN)
- Exécuter du Node.js en production
- Déployer sans build qui passe (`astro check` + `tsc`)
- Mettre des credentials dans le repo

---

## Déploiement

### Process

```bash
# 1. Build local
cd site/
npm run build          # → dist/

# 2. Déployer
rsync -avz --delete dist/ user@vps:/var/www/pdc/
rsync -avz public/api/ user@vps:/var/www/pdc/api/
rsync -avz ../content/banlist.json user@vps:/var/www/pdc/api/data/banlist.json
```

### Config serveur (Apache)

```apache
<VirtualHost *:443>
    DocumentRoot /var/www/pdc
    ServerName pauperdualcommander.com

    # Fichiers statiques (HTML, CSS, JS)
    <Directory /var/www/pdc>
        Options -Indexes
        AllowOverride None
        Require all granted

        # Clean URLs (sans .html)
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteCond %{REQUEST_FILENAME}.html -f
        RewriteRule ^(.*)$ $1.html [L]
    </Directory>

    # PHP uniquement pour l'API
    <Directory /var/www/pdc/api>
        <FilesMatch "\.php$">
            SetHandler application/x-httpd-php
        </FilesMatch>
    </Directory>

    # Cache statique agressif
    <FilesMatch "\.(css|js|woff2|png|jpg|svg)$">
        Header set Cache-Control "public, max-age=31536000, immutable"
    </FilesMatch>

    # Cache court pour HTML
    <FilesMatch "\.html$">
        Header set Cache-Control "public, max-age=3600"
    </FilesMatch>
</VirtualHost>
```

### Redirections depuis les anciennes URLs WordPress

```apache
# Ancien /tournoi/ (singulier) → /fr/tournois/
Redirect 301 /tournoi /fr/tournois
# Ancien /wp-admin → 404
RedirectMatch 404 /wp-admin
# Ancien /decklist/ sans locale → /fr/decklist/
Redirect 301 /decklist /fr/decklist
```

---

## Success Criteria

- [ ] Toutes les 10 pages de type tournoi se génèrent avec top8, meta, stats, images Scryfall
- [ ] Les 32 decklists se génèrent avec cards groupées, mana costs, stats SVG
- [ ] La page méta agrège correctement les données de tous les tournois passés
- [ ] Le validateur PHP retourne les mêmes résultats que l'actuel pour les mêmes inputs
- [ ] Le site est navigable en FR et EN avec switch de langue
- [ ] Le build complet passe sans erreur (`astro check` clean)
- [ ] Le design est visuellement identique à l'actuel (même couleurs, typo, animations)
- [ ] Les pages statiques chargent en < 1s (pas de PHP, pas de DB, pas d'API externe)
- [ ] Le deploy se fait en une commande (`npm run deploy`)

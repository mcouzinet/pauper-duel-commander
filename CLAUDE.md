# PDC - Pauper Duel Commander

## Projet
Site web pour le format Magic: The Gathering "Pauper Duel Commander" (PDC). Gestion de règles, ban list, tournois, decklists, et validateur de deck.

## Stack Technique
- **CMS**: WordPress 6.x via Bedrock (Roots)
- **Templating**: Timber v2 + Twig
- **CSS**: Tailwind CSS v3 (utility-first)
- **Build**: Roots Bud v6.24.0 (webpack-based)
- **JS**: Vanilla ES6+ (pas de framework frontend)
- **PHP**: >= 7.4
- **Plugins clés**: ACF Pro, Polylang, Classic Editor
- **API externe**: Scryfall (données cartes Magic)

## Structure du Projet
```
pdc/
├── bud.config.js                    # Config build (entry: app.js, editor.css)
├── tailwind.config.js               # Couleurs, fonts, animations custom
├── package.json                     # Scripts: dev, build, production
├── composer.json                    # Dépendances PHP racine (Bedrock)
├── web/app/themes/pdc-theme/        # THEME PRINCIPAL
│   ├── functions.php                # Point d'entrée: CPT, taxonomies, AJAX, menus
│   ├── inc/                         # Classes PHP métier
│   │   ├── blocks.php               # Registration ACF Blocks (M01-M09)
│   │   ├── class-scryfall-service.php  # API Scryfall (cache transients 30j)
│   │   ├── class-decklist-parser.php   # Parse texte decklist → array
│   │   ├── class-deck-renderer.php     # Enrichit cards avec Scryfall, tri par type/CMC
│   │   ├── class-deck-validator.php    # Validation règles PDC complètes
│   │   └── tournament-fields.php       # Champs ACF tournois (programmatique)
│   ├── views/                       # Templates Twig
│   │   ├── layouts/base.twig        # Layout HTML (head, header, main, footer)
│   │   ├── components/
│   │   │   ├── header.twig          # Nav + logo + menu + langue
│   │   │   └── footer.twig          # Footer simple
│   │   ├── blocks/                  # Blocs Gutenberg (ACF)
│   │   │   ├── m01_block_and_title.twig
│   │   │   ├── m02_hero.twig
│   │   │   ├── m03_features_grid.twig
│   │   │   ├── m04_text_image.twig
│   │   │   ├── m05_callout.twig
│   │   │   ├── m06_steps.twig
│   │   │   ├── m07_ban_list.twig    # Grille cartes bannies (Scryfall images)
│   │   │   ├── m08_faq_accordion.twig
│   │   │   └── m09_community.twig
│   │   ├── modules/                 # Modules flex (legacy, même contenu que blocks/)
│   │   ├── page.twig                # Pages standard
│   │   ├── page-validateur.twig     # Validateur de deck (form AJAX)
│   │   ├── single-tournament.twig   # Détail tournoi (top8, meta, stats)
│   │   ├── single-decklist.twig     # Détail decklist
│   │   ├── archive-tournament.twig  # Liste tournois
│   │   └── archive-decklist.twig    # Liste decklists
│   ├── src/                         # Sources (compilées par Bud)
│   │   ├── css/
│   │   │   ├── app.css              # Entry CSS (imports + Tailwind layers)
│   │   │   ├── decklist.css         # Styles decklists
│   │   │   ├── editor.css           # Styles éditeur Gutenberg
│   │   │   ├── mana-custom.css      # Symboles mana custom
│   │   │   └── components/
│   │   │       ├── accordion.css
│   │   │       └── gutenberg-blocks.css
│   │   ├── js/
│   │   │   ├── app.js               # Entry JS (imports mobile-menu, accordion, etc.)
│   │   │   ├── validator.js         # Validateur deck (AJAX)
│   │   │   ├── accordion.js         # Init accordéons
│   │   │   ├── decklist.js          # Interactions decklists
│   │   │   ├── mobile-menu.js       # Toggle menu mobile
│   │   │   └── components/
│   │   │       └── Accordion.js     # Classe Accordion réutilisable
│   │   └── img/                     # Images sources
│   ├── public/                      # Assets compilés (NE PAS MODIFIER)
│   ├── acf-json/                    # Groupes de champs ACF (JSON sync)
│   └── languages/                   # Fichiers de traduction
```

## Custom Post Types

### `decklist` (slug: `/decklist/`)
- Taxonomies: `deck_author`, `deck_archetype`, `deck_color`
- Exclue de Polylang (reste en anglais)
- Templates: `single-decklist.php/.twig`, `archive-decklist.php/.twig`

### `tournament` (slug: `/tournoi/`)
- Champs ACF: `tournament_date`, `tournament_location`, `tournament_city`, `tournament_player_count`, `tournament_signup_url`, `top8` (repeater), `participants` (repeater)
- Templates: `single-tournament.php/.twig`, `archive-tournament.php/.twig`

## Blocs ACF (Gutenberg)
Nommage: `m{NUM}-{name}`. Enregistrés dans `inc/blocks.php`, rendus via callback `pdc_acf_block_render_callback()`.

| ID | Nom | Description |
|----|-----|-------------|
| M01 | block-and-title | Titre + grille de blocs |
| M02 | hero | Section hero (titre, sous-titre, CTA) |
| M03 | features-grid | Grille 3 features avec icônes |
| M04 | text-image | 2 colonnes texte + image |
| M05 | callout | Encart important |
| M06 | steps | Liste d'étapes numérotées |
| M07 | ban-list | Grille cartes bannies (images Scryfall) |
| M08 | faq-accordion | FAQ en accordéon |
| M09 | community | Section communauté |

## Design System

### Couleurs (Tailwind)
```
brand-orange: #FF5722 (DEFAULT), light: #FF7043, dark: #E64A19, glow: #FF6E40
brand-black: #0A0E13 (DEFAULT), light: #1A1F26
mana-white: #F8F6F1, mana-blue: #0E68AB, mana-black: #150B00, mana-red: #D3202A, mana-green: #00733E
magic-gold: #FFA500
card-bg: #141821, card-border: #FF5722, card-hover: #1C212B
text-primary: #FFFFFF, text-secondary: #B8BEC8, text-muted: #6B7280, text-orange: #FF5722
bg-primary: #0A0E13, bg-secondary: #141821, bg-tertiary: #1C212B
```

### Typographie
```
font-display / font-heading: Barlow Condensed (400-900), Impact, sans-serif → TITRES
font-body: Inter (300-700), system-ui, sans-serif → TEXTE COURANT
```

### Classes CSS Composites Clés
```css
.magic-card        /* Cadre style carte MTG (border orange, gradient bg, hover) */
.magic-border      /* Border avec gradient */
.btn-primary       /* Bouton orange animé avec shimmer */
.btn-secondary     /* Bouton bordure orange transparent */
.text-magic-gradient  /* Texte avec gradient orange (shimmer animation) */
.text-mana-gradient   /* Texte avec gradient 5 manas */
.icon-glow         /* Icône avec glow animation */
.section-divider   /* Séparateur horizontal stylisé */
.glass-effect      /* Backdrop blur (header fixed) */
```

### Animations
```
animate-shimmer    (3s, gradient pulse)
animate-glow       (2s, brightness alternate)
animate-float      (6s, translateY bounce)
animate-fade-in    (0.6s, opacity)
animate-slide-up   (0.6s, opacity + translateY)
```

### Shadows
```
shadow-card, shadow-card-hover
shadow-orange, shadow-orange-intense
shadow-inner-glow, shadow-inset-dark
```

## Conventions de Code

### PHP
- Préfixe fonctions: `pdc_` (ex: `pdc_theme_enqueue_assets()`)
- Classes: `PascalCase` avec underscores (ex: `Scryfall_Service`, `Deck_Validator`)
- Hooks WP standards, sanitization systématique
- Cache Scryfall: `get_transient('scryfall_name_' . sanitize_key($name))` → 30 jours

### Twig
- Layout: `{% extends 'layouts/base.twig' %}` + `{% block content %}`
- Composants: `{% include 'components/header.twig' %}`
- Données ACF: `module.field_name` dans les blocs
- Traductions: `{{ __('Texte', 'pdc-theme') }}`
- Context Timber: `site`, `menu`, `options`, `post`

### JavaScript
- Vanilla ES6+, pas de framework
- `import` / `export` modules
- Point d'entrée: `app.js` importe tous les modules
- AJAX: `fetch()` + `URLSearchParams` + `wp_ajax_` endpoints
- Convention: `camelCase` pour fonctions/variables

### CSS/Tailwind
- Mobile-first responsive
- Classes utility Tailwind en priorité
- Composants complexes dans `@layer components {}`
- Fichiers séparés par domaine (`decklist.css`, `accordion.css`)

## APIs et Services

### Scryfall API (`class-scryfall-service.php`)
```php
Scryfall_Service::get_card_by_name($name)           // GET /cards/named?exact=
Scryfall_Service::get_card_by_set($set, $number)     // GET /cards/{set}/{number}
Scryfall_Service::get_cards_by_names(array $names)   // POST /cards/collection (batch 75)
Scryfall_Service::get_card_image($card, $size)       // Extraire image_uris.{size}
Scryfall_Service::get_mana_cost($card)               // Extraire mana_cost
Scryfall_Service::get_colors($card)                  // Extraire color_identity
Scryfall_Service::get_primary_type($card)            // 'Creature', 'Instant', etc.
```

### AJAX Endpoints
```
wp_ajax_pdc_validate_deck → pdc_ajax_validate_deck()
  POST: nonce, commander, partner, decklist
  Response: {success, data: {is_valid, errors[], warnings[], stats{}}}
```

## Commandes Build
```bash
npm run dev         # Développement avec hot reload
npm run build       # Build production
npm run production  # Alias build --mode production
```

## Points d'Attention
- Les fichiers dans `public/` sont générés par Bud, ne PAS les modifier directement
- Les modules dans `views/modules/` sont legacy, préférer `views/blocks/` pour les nouveaux blocs
- Le validateur de deck utilise la ban list du bloc M07 (cache invalidé au save_post)
- Polylang est actif: penser aux traductions `__('texte', 'pdc-theme')` dans les templates
- Les images de cartes viennent de Scryfall API, pas stockées localement
- Le thème est dark by default (bg: #0A0E13), design Magic-themed avec accents orange

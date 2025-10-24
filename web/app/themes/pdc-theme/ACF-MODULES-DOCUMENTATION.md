# Documentation des Modules ACF

Ce document décrit la structure des champs ACF (Advanced Custom Fields) pour chaque module du thème PDC.

## Configuration Flexible Content

Tous les modules doivent être ajoutés à un **groupe de champs "Modules"** avec un champ **Flexible Content** nommé `modules`.

**Emplacement** : Ce groupe de champs doit être assigné aux pages ou types de contenu souhaités.

---

## M01 - Block and Title

**Layout name**: `m01_block_and_title`
**Template**: `views/modules/m01_block_and_title.twig`

### Champs

| Nom du champ | Type | Clé | Requis | Description |
|--------------|------|-----|---------|-------------|
| Titre | Text | `title` | Oui | Titre principal du bloc |
| Description | Textarea | `desc` | Non | Description sous le titre (supporte HTML) |
| Blocs | Repeater | `blocs` | Non | Liste des blocs de contenu |

### Sous-champs du Repeater "Blocs"

| Nom du champ | Type | Clé | Requis | Description |
|--------------|------|-----|---------|-------------|
| Titre | Text | `title` | Oui | Titre du bloc |
| Description | Textarea | `desc` | Non | Description du bloc |

---

## M02 - Hero

**Layout name**: `m02_hero`
**Template**: `views/modules/m02_hero.twig`

### Champs

| Nom du champ | Type | Clé | Requis | Description |
|--------------|------|-----|---------|-------------|
| Badge | Text | `badge` | Non | Texte du badge au-dessus du titre |
| Titre ligne 1 | Text | `title_line_1` | Oui | Première ligne du titre |
| Titre ligne 2 | Text | `title_line_2` | Non | Deuxième ligne du titre (avec gradient) |
| Sous-titre | WYSIWYG / Textarea | `subtitle` | Non | Description sous le titre |
| Bouton 1 - Texte | Text | `button_1_text` | Non | Texte du bouton primaire |
| Bouton 1 - Lien | URL | `button_1_link` | Non | URL du bouton primaire |
| Bouton 2 - Texte | Text | `button_2_text` | Non | Texte du bouton secondaire |
| Bouton 2 - Lien | URL | `button_2_link` | Non | URL du bouton secondaire |
| Image de fond | Image | `background_image` | Non | Image de fond (affichée avec opacité) |

---

## M03 - Features Grid

**Layout name**: `m03_features_grid`
**Template**: `views/modules/m03_features_grid.twig`

### Champs

| Nom du champ | Type | Clé | Requis | Description |
|--------------|------|-----|---------|-------------|
| Titre | Text | `title` | Non | Titre de la section |
| Sous-titre | WYSIWYG / Textarea | `subtitle` | Non | Description de la section |
| Features | Repeater | `features` | Oui | Liste des fonctionnalités |

### Sous-champs du Repeater "Features"

| Nom du champ | Type | Clé | Requis | Description |
|--------------|------|-----|---------|-------------|
| Icône SVG | Textarea | `icon_svg` | Non | Code SVG de l'icône |
| Gradient de l'icône | Text | `icon_gradient` | Non | Classes Tailwind (ex: `from-purple-500 to-violet-600`) |
| Titre | Text | `title` | Oui | Titre de la fonctionnalité |
| Description | Textarea | `description` | Non | Description de la fonctionnalité |

**Note sur les gradients** : Si non spécifié, le gradient par défaut est `from-magic-purple to-magic-gold`.

---

## M04 - Stats Bar

**Layout name**: `m04_stats_bar`
**Template**: `views/modules/m04_stats_bar.twig`

### Champs

| Nom du champ | Type | Clé | Requis | Description |
|--------------|------|-----|---------|-------------|
| Couleur de fond | Text | `background_color` | Non | Classes Tailwind (défaut: `bg-magic-purple/10`) |
| Stats | Repeater | `stats` | Oui | Liste des statistiques |

### Sous-champs du Repeater "Stats"

| Nom du champ | Type | Clé | Requis | Description |
|--------------|------|-----|---------|-------------|
| Valeur | Text | `value` | Oui | Valeur numérique (ex: "100") |
| Label | Text | `label` | Oui | Description (ex: "card deck") |
| Icône SVG | Textarea | `icon_svg` | Non | Code SVG de l'icône |

---

## M05 - Getting Started

**Layout name**: `m05_getting_started`
**Template**: `views/modules/m05_getting_started.twig`

### Champs

| Nom du champ | Type | Clé | Requis | Description |
|--------------|------|-----|---------|-------------|
| Titre | Text | `title` | Non | Titre de la section |
| Sous-titre | WYSIWYG / Textarea | `subtitle` | Non | Description de la section |
| Cards | Repeater | `cards` | Oui | Liste des cartes (max 2 recommandé) |

### Sous-champs du Repeater "Cards"

| Nom du champ | Type | Clé | Requis | Description |
|--------------|------|-----|---------|-------------|
| Icône SVG | Textarea | `icon_svg` | Non | Code SVG de l'icône |
| Gradient de l'icône | Text | `icon_gradient` | Non | Classes Tailwind |
| Titre | Text | `title` | Oui | Titre de la carte |
| Description | Textarea | `description` | Non | Description de la carte |
| Bouton - Texte | Text | `button_text` | Non | Texte du bouton |
| Bouton - Lien | URL | `button_link` | Non | URL du bouton |
| Bouton - Style | Select | `button_style` | Non | `primary` ou `secondary` (défaut) |

**Options du Select "Bouton - Style"** :
- `primary` : Bouton avec gradient doré
- `secondary` : Bouton avec bordure violette

---

## M06 - Footer

**Layout name**: `m06_footer`
**Template**: `views/modules/m06_footer.twig`

### Champs

| Nom du champ | Type | Clé | Requis | Description |
|--------------|------|-----|---------|-------------|
| Logo | Image | `logo` | Non | Logo du site |
| Nom du site | Text | `site_name` | Non | Nom du site (affiché si pas de logo) |
| Description | Textarea | `description` | Non | Description du site |
| Copyright | Text | `copyright` | Non | Texte de copyright (auto-généré si vide) |
| Couleur de fond | Text | `background_color` | Non | Classes Tailwind (défaut: `bg-gray-800`) |
| Réseaux sociaux | Repeater | `social_links` | Non | Liens réseaux sociaux |

### Sous-champs du Repeater "Réseaux sociaux"

| Nom du champ | Type | Clé | Requis | Description |
|--------------|------|-----|---------|-------------|
| Icône SVG | Textarea | `icon_svg` | Oui | Code SVG de l'icône |
| URL | URL | `url` | Oui | Lien vers le réseau social |
| Label | Text | `label` | Oui | Label pour l'accessibilité (ex: "Facebook") |

---

## Classes Tailwind personnalisées disponibles

### Couleurs
- `text-magic-gold` : #f59e0b
- `text-magic-purple` : #8b5cf6
- `text-card-foreground` : #1f2937
- `text-muted-foreground` : #6b7280
- `border-magic-purple/20` : Bordure violette avec opacité

### Gradients
- `bg-gradient-gold` : Gradient doré horizontal
- `bg-gradient-card` : Gradient subtil pour les cartes

### Ombres
- `shadow-card` : Ombre personnalisée pour les cartes
- `shadow-magic` : Ombre violette/dorée au hover

### Exemple d'utilisation
```html
<div class="bg-gradient-card border-magic-purple/20 shadow-card hover:shadow-magic">
    <h3 class="text-magic-gold">Titre</h3>
    <p class="text-card-foreground">Contenu</p>
</div>
```

---

## Instructions d'import/création

### 1. Créer le groupe de champs "Modules"
1. Aller dans ACF > Groupes de champs
2. Ajouter un nouveau groupe nommé "Modules"
3. Définir l'emplacement (ex: Type de contenu = Page)

### 2. Ajouter le champ Flexible Content
1. Nom du champ : "Modules"
2. Clé : `modules`
3. Type : Flexible Content

### 3. Ajouter les layouts
Pour chaque module (M01 à M06), créer un layout dans le Flexible Content :
- Cliquer sur "Ajouter un layout"
- Nom du layout : Nom lisible (ex: "Hero")
- Clé du layout : Clé technique (ex: `m02_hero`)
- Ajouter les champs selon la documentation ci-dessus

### 4. Configuration recommandée
- **Min/Max layouts** : Selon les besoins (par défaut pas de limite)
- **Button label** : "Ajouter un module"
- **Layout** : "Block" (pour une meilleure lisibilité)

---

## Exemples de SVG pour les icônes

### Dollar Sign
```svg
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-white">
    <line x1="12" x2="12" y1="2" y2="22"></line>
    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
</svg>
```

### Book Open
```svg
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-white">
    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
</svg>
```

### Users
```svg
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-white">
    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
    <circle cx="9" cy="7" r="4"></circle>
    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
</svg>
```

**Note** : Pour chaque SVG, adapter la classe `class="w-8 h-8 text-white"` selon le contexte. Dans les modules, `currentColor` permet à l'icône d'hériter de la couleur du parent.

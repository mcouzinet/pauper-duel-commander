# PDC Theme

Un thème WordPress moderne utilisant Bedrock, Bud et Tailwind CSS.

## Stack technique

- **Bedrock** - Structure WordPress moderne et sécurisée
- **Bud** (@roots/bud) - Outil de build moderne basé sur webpack
- **Tailwind CSS** - Framework CSS utility-first
- **Timber** - Moteur de templates Twig pour WordPress
- **ACF Pro** - Advanced Custom Fields Pro

## Prérequis

- PHP >= 7.4
- Composer
- Node.js >= 14
- npm ou yarn

## Installation

### 1. Installer les dépendances

```bash
# Dépendances PHP (depuis le dossier du thème)
cd web/app/themes/pdc-theme
composer install

# Dépendances Node.js
npm install
```

### 2. Configuration

Configurez votre fichier `.env` à la racine du projet avec vos paramètres de base de données et l'URL du site.

### 3. Build des assets

```bash
# Mode développement avec watch
npm run dev

# Build de production
npm run production

# Build simple
npm run build
```

## Structure du thème

```
pdc-theme/
├── src/                      # Fichiers sources
│   ├── css/                  # Styles (Tailwind CSS)
│   ├── js/                   # Scripts JavaScript
│   └── views/                # Templates (optionnel)
├── views/                    # Templates Twig (Timber)
│   ├── layouts/              # Layouts de base
│   │   └── base.twig        # Layout principal
│   ├── components/           # Composants réutilisables
│   │   ├── header.twig
│   │   └── footer.twig
│   ├── modules/              # Modules ACF Flexible Content
│   │   ├── m01-hero.twig
│   │   └── m02-text-image.twig
│   ├── index.twig            # Template liste des posts
│   ├── single.twig           # Template article
│   ├── page.twig             # Template page
│   └── page-flexible.twig    # Template page avec modules
├── modules/                  # README pour les modules
│   └── README.md
├── vendor/                   # Dépendances Composer (Timber)
├── public/                   # Assets compilés (généré automatiquement)
├── bud.config.js             # Configuration Bud
├── tailwind.config.js        # Configuration Tailwind
├── composer.json             # Dépendances PHP
├── functions.php             # Fonctions du thème
├── style.css                 # Header du thème (requis par WordPress)
├── index.php                 # Controller principal
├── single.php                # Controller article
├── page.php                  # Controller page
└── page-flexible.php         # Controller page avec modules
```

## Développement

Le thème utilise Bud pour compiler les assets. Les fichiers sources se trouvent dans `src/` et les fichiers compilés sont générés dans `public/`.

### Commandes disponibles

- `npm run dev` - Mode développement avec hot reload
- `npm run build` - Build simple
- `npm run production` - Build optimisé pour la production

### Tailwind CSS

Les classes Tailwind sont disponibles dans tous les templates Twig. Le fichier de configuration se trouve dans `tailwind.config.js`.

### Timber (Twig)

Le thème utilise Timber pour séparer la logique PHP de la présentation HTML.

#### Structure des templates

- **Controllers PHP** (`index.php`, `single.php`, etc.) : Préparent les données
- **Views Twig** (`views/*.twig`) : Affichent le HTML

#### Exemple d'utilisation

**Controller PHP** (`page.php`) :
```php
<?php
$context = Timber::context();
$context['post'] = Timber::get_post();

Timber::render('page.twig', $context);
```

**View Twig** (`views/page.twig`) :
```twig
{% extends "layouts/base.twig" %}

{% block content %}
    <h1>{{ post.title }}</h1>
    <div>{{ post.content }}</div>
{% endblock %}
```

### Modules ACF

Le thème supporte les modules avec ACF Flexible Content.

#### Créer un module

1. Créez le template Twig dans `views/modules/` (ex: `m03-cards.twig`)
2. Créez le groupe de champs ACF correspondant
3. Utilisez le template "Page Flexible" sur votre page

Consultez `modules/README.md` pour plus de détails.

#### Exemple de module

**Template** (`views/modules/m01-hero.twig`) :
```twig
<section class="hero">
    <h1>{{ title }}</h1>
    <p>{{ subtitle }}</p>
</section>
```

**Utilisation dans une page** :
1. Créez une page dans WordPress
2. Sélectionnez le template "Page Flexible"
3. Ajoutez le module "Hero" via ACF Flexible Content
4. Remplissez les champs

## Activation du thème

1. Accédez au dashboard WordPress
2. Allez dans Apparence > Thèmes
3. Activez "PDC Theme"

## Support

Pour toute question ou problème, consultez la documentation :
- [Bedrock](https://roots.io/bedrock/)
- [Bud](https://bud.js.org/)
- [Tailwind CSS](https://tailwindcss.com/)
- [Timber](https://timber.github.io/docs/)
- [Twig](https://twig.symfony.com/)
- [ACF Pro](https://www.advancedcustomfields.com/resources/)

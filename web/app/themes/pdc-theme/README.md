# PDC Theme

Un thème WordPress moderne utilisant Bedrock, Bud et Tailwind CSS.

## Stack technique

- **Bedrock** - Structure WordPress moderne et sécurisée
- **Bud** (@roots/bud) - Outil de build moderne basé sur webpack
- **Tailwind CSS** - Framework CSS utility-first

## Prérequis

- PHP >= 7.4
- Composer
- Node.js >= 14
- npm ou yarn

## Installation

### 1. Installer les dépendances

```bash
# Dépendances PHP (depuis la racine du projet)
composer install

# Dépendances Node.js (depuis le dossier du thème)
cd web/app/themes/pdc-theme
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
├── src/              # Fichiers sources
│   ├── css/         # Styles (Tailwind CSS)
│   ├── js/          # Scripts JavaScript
│   └── views/       # Templates (optionnel)
├── public/          # Assets compilés (généré automatiquement)
├── bud.config.js    # Configuration Bud
├── tailwind.config.js # Configuration Tailwind
├── functions.php    # Fonctions du thème
├── style.css        # Header du thème (requis par WordPress)
├── index.php        # Template principal
├── header.php       # Header
└── footer.php       # Footer
```

## Développement

Le thème utilise Bud pour compiler les assets. Les fichiers sources se trouvent dans `src/` et les fichiers compilés sont générés dans `public/`.

### Commandes disponibles

- `npm run dev` - Mode développement avec hot reload
- `npm run build` - Build simple
- `npm run production` - Build optimisé pour la production

### Tailwind CSS

Les classes Tailwind sont disponibles dans tous les templates PHP. Le fichier de configuration se trouve dans `tailwind.config.js`.

## Activation du thème

1. Accédez au dashboard WordPress
2. Allez dans Apparence > Thèmes
3. Activez "PDC Theme"

## Support

Pour toute question ou problème, consultez la documentation :
- [Bedrock](https://roots.io/bedrock/)
- [Bud](https://bud.js.org/)
- [Tailwind CSS](https://tailwindcss.com/)

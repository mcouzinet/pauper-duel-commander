# Structure des Modules ACF

## Architecture

Ce thème utilise une architecture modulaire basée sur **ACF Flexible Content** avec le système de **Clone** pour une meilleure organisation.

## Correspondance des fichiers

| Module | Fichier ACF JSON | Template Twig | Layout Name |
|--------|------------------|---------------|-------------|
| M01 - Block et titre | `group_68fb8b6eeea49.json` | `m01_block_and_title.twig` | `m01_block_and_title` |
| M02 - Hero | `group_68fb8c0011111.json` | `m02_hero.twig` | `m02_hero` |
| M03 - Features Grid | `group_68fb8c0122222.json` | `m03_features_grid.twig` | `m03_features_grid` |
| M04 - Stats Bar | `group_68fb8c0233333.json` | `m04_stats_bar.twig` | `m04_stats_bar` |
| M05 - Getting Started | `group_68fb8c0344444.json` | `m05_getting_started.twig` | `m05_getting_started` |
| M06 - Footer | `group_68fb8c0455555.json` | `m06_footer.twig` | `m06_footer` |

**Fichier principal** : `group_68fb8b3d9d50c.json` - Contient le Flexible Content "Modules"

## Comment ajouter un nouveau module

### 1. Créer le fichier ACF JSON

Créer un nouveau fichier dans `acf-json/` avec une clé unique (ex: `group_68fb8c0566666.json`) :

```json
{
    "key": "group_68fb8c0566666",
    "title": "M07 - Mon Nouveau Module",
    "fields": [
        {
            "key": "field_68fb8c0566667",
            "label": "Mon Champ",
            "name": "mon_champ",
            "type": "text",
            ...
        }
    ],
    "location": [
        [
            {
                "param": "post_type",
                "operator": "==",
                "value": "post"
            }
        ]
    ],
    "active": false
}
```

### 2. Ajouter le layout dans le fichier principal

Éditer `group_68fb8b3d9d50c.json` et ajouter un nouveau layout dans `layouts` :

```json
"layout_68fb8c0566660": {
    "key": "layout_68fb8c0566660",
    "name": "m07_mon_nouveau_module",
    "label": "M07 - Mon Nouveau Module",
    "display": "block",
    "sub_fields": [
        {
            "key": "field_68fb8c0566670",
            "label": "Mon Nouveau Module",
            "name": "m07_mon_nouveau_module",
            "type": "clone",
            "clone": [
                "group_68fb8c0566666"
            ],
            "display": "seamless",
            "layout": "block",
            "prefix_label": 0,
            "prefix_name": 0
        }
    ]
}
```

### 3. Créer le template Twig

Créer `views/modules/m07_mon_nouveau_module.twig` :

```twig
{#
/**
 * Module M07 - Mon Nouveau Module
 *
 * Variables:
 * - mon_champ (string): Description du champ
 */
#}

<section class="py-20">
    <div class="container mx-auto px-4">
        {{ module.mon_champ }}
    </div>
</section>
```

### 4. Synchroniser dans WordPress

1. Aller dans **ACF > Groupes de champs**
2. Cliquer sur **"Synchroniser disponible"** pour les nouveaux groupes
3. Le nouveau module sera disponible dans le flexible content

## Avantages de cette architecture

### Modularité
Chaque module est défini dans son propre fichier, facilitant la maintenance et la réutilisation.

### Versionning
Les fichiers JSON peuvent être versionnés dans Git et synchronisés entre environnements.

### Réutilisabilité
Un module peut être utilisé dans plusieurs flexible content différents en ajoutant simplement un nouveau layout qui le clone.

### Seamless Clone
L'option `display: seamless` permet d'accéder aux champs directement via `module.field_name` sans préfixe supplémentaire.

## Workflow de développement

### 1. Développement local
- Modifier les fichiers JSON dans `acf-json/`
- Créer/modifier les templates Twig
- Synchroniser dans WordPress admin

### 2. Compilation des assets
```bash
npm run build
```

### 3. Test
- Créer une page de test
- Ajouter les modules via l'interface ACF
- Vérifier le rendu front-end

### 4. Commit
```bash
git add acf-json/ views/modules/
git commit -m "feat: add M07 module"
```

## Dépannage

### Le module n'apparaît pas dans WordPress
1. Vérifier que le fichier JSON est bien dans `acf-json/`
2. Aller dans ACF > Groupes de champs
3. Vérifier s'il y a un message de synchronisation
4. Synchroniser le groupe

### Les champs ne s'affichent pas
1. Vérifier que le layout dans `group_68fb8b3d9d50c.json` référence le bon groupe
2. Vérifier que `display: seamless` et `prefix_name: 0` sont bien définis
3. Vérifier que le nom du layout correspond au nom du fichier Twig

### Le template ne s'affiche pas
1. Vérifier que le nom du fichier Twig correspond exactement au `name` du layout
2. Vérifier que le fichier est dans `views/modules/`
3. Vérifier les logs de Timber pour les erreurs de template

### Classes Tailwind manquantes
1. Vérifier que `tailwind.config.js` inclut `'./views/**/*.twig'`
2. Relancer `npm run build`
3. Vider le cache du navigateur

## Références

- [ACF Flexible Content](https://www.advancedcustomfields.com/resources/flexible-content/)
- [ACF Clone Field](https://www.advancedcustomfields.com/resources/clone/)
- [ACF Local JSON](https://www.advancedcustomfields.com/resources/local-json/)
- [Timber Documentation](https://timber.github.io/docs/)

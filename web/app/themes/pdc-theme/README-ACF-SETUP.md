# Guide d'utilisation des Modules ACF

## Configuration terminée ✓

Les champs ACF pour tous les modules (M01 à M06) sont définis dans le dossier `acf-json/` avec une architecture modulaire utilisant le système de **Clone** d'ACF.

### Structure des fichiers

**Fichier principal** : `acf-json/group_68fb8b3d9d50c.json` - Groupe "Modules" avec Flexible Content

**Fichiers des modules** :
- `group_68fb8b6eeea49.json` - M01 - Block et titre
- `group_68fb8c0011111.json` - M02 - Hero
- `group_68fb8c0122222.json` - M03 - Features Grid
- `group_68fb8c0233333.json` - M04 - Stats Bar
- `group_68fb8c0344444.json` - M05 - Getting Started
- `group_68fb8c0455555.json` - M06 - Footer

Chaque module est défini dans son propre fichier JSON et est "cloné" (référencé) par le flexible content principal. Cela permet une meilleure organisation et réutilisabilité des modules.

## Synchronisation des champs ACF

1. **Connectez-vous à WordPress** : https://local.pdc.com/wp/wp-admin/
2. **Allez dans ACF** > Groupes de champs
3. **Vous devriez voir des messages** en haut indiquant que des groupes de champs sont disponibles pour la synchronisation
4. **Cliquez sur "Synchroniser disponible"** pour chaque groupe (7 groupes au total)

## Vérification de l'installation

1. **Connectez-vous à WordPress** : https://local.pdc.com/wp/wp-admin/
2. **Allez dans ACF** > Groupes de champs
3. **Vous devriez voir** un groupe nommé **"Modules"** avec 6 layouts :
   - M01 - Block and Title
   - M02 - Hero
   - M03 - Features Grid
   - M04 - Stats Bar
   - M05 - Getting Started
   - M06 - Footer

## Utilisation dans une page

### 1. Créer ou éditer une page

Dans l'admin WordPress :
- Pages > Ajouter
- Ou éditez une page existante

### 2. Ajouter des modules

En bas de la page, vous verrez une section **"Modules"** :

1. Cliquez sur **"Ajouter un module"**
2. Choisissez le type de module (M01, M02, etc.)
3. Remplissez les champs
4. Répétez pour ajouter d'autres modules
5. **Réorganisez** les modules en les glissant-déposant

### 3. Publier

Cliquez sur **"Publier"** ou **"Mettre à jour"**

## Exemple de contenu pour M02 - Hero

Voici un exemple de contenu pour tester le module Hero :

**Badge** : `New Format`

**Titre ligne 1** : `Welcome to Pauper Duel`

**Titre ligne 2** : `Commander`

**Sous-titre** :
```
Experience the thrill of Magic: The Gathering's most accessible format. Build powerful 100-card decks using only common cards and face off in exciting one-on-one battles.
```

**Bouton 1 - Texte** : `View the Rules`
**Bouton 1 - Lien** : `/rules`

**Bouton 2 - Texte** : `Check Banlist`
**Bouton 2 - Lien** : `/banlist`

## Exemple de contenu pour M03 - Features Grid

**Titre** : `Why Play Pauper Duel Commander?`

**Features** (répétez 3 fois) :

### Feature 1
**Icône SVG** :
```svg
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-white">
    <line x1="12" x2="12" y1="2" y2="22"></line>
    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
</svg>
```

**Gradient** : `from-green-500 to-emerald-600`

**Titre** : `Budget Friendly`

**Description** : `Build competitive decks without breaking the bank. Commons are affordable and accessible to everyone.`

### Feature 2
**Icône SVG** :
```svg
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-white">
    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
</svg>
```

**Gradient** : `from-blue-500 to-cyan-600`

**Titre** : `Simple Rules`

**Description** : `Easy to learn, hard to master. Perfect for both new players and veterans.`

### Feature 3
**Icône SVG** :
```svg
<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-8 h-8 text-white">
    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
    <circle cx="9" cy="7" r="4"></circle>
    <path d="M22 21v-2a4 4 0 0 0-3-3.87"></path>
    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
</svg>
```

**Gradient** : `from-purple-500 to-pink-600`

**Titre** : `Active Community`

**Description** : `Join a growing community of players dedicated to this exciting format.`

## Exemple de contenu pour M04 - Stats Bar

**Stats** (répétez 3 fois) :

1. **Valeur** : `100` / **Label** : `card deck`
2. **Valeur** : `20` / **Label** : `starting life`
3. **Valeur** : `1v1` / **Label** : `format`

## Structure d'une page type

Voici l'ordre recommandé pour une page d'accueil complète :

1. **M02 - Hero** : Section d'introduction avec CTA
2. **M04 - Stats Bar** : Statistiques du format
3. **M03 - Features Grid** : Pourquoi jouer à PDC
4. **M05 - Getting Started** : Comment commencer
5. **M06 - Footer** : Pied de page

## Champs masqués

Les champs suivants sont automatiquement masqués quand vous utilisez les modules :
- Éditeur de contenu classique
- Image à la une
- Extrait
- Commentaires
- Auteur
- Attributs de page

Cela simplifie l'interface d'édition et met l'accent sur les modules.

## Notes techniques

### Templates Twig

Les modules sont rendus par les templates dans `views/modules/` :
- `m01_block_and_title.twig`
- `m02_hero.twig`
- `m03_features_grid.twig`
- `m04_stats_bar.twig`
- `m05_getting_started.twig`
- `m06_footer.twig`

**Important** : Les noms des fichiers Twig doivent correspondre EXACTEMENT aux noms des layouts dans le flexible content (ex: `m02_hero` → `m02_hero.twig`).

### Boucle des modules

La boucle qui affiche les modules se trouve dans `views/modules/modules-loop.twig`.

Elle parcourt automatiquement le tableau `modules` et inclut le bon template selon `acf_fc_layout`.

### Accès aux champs dans les templates

Grâce au système de clone "seamless", les champs sont directement accessibles via `module.field_name`.

**Exemple pour M02 - Hero** :
```twig
{{ module.badge }}
{{ module.title_line_1 }}
{{ module.title_line_2 }}
{{ module.subtitle }}
{{ module.button_1_text }}
```

**Important** : Pas de préfixe comme `module.m02_hero.badge`, juste `module.badge` directement.

### Personnalisation

Pour personnaliser un module :
1. Éditez le fichier `.twig` correspondant
2. Relancez `npm run build` si vous ajoutez de nouvelles classes Tailwind
3. Rafraîchissez la page

## Documentation complète

Pour plus de détails sur la structure des champs ACF, consultez :
**ACF-MODULES-DOCUMENTATION.md**

## Ressources

- **ACF Documentation** : https://www.advancedcustomfields.com/resources/
- **Timber Documentation** : https://timber.github.io/docs/
- **Tailwind CSS** : https://tailwindcss.com/docs

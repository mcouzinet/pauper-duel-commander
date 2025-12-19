# Traductions du thème PDC

Ce dossier contient les fichiers de traduction pour le thème Pauper Duel Commander.

## Structure des fichiers

Le thème utilise **deux domaines de traduction distincts** :

### 1. Frontend (`pdc-theme`)
Textes visibles par les visiteurs du site :
- `pdc-theme.pot` : Template des chaînes frontend
- `pdc-theme-{locale}.po` : Traductions frontend (ex: `pdc-theme-fr_FR.po`, `pdc-theme-en_US.po`)
- `pdc-theme-{locale}.mo` : Fichiers compilés frontend

### 2. Admin (`pdc-theme-admin`)
Textes de l'interface d'administration WordPress :
- `pdc-theme-admin.pot` : Template des chaînes admin
- `pdc-theme-admin-{locale}.po` : Traductions admin (ex: `pdc-theme-admin-en_US.po`)
- `pdc-theme-admin-{locale}.mo` : Fichiers compilés admin

## Pourquoi deux domaines ?

Cette séparation permet de :
- ✅ **Mieux organiser** les traductions (frontend vs backend)
- ✅ **Cibler** facilement ce qui doit être traduit en priorité
- ✅ **Collaborer** plus facilement (un traducteur pour le frontend, un autre pour l'admin)
- ✅ **Maintenir** plus facilement les traductions

## Contenu des traductions

### Frontend (`pdc-theme`)
- Textes du site visible par les visiteurs
- Footer, header, messages d'erreur
- Pages de decklist, statistiques
- Boutons et labels d'interface

**Exemples** :
- "Courbe de Mana" → "Mana Curve"
- "Exporter la decklist" → "Export decklist"
- "par" → "by"
- "le" → "on"

### Admin (`pdc-theme-admin`)
- Labels des menus WordPress
- Noms des Custom Post Types et Taxonomies
- Messages de l'interface d'administration
- Widgets et options

**Exemples** :
- "Decklists" → "Decklists"
- "Ajouter une decklist" → "Add new decklist"
- "Auteurs" → "Authors"
- "Archétypes" → "Archetypes"

## Comment ajouter une nouvelle langue

### Méthode 1 : Via Loco Translate (recommandé)

1. Installer et activer **Loco Translate**
2. Aller dans **Loco Translate > Themes > PDC Theme**
3. Vous verrez **deux groupes de traductions** :
   - **Frontend** (pdc-theme)
   - **Admin** (pdc-theme-admin)
4. Créer une nouvelle langue pour chaque groupe
5. Traduire les chaînes

### Méthode 2 : Manuellement avec Poedit

#### Pour le Frontend :
1. Ouvrir `pdc-theme.pot` avec Poedit
2. Créer une nouvelle traduction
3. Sauvegarder comme `pdc-theme-en_US.po` (pour l'anglais)
4. Compiler (le .mo sera créé automatiquement)

#### Pour l'Admin :
1. Ouvrir `pdc-theme-admin.pot` avec Poedit
2. Créer une nouvelle traduction
3. Sauvegarder comme `pdc-theme-admin-en_US.po`
4. Compiler (le .mo sera créé automatiquement)

### Méthode 3 : En ligne de commande

```bash
# Frontend
wp i18n make-po languages/pdc-theme.pot languages/pdc-theme-fr_FR.po
msgfmt languages/pdc-theme-fr_FR.po -o languages/pdc-theme-fr_FR.mo

# Admin
wp i18n make-po languages/pdc-theme-admin.pot languages/pdc-theme-admin-fr_FR.po
msgfmt languages/pdc-theme-admin-fr_FR.po -o languages/pdc-theme-admin-fr_FR.mo
```

## Exemple de traductions FR → EN

### Frontend
| Français | Anglais | Contexte |
|----------|---------|----------|
| Courbe de Mana | Mana Curve | Titre du graphique |
| Exporter la decklist | Export decklist | Bouton d'export |
| par | by | "par Auteur" |
| le | on | "le 01/01/2025" |
| Un format Commander accessible... | An accessible and competitive... | Slogan footer |

### Admin
| Français | Anglais | Contexte |
|----------|---------|----------|
| Decklists | Decklists | Menu admin |
| Ajouter une decklist | Add new decklist | Bouton admin |
| Auteurs | Authors | Taxonomie |
| Archétypes | Archetypes | Taxonomie |
| Couleurs | Colors | Taxonomie |

## Vérification

Après avoir traduit et sauvegardé :

1. **Frontend** : Visitez le site en changeant de langue avec Polylang
2. **Admin** : Changez la langue de votre compte utilisateur dans **Profil > Langue**

Les textes frontend et admin devraient être traduits séparément!

## Plugins recommandés

- **Loco Translate** : Gère automatiquement les deux domaines de traduction
- **Polylang** : Change la langue du frontend
- **WPML** : Alternative complète avec support multi-domaines
- **TranslatePress** : Traduction visuelle

## Régénérer les fichiers POT

```bash
# Régénérer tous les POT
wp i18n make-pot . languages/pdc-theme.pot --domain=pdc-theme
wp i18n make-pot . languages/pdc-theme-admin.pot --domain=pdc-theme-admin
```

## Notes importantes

- Les fichiers .mo sont les seuls utilisés par WordPress en production
- Les fichiers .po sont les sources éditables
- Les fichiers .pot sont les templates de référence
- Ne jamais éditer directement les fichiers .mo
- Toujours recompiler après modification d'un fichier .po
- **Les deux domaines doivent être traduits** pour une traduction complète

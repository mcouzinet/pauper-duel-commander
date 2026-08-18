# Pauper Duel Commander

Le site du format **Pauper Duel Commander** : du Commander en 1 contre 1, joué
avec des cartes communes.

**→ [pauperduelcommander.fr](https://pauperduelcommander.fr)**

On y trouve les règles du format, la ban list et l'historique de ses annonces,
les tournois et leurs résultats, une collection de decklists, la méta des
généraux joués, et un validateur qui dit si un deck est légal. Le tout en
français, anglais et italien.

## Contribuer sans écrire une ligne de code

Deux formulaires alimentent le site directement — pas besoin de compte GitHub ni
de savoir s'en servir :

| | |
|---|---|
| **Partager une decklist** | [Soumettre une decklist](https://pauperduelcommander.fr/fr/soumettre-decklist/) — le formulaire vérifie que le deck est légal avant l'envoi |
| **Publier un tournoi** | [Soumettre un tournoi](https://pauperduelcommander.fr/fr/soumettre-tournoi/) — réservé aux organisateurs, avec un code d'accès à demander à l'équipe |

Chaque envoi est relu avant d'apparaître en ligne. Une erreur repérée, une idée à
proposer ? Ça se passe sur [le Discord](https://discord.gg/4MR2sSWdms).

## Pour les développeurs

Un site **statique** généré par Astro, plus une petite **API PHP** pour le peu qui
doit tourner en direct : le validateur de deck et les deux formulaires. Le contenu
vit dans des fichiers JSON versionnés, pas dans une base de données — ce qui veut
dire qu'une modification de contenu est une pull request, et qu'un merge suffit à
la publier.

Il faut **Node 24** et **PHP 8.2** — les versions utilisées par la CI.

```bash
cd site
npm install
npm run dev
```

| Commande | Rôle |
|---|---|
| `npm run dev` | Serveur de développement |
| `npm run build` | Build de production dans `site/dist/` |
| `npm run preview` | Prévisualise le build |
| `npm run check` | Vérification des types (`astro check`) |
| `npm run lint` | Idem, plus `tsc --noEmit` |
| `npm test` | Tests PHPUnit de l'API |

`npm run dev` ne sert pas le PHP. Pour essayer le validateur en local, lancer PHP
à côté, depuis `site/public/` :

```bash
php -S 127.0.0.1:8000
```

## Où lire la suite

Ce fichier est la porte d'entrée ; le détail vit ailleurs pour n'être écrit qu'une
fois.

| Fichier | Contenu |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | L'architecture en détail : structure du projet, conventions de code, et les invariants à ne pas casser |
| [`DEPLOY.md`](DEPLOY.md) | Déploiement, rollback, vérifications après mise en ligne |
| [`docs/external/README.md`](docs/external/README.md) | Ce qu'il faut poser hors du dépôt pour activer les formulaires : secrets, captcha, jeton GitHub |

## Licence

Voir [`LICENSE.md`](LICENSE.md).

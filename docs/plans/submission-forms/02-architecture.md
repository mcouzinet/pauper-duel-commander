# Architecture : Formulaires de soumission

## Fit (ce que ça touche dans l'existant)

- **PHP `site/public/api/`** — on ajoute des endpoints et des classes lib à côté
  de l'existant, en réutilisant :
  - `config.php` (constantes gardées par `if (!defined())`, helpers, autoload)
  - `RateLimiter` (anti-abus, déjà là)
  - `DeckValidator` + `ScryfallService` (validation de légalité des decklists)
  - le pattern de l'endpoint `validate-deck.php` (POST-only, `pdc_fail()`, CORS)
- **Astro `src/`** — nouvelles pages formulaire en **composant partagé + prop
  `locale`** (comme le reste), routes minces FR/EN, i18n `fr.json`/`en.json`,
  et réutilisation de `/api/validate-deck.php` pour la vérif de légalité en direct.
- **CI** — le workflow `deploy.yml` existant reste la voie de mise en ligne.
- **Aucune base de données.** Le contenu reste des fichiers JSON dans git ; une
  soumission = une PR qui ajoute ces fichiers.

## Endpoints

| Route | Verbe | Rôle |
|---|---|---|
| `/api/submit-decklist.php` | POST | Valide une decklist et ouvre une PR (1 fichier) |
| `/api/submit-tournament.php` | POST | Valide un tournoi + les decklists du top 8 et ouvre une PR (multi-fichiers) |
| `/api/validate-deck.php` | POST | **Inchangé** — réutilisé par le form decklist pour la vérif en direct |

## Data

Pas de table. Les soumissions deviennent des fichiers dans la PR, au format
**exact** des content collections (validées par zod au build) :

- **Decklist** → `site/content/decklists/<slug>.json`
  (`title, commander, partner?, date, author?, archetype?, cards`)
- **Tournoi** → `site/content/tournaments/<slug>.json`
  (`title, date, location, city, playerCount, top8[], metaList[]`)
  **+** pour chaque joueur du top 8 ayant fourni une liste :
  `site/content/decklists/<slug-joueur>.json`, avec `top8[].decklistSlug` câblé.

Le slug est **généré et assaini côté serveur** (minuscules, `[^a-z0-9-]→-`), et
le chemin est construit à partir d'un répertoire en dur + slug — jamais depuis une
entrée client (pas de traversée de chemin).

## Flow (chemin principal — decklist)

```
1. Form (Astro) — l'utilisateur saisit ; le client appelle /api/validate-deck.php
   pour un retour de légalité en direct (déjà existant).
2. Envoi → POST /api/submit-decklist.php
3. Endpoint, dans l'ordre :
   a. POST-only, CORS
   b. RateLimiter (limite SPÉCIFIQUE aux soumissions, plus stricte)
   c. taille max + champ honeypot vide
   d. Turnstile : vérif du token côté serveur (siteverify)
   e. sanitize + validation de forme des champs
   f. DeckValidator::validate() — si invalide → 422, AUCUNE PR (le déchet ne
      devient jamais une PR)
   g. SubmissionBuilder → construit le JSON canonique + slug sûr
   h. GitHubClient → crée une branche depuis main, écrit le fichier, ouvre la PR
   i. Réponse 200 « soumission reçue »
4. L'équipe relit et merge la PR sur GitHub.
5. Merge → main → deploy.yml → en ligne.
```

Tournoi : identique, avec en (b') **vérification du code d'accès organisateur**,
et en (h) un **commit multi-fichiers** (tournoi + N decklists) sur une seule
branche via l'API Git Trees, puis une PR.

## External (APIs tierces + NOMS de variables, jamais les valeurs)

- **GitHub REST API** (`api.github.com`, dépôt `mcouzinet/pauper-duel-commander`)
  pour : lire la ref de `main`, créer une branche, écrire le(s) fichier(s),
  ouvrir la PR. Auth : **fine-grained PAT** (ce dépôt seulement, droits
  `contents:write` + `pull_requests:write` — **ni workflow, ni merge**).
  Variable : `PDC_GITHUB_TOKEN`.
- **Cloudflare Turnstile** (`.../turnstile/v0/siteverify`). Variable secrète :
  `PDC_TURNSTILE_SECRET`. La *site key* publique va dans le formulaire (non secrète).
- **Scryfall** — déjà utilisé par `DeckValidator`, inchangé.

### Où vivent les secrets (point sensible)

Le déploiement ne touche que `www/`. Les secrets sont posés **une fois à la main
HORS de `www/`** (dossier parent du compte OVH), dans un fichier PHP renvoyant un
tableau, inclus par chemin absolu. Jamais commités, jamais déployés. Variables
concernées : `PDC_GITHUB_TOKEN`, `PDC_TURNSTILE_SECRET`, `PDC_ORGANIZER_CODE`.

## Décisions d'architecture à trancher

1. **Déploiement auto au merge.** Aujourd'hui `deploy.yml` est en
   `workflow_dispatch` (manuel). Pour tenir la promesse « publié
   automatiquement après validation », on **active le déclencheur `push` sur
   `main`** (tout merge → déploiement). Alternative : le relecteur lance le
   déploiement à la main après merge. → *Proposé : activer `push: [main]`.*
2. **Protection de branche `main`.** Le PAT a `contents:write`, donc en théorie
   il pourrait pousser direct sur `main`. Mitigation : **protéger `main`**
   (PR obligatoire, pas de push direct). Le token n'ouvre alors que des PR ; le
   merge reste humain. → *Proposé : activer la protection de branche.*
3. **Un endpoint par type** (`submit-decklist`, `submit-tournament`) plutôt qu'un
   endpoint générique — plus clair, chacun avec sa validation. → *Proposé : oui.*

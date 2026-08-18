# Status: Formulaires de soumission (decklists + tournois)

- Gate 1 — Product: APPROVED 2026-08-09
- Gate 2 — Architecture: APPROVED 2026-08-09
- Gate 3 — Program Design: APPROVED 2026-08-09
- Gate 4 — Slice plan: APPROVED 2026-08-09

## Slices
- [x] Slice 1 — tracer bullet : GitHubClient + endpoint minimal ouvrant une PR (tests hermétiques)
- [x] Slice 2 — decklist, chemin heureux réel (DecklistSubmission + contrôleur + vraie PR)
- [x] Slice 3 — garde-fous (Turnstile fail-closed, honeypot, quota 5/h, deck illégal -> 422 sans PR)
- [x] Slice 4 — formulaire decklist (UI) : page FR/EN, vérif en direct, succès  => decklists utilisables
- [x] Slice 5 — déploiement au merge (push:[main]) + docs/external (secrets, PAT, Turnstile)
- [x] Slice 6 — tournoi (backend) : PR multi-fichiers + code d'accès organisateur
- [x] Slice 7 — formulaire tournoi (UI)  => tournois utilisables


## Notes for a fresh session
- Approche validée par l'utilisateur : **Auto-PR**. Le formulaire poste vers un
  endpoint PHP (OVH) qui valide, vérifie un captcha, construit le JSON au format
  des content collections, et ouvre une PR GitHub ; le merge déclenche le
  déploiement (workflow existant `.github/workflows/deploy.yml`).
- Deux publics : decklists = public modéré ; tournois = organisateurs (accès
  protégé). Construire decklists d'abord, tournois ensuite.
- Défauts proposés (à confirmer en Gate 2/3) : fine-grained PAT repo-scoped
  (contents+PR write) stocké hors de `www/` ; decklists du top 8 soumises inline
  avec le tournoi (PR multi-fichiers) ; accès organisateurs par code partagé.
- Réutilise : `DeckValidator`, `RateLimiter`, `config.php`, le pipeline CI.

## Décisions figées (Gate 3)
- Turnstile **fail-closed**. Commit via **Git Trees** (1 commit multi-fichiers).
  Slug avec **suffixe court anti-collision**. Quota **5/heure/IP**.
- Liens vers les formulaires en **CTA discret** sur les pages listes
  (decklists / tournois), pas dans la nav principale.
- Endpoint mince + **contrôleur à dépendances injectables** (HTTP stubé en test).
- Prérequis MANUELS (utilisateur, hors Claude) : créer un fine-grained PAT
  (repo, contents+PR write) et une site Turnstile ; poser les secrets HORS www/ ;
  activer `push:[main]` + protection de branche `main`. Le PR live ne peut être
  prouvé qu'une fois ces secrets posés (Claude ne manipule pas les secrets).
- ⚠ Le SFTP ne pousse pas les dotfiles : `site/public/api/.htaccess` (qui
  autorise submit-decklist.php) doit être uploadé À LA MAIN vers www/api/.htaccess
  une fois, sinon l'endpoint reste en 403. Cf. docs/external/README.md.

## Décisions figées (tranches 6-7)
- **Résultats uniquement.** Une date future est refusée (422 `date_future`) : la
  page tournoi masque le bloc résultats tant que la date n'est pas passée, donc
  une telle soumission publierait une page vide de ce qui a été saisi. Les
  annonces de tournois à venir restent éditées à la main.
- **Une decklist illégale ne coule pas le tournoi.** Elle est écartée de la PR,
  sa place garde `decklistSlug: null` (l'état normal de la collection), et le
  motif est rendu à l'organisateur + listé dans le corps de la PR. L'invariant
  conservé est celui qui compte : une liste illégale ne devient jamais du contenu
  publié. Le tournoi, lui, est un fait — il ne doit pas dépendre d'une faute de
  frappe sur l'une des huit listes.
- **Slug de tournoi sans suffixe aléatoire** (contrairement aux decklists) : il
  devient l'URL publique, et une resoumission DOIT retomber sur le même fichier
  pour que la PR montre une correction plutôt qu'un doublon.
- **Code d'accès vérifié APRÈS Turnstile** : l'inverse laisserait un bot
  force-brute le code sans jamais résoudre de captcha.
- **Budget de validation** (`PDC_SUBMIT_VALIDATION_BUDGET`, 20 s) : huit decks à
  valider peuvent dépasser le max_execution_time d'un mutualisé. Au-delà, les
  listes restantes sont signalées « non vérifiées » et écartées — jamais publiées
  sans contrôle.
- **Erreurs de formulaire = codes stables**, pas de prose : le message traverse le
  réseau et est traduit dans le navigateur (`submitTournament.js.formErrors`).

## Mise en ligne
- **Decklists EN LIGNE le 2026-08-17** : secrets OVH posés (PAT + Turnstile),
  api/.htaccess mis à jour, variables GitHub PUBLIC_TURNSTILE_SITE_KEY +
  PUBLIC_SUBMISSIONS_ENABLED=true, déployé.
- Test humain fait : PR #10 (soumission decklist réelle) mergée le 2026-08-18.
  `main` est protégée (ruleset « Protect main », PR obligatoire).
- **Tournois : code écrit et vérifié, PAS encore en ligne.** Il reste à poser
  `ORGANIZER_CODE` dans `pdc-secrets.php` sur OVH (cf. docs/external). Sans lui
  l'endpoint répond 503 et le formulaire reste inutilisable — le reste du site
  n'est pas affecté. `api/.htaccess` autorise déjà `submit-tournament.php`, donc
  aucun ré-upload manuel n'est nécessaire cette fois.
- Côté GitHub, plus rien à faire : « build & test » est un required status check
  du ruleset « Protect main », sans bypass (fait en #12), et l'étape de type check
  de la CI n'est plus en `continue-on-error`. Une PR de soumission qui casse le
  build ou le typage ne peut donc plus être mergée.


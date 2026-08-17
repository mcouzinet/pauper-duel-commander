# Vertical Slices : Formulaires de soumission

Une tranche = un incrément **testable et démontrable**. On construit dans cet
ordre ; on prouve chaque tranche avant la suivante.

> **Contrainte clé** : la preuve *live* d'une PR nécessite le PAT GitHub posé sur
> OVH (secret de l'utilisateur — Claude ne le manipule pas). Chaque tranche est
> donc prouvée **localement par des tests hermétiques** (HTTP GitHub/Turnstile
> stubé) ; la démo *live* se fait par l'utilisateur une fois les secrets posés.

## Tranches — decklist d'abord, tournoi ensuite

- **Slice 1 — Tracer bullet (le tuyau GitHub).** `GitHubClient` + un
  `submit-decklist.php` minimal qui ouvre une PR ajoutant un fichier trivial
  (pas de validation, pas de Turnstile). *Prouve* : `GitHubClientTest` (forme les
  bons appels API, gère les erreurs). Démo live optionnelle : l'utilisateur pose
  un PAT et un `curl` ouvre une vraie PR. → le point le plus risqué, éprouvé en 1er.

- **Slice 2 — Decklist, chemin heureux réel.** `DecklistSubmission`
  (input → JSON canonique + slug sûr) + `DecklistSubmissionController` câblant
  `DeckValidator` et `GitHubClient`. *Prouve* : la PR contient un JSON decklist
  **valide au regard du schéma zod** de la collection ; tests
  `DecklistSubmissionTest` + happy-path du contrôleur.

- **Slice 3 — Garde-fous.** Turnstile (fail-closed), honeypot, quota soumissions
  (5/h/IP), taille max, et **deck illégal → 422 sans PR**. *Prouve* : les tests de
  rejet (turnstile KO / honeypot rempli / deck banni → aucune PR ; quota → 429).

- **Slice 4 — Formulaire decklist (UI).** `SubmitDecklistPage.astro` + routes
  FR/EN + i18n + vérif de légalité en direct (via `validate-deck.php`) + état de
  succès + CTA depuis la page decklists. *Prouve* : navigateur — remplir, vérifier,
  soumettre, voir le succès.
  → **Après cette tranche, la soumission de decklists est utilisable de bout en bout.**

- **Slice 5 — Déploiement au merge + docs setup.** Activer `push:[main]` dans
  `deploy.yml` ; `docs/external/` (création PAT + Turnstile, pose des secrets hors
  www/, protection de branche). *Prouve* : merge d'une PR de contenu → déploiement
  déclenché (vérifié par l'utilisateur).

- **Slice 6 — Tournoi (backend).** `TournamentSubmission` + contrôleur : PR
  **multi-fichiers** (tournoi + decklists du top 8, `decklistSlug` câblés) via Git
  Trees, + **code d'accès organisateur**. *Prouve* : tests + PR multi-fichiers
  cohérente.

- **Slice 7 — Formulaire tournoi (UI).** `SubmitTournamentPage.astro` : code
  d'accès, lignes top 8 ajoutables avec decklist inline, méta, routes/i18n.
  *Prouve* : navigateur.
  → **Après cette tranche, la soumission de tournois est utilisable de bout en bout.**

## Jalons de valeur
- Fin de **Slice 4** : les joueurs peuvent soumettre des decklists (public, modéré).
- Fin de **Slice 7** : les organisateurs peuvent soumettre des tournois complets.

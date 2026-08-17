# Status: Formulaires de soumission (decklists + tournois)

- Gate 1 — Product: APPROVED 2026-08-09
- Gate 2 — Architecture: APPROVED 2026-08-09
- Gate 3 — Program Design: APPROVED 2026-08-09
- Gate 4 — Slice plan: APPROVED 2026-08-09

## Slices
- [x] Slice 1 — tracer bullet : GitHubClient + endpoint minimal ouvrant une PR (tests hermétiques)
- [x] Slice 2 — decklist, chemin heureux réel (DecklistSubmission + contrôleur + vraie PR)
- [x] Slice 3 — garde-fous (Turnstile fail-closed, honeypot, quota 5/h, deck illégal -> 422 sans PR)
- [ ] Slice 4 — formulaire decklist (UI) : page FR/EN, vérif en direct, succès  => decklists utilisables
- [ ] Slice 5 — déploiement au merge (push:[main]) + docs/external (secrets, PAT, Turnstile)
- [ ] Slice 6 — tournoi (backend) : PR multi-fichiers + code d'accès organisateur
- [ ] Slice 7 — formulaire tournoi (UI)  => tournois utilisables


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


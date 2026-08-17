# Product : Formulaires de soumission

## Problème

- **Un joueur** a une decklist qu'il aimerait voir sur le site officiel du
  format. Aujourd'hui, aucun moyen : ça se partage sur Discord et ça se perd.
- **Un organisateur** a fait tourner un tournoi PDC. Pour que ses résultats, son
  top 8 et son méta apparaissent sur le site, il doit transmettre les infos à
  quelqu'un de l'équipe et attendre une saisie manuelle — plusieurs jours, quand
  ce n'est pas oublié.
- **L'équipe** ajoute tout le contenu à la main. C'est un goulot d'étranglement :
  publications lentes, irrégulières, et 4 tournois déjà en ligne sont restés sans
  résultats faute de temps de saisie.

## Success metric

**Délai de publication** : temps entre la fin d'un tournoi (ou l'envoi d'une
decklist) et sa mise en ligne.

- Aujourd'hui (saisie manuelle) : plusieurs jours, souvent jamais.
- Cible : **< 1 h** (soumission → relecture → publication automatique).
- Mesuré sur les 10 dernières publications.

Secondaire : **part du nouveau contenu arrivé par formulaire** — cible ≥ 80 % à
3 mois (le reste = saisie manuelle exceptionnelle).

## Announcement — le post d'annonce avant la fonctionnalité

> **Soumettez vos decklists et vos tournois directement sur le site.**
> Vous avez une decklist qui tourne bien en Pauper Duel Commander ? Un tournoi à
> faire remonter ? Un formulaire est désormais là pour ça. Pour une decklist, le
> validateur du site vérifie sa légalité en direct pendant que vous la saisissez.
> Chaque soumission est relue par l'équipe avant d'être publiée — puis elle
> apparaît toute seule sur le site. Fini les allers-retours Discord et les
> semaines d'attente : le format se documente désormais avec sa communauté.

## Screens

- `mockups/decklist-form.html` — **Soumettre une decklist** (public). Général,
  partenaire optionnel, decklist au format MTGO, auteur, archétype. Vérification
  de légalité en direct (réutilise le validateur) et affichage des erreurs.
- `mockups/tournament-form.html` — **Soumettre un tournoi** (organisateurs).
  Accès réservé (code). Infos du tournoi (nom, date, lieu, participants), top 8
  en lignes ajoutables, méta, et la decklist de chaque joueur du top 8.
- `mockups/submission-success.html` — **Confirmation** : « Soumission reçue,
  en attente de relecture par l'équipe. » Rappel que rien n'est publié sans
  validation.

## Hors périmètre (pour cette version)

- Pas de comptes utilisateurs ni de connexion (le format n'en a pas).
- Pas d'édition/suppression en self-service d'une soumission une fois envoyée.
- Pas de publication instantanée sans relecture humaine.

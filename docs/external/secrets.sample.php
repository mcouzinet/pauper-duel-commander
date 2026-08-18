<?php
/**
 * MODÈLE — copier en `pdc-secrets.php` et poser sur le serveur OVH, dans le
 * dossier qui CONTIENT www/ (jamais dans www/, jamais dans git).
 *
 * pdc_secret() le trouve automatiquement (chemin par défaut : un cran au-dessus
 * de www/). Si votre hébergement diffère, définissez à la place la variable
 * d'environnement PDC_SECRETS_FILE vers son chemin absolu.
 *
 * Remplacez les valeurs ci-dessous par les vraies.
 */
return array(
    // Fine-grained PAT GitHub, limité à ce dépôt, droits Contents + Pull requests (write).
    'GITHUB_TOKEN'    => 'github_pat_xxxxxxxxxxxxxxxxxxxx',

    // Clé SECRÈTE Cloudflare Turnstile (la clé de SITE, publique, va côté build).
    'TURNSTILE_SECRET' => '0x4xxxxxxxxxxxxxxxxxxxxxxxxxxx',

    // Code d'accès du formulaire tournoi, partagé aux organisateurs. Choisir une
    // valeur robuste : c'est la seule chose qui sépare le public des résultats.
    // Absent, /api/submit-tournament.php répond 503 (inerte).
    'ORGANIZER_CODE'  => 'change-me',
);

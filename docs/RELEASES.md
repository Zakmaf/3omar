# Releases

## v3.7.1 - 2026-11-27

Entrée préparée le 2026-11-27. Cette date n'est pas une preuve de publication. Le scénario fiscal 2027 reste un projet.

### Améliorations

- Les annonces d'état de l'assistant du calculateur sont restituées aux technologies d'assistance. #286
- La documentation de l'API distingue le dépassement du plafond des frais professionnels de son égalité : `fp_plafonne` vaut `false` quand les frais sont exactement égaux au plafond. Documentation seule, le contrat de l'API ne change pas. #316

### Correctifs

- Le calcul net vers brut retient le meilleur candidat valide et signale un net inatteignable au-delà du plafond technique de la recherche, au lieu de le dépasser. #315

### Sécurité

- Corrige cinq avis de sécurité Symfony : CVE-2026-133743, CVE-2026-133751, CVE-2026-133755, CVE-2026-133746 et CVE-2026-133754. Le verrou de dépendances de cette version porte `symfony/http-kernel` v8.1.13, `symfony/mailer` v8.1.12, `symfony/routing` v8.1.12 et `symfony/http-foundation` v8.1.13. #317
- Supprime Google AdSense et la route `/ads.txt`. Les variables `ADSENSE_*` ne sont plus lues. La page de confidentialité est précisée en conséquence. #300
- Le périmètre de signalement des vulnérabilités documenté inclut désormais la page de gain. #298

### Mise à jour de la stack

- Passe `laravel/framework` de 13.42.0 à 13.44.1 (PR #308).
- Passe `laravel/tinker` de 3.1.0 à 3.2.0 (PR #305).
- Met à jour axe-core vers 4.14.1 et PHPUnit vers 13.4.8, deux outils de test non embarqués (PR #310 et #304).

### Migration

Mise à jour depuis v3.7.0, stable précédente. Aucune migration de base de données ni modification du schéma de requête ou de réponse de l'API. Les cibles nettes qui exigeraient un brut supérieur au plafond sont désormais signalées comme inatteignables (#315).

- **Déploiement** : aucun déploiement automatique. Après la publication de la release GitHub `v3.7.1` et la vérification de ses tags OCI, tirer le tag exact et recréer le conteneur avec cette image selon la procédure de déploiement habituelle, puis vérifier la version annoncée par `/api/v1/health` et le digest du conteneur comparé à celui du tag sur GHCR. Une variable `APP_VERSION` passée explicitement au conteneur reste prioritaire sur la version de l'image : la retirer pour annoncer `v3.7.1`.
- **Publicité** (#300) : les variables `ADSENSE_ENABLED`, `ADSENSE_PUBLISHER_ID`, `ADSENSE_SLOT_HEADER` et `ADSENSE_SLOT_FOOTER` peuvent être retirées de l'environnement, sans autre effet. La route `/ads.txt` répond désormais 404.

Commande à utiliser seulement après la publication de la release `v3.7.1` et la vérification de ses tags :

```bash
docker pull ghcr.io/zakmaf/3omar:v3.7.1
```

---

## v3.7.0 - 2026-11-20

Préparation : entrée rédigée le 2026-11-20, date du titre ; ce n'est pas une date de publication. Une entrée dans ce fichier ou la version inscrite dans les sources ne prouvent pas une publication. Trois états se vérifient séparément : l'existence de la release GitHub `v3.7.0`, l'image construite et ses tags vérifiés par le run `docker-release.yml` de cette release, et une instance déployée, que seule la comparaison du digest de son conteneur au digest du tag sur GHCR atteste, `/api/v1/health` n'indiquant que la version déclarée, que `APP_VERSION` peut surcharger (voir `docs/DEPLOIEMENT.md`, « Tag OCI et APP_VERSION », et `CONTRIBUTING.md`, « États de publication »). Les pré-releases de développement `v3.7.0-dev.N` ne sont pas des stables. Cette version absorbe la préparation v3.6.1 (#275), fusionnée mais jamais publiée : ses changements figurent ci-dessous et n'ont fait partie d'aucune stable. Version mineure, et non correctif, parce qu'elle ajoute des fonctions fiscales.

### Nouveautés

- Le calculateur, l'API et les liens partagés acceptent un exercice fiscal (`exercice`). Sans exercice, le calcul reste en 2026, comme les liens et requêtes existants, quelle que soit la date du jour. #281
- Le formulaire propose les exercices 2026 à 2031 avec leur statut. 2026, en vigueur, et 2027, en projet, sont calculables ; 2028 à 2031 sont affichés comme projets non calculables tant que leurs frais professionnels ne sont pas définis. #281 #282
- Chaque exercice applique son propre barème d'IR, lu dans la configuration. L'exercice 2027 simule le barème d'IR propre à 2027 proposé par le PLF n° 60-26 tel que déposé (art. 247-XXXXVII-1 CGI, projet, susceptible d'amendements), distinct du barème 2026. Les barèmes 2028 à 2031 du même projet sont enregistrés comme projections, mais ces exercices restent non calculables. #281
- Hypothèse de simulation pour 2027 : seuls le barème d'IR et les frais professionnels changent. Les cotisations sociales, le SMIG et les charges de famille reprennent les paramètres de 2026, non versionnés par exercice ; cette reprise est une hypothèse, pas une règle vérifiée pour 2027. #281 #283
- L'exercice 2027 simule les frais professionnels proposés par le PLF n° 60-26 tel que déposé (art. 59-I-A modifié, susceptible d'amendements) : 45 % du salaire brut imposable sans seuil de salaire, plafonnés à 35 000 MAD par an (2 916,67 MAD par mois). Les catégories journaliste et artiste relèvent de ce régime, le projet abrogeant leur régime particulier ; le résultat et la page Fiabilité le précisent, dans les quatre langues. Les indemnités exonérées et le calcul 2026 sont inchangés. #282
- 2027 reste un projet : aucun exercice ne passe « en vigueur » avec cette version, et le suivi de la loi de finances comme toute activation juridique de 2027 après validation (loi publiée, dates d'effet, validation réglementaire) relèvent de #297. #280 a porté la préparation de 2027 en mode projet. #281 #282 #283
- Le résultat, la comparaison et la page Fiabilité affichent l'exercice appliqué, son statut, son texte source et sa référence IR. Un projet affiche en plus la version et la date du texte déposé (PLF n° 60-26, 23/10/2026) et une réserve : ce n'est ni une loi promulguée ni un texte consolidé. #281
- Chaque résultat de l'API expose `exercice` et `reference_fiscale`, provenance du profil appliqué, sans barème ni frais professionnels. #281
- Nouvelle page de gain fiscal, ouverte par le lien « Mon gain fiscal en janvier 2027 » de la page résultat : la même saisie est calculée en 2026 et sous le projet 2027 à brut constant, avec le gain mensuel sur le net, une projection annuelle indicative sur douze mois identiques et les écarts d'IR et de frais professionnels. La page porte le badge « Projet / estimation », la source et la date du texte déposé ; ce n'est pas une prévision de paie réelle. #283
- La page de gain décompose le gain en deux parts calculées dans un ordre fixe, barème d'IR 2027 proposé puis frais professionnels 2027 proposés, dont la somme vaut le gain au centime, sans double comptage. #283
- Pour une simulation net vers brut faite en 2026, la page de gain détermine une seule fois le brut qui atteint le net cible en 2026, puis le fige pour les deux exercices. Un net cible saisi en 2027, ou non atteint, est refusé avec un message traduit et un lien vers le formulaire prérempli. #283
- Le lien de la page de gain rejoue la même comparaison sans stockage serveur ; la page est disponible en FR, EN, AR et ES et dispose d'une mise en page d'impression. #283

### Améliorations

- Le détail des frais professionnels du bulletin lit le seuil du profil d'exercice appliqué, et l'aide du formulaire indique que son plafond est un paramètre de l'exercice 2026. #281
- Une comparaison entre une simulation sans exercice et la même simulation en 2026 explicite n'annonce plus d'écart. #281
- Les catégories professionnelles du formulaire et de la comparaison ne citent plus de taux : le taux appliqué dépend de l'exercice et se lit dans le résultat. #282
- Le schéma OpenAPI documente `plafond_fp` et `fp_plafonne`, déjà renvoyés par l'API. #282
- Le schéma OpenAPI déclare `nullable` les champs de requête pour lesquels l'API acceptait déjà `null`, et la documentation de l'API précise l'effet de l'omission ou de `null` : valeur par défaut, sauf pour les cinq champs patronaux où `null` signifie « inconnu » et rend le coût employeur partiel. La validation et les calculs ne changent pas. #284
- L'image publiée annonce la version de sa release, lue dans le tag (pied de page et `/api/v1/health`), et le build refuse toute version hors des formats `vX.Y.Z` et `vX.Y.Z-dev.N`. #253
- Des pré-releases de développement `vX.Y.Z-dev.N` peuvent être publiées pour tester `main` avant une stable : leur image porte le tag exact et l'étiquette `dev`, jamais `latest`, `vX` ni `vX.Y`. #253
- La politique de sécurité (`SECURITY.md`) ne prend en charge que la dernière release stable, exclut les pré-releases `-dev.N` et cite dans son périmètre les quatre routes de calcul et de comparaison. #266
- Retire seize clés de traduction inutilisées des quatre langues. #256
- Retire de la mise en page des règles CSS qui ne s'appliquaient à aucun élément. #257
- Supprime `docker-compose.v1.1.yml`, ancienne pile de développement V1.1 utilisée par aucun script ni document. #258

### Correctifs

- Après une erreur de validation ou un changement de langue, le calculateur revient à la page de l'onglet d'origine avec la saisie conservée, même si un autre onglet de la même session a ouvert une autre page entre-temps. Un changement de langue sur une page de résultat ouvre une adresse rejouable portant la même saisie ou la même comparaison. #295
- Le libellé de tranche d'IR du bulletin détaillé a la même forme dans les quatre langues, sans tiret cadratin : le taux, puis la référence légale entre parenthèses (« Tranche ...% (Art. 73 CGI) »). #256 #261
- Sur petit écran, les tableaux de la comparaison qui défilent horizontalement sont atteignables au clavier et défilent aux flèches, comme ceux du résultat. #282
- Dans le tableau des indicateurs de la comparaison, les écarts nuls et la note sur les montants sont plus contrastés, pour rester lisibles sur fond clair (contraste WCAG 4,5:1). #282
- L'image d'une pré-release est publiée sous son tag exact, préfixe `v` compris (`vX.Y.Z-dev.N`). `v3.7.0-dev.1` avait été publiée sous `3.7.0-dev.1`, sans `v` ; cette image historique n'est ni renommée ni supprimée. #288

### Sécurité

- Redirige en `301` vers `https://` (même chemin, même chaîne de requête) toute visite reçue en HTTP simple derrière le proxy de confiance (Cloudflare, Traefik), c'est-à-dire avec `X-Forwarded-Proto: http` venant d'une plage privée. La page et les cookies de session étaient jusqu'ici servis en clair. Un client public ne peut toujours rien imposer par cet en-tête, et un accès HTTP direct sans cet en-tête (`HEALTHCHECK`, `http://localhost`) n'est pas redirigé. La CI vérifie la redirection sur l'image release. #264
- Les pages du calculateur (formulaire, résultat, comparaison, page de gain), qui portent des montants de salaire, ne chargent plus aucun script, emplacement ni initialisation publicitaire, même avec la publicité activée en production ; les pages éditoriales la conservent. #283
- Les pages du calculateur, leurs redirections et le changement de langue (`/lang/...`) répondent avec `Referrer-Policy: no-referrer` : la page ouverte ensuite ne reçoit plus l'adresse de la simulation ni ses montants. Les autres pages gardent `strict-origin-when-cross-origin`. L'en-tête est envoyé une seule fois, et la CI le vérifie sur l'image release. #283 #295
- La page de gain demande aux moteurs de recherche de ne pas l'indexer ni suivre ses liens (`noindex, nofollow`). #283
- Les liens vers les sources officielles du bloc de provenance (résultat, comparaison, page de gain, page Fiabilité) s'ouvrent sans transmettre de référent (`noopener noreferrer`). #281 #283
- La cible de retour d'un changement de langue ou d'une erreur de validation est revalidée sur une liste de pages internes : une cible externe, un schéma dangereux ou une page non rejouable est refusé. #295

### Mise à jour de la stack

- Passe `laravel/framework` de 13.39.0 à 13.40.0. #270
- Passe `laravel/tinker` de 3.0.2 à 3.1.0. #291
- Passe `laravel/pint` de 1.32.1 à 1.32.3, outil de développement non embarqué dans l'image de production. #292
- Passe `docker/login-action` de 4.7.0 à 4.8.0 dans le workflow de publication. Rien n'est embarqué dans l'image. #269
- Après chaque publication, le workflow relit le registre : chaque tag publié doit porter le digest poussé, et chaque tag à préserver garder le sien. Un registre illisible fait échouer le run sans être pris pour un tag absent. La CI rejoue la génération des tags et cette vérification contre des registres jetables, jamais contre GHCR. #288
- La CI s'exécute aussi chaque jour sur `main`, pour signaler un avis de sécurité publié après la dernière fusion. #265
- La CI publie les captures de preuve du parcours d'exercice fiscal, de la page de gain et des retours entre onglets dans un artefact `preuves-exercice` conservé 7 jours. #281 #283 #295
- Couvre par des tests le cas « net inatteignable » du calcul net vers brut, dans le moteur et dans l'API. #240
- Couvre par des tests la tranche d'IR retenue à chaque seuil annuel du barème. #272
- Couvre par des tests le plafond de la déduction pour charges de famille et l'IR net nul quand cette déduction dépasse l'IR brut. #273
- Couvre par des tests la conservation, dans les liens de simulation, des cinq indicateurs de part employeur inconnue. #274

### Migration

Mise à jour depuis v3.6.0, stable précédente. La préparation v3.6.1 n'ayant jamais été publiée, il n'y a aucune étape intermédiaire : les points ci-dessous s'appliquent tous en passant de v3.6.0 à v3.7.0.

- **Proxy et HTTPS** (#264) : aucune action de configuration avec un proxy qui transmet le schéma d'origine. Après la mise à jour, une visite en `http://` derrière Cloudflare ou Traefik est redirigée vers `https://`. Un proxy qui enverrait `X-Forwarded-Proto: http` pour une visite reçue en HTTPS ferait boucler la redirection : il doit transmettre le schéma d'origine (voir `docs/DEPLOIEMENT.md`, section « Adresse réelle et HTTPS »). Si Traefik est placé derrière un autre proxy, `forwardedHeaders.trustedIPs` ne doit lister que ce proxy amont réel, pour que Traefik conserve son `X-Forwarded-Proto`. Les clients de l'API doivent appeler directement l'URL en `https://` : certains clients rejouent en GET une requête POST redirigée en `301`.
- **Version annoncée** (#253) : une variable `APP_VERSION` passée au démarrage du conteneur reste prioritaire sur la version de l'image. La retirer pour que le pied de page et `/api/v1/health` annoncent `v3.7.0`.
- **Clients de l'API** (#281 #282 #284) : les requêtes sans exercice sont calculées en 2026 comme avant, et les nouveaux champs `exercice` et `reference_fiscale` peuvent être ignorés. Une requête qui envoie `exercice` reçoit une erreur `422` sur ce seul champ pour un exercice inconnu, non entier ou non calculable. Un calcul en 2027 renvoie la nouvelle valeur `commun_unique` dans `regime_fp` : un client qui énumère les régimes doit l'accepter. Les clients générés depuis le schéma OpenAPI acceptent désormais `null` sur les champs où le serveur l'acceptait déjà ; sur les cinq champs patronaux, `null` signifie « inconnu » et diffère de l'omission.
- **Statut juridique** (#281 #282 #283) : le statut « en vigueur » de 2026 reprend la référence historique du simulateur et ne constitue pas une nouvelle validation juridique. 2027 reste un projet (PLF n° 60-26 tel que déposé), sans activation juridique ; le suivi de la loi de finances et toute activation après validation relèvent de #297. Son barème d'IR et ses frais professionnels sont ceux du texte déposé ; ses cotisations sociales, son SMIG et ses charges de famille reprennent par hypothèse ceux de 2026.
- **Confidentialité et publicité** (#283 #295) : avec AdSense activé, les emplacements publicitaires disparaissent des pages du calculateur, sans réglage possible ; les pages éditoriales les conservent. Un proxy placé devant l'image ne doit ni réécrire ni ajouter `Referrer-Policy`.
- **Tags d'image** (#288) : l'image se tire par son tag exact avec le préfixe `v` (`v3.7.0`) ; depuis #288, les formes sans `v` ne sont plus publiées, l'exception observée étant `3.7.0-dev.1` (voir Correctifs). Pour `latest`, `v3` et la nouvelle mineure `v3.7`, comparer leurs digests à celui de `v3.7.0` après la publication et avant tout usage, plutôt que supposer le déplacement d'un tag mobile (voir `docs/DEPLOIEMENT.md`, section « Tags publiés par mode de release »).

Commande à utiliser seulement après la publication de la release `v3.7.0` et la vérification de ses tags :

```bash
docker pull ghcr.io/zakmaf/3omar:v3.7.0
```

---

## v3.6.0 - 2026-11-05

### Nouveautés

- L'API expose le régime de frais professionnels retenu sous forme de donnée, et non plus de phrase : `regime_fp` (`commun_bas`, `commun_haut`, `journaliste` ou `artiste`). Le taux `taux_fp`, déjà renvoyé, est désormais documenté dans la spécification OpenAPI. Chaque client construit le libellé dans sa langue. #241

### Améliorations

- Les liens du sélecteur de langue portent un nom accessible qui contient le texte affiché (« FR Français » au lieu de « Français »), comme le demande WCAG 2.5.3. #248

### Correctifs

- Le détail des frais professionnels du bulletin s'affiche dans la langue choisie, avec le taux et le seuil lus dans la configuration. Il restait en français, avec un tiret cadratin, dans les quatre langues et dans l'API. #241
- Traduit les avertissements du calcul dans les quatre langues et retire une référence interne du message AMO. Les taux s'affichent avec une virgule (`2,26%`) et le salaire sous le SMIG est formaté (`3 000,00`). #207
- Traduit les badges de références légales du bulletin détaillé (« Order No. 1314-25 », « Law No. 65-00 », « القرار رقم 1314-25 »), numéros protégés du réordonnancement en arabe. #215
- Traduit les résumés d'étapes du calculateur, l'aperçu et les tranches d'ancienneté et l'aide des charges de famille. #214
- Traduit l'exemple de plafond de l'épargne retraite et l'unité du SMIG mensuel de la page documentation. #216

### Sécurité

- Passe `symfony/http-foundation`, `symfony/http-kernel` et `symfony/routing` de 7.4.22 à 7.4.23 : six avis, dont CVE-2026-118514 et CVE-2026-118516, sur le contournement de règles d'accès, les raccourcis de proxys de confiance, la génération d'URL et les en-têtes transférés. Exposition limitée : l'image de production ne fait confiance à aucun proxy. #244

### Mise à jour de la stack

- Passe `laravel/framework` de 13.37.0 à 13.39.0.
- Passe les outils de test PHPUnit de 11.5.55 à 13.4.5, `@playwright/test` de 1.61.1 à 1.64.1, `axe-core` et `@axe-core/playwright` en 4.14.0. Aucun n'est embarqué dans l'image de production.
- La CI teste uniquement la version de PHP livrée dans l'image (8.5), échoue sur toute dépréciation PHPUnit, exécute les 55 contrats navigateur Playwright et axe sur chaque PR et refuse les tirets cadratins et les émojis dans les lignes modifiées. Un check agrégé `gate` regroupe tous les jobs. #229 #220 #221 #226
- Couvre par des tests le montant des heures supplémentaires et ses quatre majorations, les bornes des tranches d'ancienneté, les frais professionnels du statut artiste et la frontière du seuil de 6 500 MAD : 375 tests au total. #237 #239 #238
- Les règles de contribution et des agents suivent le standard commun du projet : `AGENTS.md` comme fichier de référence et modèle de PR unique. #231

### Migration

Aucune action de configuration requise pour l'image. Pour les clients de l'API :

- les `avertissements` de la réponse sont désormais en anglais, comme les messages d'erreur ; #207
- le champ non documenté `desc_fp` (phrase en français) disparaît du résultat : utiliser `regime_fp`, `taux_fp` et `fp_plafonne`. #241

Une installation hors Docker demande désormais PHP 8.5, comme les images. #229

```bash
docker pull ghcr.io/zakmaf/3omar:v3.6.0
```

---

## v3.5.0 - 2026-10-30

### Nouveautés

- Page résultat refaite pour la lisibilité : un seul ordre de lecture à toutes les largeurs (montants clés, diagnostic, du brut au net, répartition, tableaux détaillés), et des tableaux qui restent lisibles sur mobile, en tablette portrait et en zoom 200 %. #122
- Le passage du brut au net tient en une seule suite d'étapes qui aboutit au net affiché, indemnités exonérées et retenues comprises. Les étapes pouvaient auparavant ne pas retomber sur le net (13 154,50 - 1 355,36 - 804,74 = 10 994,40 pour un net de 11 794,40). Aucun calcul ne change. #122

### Améliorations

- Les erreurs de saisie s'affichent en clair dans les quatre langues (« Le champ ... ne peut pas dépasser ... ») au lieu de clés techniques comme `validation.max.numeric`. L'API répond toujours en anglais et nomme le champ par sa clé JSON. #187
- Un avertissement signale désormais un salaire net négatif sur la page résultat et dans la réponse de l'API. #167
- La spécification OpenAPI et la documentation de l'API correspondent au moteur : cinq champs ignorés retirés, sept champs acceptés ajoutés avec leurs bornes, exemples de réponse exacts. Des tests de parité empêchent toute nouvelle dérive. #168
- Contrastes conformes AA sur la page résultat et dans le pied de page, graphique sans animation quand le mouvement réduit est demandé. #122
- Les textes d'aide du calculateur sur le taux AMO et la base horaire suivent la configuration au lieu d'une valeur écrite dans les traductions. Texte affiché inchangé. #200
- La documentation du calcul décrit la CIMR telle qu'implémentée (deux taux indépendants, salarié et employeur) et liste toutes les charges du coût employeur, assurance accident du travail et RC professionnelle comprises. #201

### Correctifs

- Les taux et montants saisis sont plafonnés (100 % pour les taux, 10 000 000 MAD pour les montants) sur le formulaire, l'API et les liens de partage. Un taux CIMR de 200 % donnait jusqu'ici un net de -10 494,80 MAD sans erreur. #167
- En arabe, les montants s'affichent dans le bon ordre (« 14 254,50 » pouvait apparaître « 254,50 14 »). #122
- Les éléments ajoutés à l'en-tête des pages ne sont plus émis deux fois : un seul lien canonique sur les pages guides, Chart.js chargé une seule fois sur la page résultat, plus de CSS en double. #199

### Sécurité

- Passe `symfony/mime` de 7.4.19 à 7.4.22 (CVE-2026-45070, injection d'en-tête e-mail). Exposition faible : 3omar n'envoie aucun e-mail. #196
- Chaque en-tête de sécurité (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`) est envoyé une seule fois, y compris sur l'API et les fichiers statiques, qui n'en recevaient aucun. `X-Frame-Options` passe à `DENY`. La CI vérifie ces en-têtes sur l'image release. #185
- Ajoute l'intégrité SRI à Chart.js et Bootstrap Icons chargés depuis le CDN. #186
- Masque la version de PHP : l'en-tête `X-Powered-By` n'est plus envoyé.

### Mise à jour de la stack

- Passe `laravel/framework` de 13.29.0 à 13.37.0.
- La CI exécute les tests sur PHP 8.3 et 8.5 (301 tests), Laravel Pint, `composer audit` et le build de l'image release avec contrôle de santé du conteneur. #169

### Migration

Aucune action de configuration requise. Les clients de l'API qui envoient un taux supérieur à 100 ou un montant supérieur à 10 000 000 reçoivent désormais une erreur 422 au lieu d'un résultat incohérent.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.5.0
```

---

## v3.4.1 - 2026-10-25

### Sécurité

- Corrige deux failles critiques de PHP permettant l'exécution de code à distance sans authentification (CVE-2026-109618 et CVE-2026-109619, dites « php2shell »). L'image de v3.4.0 embarquait PHP 8.5.11, vulnérable. #180

### Mise à jour de la stack

- Passe l'image de base de PHP 8.5.11 à PHP 8.5.12, désormais épinglée en version exacte. #180

### Migration

Aucune action de configuration requise. Mettre à jour l'instance au plus vite et redémarrer le conteneur.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.4.1
```

---

## v3.4.0 - 2026-10-07

### Nouveautés

- Quatre guides thématiques : `/calcul-salaire-net-maroc`, `/cout-employeur-maroc`, `/cnss-amo-maroc` et `/prime-anciennete-maroc`. Chaque guide présente un exemple calculé par le moteur et un lien vers le simulateur prérempli avec les mêmes chiffres, en FR, EN, AR et ES. #117

### Améliorations

- Le site affiche la balise meta `google-adsense-account` dès qu'un identifiant éditeur est configuré, même annonces désactivées, ce qui permet à Google de vérifier le site avant leur activation. #176
- Une seule variable, `ADSENSE_PUBLISHER_ID`, configure désormais `ads.txt`, la balise de vérification et le script AdSense. #176

### Correctifs

- Les retenues exonérées d'IR sont désormais prélevées sur le net à payer, comme la mutuelle salariale. Elles réduisaient l'IR sans être retirées du net, qui se trouvait surévalué. Exemple pour une base de 10 000 MAD et 1 000 MAD de retenue : net corrigé de 9 137,49 à 8 137,49 MAD. #166

### Migration

`ADSENSE_CLIENT` n'est plus lue. Si AdSense est utilisé, définir `ADSENSE_PUBLISHER_ID` avec l'identifiant éditeur sans le préfixe `ca-` (par exemple `pub-1234567890123456`), sinon les annonces ne s'affichent plus. Les autres déploiements n'ont aucune action à faire.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.4.0
```

---

## v3.3.1 - 2026-10-06

### Correctifs

- Une URL de calculateur ou de comparaison portant un paramètre sous forme de tableau (par exemple `?profil[]=x`) affiche désormais le formulaire au lieu d'une erreur serveur. #152
- La bannière de profil ou de simulation restaurée n'apparaît plus que si le formulaire a réellement été prérempli, et l'alerte de lien invalide seulement si ce lien a été utilisé. #153

### Sécurité

- Les limiteurs de débit ne se contournent plus en envoyant un faux en-tête `X-Forwarded-For` : Laravel ne fait plus confiance à n'importe quel proxy, et la liste se règle par la variable `TRUSTED_PROXIES`, vide par défaut. Un client ne peut plus non plus se déclarer en HTTPS par un en-tête. #170
- Tests de non-régression ajoutés sur la taille maximale d'un lien de simulation après décompression et sur la limitation de débit partagée entre calcul et comparaison. #154

### Mise à jour de la stack

- Passe `laravel/framework` de 13.25.0 à 13.29.0. #163
- Passe `laravel/pint` de 1.30.5 à 1.32.1. #164

### Migration

Aucune action requise avec l'image de release : laisser `TRUSTED_PROXIES` vide. Pour une autre topologie (Laravel directement derrière un proxy, sans le Nginx de l'image), voir `docs/DEPLOIEMENT.md`, section « Adresse réelle et HTTPS ». Derrière un tunnel `cloudflared` passant par Traefik, vérifier que Traefik fait confiance au réseau de `cloudflared`.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.3.1
```

---

## v3.3.0 - 2026-07-26

### Nouveautés

- Profils de simulation prêts à l'emploi sur la page calculateur : SMIG, salarié standard, cadre, avec primes, avec CIMR et journaliste. Un clic préremplit le formulaire, tous les champs restent modifiables avant le calcul. #51
- Reprise et partage d'une simulation par lien : le lien reconstruit la totalité des saisies, y compris les indemnités et les heures supplémentaires, sans qu'aucune donnée ne soit enregistrée sur le serveur. #50
- Comparaison de deux scénarios de paie : écarts absolus et relatifs sur le net à payer, l'IR, les cotisations et le coût employeur, avec mise en avant du net et du coût employeur. #47
- Tableau des entrées modifiées sur la page de comparaison : les lignes qui expliquent les écarts sont mises en évidence, et les écarts portés par une entrée avancée sont signalés explicitement. #47
- Lien de comparaison partageable et rejouable : les deux scénarios voyagent dans l'URL, ce qui permet de transmettre ou de conserver une comparaison. #47

### Améliorations

- Avertissement de confidentialité affiché à côté de chaque lien de partage : les montants saisis sont inscrits dans le lien lui-même et ne doivent être transmis qu'à des personnes autorisées. #50
- Les avertissements réglementaires des deux scénarios sont regroupés sans doublon sur la page de comparaison. #47
- Un lien de simulation illisible ou périmé est signalé à l'utilisateur au lieu d'ouvrir un formulaire vide sans explication. #50
- Les profils prêts à l'emploi lisent le SMIG et le plafond d'indemnité de transport dans la configuration réglementaire, ce qui évite toute duplication des règles. #51
- 49 nouveaux tests couvrant les profils, l'encodage des liens partagés, les écarts de comparaison, le rendu de la comparaison dans les quatre langues et l'absence de libellé non traduit sur les nouveaux écrans. #47 #50 #51 #139
- Golden test de démonstration 2026 ajouté : un profil fictif exerce en un seul cas l'ancienneté, une prime imposable, une indemnité exonérée, la mutuelle pré-fiscale, la CIMR et les charges de famille, avec des montants vérifiables à la main. #139

### Correctifs

- Ancienneté des profils calée sur les débuts de tranche définis dans la configuration : une autre valeur était réaffichée à zéro par le sélecteur du formulaire, et la simulation chargée ne correspondait plus au profil annoncé. #51
- Entrée v3.2.0 absente de `docs/RELEASES.md` ajoutée.

### Sécurité

- Tout lien de simulation ou de comparaison repasse par les règles de validation du formulaire avant d'être utilisé : un lien fabriqué à la main ne peut ni injecter un champ inconnu, ni forcer une catégorie professionnelle ou un type d'indemnité hors liste. #50
- Longueur des liens et taille après décompression bornées, pour écarter les charges utiles hostiles. #50
- La comparaison est soumise à la même limitation de débit que le calcul, puisqu'elle exécute le moteur deux fois. #47
- Les logs d'accès de l'image de production ne journalisent plus la chaîne de requête ni le référent : les montants portés par un lien de simulation ou de comparaison ne peuvent plus se retrouver dans les journaux du serveur. #50

### Mise à jour de la stack

- Passe `laravel/framework` de 13.18.1 à 13.25.0. #148
- Passe `laravel/pint` de 1.29.3 à 1.30.5. #149

### Migration

Aucune action requise. Mise à jour transparente depuis v3.2.0.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.3.0
```

---

## v3.2.0 - 2026-07-08

### Nouveautés

- Option avancée de taux AMO salarié personnalisé : permet de reproduire un bulletin réel dont le taux appliqué diffère du taux légal, avec un avertissement explicite rappelant le caractère dérogatoire de cette saisie. #138

### Mise à jour de la stack

- Passe `laravel/framework` de 13.16.1 à 13.18.1. #125
- Passe `actions/cache` de la version 5 à la version 6 dans les workflows CI. #110

### Migration

Aucune action requise. Mise à jour transparente depuis v3.1.0.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.2.0
```

---

## v3.1.0 - 2026-07-06

### Nouveautés

- Documentation API enrichie avec schémas de requête et réponse, exemples complets, exemples JavaScript, boutons de copie et panneaux d'essai interactif pour les endpoints publics #118
- Appels à l'action contextuels entre calculateur, résultat et documentation pour relancer une simulation, comprendre les règles ou vérifier les limites du simulateur #119
- Page Fiabilité complétée avec le flux de données saisi, la distinction entre simulation pédagogique et bulletin officiel, le lien de sécurité et un signalement d'erreur de calcul visible sur mobile #121

### Améliorations

- Icônes Bootstrap décoratives masquées aux lecteurs d'écran dans les vues principales, avec conservation des icônes porteuses de sens via libellés accessibles #120
- Endpoint `/api/v1/health` et exemples OpenAPI alignés sur la version applicative affichée, pour éviter la dérive de version dans la documentation développeur #118
- Tests de rendu étendus pour les CTA, la documentation API, la page Fiabilité, les icônes accessibles et les quatre locales FR, EN, ES, AR #118 #119 #120 #121

### Correctifs

- Mutuelle salariale traitée comme retenue pré-fiscale dans l'assiette IR lorsque saisie, ce qui aligne le calcul sur le simulateur Excel BP av2026 pour ce cas confirmé #126
- Documentation de calcul mise à jour pour préciser le traitement fiscal de la mutuelle salariale #126

### Migration

Aucune action requise. Mise à jour transparente depuis v3.0.1.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.1.0
```

---

## v3.0.1 - 2026-07-06

### Nouveautés

- Page Fiabilité (`/fiabilite`) : présente les engagements de confidentialité, les limites du simulateur et le statut open source, avec une matrice listant chaque règle de calcul couverte (source légale, taux, plafond et niveau de confiance). #123
- Cartes personas sur la page d'accueil : quatre cartes dédiées (Salarié, RH/Paie, Développeur/Intégrateur, Employeur/Décideur) décrivent le cas d'usage et proposent un appel à l'action contextuel.
- Panneau de confiance avant la simulation : affiche en quatre cases ce que le calculateur produit (CNSS, AMO, IR, frais pro, indemnités, coût employeur), la garantie de confidentialité, les limites connues et un lien vers la page Fiabilité, avant toute saisie.
- Section Diagnostic sur la page de résultat : trois cartes de ratios - taux effectif global (cotisations + IR / brut), ratio net/brut et surcoût employeur - pour situer la simulation en un coup d'oeil.
- Points clés dynamiques sur le résultat : jusqu'à trois insights contextuels adaptés à la situation calculée (IR non prélevé ce mois-ci, plafond CNSS atteint, suggestion CIMR si taux marginal élevé, coût employeur potentiellement sous-estimé).
- Section "Et maintenant ?" sur le résultat : liens directs vers une nouvelle simulation, la documentation des règles, la page Fiabilité, l'API REST et l'impression.

### Améliorations

- Bandeau de confidentialité sur la page de résultat : rappel discret que la simulation n'est pas conservée, avec lien vers la page Fiabilité.
- Lien Fiabilité ajouté dans la barre de navigation et le pied de page, accessible depuis toutes les pages.
- Cohérence du mode sombre : tous les nouveaux éléments (page Fiabilité, personas, panneau pré-simulation, diagnostic, points clés) respectent les tokens CSS existants.
- 10 nouveaux tests de fumée couvrant la page Fiabilité, les personas, le panneau pré-simulation, les cartes de diagnostic, les points clés, les CTAs de résultat, le cas IR nul, le bouton impression et le rendu dans les quatre langues.

### Correctifs

- Numéro de version dans le pied de page corrigé : affichait V2.2.2 au lieu de v3.0.0 sur les déploiements sans variable `APP_VERSION`.
- Section V3 du README nettoyée : suppression de la mention "en cours" et du nom de branche devenus obsolètes après la publication de la release.
- Table des tags Docker dans `docs/DEPLOIEMENT.md` mise à jour de v2.2.2 vers v3.0.x.
- Entrée v3.0.0 absente de `docs/RELEASES.md` ajoutée.

### Migration

Aucune action requise. Mise à jour transparente depuis v3.0.0.

```bash
docker pull ghcr.io/zakmaf/3omar:v3.0.1
```

---

## v3.0.0 - 2026-07-03

Release initiale de la série 3.x. Notes incomplètes - voir v3.0.1 pour le détail complet des fonctionnalités livrées dans cette série.

---

## v2.2.2 - 2026-06-30

### Accessibilité

- Ajout d'une alternative tabulaire accessible au graphique de répartition du salaire brut sur la page résultat.
- Les libellés du graphique utilisent désormais les traductions existantes au lieu de chaînes codées en dur.
- Ajout des libellés de l'alternative accessible en français, anglais, espagnol et arabe.

### Vérification

- `./vendor/bin/phpunit --filter ResultPageTest`
- `./vendor/bin/phpunit`

## v1.4.0 - 2026-06-21

### Nouveautés

- Refondre la page résultat pour séparer la synthèse, l'explication pédagogique et le détail bulletin.
- Rendre le formulaire plus lisible avec des choix contraints pour l'ancienneté, les enfants à charge et la CIMR.
- Clarifier le bouton de lancement du calcul pour éviter l'ambiguïté avec les liens de navigation.

### Correctifs

- Uniformiser l'affichage des unités sur les montants du résultat.
- Regrouper les primes imposables en une seule saisie.
- Vérifier la déduction fiscale de la retraite complémentaire dans l'IR.
- Documenter les parcours de calcul avec des tests de rendu dédiés.

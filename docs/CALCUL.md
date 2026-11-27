# Règles de calcul

Ce document décrit le comportement réellement implémenté. `config/payroll.php` reste la source des paramètres chiffrés ; toute évolution réglementaire doit être validée avant modification.

## Séquence brut → net

1. Le **SBI** (salaire brut imposable) additionne salaire de base, primes imposables, ancienneté, heures supplémentaires, excédents d'indemnités dépassant leur plafond exonéré et avantages CNSS exonérés (imposables IR mais exclus de l'assiette CNSS/AMO).
2. **CNSS et AMO** sont calculées sur l'**assiette sociale** (SBI hors avantages CNSS exonérés). La **CIMR** comporte une part salarié et une part employeur, chacune selon son propre taux ; seule la part salarié est déduite du SNC.
3. Le **RNI** (revenu net imposable) déduit les cotisations sociales, la mutuelle salariale, les frais professionnels et les retenues exonérées d'IR (`retenues_exonerees_ir`) du SBI.
4. L'**IR** est calculé sur le RNI annualisé, puis diminué des charges de famille.
5. Le **net à payer** ajoute les parts d'indemnités traitées comme exonérées et retire toutes les retenues salariales (`total_retenues`) : retenues exonérées d'IR, retenues imposées à l'IR et mutuelle salariale. La mutuelle salariale et les retenues exonérées d'IR réduisent aussi l'assiette IR, sans être agrégées aux cotisations sociales CNSS/AMO/CIMR.
6. Le **coût total employeur** additionne le salaire brut total versé (indemnités comprises) et les charges patronales (CNSS, AMO, AF, TFP, mutuelle patronale, CIMR part employeur, retraite complémentaire part employeur, assurance accident du travail `SBI × assurance_at_taux`, assurance RC professionnelle `assurance_rc_pro`).

## Hypothèses explicites

- Les montants sont mensualisés sur 12 mois.
- La retraite complémentaire (bancassurance) réduit uniquement l'assiette IR simulée ; elle n'est pas retirée du net à payer.
- Les indemnités configurées sont traitées comme exonérées dans leurs plafonds. Leur éligibilité CNSS et IR réelle dépend de la nature, des justificatifs et de la situation contractuelle.
- Les déclarations d'une même indemnité sont agrégées avant application d'un plafond unique.
- Les frais professionnels utilisent le SBI et le plafond mensuel configuré.
- La mutuelle salariale est traitée comme une cotisation salariale pré-fiscale pour le calcul de l'IR, puis retirée du net à payer.
- Les retenues exonérées d'IR (`retenues_exonerees_ir`) sont des retenues réellement prélevées sur la paie et déductibles de l'IR : elles réduisent le RNI avant le barème, puis sont retirées du net à payer (décision Product owner, #165 question 3, #166). Le net baisse donc du montant saisi moins l'économie d'IR.
- Les retenues imposées à l'IR (`retenues_imposees_ir`, alias API `autres_retenues`) sont retirées du net à payer sans effet sur l'IR.
- L'avertissement SMIG compare le SMIG mensuel au **salaire de base** saisi, pas au SBI total.
- Un net à payer négatif, possible quand des retenues toutes valides dépassent la rémunération, est servi tel quel avec un avertissement traduit dans `avertissements` (#167).

## CIMR : part salarié et part employeur (V1.2)

Deux taux indépendants sont saisis, chacun librement entre 0 et 100 % (borne technique de saisie, #167). Il n'existe pas de mode de répartition : chaque part est calculée dès que son taux est positif.

| Champ | Montant | Traitement |
|-------|---------|------------|
| `cimr_taux` | `SBI × cimr_taux` | Part salarié : déduite du SNC et de l'assiette IR, retirée du net à payer |
| `cimr_taux_employeur` | `SBI × cimr_taux_employeur` | Part employeur : intégrée au `total_patronal` et au `cout_total_employeur`, sans effet sur le net salarié |

Un avertissement est émis pour chaque taux qui dépasse `config('payroll.cimr.taux_max')` (actuellement 50 %, Art. 28-III CGI). Aucun avertissement n'est émis sous un plancher : la fourchette réglementaire des taux CIMR reste à valider (#165 question 2, #15).

## Avantages CNSS exonérés (V1.2)

Les primes de scolarité, des Aïd et autres avantages configurés dans `config('payroll.avantages_cnss_exoneres')` sont :

- **Inclus** dans le SBI (soumis à l'IR).
- **Exclus** de l'assiette sociale : CNSS et AMO sont calculées sur `assiette_sociale = SBI − total_avantages_cnss_exoneres`.
- Les cotisations patronales (CNSS, AMO, AF, TFP) utilisent également cette assiette réduite.

## Retraite complémentaire — Part employeur (V1.2)

Le champ `rc_part_employeur` permet de distinguer la part employeur de la cotisation bancassurance. Ce montant est ajouté au `total_patronal` sans affecter le net salarié ni l'assiette IR.

## Taux AMO salarié personnalisé (option avancée, V3.2)

Par défaut, `cotisation_amo = assiette_sociale × config('payroll.amo.taux')` (2,26 %, Loi n° 65-00) : ce comportement légal ne change jamais implicitement.

Deux champs optionnels permettent de surcharger explicitement ce taux, pour reproduire un bulletin de paie réel dont le taux AMO salarié observé diffère du taux légal :

| Champ | Type | Effet |
|-------|------|-------|
| `amo_taux_salarie_personnalise` | booléen, `false` par défaut | Active la dérogation. Si absent ou `false`, le taux légal s'applique et `amo_taux_salarie` est ignoré. |
| `amo_taux_salarie` | pourcentage (0–100), `0` par défaut | Taux effectivement appliqué à l'assiette sociale quand la dérogation est active (peut être `0`). |

Quand la dérogation est active, un avertissement est systématiquement ajouté à `avertissements`, rappelant le taux légal standard et invitant à vérifier la pratique de l'employeur avec son service RH.

Cette option ne modifie **que** la part salariale : la part patronale (`cout_amo_patronal`) continue d'utiliser le taux légal `config('payroll.amo.taux_patronal')`, cette dérogation étant réservée au constat d'un écart côté salarié et non à une hypothèse sur la pratique de l'employeur.

Origine : écart constaté entre 3omar et un bulletin de paie réel (AMO salariale nulle) — voir issue #128, dont la question réglementaire de fond reste ouverte, et issue #137 pour le suivi d'implémentation de cette option.

## Solver net → brut (V1.1)

### Principe

La méthode `PayrollCalculatorService::resoudreDepuisNet()` réutilise `calculer()` sans dupliquer les formules. Elle résout par **recherche dichotomique** la valeur de `salaire_base` qui produit le `net_cible` demandé, toutes autres entrées fixes.

### Algorithme

1. **Borne basse** : `salaire_base = 0`.
2. **Borne haute** : `min(max(net_cible × 2, SMIG mensuel, 1 000), plafond technique)`. Si le net calculé est encore inférieur au cible, on double la borne haute, chaque doublement étant ramené au plafond technique, jusqu'à l'encadrer ou atteindre ce plafond. Le plafond technique est la borne de saisie de `salaire_base` (`PayrollValidation::MONTANT_MAX`, #167) : aucune borne explorée ni base rendue ne la dépasse, de sorte que la base retournée reste acceptée par une simulation brut vers net (#315). Si le net au plafond reste inférieur au cible, la résolution s'arrête sans convergence (`converge = false`), avec l'avertissement de cible inatteignable et le meilleur candidat exploré dans la plage.
3. **Bisection** : à chaque itération, candidat = `(borneBasse + borneHaute) / 2`. On rétrécit l'intervalle selon le signe de l'écart.
4. **Arrêt** : `écart ≤ 0,01 MAD` ou 80 itérations maximum.
5. **Résultat** : le candidat ayant produit le plus faible écart est retourné, enrichi de `resolution_net`.

### Résultat enrichi

Le tableau retourné est celui de `calculer()`, avec `mode = 'net_to_gross'` et le sous-tableau `resolution_net` :

| Clé | Description |
|-----|-------------|
| `net_cible` | Net à payer demandé (arrondi 2 décimales) |
| `net_obtenu` | Net à payer du résultat retenu |
| `ecart` | `abs(net_obtenu − net_cible)` en MAD |
| `iterations` | Nombre d'itérations de bisection |
| `precision` | Précision cible : `0.01` MAD |
| `borne_basse` / `borne_haute` | Bornes finales de l'intervalle |
| `converge` | `true` si `ecart ≤ precision` |

### Cas limites

| Situation | Comportement |
|-----------|-------------|
| `net_cible ≤ 0` | `InvalidArgumentException` |
| Net déjà dépassé avec `salaire_base = 0` (indemnités fixes importantes) | Retourne `salaire_base = 0` + avertissement |
| Net impossible à atteindre (plafond technique) | Retourne le meilleur candidat + avertissement |
| Non-convergence en 80 itérations | Retourne le meilleur candidat, `converge = false` + avertissement |

### Hypothèses du solver

- La variable résolue est toujours `salaire_base`. Primes, indemnités, heures supplémentaires et retenues fixes restent constants.
- Le net ciblé est le **net à payer** (`salaire_net` dans le moteur), pas le net comptable.
- La relation brut/net est monotone dans les cas courants. Des retenues post-fiscales fixes peuvent créer des zones plates à faible salaire ; le solver retourne alors le candidat à écart minimal.
- Toute évolution des formules de `calculer()` est automatiquement répercutée sur le solver.
- L'exercice fiscal est vérifié une fois avant la première itération, puis chaque itération reprend la même entrée : le même exercice (ou le même repli 2026) s'applique à tous les candidats, et le résultat retenu expose cet exercice.

## Exercices fiscaux (#281)

Les paramètres qui changent d'une année à l'autre sont regroupés par exercice dans `config('payroll.exercices')`. Les barèmes et dates ne sont pas recopiés ici : la configuration reste la source.

- **Sélection.** Le champ facultatif `exercice` (API, formulaire, liens partagés) choisit le profil. Absent, `null` ou vide, il vaut `exercice_defaut`, soit **2026, de façon permanente** : les liens et requêtes historiques restent interprétés en 2026, et ce repli ne dépend jamais de la date du jour. Un exercice ultérieur, même après promulgation, se demande explicitement.
- **Profil 2026.** Statut `en_vigueur`. Ses `ir_baremes` et `frais_pro` sont des chaînes qui renvoient aux clés historiques `ir.baremes` et `frais_pro`, seule source des valeurs 2026. Les clés `year` et `regulation` restent les archives 2026.
- **Profils 2027 à 2031.** Statut `projet`, source datée du texte déposé (PLF n° 60-26, publication du 23/10/2026, URL et empreinte du document), `reference_ir` vers l'article du projet, `date_effet_projetee` annoncée par le texte et `validation_legale` à `null`. Ces dates d'effet projetées ne valent pas validation : aucun profil ne passe `en_vigueur` sans la loi publiée au Bulletin officiel, ses dates d'effet vérifiées et l'accord du propriétaire.
- **Barèmes projetés.** Chaque barème futur est décrit par ses seuils et ses taux marginaux ; la déduction de chaque tranche est dérivée par continuité au seuil précédent, `déduction(i) = déduction(i-1) + max(i-1) x (taux(i) - taux(i-1))`.
- **Calculabilité.** Un exercice n'est calculable que si son profil déclare un barème IR et des frais professionnels complets. 2027 porte les frais professionnels proposés (#282, section suivante) ; les profils 2028 à 2031 ont `frais_pro` à `null` : la validation renvoie une erreur sur `exercice` et le moteur lève une exception avant tout calcul. Aucun paramètre 2026 ne complète un profil incomplet ou partiel. Un exercice non déclaré est refusé de la même manière.
- **Provenance.** Chaque résultat expose `exercice` (entier) et `reference_fiscale` (statut, source, référence IR, période, date d'effet projetée, validation légale du profil appliqué). Ces métadonnées sont distinctes de la version applicative et ne contiennent ni barème ni frais professionnels.
- **Interface.** Le formulaire liste les exercices déclarés, dans l'ordre de la configuration, avec le statut de leur profil ; seul un exercice calculable est activable (2026 et 2027 en production), et un profil reste affiché « projet » même s'il devient calculable. Le résultat, chaque scénario d'une comparaison et la page Fiabilité portent un même bloc de provenance (exercice, statut, texte source, version et date du texte pour un projet, référence IR, réserve pour un projet). Une valeur renvoyée par la validation que le moteur ne peut pas calculer n'est jamais présentée comme un autre exercice : aucun choix n'est présélectionné et le formulaire exige un choix explicite.
- **Paramètres propres au profil.** Le détail des frais professionnels du bulletin lit le seuil du profil appliqué, ou décrit le taux unique d'un profil qui n'a pas de seuil. L'aide du formulaire, qui ne connaît pas encore l'exercice choisi, affiche le plafond du profil de repli et le présente comme paramètre de cet exercice. Les cotisations sociales, le SMIG et les charges de famille ne sont pas versionnés par exercice dans ce socle.
- **Comparaison.** L'exercice absent, `null` ou blanc vaut le repli 2026 pour la détection des écarts d'entrée : une simulation sans exercice et la même simulation en 2026 explicite ne diffèrent pas. Une valeur non résoluble reste comparée telle quelle.
- **Hors de ce socle.** Les frais professionnels des profils 2028 à 2031 ne sont pas implémentés. Le comparateur de gain 2026 / 2027 est décrit plus bas (#283). Le statut `en_vigueur` de 2026 reprend la référence historique du simulateur ; ce n'est pas une nouvelle validation juridique formelle.

## Frais professionnels proposés pour 2027 (#282)

**Source.** Projet de loi de finances n° 60-26 pour 2027, **texte déposé** (publié le 23/10/2026, PDF identifié par son empreinte dans `config/payroll.php`), art. 7-I modifiant l'art. 59-I du CGI (page « - 8 - » du PDF) et art. 7-IV-5 (application aux revenus acquis à compter du 1er janvier 2027). Ce n'est ni un texte voté ni un texte promulgué : les paramètres sont susceptibles d'amendements, le profil reste `projet` et `validation_legale` reste `null`. L'état de la procédure parlementaire n'est pas vérifié par ce document ; la confrontation au texte définitif publié au Bulletin officiel reste à faire (#280, #15).

Texte proposé, cité : « A. – 45% pour les personnes ne relevant pas de la catégorie professionnelle visée au C ci-après, sans que cette déduction puisse excéder trente-cinq mille (35 000) dirhams ; B. – (abrogé) ; C. – ... maritime. »

| Élément | 2026 (en vigueur, inchangé) | 2027 (projet) |
|---|---|---|
| Régime commun | 35 % jusqu'au seuil mensuel, 25 % au-delà | 45 %, sans seuil de salaire |
| Plafond | 35 000 MAD/an, configuré mensualisé | `plafond_annuel` 35 000 MAD, seule valeur configurée |
| Journaliste, artiste | Taux propres de `config('payroll.frais_pro')` | Régime A du projet (B abrogé), rattachement explicite |
| `regime_fp` | `commun_bas`, `commun_haut`, `journaliste`, `artiste` | `commun_unique` |

- **Plafond mensuel.** Le moteur dérive le plafond mensuel du plafond annuel canonique : `round(plafond_annuel / nb_mois, 2)`, soit 2 916,67 MAD, même méthode et même montant qu'en 2026. Sur douze mois, l'arrondi au centime donne 35 000,04 MAD, comme en 2026 ; l'écart de 0,04 MAD est une conséquence connue de la mensualisation.
- **Assiette.** Inchangée : le taux s'applique au SBI, comme en 2026. Le projet conserve pour le A la règle d'assiette hors avantages en argent ou en nature ; le simulateur n'isole pas ces avantages dans l'assiette des frais professionnels, ni en 2026 ni en 2027.
- **Catégories.** Le profil 2027 déclare explicitement `categories` : `commun`, `journaliste`, `artiste`. Le B (où figuraient en 2026 les journalistes, à 45 %, et les artistes, à 35 % selon le CGI) est abrogé par le projet : ces personnes relèvent du A. Une catégorie que le profil ne rattache pas est refusée (erreur sur `type_frais_pro` à la validation, exception du moteur), jamais calculée par repli. Le C (personnel navigant de la marine marchande et de la pêche maritime, 40 % en 2026) n'est pas modélisé : aucune catégorie de saisie n'y correspond, ni en 2026 ni en 2027.
- **Indemnités.** Inchangées en 2027 : la modification de l'art. 57-1° (indemnités de frais) ne s'applique qu'aux revenus acquis à compter du 1er janvier 2031 selon l'art. 7-IV-4 du projet, le texte en vigueur au 31/12/2026 restant applicable jusqu'au 31/12/2030. Suivi dans #158 et #15.
- **Nature.** Les frais professionnels réduisent l'assiette IR ; ils ne sont pas versés au salarié et n'apparaissent pas dans le net.
- **Net vers brut et API.** Le solveur et l'API appliquent les frais professionnels de l'exercice demandé, avec la même règle de catégorie que la validation.
- **Écart 2026 signalé, non corrigé ici.** `config('payroll.frais_pro.artiste.taux')` vaut 40 % alors que l'art. 59-I-B du CGI 2026 prévoit 35 % pour les artistes. Les valeurs 2026 ne sont pas modifiées par #282 ; l'écart relève d'une revue réglementaire distincte.

## Gain de salaire net en janvier 2027 face à 2026 (#283)

Page `GET /calculateur/gain-2027?s=<payload>` (route `calculator.gain2027`, limitée comme le calcul), ouverte depuis le lien « Mon gain fiscal en janvier 2027 » de la page résultat. Le payload est celui du lien de partage, décodé et revalidé par `SimulationCodec` ; le lien de la page rejoue la même comparaison, sans base de données. Comme tout lien de partage, l'URL expose les montants de la saisie : la page le rappelle.

- **Comparaison à brut constant.** Une seule saisie est calculée par le moteur sous l'exercice 2026 (en vigueur) et sous l'exercice 2027 (projet). Toutes les autres entrées (famille, CIMR, AMO, mutuelle, retenues, parts patronales inconnues, indemnités) sont transmises telles quelles aux deux calculs. Cotisations sociales, SMIG, charges de famille et indemnités ne sont pas versionnés par exercice : seuls le barème IR et les frais professionnels changent. Ce n'est pas une prévision de paie réelle.
- **Écarts.** Les montants sont ceux du moteur ; les écarts viennent de `SimulationComparator`. `gain = net 2027 - net 2026`, au centime ; le pourcentage est l'écart relatif du comparateur, rapporté au net 2026.
- **Décomposition, ordre fixe.** `PayrollCalculatorService::decomposerChangementExercice()` produit un troisième calcul complet, intermédiaire : barème IR 2027 et frais professionnels 2026, tout le reste inchangé. Part barème = net intermédiaire - net 2026 ; part frais professionnels = net 2027 - net intermédiaire. Chaque part est arrondie au centime et leur somme vaut le gain au centime, sans double comptage. Un autre ordre (frais professionnels d'abord) donnerait d'autres parts pour le même total : seul l'ordre affiché est calculé. Le calcul intermédiaire n'appartient à aucun exercice (`exercice` et `reference_fiscale` à `null`, `composition` indique l'origine de chaque paramètre) ; seul son net est affiché, jamais le bulletin intermédiaire.
- **IR déjà nul.** Si l'IR 2026 de la saisie est nul, la page affiche « Aucun gain d'IR calculé » plutôt qu'un gain fictif.
- **Projection annuelle indicative.** Gain mensuel x `config('payroll.ir.nb_mois')`, en supposant douze mois identiques : aucune prime ponctuelle, aucune régularisation annuelle, aucune année réelle n'est calculée (#44).
- **Saisie d'origine 2027.** Une simulation faite en 2027 est recalculée en 2026 et en 2027 à partir de la même saisie ; la page le signale (exercice source exposé, jamais écrasé en silence). Un exercice non calculable (2028 à 2031) est refusé par le codec.
- **Net cible.** Une simulation net vers brut faite en 2026 (ou sans exercice) est résolue **une seule fois**, en 2026 ; le salaire de base trouvé est ensuite figé pour les deux exercices, les autres entrées restant inchangées. Le net 2026 vaut donc la cible au centime et le net 2027 en diffère du gain. La page l'indique : « brut déterminé pour atteindre le net cible en 2026 ». Un net cible d'une simulation 2027 est **refusé explicitement** : le résoudre en 2026 changerait en silence le sens de la cible.
- **Statut juridique.** Le profil 2027 reste `projet` : la page porte le badge « Projet / estimation », la source officielle et la date du texte déposé (lues dans `config/payroll.php`) et la réserve du projet. Aucune bascule vers « loi applicable » n'a lieu ici, même si `validation_legale` venait à être renseignée : cette bascule, avec la référence au Bulletin officiel et la validation réglementaire, relève de #280.
- **Net garanti.** Pour un contrat en net garanti, le bénéficiaire de la baisse d'IR dépend du contrat et du droit applicable. L'amendement visant à l'attribuer au salarié n'est pas présenté comme adopté.
- **Projections 2028 à 2031.** Non affichées : ces profils ne sont pas calculables (frais professionnels non paramétrés).
- **Net cible non atteint.** Si la résolution 2026 n'atteint pas la cible (`resolution_net.converge = false` : cible hors de la borne de recherche du solveur, cible déjà dépassée avec un salaire de base nul par des primes ou des indemnités, ou non-convergence), la comparaison est **refusée avant tout calcul de gain**, avec le message traduit `ui.gain2027.net_target_not_reached`. Aucun gain n'est présenté comme obtenu « à net cible » alors que ce net n'est pas atteint. La page résultat d'une telle simulation, comme celle d'un net cible 2027, ne propose pas le lien vers la page de gain : elle affiche le motif et un lien vers le formulaire prérempli.
- **Refus et saisie.** Tout refus d'un payload valide (net cible 2027, net cible non atteint, catégorie non calculable en 2027) renvoie au formulaire prérempli par `?s=<même saisie>` : rien n'est à ressaisir. Un payload absent, illisible ou d'un exercice non calculable renvoie au formulaire vide avec une notice.
- **Précision du brut figé (net cible).** Le salaire de base figé est le candidat de la bissection du solveur, non arrondi : les deux exercices le reçoivent tel quel, et la page l'affiche arrondi au centime. Ressaisir ce montant arrondi en brut vers net peut donc donner un net qui diffère d'un centime du net 2026 de la page. Le modèle numérique du solveur n'est pas modifié par #283.
- **Confidentialité.** Les routes `calculator.*` (formulaire, résultat, comparaison, page de gain) portent des montants de salaire, dans la page ou dans l'URL. Le site ne charge plus aucune publicité (AdSense retiré, #300). La page de gain porte `<meta name="robots" content="noindex, nofollow">` : un lien partagé publiquement ne doit être ni indexé ni suivi. Ces routes répondent aussi avec `Referrer-Policy: no-referrer` : la page éditoriale ouverte ensuite ne reçoit pas l'URL et son payload dans `document.referrer`. Le retour après changement de langue ou erreur de validation passe par l'URL précédente gardée en session, et revient donc toujours à l'URL partagée ou au formulaire.

## Contrôles de cohérence

- Aucun taux métier ne doit être dupliqué dans les vues ou les tests.
- Toute nouvelle règle doit préciser sa source légale ou son statut d'hypothèse dans `config/payroll.php`.
- Toute modification de formule doit inclure un test de limite et mettre à jour ce document.
- Les références légales affichées sont informatives et doivent être revérifiées à chaque changement d'exercice.

## Points à valider par un spécialiste

Avant un usage opérationnel, faire confirmer :

- Le montant et le plafond applicables aux charges de famille.
- L'éligibilité CNSS et IR de chaque type d'indemnité et avantage.
- Les règles d'assiette et de déductibilité CIMR et bancassurance.
- Les assiettes exactes des contributions patronales.
- La légitimité d'une AMO salariale nulle ou variable observée sur un bulletin de paie réel (issue #128).

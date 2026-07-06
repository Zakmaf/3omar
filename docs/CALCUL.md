# Règles de calcul

Ce document décrit le comportement réellement implémenté. `config/payroll.php` reste la source des paramètres chiffrés ; toute évolution réglementaire doit être validée avant modification.

## Séquence brut → net

1. Le **SBI** (salaire brut imposable) additionne salaire de base, primes imposables, ancienneté, heures supplémentaires, excédents d'indemnités dépassant leur plafond exonéré et avantages CNSS exonérés (imposables IR mais exclus de l'assiette CNSS/AMO).
2. **CNSS et AMO** sont calculées sur l'**assiette sociale** (SBI hors avantages CNSS exonérés). La **CIMR** peut être à la charge du salarié, de l'employeur ou partagée ; seule la part salarié est déduite du SNC.
3. Le **RNI** (revenu net imposable) déduit les cotisations sociales, la mutuelle salariale, les frais professionnels et les retenues pré-fiscales du SBI.
4. L'**IR** est calculé sur le RNI annualisé, puis diminué des charges de famille.
5. Le **net à payer** ajoute les parts d'indemnités traitées comme exonérées et retire les retenues salariales post-fiscales. La mutuelle salariale est affichée dans les retenues du net et réduit aussi l'assiette IR, sans être agrégée aux cotisations sociales CNSS/AMO/CIMR.
6. Le **coût total employeur** additionne le salaire brut total versé (indemnités comprises) et les charges patronales (CNSS, AMO, AF, TFP, mutuelle patronale, CIMR part employeur, retraite complémentaire part employeur).

## Hypothèses explicites

- Les montants sont mensualisés sur 12 mois.
- La retraite complémentaire (bancassurance) réduit uniquement l'assiette IR simulée ; elle n'est pas retirée du net à payer.
- Les indemnités configurées sont traitées comme exonérées dans leurs plafonds. Leur éligibilité CNSS et IR réelle dépend de la nature, des justificatifs et de la situation contractuelle.
- Les déclarations d'une même indemnité sont agrégées avant application d'un plafond unique.
- Les frais professionnels utilisent le SBI et le plafond mensuel configuré.
- La mutuelle salariale est traitée comme une cotisation salariale pré-fiscale pour le calcul de l'IR, puis retirée du net à payer.
- L'avertissement SMIG compare le SMIG mensuel au **salaire de base** saisi, pas au SBI total.

## CIMR — Répartition employeur/salarié (V1.2)

Le taux CIMR est librement saisi (pas de plafond technique). Un avertissement signale les taux hors de la fourchette réglementaire 3–10 % (Art. 28-III CGI).

| Répartition | Part salarié | Part employeur |
|-------------|-------------|----------------|
| `salarie` | `SBI × taux` (déduit du SNC, déductible IR) | — |
| `employeur` | — | `SBI × taux` (charge patronale) |
| `partage` | `SBI × taux_salarié` | `SBI × taux_employeur` |

La part employeur est intégrée au `total_patronal` et au `cout_total_employeur` sans impacter le net salarié.

## Avantages CNSS exonérés (V1.2)

Les primes de scolarité, des Aïd et autres avantages configurés dans `config('payroll.avantages_cnss_exoneres')` sont :

- **Inclus** dans le SBI (soumis à l'IR).
- **Exclus** de l'assiette sociale : CNSS et AMO sont calculées sur `assiette_sociale = SBI − total_avantages_cnss_exoneres`.
- Les cotisations patronales (CNSS, AMO, AF, TFP) utilisent également cette assiette réduite.

## Retraite complémentaire — Part employeur (V1.2)

Le champ `rc_part_employeur` permet de distinguer la part employeur de la cotisation bancassurance. Ce montant est ajouté au `total_patronal` sans affecter le net salarié ni l'assiette IR.

## Solver net → brut (V1.1)

### Principe

La méthode `PayrollCalculatorService::resoudreDepuisNet()` réutilise `calculer()` sans dupliquer les formules. Elle résout par **recherche dichotomique** la valeur de `salaire_base` qui produit le `net_cible` demandé, toutes autres entrées fixes.

### Algorithme

1. **Borne basse** : `salaire_base = 0`.
2. **Borne haute** : `max(net_cible × 2, SMIG mensuel, 1 000)`. Si le net calculé est encore inférieur au cible, on double la borne haute jusqu'à l'encadrer ou atteindre le plafond technique.
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

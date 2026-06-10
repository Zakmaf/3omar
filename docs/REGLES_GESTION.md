# Règles de gestion

Ce document décrit le comportement réellement implémenté. `config/payroll.php` reste la source des paramètres chiffrés ; toute évolution réglementaire doit être validée avant modification.

## Séquence de calcul

1. Le SBI additionne salaire de base, primes imposables, ancienneté, heures supplémentaires et excédents d'indemnités.
2. CNSS, AMO et CIMR sont calculées sur leurs assiettes configurées.
3. Le RNI déduit les cotisations sociales et frais professionnels du SBI.
4. L'IR est annualisé, puis diminué des charges de famille.
5. Le net ajoute les parts d'indemnités traitées comme exonérées et retire les retenues salariales.
6. Le coût employeur additionne le salaire brut total versé, indemnités comprises, et les charges patronales.

## Hypothèses explicites

- Les montants sont mensualisés sur 12 mois.
- La retraite complémentaire bancassurance réduit uniquement l'assiette IR simulée ; elle n'est pas retirée du net à payer.
- Les indemnités configurées sont traitées comme exonérées dans leurs plafonds. Leur éligibilité CNSS et IR dépend de leur nature, des justificatifs et de la situation réelle.
- Les déclarations d'une même indemnité sont agrégées avant application d'un plafond unique.
- Les frais professionnels utilisent le SBI et le plafond mensuel configuré.
- L'avertissement SMIG compare le SMIG mensuel au salaire de base saisi.

## Contrôles de cohérence

- Aucun taux métier ne doit être dupliqué dans les vues.
- Toute nouvelle règle doit préciser sa source ou son statut d'hypothèse.
- Toute modification de formule doit inclure un test de limite et mettre à jour ce document.
- Les références légales affichées sont informatives et doivent être revérifiées lors de chaque changement d'exercice.

## Points à faire valider

Avant un usage opérationnel, faire confirmer par un spécialiste :

- le montant et le plafond applicables aux charges de famille ;
- l'éligibilité CNSS et IR de chaque type d'indemnité et avantage ;
- les règles d'assiette et de déductibilité CIMR et bancassurance ;
- les assiettes exactes des contributions patronales.

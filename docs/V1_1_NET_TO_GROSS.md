# V1.1 — Mode net → brut

## Objectif

Permettre de partir d'un **net à payer mensuel cible** pour reconstituer le salaire de base brut correspondant, ainsi que toutes les grandeurs du bulletin : cotisations salariales, IR, cotisations patronales et coût total employeur.

La fonctionnalité cible les équipes RH qui négocient en net mais budgétisent en brut et en coût employeur.

## Utilisation

Sur la page `/calculateur`, un toggle radio permet de choisir le mode :

- **Je connais le brut** — parcours historique ; champ `salaire_base` obligatoire.
- **Je connais le net** — V1.1 ; champ `net_cible` obligatoire (salaire de base masqué).

Toutes les options avancées restent disponibles dans les deux modes : catégorie frais professionnels, charges de famille, CIMR, retraite complémentaire, mutuelle, primes, heures supplémentaires, indemnités, autres retenues.

La page résultat affiche un bandeau de résolution en tête, puis le bulletin complet identique au mode direct.

## Algorithme

La méthode `PayrollCalculatorService::resoudreDepuisNet(array $input): array` résout le problème par **recherche dichotomique** :

1. **Initialisation** — borne basse à `0`, borne haute à `max(net_cible × 2, SMIG mensuel, 1 000)`.
2. **Extension** — si le net calculé pour la borne haute est inférieur au net cible, on double la borne haute jusqu'à l'encadrer ou atteindre le plafond technique.
3. **Bisection** — à chaque itération, on calcule le net pour le candidat `(borneBasse + borneHaute) / 2` et on rétrécit l'intervalle selon que le net obtenu est inférieur ou supérieur au cible.
4. **Critère d'arrêt** — écart ≤ `0.01 MAD` ou 80 itérations maximum.
5. **Résultat** — le candidat ayant produit le plus faible écart est retourné, enrichi des métadonnées de résolution.

La méthode **réutilise `calculer()`** sans dupliquer les formules de paie.

## Résultat enrichi

Le tableau retourné est celui de `calculer()` avec deux ajouts :

| Clé | Type | Description |
|-----|------|-------------|
| `mode` | `string` | `'net_to_gross'` |
| `resolution_net` | `array` | Métadonnées ci-dessous |

Structure de `resolution_net` :

| Clé | Description |
|-----|-------------|
| `net_cible` | Net à payer demandé (arrondi à 2 décimales) |
| `net_obtenu` | Net à payer du résultat retenu |
| `ecart` | `abs(net_obtenu - net_cible)` en MAD |
| `iterations` | Nombre d'itérations de bisection effectuées |
| `precision` | Précision cible : `0.01` MAD |
| `borne_basse` | Borne basse finale de l'intervalle |
| `borne_haute` | Borne haute finale de l'intervalle |
| `converge` | `true` si `ecart ≤ precision` |

Si la résolution n'a pas convergé (cas impossibles ou plafond technique atteint), un message d'avertissement est ajouté à `avertissements[]`.

## Cas limites

| Situation | Comportement |
|-----------|-------------|
| `net_cible ≤ 0` | `InvalidArgumentException` levée |
| Net déjà dépassé avec `salaire_base = 0` (indemnités fixes importantes) | Retourne le résultat avec `salaire_base = 0` et un avertissement explicite |
| Net cible impossible à atteindre (plafond technique) | Retourne le meilleur candidat et un avertissement |
| Convergence non atteinte en 80 itérations | Retourne le meilleur candidat avec `converge = false` et un avertissement |

## Hypothèses

- La variable résolue est toujours **`salaire_base`**. Les primes, indemnités, heures supplémentaires et retenues fixes saisies restent constantes.
- Le net ciblé est le **net à payer** (`salaire_net` dans le moteur), pas le net comptable.
- La relation brut/net est monotone dans les cas courants. Des retenues post-fiscales fixes peuvent créer des zones plates à faible salaire ; le solver retourne le candidat avec l'écart minimal.
- L'arrondi à chaque étape peut laisser un écart résiduel ≤ 0,01 MAD — comportement attendu et documenté dans le résultat.

## Tests

| Test | Vérification |
|------|-------------|
| Salaire simple | `resoudreDepuisNet` retrouve le `salaire_base` avec `ecart ≤ 0.01` |
| Charges de famille | Idem avec enfants + conjoint à charge |
| CIMR à taux décimal | Taux 3,5 % correctement pris en compte |
| Indemnités dont le plafond dépend du salaire de base | Convergence malgré l'interdépendance |
| `net_cible ≤ 0` | `InvalidArgumentException` |

## Localisation

Nouvelles clés ajoutées dans `lang/{fr,en,es,ar}/ui.php` :

- `ui.calculator.mode_label`, `mode_gross_to_net`, `mode_net_to_gross`
- `ui.calculator.net_target_label`, `net_target_help`
- `ui.result.net_to_gross_badge`, `net_to_gross_title`, `net_to_gross_intro`
- `ui.result.net_target`, `net_resolved`, `resolved_base_salary`, `resolution_gap`
- `ui.validation.net_target_required`, `net_target_positive`

Voir [`docs/I18N.md`](I18N.md) pour les conventions de traduction.

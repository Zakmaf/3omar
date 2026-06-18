# V1.1 - Reconstitution depuis le salaire net

## Objectif

Ajouter un mode de simulation partant d'un salaire net mensuel cible pour reconstituer :

- le salaire brut imposable ;
- le salaire de base brut ;
- le salaire brut total versé, indemnités comprises ;
- le coût total employeur.

La fonctionnalité vise les équipes RH au Maroc qui négocient fréquemment en net mais budgétisent en brut et en coût employeur.

## Principe fonctionnel

Le simulateur V1 conserve le mode actuel `brut -> net`. La V1.1 ajoute un second mode `net -> brut` sur le même calculateur.

Le formulaire doit permettre de saisir :

- le net à payer mensuel cible ;
- les mêmes hypothèses utiles que le calcul direct : catégorie frais professionnels, charges de famille, CIMR, retraite complémentaire, mutuelle, retenues, primes, indemnités et heures supplémentaires ;
- une option explicite de reconstitution du salaire de base quand des éléments variables existent.

Les resultats doivent afficher au minimum :

- net à payer cible ;
- net à payer obtenu après résolution ;
- écart de résolution ;
- salaire de base brut reconstitué ;
- salaire brut imposable ;
- salaire brut total verse ;
- cotisations salariales ;
- IR ;
- cotisations patronales ;
- coût total employeur.

## Choix technique

Ne pas dupliquer les formules de paie. La résolution inverse doit réutiliser `App\Services\PayrollCalculatorService::calculer()`.

Approche recommandée :

1. Ajouter une méthode dédiée, par exemple `resoudreDepuisNet(array $input): array`.
2. Considérer `salaire_base` comme la variable cherchée par défaut.
3. Pour chaque candidat de salaire de base, appeler `calculer(array_merge($input, ['salaire_base' => $candidat]))`.
4. Résoudre contre la clé métier `salaire_net`, c'est-à-dire le net à payer, pas contre `snc`.
5. Utiliser une recherche dichotomique sur un intervalle borné.
6. Retourner le résultat complet du calcul direct enrichi avec des métadonnées de résolution.

Métadonnées attendues :

- `mode` : `net_to_gross` ;
- `net_cible` ;
- `net_obtenu` ;
- `ecart` ;
- `iterations` ;
- `precision` ;
- `borne_basse` et `borne_haute` finales ;
- avertissement si la précision cible n'est pas atteinte.

## Contraintes de calcul

- Précision cible : `0.01 MAD`.
- Itérations maximales proposées : `80`.
- Borne basse initiale : `0`.
- Borne haute initiale : doubler progressivement à partir du net cible jusqu'à obtenir un net calculé supérieur ou égal au net cible.
- Si la borne haute dépasse un plafond technique raisonnable sans encadrer le net cible, retourner une erreur explicite plutôt qu'un résultat trompeur.
- Les retenues post-fiscales et la mutuelle salariale peuvent rendre certains nets impossibles pour de tres faibles bruts ; les messages doivent le signaler.
- Si le net cible est déjà dépassé avec `salaire_base = 0` à cause d'indemnités ou d'autres éléments fixes, retourner le meilleur résultat possible avec un avertissement explicite.

## Points produit à trancher

- Libellé principal : `Je connais le net` / `Je connais le brut`.
- Faut-il demander un `net à payer` ou un `net comptable` ? Pour les RH, la V1.1 doit cibler le `net à payer`, car c'est le montant généralement négocié.
- Quand des primes fixes sont saisies, le solver reconstitue le salaire de base et laisse ces primes constantes.
- Quand des indemnités exonérées sont saisies, elles sont conservées comme montants déclarés ; le salaire de base varie donc aussi les plafonds d'indemnités basées sur le salaire.
- Les résultats doivent expliquer que la reconstitution est une estimation dépendante des hypothèses saisies.

## Parcours UI

Etape 1 :

- ajouter un contrôle de mode en haut du formulaire ;
- en mode `brut -> net`, conserver le parcours actuel ;
- en mode `net -> brut`, remplacer le champ obligatoire `salaire_base` par `net_cible`, puis garder les options avancées ;
- côté serveur, rendre `salaire_base` obligatoire seulement en mode `brut -> net` et `net_cible` obligatoire seulement en mode `net -> brut` ;
- côté formulaire, conserver les valeurs avec `old('mode')`, `old('salaire_base')` et `old('net_cible')` après une erreur de validation.

Etape 2 :

- adapter la page résultat pour afficher le mode de calcul ;
- mettre en avant le net cible, le brut reconstitué et le coût employeur ;
- ajouter l'écart de résolution dans le détail.

Etape 3 :

- mettre à jour les textes dans `lang/fr/ui.php`, `lang/en/ui.php`, `lang/ar/ui.php` et `lang/es/ui.php` ;
- vérifier le rendu RTL arabe.

## Tests V1.1

Tests unitaires :

- retrouver le salaire de base à partir du net produit par un calcul direct simple ;
- retrouver le salaire de base avec charges de famille ;
- retrouver le salaire de base avec CIMR à taux décimal ;
- retrouver le salaire de base avec indemnités dont une part dépend du salaire de base ;
- signaler un net cible invalide ou impossible ;
- garantir que l'écart reste inférieur ou égal à `0.01 MAD` dans les cas nominaux.

Tests feature :

- le mode `net -> brut` est accessible depuis `/calculateur` ;
- une soumission avec `net_cible` affiche le brut reconstitué et le coût employeur ;
- les erreurs de validation utilisent les clés de traduction ;
- le mode existant `brut -> net` continue de fonctionner.

## Découpage proposé

1. Ajouter le solver dans `PayrollCalculatorService` avec tests unitaires.
2. Étendre `CalculatorController` pour valider les deux modes.
3. Adapter le formulaire Blade avec un sélecteur de mode et les clés i18n.
4. Adapter la page résultat.
5. Compléter les tests feature et lancer Pint/PHPUnit.

## Risques

- La relation brut/net est monotone dans les cas usuels, mais des retenues fixes peuvent créer des zones peu intuitives à bas salaire.
- L'arrondi à chaque étape peut provoquer plusieurs bruts valides pour le même net au centime ; retourner le candidat avec l'écart le plus faible.
- Les utilisateurs peuvent confondre `net à payer` et `net comptable` ; l'interface doit nommer explicitement le montant cible.

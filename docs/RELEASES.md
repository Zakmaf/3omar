# Releases

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

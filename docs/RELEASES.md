# Releases

## v3.0.0 - 2026-07-03

### Nouveautes

- Page Fiabilite (`/fiabilite`) : confidentialite, limites, open source, matrice de fiabilite des regles avec source, date de verification et niveau de confiance pour chaque taux.
- Positionnement par persona sur la page d'accueil : cartes dediees aux salaries, RH/paie, developpeurs/integrateurs et decideurs/employeurs, chacune avec un CTA contextuel.
- Panneau de confiance avant simulation : resume de ce que le calculateur va produire, engagement de confidentialite, lien vers les limites, avant de saisir le formulaire.
- Diagnostic actionnable sur la page de resultat : cartes de ratios (taux effectif global, ratio net/brut, surcout patronal), bandeau de confidentialite, section "Et maintenant ?" avec CTAs vers une nouvelle simulation, la page de fiabilite et la documentation.
- Points cles dynamiques sur le resultat : insights contextuels (IR non preleve, plafond CNSS atteint, suggestion CIMR, valeurs employeur manquantes).
- Action Imprimer dans les etapes suivantes : le resultat propose directement l'impression.

### Ameliorations

- Lien Fiabilite dans la navigation et le pied de page : accessible depuis chaque page.
- CTA contextuel enrichi : navigation coherente entre accueil, calculateur, resultat, documentation, API et page de fiabilite.
- Coherence dark mode : tous les nouveaux elements respectent les tokens CSS existants.

### Couverture de tests

- 9 nouveaux tests de fumee : trust page, personas, bandeau, panneau preview, verdict, CTAs, takeaways, IR nul, impression.

### Migration

```bash
docker pull ghcr.io/zakmaf/3omar:v3.0.0
```

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

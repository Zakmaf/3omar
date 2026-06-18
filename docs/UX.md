# UX & accessibilité

## Principes appliqués

- **Divulgation progressive** : les options avancées du formulaire sont masquées par défaut pour réduire la charge cognitive.
- **Hiérarchie claire** : la page résultat présente d'abord les montants clés et les actions, puis le tableau exhaustif à la demande.
- **Accessibilité** : focus clavier visible (`:focus-visible`), cibles tactiles ≥ 44 px, libellés associés aux champs, erreurs avec focus automatique.
- **Mouvement réduit** : toutes les animations respectent `prefers-reduced-motion`.
- **Source unique** : les taux et paramètres viennent de `config/payroll.php` — pas de duplication dans les vues ou le JavaScript.

## Décisions d'interface intégrées

- Le message principal promet une compréhension pédagogique, sans présenter les hypothèses comme certifiées.
- Le formulaire propose un parcours simple centré sur le salaire de base ; les compléments sont affichés à la demande.
- La page documentation distingue paramètres, hypothèses et références citées.
- Le mode net → brut (V1.1) utilise le même formulaire avec un sélecteur de mode en tête — pas de page séparée.

## Suivi recommandé

- Tester le parcours avec de vrais salariés, gestionnaires de paie et utilisateurs mobiles.
- Mesurer le taux d'abandon du formulaire sans collecter de données salariales.
- Ajouter des tests automatisés d'accessibilité (axe, Lighthouse) et de régression visuelle.
- Valider les contrastes, le zoom à 200 % et la navigation clavier dans les navigateurs ciblés.
- Mesurer l'impact du toggle brut/net sur la complétion du formulaire.

# Audit UX/UI

Audit réalisé le 10 juin 2026 sur les parcours accueil, simulation, résultat et documentation.

## Changements intégrés

- Le message principal promet une compréhension pédagogique, sans présenter les hypothèses comme certifiées.
- Le formulaire propose un parcours simple centré sur le salaire de base ; les compléments sont affichés à la demande.
- Les erreurs reçoivent le focus, les libellés sont associés aux champs et les actions tactiles visent une hauteur de 44 px.
- Le résultat commence par les montants clés et les actions ; le tableau exhaustif est disponible à la demande.
- La documentation possède une navigation rapide et distingue paramètres, hypothèses et références citées.
- Les animations respectent `prefers-reduced-motion`.

## Principes appliqués

- Divulgation progressive pour réduire la charge cognitive.
- Hiérarchie claire : décision, synthèse, puis détail.
- Focus clavier visible et cibles tactiles suffisamment grandes.
- Source unique pour les règles métier utilisées dans les aperçus côté client.

## Suivi recommandé

- Tester le parcours avec de vrais salariés, gestionnaires de paie et utilisateurs mobiles.
- Mesurer le taux d'abandon du formulaire sans collecter de données salariales.
- Ajouter des tests automatisés d'accessibilité et de régression visuelle.
- Valider les contrastes, le zoom à 200 % et la navigation clavier dans les navigateurs ciblés.

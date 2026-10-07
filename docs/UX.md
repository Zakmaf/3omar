# UX & accessibilité

## Principes appliqués

- **Divulgation progressive** : les options avancées du formulaire sont masquées par défaut pour réduire la charge cognitive.
- **Hiérarchie claire** : la page résultat présente d'abord les montants clés, puis le diagnostic, l'explication, la répartition et enfin les tableaux détaillés.
- **Accessibilité** : focus clavier visible (`:focus-visible`), cibles tactiles ≥ 44 px, libellés associés aux champs, erreurs avec focus automatique.
- **Mouvement réduit** : toutes les animations respectent `prefers-reduced-motion`.
- **Source unique** : les taux et paramètres viennent de `config/payroll.php` — pas de duplication dans les vues ou le JavaScript.

## Décisions d'interface intégrées

- Le message principal promet une compréhension pédagogique, sans présenter les hypothèses comme certifiées.
- Le formulaire propose un parcours simple centré sur le salaire de base ; les compléments sont affichés à la demande.
- La page documentation distingue paramètres, hypothèses et références citées.
- Le mode net → brut (V1.1) utilise le même formulaire avec un sélecteur de mode en tête — pas de page séparée.

## Page résultat (#122)

La page suit un ordre de lecture unique, identique à toutes les largeurs. Chaque
section porte un attribut `data-result-section` que les contrats navigateur vérifient.

| Ordre | Section | Contenu |
|---|---|---|
| 1 | `synthese` | Salaire brut, net à payer (mis en avant), coût total employeur. Bloc net vers brut si ce mode est actif. |
| 2 | `diagnostic` | Avertissements réglementaires, trois ratios (taux effectif, net / brut, surcoût employeur), points clés. |
| 3 | `explication` | Du brut au net en étapes : brut imposable, cotisations, IR, puis indemnités exonérées et retenues quand elles existent, jusqu'au net. Le coût employeur est lu à part. |
| 4 | `repartition` | Donut décoratif et tableau accessible qui sert de légende. |
| 5 | `details` | Bulletin du salarié (pleine largeur), coût employeur détaillé et barème IR. |
| 6 | `actions` | Partage, comparaison et liens secondaires. Masqué à l'impression. |

Règles de composition :

- **Rangées flexibles, pas de colonnes imbriquées.** Chaque bloc déclare une largeur de
  base cohérente avec sa densité (`--res-basis`). Une rangée incomplète s'étire au lieu de
  laisser une colonne vide à côté d'une colonne comprimée.
- **Montants insécables.** Tout montant est rendu dans un `.num` : pas de retour à la ligne
  à l'intérieur du nombre, chiffres tabulaires, isolation LTR. Sans cette isolation, un
  paragraphe arabe inverse les groupes de chiffres séparés par une espace
  (`254,50 14` au lieu de `14 254,50`). Les montants insérés dans une traduction passent
  par le même traitement.
- **L'unité peut passer à la ligne, pas le nombre.** Dans les tableaux, l'unité figure dans
  l'en-tête de colonne et n'est pas répétée sur les totaux.
- **Tableaux denses.** Sous 768 px (téléphone, tablette portrait, zoom 200 %), les tableaux
  à 4 colonnes passent en mode compact pour garder la colonne Montant visible. Chaque
  tableau reste dans une zone défilante nommée et atteignable au clavier, en dernier recours.
- **Prose bornée.** Paragraphes et listes limités à 75 caractères de large.
- **Contraste.** Les montants employeur utilisent une variante foncée de l'orange
  (`--res-warn-ink`) et le taux marginal une variante foncée du rouge (`--res-tax-ink`).
  `--ink-3` n'est pas utilisé sur cette page.
- **Mouvement réduit.** Le donut n'est pas animé quand `prefers-reduced-motion` est actif.

## Harnais de vérification navigateur

Les exigences de mise en page et d'accessibilité sont vérifiées par mesure dans un
navigateur réel, pas par relecture de capture. Playwright et axe sont épinglés par
`package-lock.json` et exécutés dans une image Playwright épinglée. Commandes et mise en
route : [DEVELOPPEMENT.md](DEVELOPPEMENT.md), section « Tests navigateur ».

### Ce qui est mesuré

Les contrats vivent dans `tests/browser/contracts/` et portent sur la géométrie calculée
par le navigateur, jamais sur la simple présence d'un titre ou d'un sélecteur :

| Contrat | Propriété vérifiée |
|---|---|
| `result-composition` | Aucune bande multi-colonnes ne laisse plus de 15 % de sa surface vide. Aucun montant ni pourcentage coupé par un retour à la ligne. |
| `result-responsive` | Absence de défilement horizontal du document, ordre de lecture des sections, largeur des tableaux denses (140 px par colonne sur grand écran) ou débordement local focusable, longueur de ligne de prose. |
| `result-accessibility` | axe sans violation `critical` ou `serious`, indicateur de focus visible à chaque arrêt de tabulation, hiérarchie de titres sans saut de niveau. |
| `result-print` | Sous `@media print` : contenu essentiel présent et ordonné, habillage d'écran masqué, aucun débordement. |
| `result-structure` | Instantané textuel du squelette de la page, comparé à la référence versionnée de `tests/browser/__structure__/`. |

La régression est **structurelle** et non pixel : un squelette comparé ligne à ligne se
relit dans un diff et désigne exactement ce qui a bougé, là où une référence PNG rejouerait
le bruit des polices et du canvas.

### Matrice de largeurs et de locales

Définie une seule fois dans `tests/browser/helpers/matrix.js` :

| Cellule | Largeur | Locale |
|---|---|---|
| Desktop | 1440x900 | fr |
| Portable | 1280x800 | en, es (libellés les plus longs) |
| Tablette | 768x1024 | fr |
| Mobile | 390x844 | fr |
| Zoom 200 % | 720x450 | fr |
| RTL mobile | 390x844 | ar |
| RTL desktop | 1440x900 | ar |

Le zoom 200 % est reproduit par la réduction équivalente du viewport CSS : un écran
1440x900 zoomé à 200 % expose exactement 720x450 pixels CSS. C'est la méthode retenue,
faute de pilotage du zoom natif par Playwright.

### Données et captures

Toutes les vérifications et toutes les captures partent d'une fixture **entièrement
synthétique** définie dans `tests/browser/helpers/simulation.js` (salaire de base
10 000 MAD, montants ronds). Aucune donnée réelle ni traçable n'entre dans le dépôt.

Les captures durables et leur inventaire sont dans
[ux/122-result-layout/](ux/122-result-layout/INVENTAIRE.md).

## Suivi recommandé

- Tester le parcours avec de vrais salariés, gestionnaires de paie et utilisateurs mobiles.
- Mesurer le taux d'abandon du formulaire sans collecter de données salariales.
- Étendre le harnais navigateur aux autres pages : il ne couvre aujourd'hui que la page résultat.
- Traiter le contraste du jeton `--ink-3` au niveau de la charte : il plafonne à 3,7:1 sur fond blanc et reste non conforme AA sur les pages autres que le résultat.
- Mesurer l'impact du toggle brut/net sur la complétion du formulaire.

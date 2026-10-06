# Captures de référence de la page résultat (#122)

Captures pleine page produites par `tests/browser/captures/result-captures.spec.js` à
partir de la fixture entièrement synthétique de `tests/browser/helpers/simulation.js`
(salaire de base 10 000 MAD, montants ronds). Aucune donnée réelle.

Ce sont des preuves relues par un humain, pas des références de comparaison pixel. La
non-régression est couverte par les contrats de `tests/browser/contracts/`.

| Fichier | Largeur CSS | Locale | Ce qu'il faut vérifier |
|---|---|---|---|
| `01-desktop-1440x900-fr.png` | 1440 | fr | Aucune zone vide à côté d'une colonne comprimée, étapes du brut au net sur une ligne. |
| `02-laptop-1280x800-en.png` | 1280 | en | Libellés anglais, tableau employeur et barème IR côte à côte. |
| `03-tablette-768x1024-fr.png` | 768 | fr | Sections empilées dans l'ordre, tableaux pleine largeur. |
| `04-mobile-390x844-fr.png` | 390 | fr | Étapes en colonne, tableaux compacts avec la colonne Montant visible. |
| `05-zoom200-720x450-fr.png` | 720 | fr | Équivalent d'un écran 1440 zoomé à 200 % : rien de tronqué, aucun défilement de page. |
| `06-rtl-mobile-390x844-ar.png` | 390 | ar | Mise en miroir, montants dans le bon ordre de chiffres. |
| `07-rtl-desktop-1440x900-ar.png` | 1440 | ar | Montants alignés en fin de ligne logique, donut et tableau inversés. |
| `08-laptop-1280x800-es.png` | 1280 | es | Libellés espagnols les plus longs. |
| `09-impression-desktop-fr.png` | 1440 | fr | Rendu `@media print` : contenu complet, actions et habillage masqués. |

Régénération : `./tests/browser/run.sh tests/browser/captures` (voir
[DEVELOPPEMENT.md](../../DEVELOPPEMENT.md), section « Tests navigateur »).

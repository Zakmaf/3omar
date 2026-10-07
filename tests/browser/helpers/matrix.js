// @ts-check

/**
 * Matrice de verification de la page resultat (issue #122).
 *
 * Chaque cellule croise une largeur reelle et une locale. Les locales longues
 * (EN, ES) et le RTL arabe sont verifies separement, aux largeurs ou leurs
 * libellees sont les plus contraignants.
 *
 * Le zoom 200 % est reproduit par la reduction equivalente du viewport CSS :
 * un ecran 1440x900 zoome a 200 % expose exactement 720x450 pixels CSS. C'est
 * la methode retenue faute de pilotage du zoom natif par Playwright.
 */
const VIEWPORT_MATRIX = [
    { name: 'desktop 1440x900 fr', viewport: { width: 1440, height: 900 }, locale: 'fr', wide: true },
    { name: 'laptop 1280x800 en', viewport: { width: 1280, height: 800 }, locale: 'en', wide: true },
    { name: 'laptop 1280x800 es', viewport: { width: 1280, height: 800 }, locale: 'es', wide: true },
    { name: 'tablette 768x1024 fr', viewport: { width: 768, height: 1024 }, locale: 'fr', wide: false },
    { name: 'mobile 390x844 fr', viewport: { width: 390, height: 844 }, locale: 'fr', wide: false },
    { name: 'zoom 200% 720x450 fr', viewport: { width: 720, height: 450 }, locale: 'fr', wide: false },
    { name: 'rtl mobile 390x844 ar', viewport: { width: 390, height: 844 }, locale: 'ar', wide: false },
    { name: 'rtl desktop 1440x900 ar', viewport: { width: 1440, height: 900 }, locale: 'ar', wide: true },
];

/**
 * Ordre de lecture impose par l'issue #122. Il doit etre identique a toutes
 * les largeurs : l'ordre est explicite, jamais herite de l'ordre des colonnes.
 */
const EXPECTED_SECTIONS = [
    'synthese',
    'diagnostic',
    'explication',
    'repartition',
    'details',
    'actions',
];

/**
 * Largeur minimale confortable d'une colonne de tableau dense sur grand ecran.
 * Un tableau a 4 colonnes doit donc disposer d'au moins 560 px, un tableau
 * cle / valeur a 2 colonnes d'au moins 280 px.
 */
const MIN_DENSE_COLUMN_WIDTH = 140;

module.exports = { VIEWPORT_MATRIX, EXPECTED_SECTIONS, MIN_DENSE_COLUMN_WIDTH };

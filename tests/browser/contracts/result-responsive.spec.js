// @ts-check
const { test, expect } = require('@playwright/test');
const { openResultPage } = require('../helpers/simulation');
const {
    measureHorizontalOverflow,
    readLandmarkOrder,
    measureDenseTables,
    findFragmentedAmounts,
    measureColumnBands,
    findOverlongProse,
} = require('../helpers/layout');
const { VIEWPORT_MATRIX, EXPECTED_SECTIONS, MIN_DENSE_COLUMN_WIDTH } = require('../helpers/matrix');

/**
 * Contrats responsive de la page resultat (issue #122).
 *
 * Chaque cellule de la matrice doit prouver deux choses : la page ne deborde
 * pas horizontalement, et les sections essentielles restent presentes, dans
 * l'ordre de lecture impose. Un titre visible ne suffit pas : on mesure la
 * geometrie reelle.
 */
for (const cell of VIEWPORT_MATRIX) {
    test.describe(`Page resultat - ${cell.name}`, () => {
        test.use({ viewport: cell.viewport });

        test('le document ne deborde pas horizontalement', async ({ page }) => {
            await openResultPage(page, { locale: cell.locale });

            const overflow = await measureHorizontalOverflow(page);

            expect(
                overflow.overflow,
                `Debordement horizontal de ${overflow.overflow}px `
                + `(scrollWidth ${overflow.scrollWidth} > clientWidth ${overflow.clientWidth}).`,
            ).toBeLessThanOrEqual(1);
        });

        test("l'ordre de lecture des sections est celui attendu", async ({ page }) => {
            await openResultPage(page, { locale: cell.locale });

            const order = await readLandmarkOrder(page);

            expect(order).toEqual(EXPECTED_SECTIONS);
        });

        test('les tableaux denses gardent une largeur lisible ou un debordement accessible', async ({ page }) => {
            await openResultPage(page, { locale: cell.locale });

            const tables = await measureDenseTables(page);
            expect(tables.length, 'aucun tableau dense detecte : la page a-t-elle bien rendu ?').toBeGreaterThan(0);

            // Un debordement local est tolere, mais il doit etre utilisable au clavier.
            const unreachable = tables.filter((t) => !t.scrollerAccessible);
            expect(
                unreachable,
                `Tableaux qui debordent sans zone defilante focusable :\n${JSON.stringify(unreachable, null, 2)}`,
            ).toEqual([]);

            if (cell.wide) {
                // Sur grand ecran, la largeur utile doit reellement profiter aux donnees.
                const starved = tables.filter((t) => t.width < t.columns * MIN_DENSE_COLUMN_WIDTH);
                expect(
                    starved,
                    `Tableaux denses comprimes sous ${MIN_DENSE_COLUMN_WIDTH}px par colonne :\n${JSON.stringify(starved, null, 2)}`,
                ).toEqual([]);
            }
        });

        test('aucun montant ni pourcentage ne se fragmente', async ({ page }) => {
            await openResultPage(page, { locale: cell.locale });

            const fragmented = await findFragmentedAmounts(page);

            expect(
                fragmented,
                `Valeurs numeriques coupees :\n${JSON.stringify(fragmented, null, 2)}`,
            ).toEqual([]);
        });

        test('aucune bande multi-colonnes ne laisse une grande zone vide', async ({ page }) => {
            await openResultPage(page, { locale: cell.locale });

            const wasteful = (await measureColumnBands(page)).filter((band) => band.deadRatio > 0.15);

            expect(
                wasteful,
                `Bandes gaspillant plus de 15% de leur surface :\n${JSON.stringify(wasteful, null, 2)}`,
            ).toEqual([]);
        });

        if (cell.wide) {
            test('la prose ne produit pas de lignes interminables', async ({ page }) => {
                await openResultPage(page, { locale: cell.locale });

                const overlong = await findOverlongProse(page);

                expect(
                    overlong,
                    `Blocs de texte trop larges :\n${JSON.stringify(overlong, null, 2)}`,
                ).toEqual([]);
            });
        }
    });
}

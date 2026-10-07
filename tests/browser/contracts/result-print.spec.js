// @ts-check
const { test, expect } = require('@playwright/test');
const { openResultPage, stabilize } = require('../helpers/simulation');
const { measureHorizontalOverflow, readLandmarkOrder, findFragmentedAmounts } = require('../helpers/layout');

/**
 * Contrat d'impression de la page resultat (issue #122).
 *
 * L'impression est un rendu de sortie a part entiere : la refonte ne doit pas
 * la degrader. On verifie sous `@media print` que le contenu utile subsiste,
 * que l'habillage d'ecran disparait, et que rien ne deborde de la page.
 */
test.describe('Page resultat - rendu imprime (desktop fr)', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    test('le contenu essentiel survit a l\'impression', async ({ page }) => {
        await openResultPage(page, { locale: 'fr' });
        await page.emulateMedia({ media: 'print' });
        await stabilize(page);

        // Les sections de contenu restent presentes et ordonnees.
        const order = await readLandmarkOrder(page);
        expect(order).toContain('synthese');
        expect(order).toContain('explication');
        expect(order).toContain('repartition');
        expect(order).toContain('details');
        expect(order.indexOf('synthese')).toBeLessThan(order.indexOf('details'));

        // L'habillage d'ecran est retire.
        const screenOnlyVisible = await page.evaluate(() => Array.from(
            document.querySelectorAll('.no-print, nav.navbar, footer'),
        )
            .filter((el) => el.getBoundingClientRect().height > 0)
            .map((el) => el.tagName.toLowerCase() + (el.className ? `.${String(el.className).split(/\s+/)[0]}` : ''))
            .slice(0, 10));

        expect(
            screenOnlyVisible,
            `Elements d'ecran encore visibles a l'impression :\n${JSON.stringify(screenOnlyVisible, null, 2)}`,
        ).toEqual([]);

        // Rien ne deborde de la largeur de page.
        const overflow = await measureHorizontalOverflow(page);
        expect(
            overflow.overflow,
            `Debordement horizontal de ${overflow.overflow}px a l'impression.`,
        ).toBeLessThanOrEqual(1);

        // Les montants restent lisibles.
        const fragmented = await findFragmentedAmounts(page);
        expect(
            fragmented,
            `Valeurs coupees a l'impression :\n${JSON.stringify(fragmented, null, 2)}`,
        ).toEqual([]);
    });
});

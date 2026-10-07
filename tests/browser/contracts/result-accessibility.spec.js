// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { openResultPage } = require('../helpers/simulation');
const { walkFocusOrder } = require('../helpers/layout');

/**
 * Contrats d'accessibilite de la page resultat (issue #122).
 *
 * Objectif : WCAG 2.2 AA, aucune violation axe d'impact `critical` ou
 * `serious`, contenu utilisable au clavier avec un focus visible.
 */

/** Cellules representatives : large, etroite, RTL, et zoom 200 %. */
const A11Y_CELLS = [
    { name: 'desktop 1440x900 fr', viewport: { width: 1440, height: 900 }, locale: 'fr' },
    { name: 'mobile 390x844 fr', viewport: { width: 390, height: 844 }, locale: 'fr' },
    { name: 'zoom 200% 720x450 fr', viewport: { width: 720, height: 450 }, locale: 'fr' },
    { name: 'rtl desktop 1440x900 ar', viewport: { width: 1440, height: 900 }, locale: 'ar' },
];

const BLOCKING_IMPACTS = ['critical', 'serious'];

for (const cell of A11Y_CELLS) {
    test.describe(`Accessibilite - ${cell.name}`, () => {
        test.use({ viewport: cell.viewport });

        test('axe ne remonte aucune violation critical ou serious', async ({ page }) => {
            await openResultPage(page, { locale: cell.locale });

            const results = await new AxeBuilder({ page })
                .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
                .analyze();

            const blocking = results.violations
                .filter((violation) => BLOCKING_IMPACTS.includes(violation.impact))
                .map((violation) => ({
                    id: violation.id,
                    impact: violation.impact,
                    help: violation.help,
                    nodeCount: violation.nodes.length,
                    nodes: violation.nodes.slice(0, 8).map((node) => node.target.join(' ')),
                }));

            expect(
                blocking,
                `Violations bloquantes :\n${JSON.stringify(blocking, null, 2)}`,
            ).toEqual([]);
        });
    });
}

test.describe('Accessibilite - clavier et titres (desktop 1440x900 fr)', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    test('chaque arret de tabulation porte un focus visible', async ({ page }) => {
        await openResultPage(page, { locale: 'fr' });

        const stops = await walkFocusOrder(page);
        expect(stops.length, 'aucun element focusable atteint').toBeGreaterThan(5);

        const invisible = stops.filter((stop) => stop.focusIndicator === false);
        expect(
            invisible,
            `Arrets sans indicateur de focus visible :\n${JSON.stringify(invisible, null, 2)}`,
        ).toEqual([]);

        const hidden = stops.filter((stop) => stop.offscreen);
        expect(
            hidden,
            `Arrets sur un element sans surface :\n${JSON.stringify(hidden, null, 2)}`,
        ).toEqual([]);
    });

    test('la hierarchie des titres est coherente', async ({ page }) => {
        await openResultPage(page, { locale: 'fr' });

        const headings = await page.evaluate(() => Array.from(
            document.querySelectorAll('main h1, main h2, main h3, main h4, main h5, main h6'),
        )
            .filter((el) => el.getBoundingClientRect().height > 0)
            .map((el) => ({ level: Number(el.tagName[1]), text: (el.textContent || '').trim().slice(0, 50) })));

        const h1Count = headings.filter((h) => h.level === 1).length;
        expect(h1Count, `titres h1 trouves : ${h1Count}`).toBe(1);

        const skips = [];
        for (let i = 1; i < headings.length; i++) {
            if (headings[i].level > headings[i - 1].level + 1) {
                skips.push({ from: headings[i - 1], to: headings[i] });
            }
        }
        expect(
            skips,
            `Sauts de niveau de titre :\n${JSON.stringify(skips, null, 2)}`,
        ).toEqual([]);
    });
});

// @ts-check
const { test, expect } = require('@playwright/test');
const { openResultPage } = require('../helpers/simulation');

/**
 * Regression structurelle de la page resultat (issue #122).
 *
 * Le squelette de la page est fige sous forme textuelle plutot qu'en
 * comparaison pixel : une reference PNG rejouerait le bruit des polices et du
 * canvas, alors qu'un squelette compare ligne a ligne se relit dans un diff et
 * signale exactement ce qui a bouge.
 *
 * Aucun montant n'est capture : ce contrat surveille la composition, pas le
 * moteur de calcul.
 */

/** Squelette normalise d'une largeur donnee. */
async function readSkeleton(page) {
    return page.evaluate(() => {
        const container = document.querySelector('main') || document.body;
        const containerWidth = container.getBoundingClientRect().width;

        return Array.from(document.querySelectorAll('[data-result-section]')).map((section) => {
            const rect = section.getBoundingClientRect();
            const heading = section.querySelector('h1, h2, h3');
            const tables = Array.from(section.querySelectorAll('table'));

            return {
                section: section.getAttribute('data-result-section'),
                tag: section.tagName.toLowerCase(),
                labelled: !!(section.getAttribute('aria-labelledby') || section.getAttribute('aria-label')),
                headingLevel: heading ? Number(heading.tagName[1]) : null,
                // Part de la largeur utile reellement occupee, arrondie au dixieme.
                widthShare: Math.round((rect.width / containerWidth) * 10) / 10,
                tables: tables.length,
                tableShapes: tables.map((table) => ({
                    columns: Math.max(0, ...Array.from(table.querySelectorAll('tr')).map((tr) => tr.children.length)),
                    bodyRows: table.querySelectorAll('tbody tr').length,
                })),
                landmarks: Array.from(section.querySelectorAll('[data-result-block]'))
                    .map((block) => block.getAttribute('data-result-block')),
            };
        });
    });
}

const SKELETON_CELLS = [
    { file: 'squelette-desktop-1440.json', viewport: { width: 1440, height: 900 } },
    { file: 'squelette-mobile-390.json', viewport: { width: 390, height: 844 } },
];

for (const cell of SKELETON_CELLS) {
    test.describe(`Squelette ${cell.viewport.width}px`, () => {
        test.use({ viewport: cell.viewport });

        test('la structure de la page resultat est stable', async ({ page }) => {
            await openResultPage(page, { locale: 'fr' });

            const skeleton = await readSkeleton(page);

            expect(skeleton.length, 'aucune section balisee trouvee').toBeGreaterThan(0);
            expect(JSON.stringify(skeleton, null, 2)).toMatchSnapshot({ name: cell.file });
        });
    });
}

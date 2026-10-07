// @ts-check
const path = require('path');
const { test } = require('@playwright/test');
const { openResultPage } = require('../helpers/simulation');

/**
 * Captures durables de la page resultat (issue #122).
 *
 * Ces captures sont des preuves relues par un humain, pas des references de
 * comparaison pixel : la non-regression structurelle est couverte par
 * ../contracts/. Elles sont produites a partir de la fixture synthetique
 * partagee (salaire de base 10 000 MAD), jamais de donnees reelles.
 *
 * Sortie par defaut : docs/ux/122-result-layout/ (versionne).
 * CAPTURE_DIR permet de rediriger la sortie, par exemple pour figer un etat
 * de reference hors depot avant modification.
 */
const OUTPUT_DIR = process.env.CAPTURE_DIR
    || path.join(__dirname, '..', '..', '..', 'docs', 'ux', '122-result-layout');

/**
 * Matrice de captures.
 *
 * Le zoom 200% est reproduit par la reduction equivalente du viewport CSS :
 * un ecran 1440x900 zoome a 200% expose exactement 720x450 pixels CSS.
 */
const MATRIX = [
    { file: '01-desktop-1440x900-fr', viewport: { width: 1440, height: 900 }, locale: 'fr' },
    { file: '02-laptop-1280x800-en', viewport: { width: 1280, height: 800 }, locale: 'en' },
    { file: '03-tablette-768x1024-fr', viewport: { width: 768, height: 1024 }, locale: 'fr' },
    { file: '04-mobile-390x844-fr', viewport: { width: 390, height: 844 }, locale: 'fr' },
    { file: '05-zoom200-720x450-fr', viewport: { width: 720, height: 450 }, locale: 'fr' },
    { file: '06-rtl-mobile-390x844-ar', viewport: { width: 390, height: 844 }, locale: 'ar' },
    { file: '07-rtl-desktop-1440x900-ar', viewport: { width: 1440, height: 900 }, locale: 'ar' },
    { file: '08-laptop-1280x800-es', viewport: { width: 1280, height: 800 }, locale: 'es' },
];

for (const cell of MATRIX) {
    test(`capture ${cell.file}`, async ({ page }) => {
        await page.setViewportSize(cell.viewport);
        await openResultPage(page, { locale: cell.locale });
        await page.screenshot({
            path: path.join(OUTPUT_DIR, `${cell.file}.png`),
            fullPage: true,
        });
    });
}

test('capture 09-impression-desktop-fr', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await openResultPage(page, { locale: 'fr' });
    await page.emulateMedia({ media: 'print' });
    await page.screenshot({
        path: path.join(OUTPUT_DIR, '09-impression-desktop-fr.png'),
        fullPage: true,
    });
});

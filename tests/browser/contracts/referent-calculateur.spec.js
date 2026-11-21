// @ts-check
const { test, expect } = require('@playwright/test');
const { setLocale, stabilize } = require('../helpers/simulation');

/**
 * Referent apres une page du calculateur (issue #283, contre-revue).
 *
 * Une URL partagee /calculateur?s=<payload> porte des montants. Avec
 * strict-origin-when-cross-origin, un clic vers une page editoriale de la meme
 * origine transmet l'URL complete : la page suivante, et tout script qu'elle
 * charge, la verrait dans document.referrer. Contrat verifie par de
 * VRAIS clics sur les liens internes (jamais page.goto vers la cible, qui
 * n'emet aucun referent et masquerait le defaut) :
 * - depuis /calculateur?s=<payload> ou /calculateur/gain-2027?s=<payload>, un
 *   clic sur le lien Documentation de la navigation donne un
 *   document.referrer vide ;
 * - depuis une page editoriale, la politique strict-origin-when-cross-origin
 *   reste active : le referent d'un clic vers une autre page editoriale est
 *   l'URL de la page de depart ;
 * - le changement de langue depuis l'URL partagee (vrai clic) revient a la
 *   meme URL, payload compris, formulaire toujours prerempli.
 *
 * Le payload vient du lien de partage d'un vrai resultat (formulaire soumis),
 * jamais d'une fixture. Statut attendu sur le HEAD distant : ROUGE pour le
 * formulaire partage (referent complet) ; le cas gain-2027 est masque tant que
 * la route est absente.
 */

const SALAIRE = '12345';

/** URL partagee d'un vrai resultat, relative a l'origine. */
async function urlPartagee(page) {
    await page.goto('/calculateur', { waitUntil: 'domcontentloaded' });
    await stabilize(page);
    await page.locator('#salaire_base').fill(SALAIRE);
    await page.locator('select[name="exercice"]').selectOption('2026');
    await Promise.all([
        page.waitForURL('**/calculateur/calculer'),
        page.locator('#payrollForm .quick-submit-panel button[type="submit"]').click(),
    ]);
    const lien = await page.locator('#shareUrl').inputValue();
    const u = new URL(lien, page.url());
    expect(u.pathname, 'lien de partage du calculateur').toBe('/calculateur');
    expect(u.searchParams.get('s'), 'payload present').toBeTruthy();
    return `${u.pathname}${u.search}`;
}

/** Vrai clic sur le lien Documentation de la navigation principale. */
async function cliquerDocumentation(page) {
    const lien = page.locator('nav.navbar a.nav-link[href$="/documentation"]');
    await expect(lien, 'lien Documentation dans la navigation').toHaveCount(1);
    await Promise.all([
        page.waitForURL('**/documentation'),
        lien.click(),
    ]);
    await page.locator('h1').first().waitFor({ state: 'visible' });
}

test.describe('Referent depuis le calculateur', () => {
    test.use({ viewport: { width: 1440, height: 900 } });

    test('formulaire partage puis clic vers la documentation : aucun referent', async ({ page }) => {
        await setLocale(page, 'fr');
        const partage = await urlPartagee(page);

        await page.goto(partage, { waitUntil: 'domcontentloaded' });
        await stabilize(page);
        await expect(page.locator('#salaire_base'), 'formulaire prerempli par le lien').toHaveValue(SALAIRE);

        await cliquerDocumentation(page);
        const referent = await page.evaluate(() => document.referrer);
        expect(referent, 'aucun referent, donc aucun payload, apres le calculateur').toBe('');
    });

    test('page de gain puis clic vers la documentation : aucun referent', async ({ page }) => {
        await setLocale(page, 'fr');
        const partage = await urlPartagee(page);
        const s = new URL(partage, 'http://localhost').searchParams.get('s');

        await page.goto(`/calculateur/gain-2027?s=${s}`, { waitUntil: 'domcontentloaded' });
        await page.locator('[data-gain2027-page]').waitFor({ state: 'visible' });
        await stabilize(page);

        await cliquerDocumentation(page);
        expect(await page.evaluate(() => document.referrer), 'aucun referent apres la page de gain').toBe('');
    });

    test('hors calculateur, le referent de meme origine est conserve', async ({ page }) => {
        await setLocale(page, 'fr');
        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await stabilize(page);
        const depart = page.url();

        await cliquerDocumentation(page);
        expect(await page.evaluate(() => document.referrer), 'strict-origin-when-cross-origin : URL de depart').toBe(depart);
    });

    test('changement de langue depuis l URL partagee : meme URL, saisie conservee', async ({ page }) => {
        await setLocale(page, 'fr');
        const partage = await urlPartagee(page);

        await page.goto(partage, { waitUntil: 'domcontentloaded' });
        await stabilize(page);

        const anglais = page.locator('a.language-switcher-link[hreflang="en"]');
        await expect(anglais).toHaveCount(1);
        // L'URL de depart est deja /calculateur : on attend la vraie reponse
        // de /lang/en, puis la page d'arrivee dans la nouvelle langue.
        await Promise.all([
            page.waitForResponse((r) => new URL(r.url()).pathname === '/lang/en'),
            anglais.click(),
        ]);
        await expect(page.locator('html'), 'page d arrivee en anglais').toHaveAttribute('lang', 'en');
        await page.waitForLoadState('domcontentloaded');

        const arrivee = new URL(page.url());
        const attendue = new URL(partage, arrivee.origin);
        expect(arrivee.pathname).toBe(attendue.pathname);
        expect(arrivee.searchParams.get('s'), 'retour a la meme URL partagee, payload compris').toBe(attendue.searchParams.get('s'));
        await expect(page.locator('html'), 'langue appliquee').toHaveAttribute('lang', 'en');
        await expect(page.locator('#salaire_base'), 'saisie toujours prechargee').toHaveValue(SALAIRE);
    });
});

// @ts-check
const path = require('path');
const { test, expect } = require('@playwright/test');
const { LOCALE_DIRECTION, openResultPage, stabilize } = require('../helpers/simulation');

/**
 * Retours sans referent avec deux onglets de la meme session (issue #295).
 *
 * Les routes calculator.* sont servies sous Referrer-Policy: no-referrer (#283).
 * Deux vrais onglets du meme contexte navigateur partagent la session : on
 * prepare l'onglet A (calculateur), on consulte la documentation dans
 * l'onglet B, puis on agit dans A par de vrais clics :
 *
 * - validation : saisie hors bornes serveur, envoyee volontairement sans la
 *   validation HTML5 (form.noValidate positionne avant le vrai clic sur le
 *   bouton de soumission), pour que la requete HTTP parte et que le serveur
 *   reponde par une erreur de validation ;
 * - langue : clic sur le lien du selecteur reellement rendu dans A (menu
 *   replie ouvert par son bouton en mobile), depuis le formulaire restaure et
 *   depuis la page resultat obtenue par POST.
 *
 * Chaque retour doit rester dans A : meme formulaire (erreurs, saisie et
 * scenario de comparaison conserves) ou meme simulation dans la nouvelle
 * langue, jamais la page de B. L'absence de referent est prouvee sur les
 * requetes de navigation interceptees (en-tetes reellement envoyes) et par
 * document.referrer. Les erreurs console et pageerror des deux onglets sont
 * surveillees sans filtrage.
 *
 * Donnees : fixture entierement synthetique de tests/browser/helpers/simulation.js.
 *
 * Captures deterministes (cellules capture) dans
 * tests/browser/.output/preuves-exercice/multi-onglets/, publiees par
 * l'artefact preuves-exercice existant. Manifeste, deux cellules :
 *   <cellule>-01-onglet-a-saisie-invalide.png
 *   <cellule>-02-onglet-a-retour-validation.png
 *   <cellule>-03-onglet-a-retour-langue.png
 */

const CAPTURE_DIR = path.join(__dirname, '..', '.output', 'preuves-exercice', 'multi-onglets');

const CELLS = [
    { id: 'fr-desktop-1440x900', viewport: { width: 1440, height: 900 }, locale: 'fr', cible: 'en', menuReplie: false, capture: true },
    { id: 'ar-rtl-mobile-390x844', viewport: { width: 390, height: 844 }, locale: 'ar', cible: 'fr', menuReplie: true, capture: true },
];

/** Valeur synthetique de salaire de base de la fixture, telle que restituee par le formulaire. */
const SALAIRE_FIXTURE = /^10000(\.0+)?$/;

/** Relatif a l'application : le lien partage peut porter l'URL absolue de APP_URL. */
function relatif(url) {
    const u = new URL(url, 'http://localhost');
    return `${u.pathname}${u.search}`;
}

/** Chemin et parametres de requete, independamment de leur ordre. */
function cheminEtRequete(url) {
    const u = new URL(url, 'http://localhost');
    const params = [...u.searchParams.entries()].sort(([a], [b]) => a.localeCompare(b));
    return { chemin: u.pathname, params };
}

async function capture(page, cell, step) {
    if (!cell.capture) return;
    await page.screenshot({ path: path.join(CAPTURE_DIR, `${cell.id}-${step}.png`), fullPage: true });
}

/** Erreurs console et exceptions de page, sans filtrage. */
function surveiller(page, onglet, erreurs) {
    page.on('console', (message) => {
        if (message.type() === 'error') {
            erreurs.push(`${onglet} console : ${message.text()}`);
        }
    });
    page.on('pageerror', (erreur) => erreurs.push(`${onglet} pageerror : ${erreur.message}`));
}

/** Requetes de navigation du document principal, redirections comprises. */
function journalNavigations(page) {
    const requetes = [];
    page.on('request', (request) => {
        if (request.isNavigationRequest() && request.frame() === page.mainFrame()) {
            requetes.push(request);
        }
    });
    return requetes;
}

/**
 * Aucun en-tete Referer sur les requetes reellement envoyees, ni de
 * document.referrer sur la page d'arrivee. Les messages ne citent que le
 * chemin, jamais la chaine de requete.
 */
async function assertSansReferent(page, requetes, where) {
    expect(requetes.length, `${where} : navigation observee`).toBeGreaterThan(0);
    for (const requete of requetes) {
        const entetes = await requete.allHeaders();
        expect(
            entetes.referer,
            `${where} : aucun referent pour ${requete.method()} ${new URL(requete.url()).pathname}`,
        ).toBeUndefined();
    }
    expect(await page.evaluate(() => document.referrer), `${where} : document.referrer vide`).toBe('');
}

/** Politique de referent servie avec le document d'arrivee. */
async function assertPolitiqueArrivee(requetes, attendue, where) {
    const reponse = await requetes[requetes.length - 1].response();
    expect(reponse, `${where} : reponse du document`).toBeTruthy();
    expect(await reponse.headerValue('referrer-policy'), `${where} : Referrer-Policy`).toBe(attendue);
}

/** Vrai clic suivi du nouveau document du cadre principal. */
async function cliquerEtAttendreDocument(page, locator) {
    const navigation = page.waitForEvent('framenavigated', { predicate: (frame) => frame === page.mainFrame() });
    await locator.click();
    await navigation;
    await page.waitForLoadState('domcontentloaded');
    await stabilize(page);
}

/** Ouvre l'onglet B sur une page editoriale de la meme session, puis revient a A. */
async function consulterDansOngletB(context, pageA, erreurs) {
    const pageB = await context.newPage();
    surveiller(pageB, 'B', erreurs);
    await pageB.goto('/documentation', { waitUntil: 'domcontentloaded' });
    await stabilize(pageB);
    expect(new URL(pageB.url()).pathname, 'onglet B sur la documentation').toBe('/documentation');
    await pageA.bringToFront();
    return pageB;
}

/** Ouvre une etape repliee du formulaire par un vrai clic sur son resume. */
async function ouvrirEtape(page, id) {
    const etape = page.locator(`#${id}`);
    await expect(etape, `etape ${id}`).toHaveCount(1);
    if (!(await etape.evaluate((el) => /** @type {HTMLDetailsElement} */ (el).open))) {
        await etape.locator('summary').click();
    }
    await expect(etape).toHaveAttribute('open', '');
}

/**
 * Lien de langue reellement rendu dans le selecteur de la page. En mobile, le
 * menu est replie : il est ouvert par son bouton avant le clic.
 */
async function lienDeLangue(page, cell, langue) {
    const lien = page.locator(`.language-switcher a[hreflang="${langue}"]`);
    await expect(lien, `un seul lien de langue ${langue}`).toHaveCount(1);

    const bouton = page.locator('.navbar-toggler');
    expect(await bouton.isVisible(), 'etat du menu de navigation').toBe(cell.menuReplie);
    if (cell.menuReplie) {
        await expect(lien, 'lien masque tant que le menu est replie').toBeHidden();
        await bouton.click();
        await expect(page.locator('#nav')).toHaveClass(/\bshow\b/);
    }

    await expect(lien).toBeVisible();
    return lien;
}

async function assertLangue(page, langue, where) {
    expect(await page.getAttribute('html', 'lang'), `${where} : langue appliquee`).toBe(langue);
    expect(await page.getAttribute('html', 'dir'), `${where} : sens d'ecriture`).toBe(LOCALE_DIRECTION[langue]);
}

for (const cell of CELLS) {
    test.describe(`Retour multi-onglets - ${cell.id}`, () => {
        test.use({ viewport: cell.viewport });

        test('validation : erreur soumise depuis A apres B, retour au formulaire de A', async ({ context, page }) => {
            const erreurs = [];
            surveiller(page, 'A', erreurs);

            // Onglet A : resultat reel, puis vrai lien d'armement de la comparaison.
            await openResultPage(page, { locale: cell.locale });
            const armer = page.locator('[data-result-section="actions"] [data-result-block="share"] a:has(i.bi-columns-gap)');
            await expect(armer, 'un lien de comparaison sur le resultat').toHaveCount(1);
            await cliquerEtAttendreDocument(page, armer);

            const depart = relatif(page.url());
            const scenarioA = new URL(page.url()).searchParams.get('a');
            expect(new URL(page.url()).pathname, 'formulaire du calculateur').toBe('/calculateur');
            expect(scenarioA, 'scenario A dans l\'URL').toBeTruthy();
            await expect(page.locator('#payrollForm input[type="hidden"][name="comparer_a"]')).toHaveValue(String(scenarioA));
            await expect(page.locator('#salaire_base')).toHaveValue(SALAIRE_FIXTURE);

            // Saisie : jours travailles hors bornes serveur (max HTML contourne
            // par noValidate), primes imposables modifiees.
            await ouvrirEtape(page, 'step-indemnites');
            await page.locator('#joursTravailles').fill('40');
            await ouvrirEtape(page, 'step-primes');
            await page.locator('#autres_primes').fill('2500');
            await page.locator('#payrollForm').evaluate((form) => { /** @type {HTMLFormElement} */ (form).noValidate = true; });
            expect(await page.locator('#payrollForm').evaluate((form) => /** @type {HTMLFormElement} */ (form).noValidate)).toBe(true);
            await capture(page, cell, '01-onglet-a-saisie-invalide');

            // Onglet B : documentation dans la meme session.
            const pageB = await consulterDansOngletB(context, page, erreurs);

            // Vrai clic de soumission dans A.
            const requetes = journalNavigations(page);
            await cliquerEtAttendreDocument(page, page.locator('#payrollForm .quick-submit-panel button[type="submit"]'));
            await capture(page, cell, '02-onglet-a-retour-validation');

            expect(
                requetes.some((r) => r.method() === 'POST' && new URL(r.url()).pathname === '/calculateur/calculer'),
                'la requete HTTP de soumission est partie',
            ).toBe(true);
            expect(cheminEtRequete(page.url()), 'retour au formulaire de A, jamais a la page de B').toEqual(cheminEtRequete(depart));

            await expect(page.locator('#formErrors'), 'erreurs de validation affichees').toBeVisible();
            await expect(page.locator('#joursTravailles'), 'saisie invalide restituee').toHaveValue('40');
            await expect(page.locator('#joursTravailles')).toHaveClass(/\bis-invalid\b/);
            await expect(page.locator('#autres_primes'), 'primes saisies restituees').toHaveValue(/^2500(\.0+)?$/);
            await expect(page.locator('#payrollForm input[type="hidden"][name="prime_bilan"]'), 'pas de double comptage').toHaveValue('0');
            await expect(page.locator('#payrollForm input[type="hidden"][name="prime_rendement"]'), 'pas de double comptage').toHaveValue('0');
            await expect(page.locator('#salaire_base'), 'salaire conserve').toHaveValue(SALAIRE_FIXTURE);
            await expect(page.locator('#payrollForm input[type="hidden"][name="comparer_a"]'), 'scenario A conserve').toHaveValue(String(scenarioA));
            await assertLangue(page, cell.locale, 'retour de validation');

            await assertSansReferent(page, requetes, 'retour de validation');
            await assertPolitiqueArrivee(requetes, 'no-referrer', 'retour de validation');
            expect(new URL(pageB.url()).pathname, 'onglet B inchange').toBe('/documentation');
            expect(erreurs, 'aucune erreur console ni exception de page').toEqual([]);
        });

        test('langue : lien reel de A apres B, retour a la meme simulation', async ({ context, page }) => {
            const erreurs = [];
            surveiller(page, 'A', erreurs);

            // Onglet A : simulation reprise par son vrai lien de partage.
            await openResultPage(page, { locale: cell.locale });
            await page.goto(relatif(await page.locator('#shareUrl').inputValue()), { waitUntil: 'domcontentloaded' });
            await stabilize(page);
            const depart = relatif(page.url());
            expect(new URL(page.url()).searchParams.get('s'), 'simulation dans l\'URL').toBeTruthy();
            await expect(page.locator('#salaire_base')).toHaveValue(SALAIRE_FIXTURE);
            await assertLangue(page, cell.locale, 'formulaire repris');

            const pageB = await consulterDansOngletB(context, page, erreurs);

            const requetes = journalNavigations(page);
            await cliquerEtAttendreDocument(page, await lienDeLangue(page, cell, cell.cible));
            await capture(page, cell, '03-onglet-a-retour-langue');

            expect(cheminEtRequete(page.url()), 'retour a la simulation de A, jamais a la page de B').toEqual(cheminEtRequete(depart));
            await assertLangue(page, cell.cible, 'retour de langue');
            await expect(page.locator('#salaire_base'), 'simulation rechargee').toHaveValue(SALAIRE_FIXTURE);

            await assertSansReferent(page, requetes, 'retour de langue');
            await assertPolitiqueArrivee(requetes, 'no-referrer', 'retour de langue');
            expect(new URL(pageB.url()).pathname, 'onglet B inchange').toBe('/documentation');
            expect(erreurs, 'aucune erreur console ni exception de page').toEqual([]);
        });

        test('langue depuis le resultat (POST) apres B : calculateur rejouable avec la saisie', async ({ context, page }) => {
            const erreurs = [];
            surveiller(page, 'A', erreurs);

            // Onglet A : page resultat obtenue par le vrai POST du formulaire.
            await openResultPage(page, { locale: cell.locale });
            expect(new URL(page.url()).pathname, 'page resultat').toBe('/calculateur/calculer');
            expect(new URL(await page.locator('#shareUrl').inputValue()).searchParams.get('s'), 'lien partage du resultat').toBeTruthy();

            const pageB = await consulterDansOngletB(context, page, erreurs);

            const requetes = journalNavigations(page);
            await cliquerEtAttendreDocument(page, await lienDeLangue(page, cell, cell.cible));

            const arrivee = new URL(page.url());
            expect(arrivee.pathname, 'calculateur rejouable, ni le resultat POST ni la page de B').toBe('/calculateur');
            expect(arrivee.searchParams.get('s'), 'saisie rechargee par le lien partage').toBeTruthy();
            await assertLangue(page, cell.cible, 'langue depuis le resultat');
            await expect(page.locator('#salaire_base'), 'saisie rechargee').toHaveValue(SALAIRE_FIXTURE);
            await expect(page.locator('#nb_enfants'), 'saisie rechargee').toHaveValue('2');

            await assertSansReferent(page, requetes, 'langue depuis le resultat');
            await assertPolitiqueArrivee(requetes, 'no-referrer', 'langue depuis le resultat');
            expect(new URL(pageB.url()).pathname, 'onglet B inchange').toBe('/documentation');
            expect(erreurs, 'aucune erreur console ni exception de page').toEqual([]);
        });
    });
}

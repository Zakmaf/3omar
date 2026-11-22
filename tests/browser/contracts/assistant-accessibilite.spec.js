// @ts-check
const { test, expect } = require('@playwright/test');
const { setLocale, stabilize } = require('../helpers/simulation');

/**
 * Assistant du formulaire accessible (issue #286), premier jalon RED.
 *
 * Contrat minimal verifie dans le formulaire reel /calculateur, en francais,
 * dans deux cellules : bureau 1440x900 (liste laterale de l'assistant) et
 * mobile 390x844 (pastilles de l'assistant). Le bouton rendu a chaque largeur
 * est repere par son attribut data-wizard-nav (bureau) ou data-wizard-mobile
 * (mobile), dont la valeur est l'index de la rubrique.
 *
 * 1. Etat initial : le bouton d'index 0 controle la rubrique ouverte au
 *    chargement (aria-controls="step-remuneration") et expose son etat reel
 *    (aria-expanded="true").
 * 2. Coche d'une rubrique fermee : #step-primes est ouverte par un vrai clic
 *    sur son resume, 150 est saisi dans le champ optionnel #autres_primes, puis
 *    la rubrique est refermee par le meme resume. Le bouton d'index 1 doit alors
 *    porter une description accessible contenant "renseignée". Controle
 *    negatif : apres effacement du meme champ et nouvelle fermeture, cette
 *    description ne doit plus etre presente (sur main, il passe faute de
 *    toute description).
 *
 * La description est lue par le calcul d'accessibilite du navigateur
 * (toHaveAccessibleDescription), quel que soit le mecanisme retenu : aucune
 * classe CSS ni aucun detail d'implementation n'est verifie. Les etapes du
 * parcours sont des assertions bloquantes ; les assertions du contrat sont
 * souples (expect.soft), pour que chaque attribut ou description absent soit
 * rapporte sans masquer les preuves suivantes.
 *
 * Statut attendu sur main : ROUGE pour les deux tests de chaque cellule, par
 * absence de aria-controls, de aria-expanded et de la description, et non par
 * le parcours. Les transitions (navigation clavier, ouverture et fermeture
 * directes laissant plusieurs rubriques ouvertes, autres rubriques) relevent
 * d'un contrat ulterieur.
 */

const CELLS = [
    { id: 'fr-desktop-1440x900', viewport: { width: 1440, height: 900 }, attribut: 'data-wizard-nav' },
    { id: 'fr-mobile-390x844', viewport: { width: 390, height: 844 }, attribut: 'data-wizard-mobile' },
];

/**
 * Bouton de l'assistant pour la rubrique d'index donne, dans la variante
 * rendue a cette largeur.
 * @param {import('@playwright/test').Page} page
 * @param {{ attribut: string }} cell
 * @param {number} index
 */
function boutonAssistant(page, cell, index) {
    return page.locator(`[${cell.attribut}="${index}"]`);
}

/**
 * Formulaire reel en francais, rendu deterministe par le harnais.
 * @param {import('@playwright/test').Page} page
 */
async function ouvrirCalculateur(page) {
    await setLocale(page, 'fr');
    await page.goto('/calculateur', { waitUntil: 'domcontentloaded' });
    await stabilize(page);
}

/**
 * Ouvre ou ferme une rubrique par un vrai clic sur son resume : l'etat de
 * depart est verifie avant le clic, l'etat d'arrivee attendu apres.
 * @param {import('@playwright/test').Page} page
 * @param {string} id
 * @param {boolean} ouvrir
 */
async function basculerRubrique(page, id, ouvrir) {
    const rubrique = page.locator(`#${id}`);
    const resume = page.locator(`#${id} > summary`);
    await expect(resume, `resume de la rubrique ${id}`).toHaveCount(1);

    if (ouvrir) {
        await expect(rubrique, `rubrique ${id} fermee avant le clic`).not.toHaveAttribute('open');
        await resume.click();
        await expect(rubrique, `rubrique ${id} ouverte par son resume`).toHaveAttribute('open', '');
    } else {
        await expect(rubrique, `rubrique ${id} ouverte avant le clic`).toHaveAttribute('open', '');
        await resume.click();
        await expect(rubrique, `rubrique ${id} fermee par son resume`).not.toHaveAttribute('open');
    }
}

for (const cell of CELLS) {
    test.describe(`Assistant accessible - ${cell.id}`, () => {
        test.use({ viewport: cell.viewport });
        // Sur main, chaque assertion souple epuise le delai d'expect (10 s) avant
        // d'echouer : 60 s laissent rapporter chacune avant l'expiration du test.
        test.describe.configure({ timeout: 60_000 });

        test('etat initial : le bouton d\'index 0 controle la rubrique ouverte et expose son etat', async ({ page }) => {
            await ouvrirCalculateur(page);

            // Parcours : rubrique ouverte au chargement, bouton rendu a cette largeur.
            await expect(page.locator('#step-remuneration'), 'rubrique step-remuneration ouverte au chargement').toHaveAttribute('open', '');
            const bouton = boutonAssistant(page, cell, 0);
            await expect(bouton, `bouton ${cell.attribut}="0" rendu a cette largeur`).toBeVisible();

            // Contrat : relation de controle et etat reel exposes par le bouton.
            await expect.soft(bouton, 'aria-controls designe la rubrique step-remuneration').toHaveAttribute('aria-controls', 'step-remuneration');
            await expect.soft(bouton, 'aria-expanded expose l\'etat ouvert de la rubrique').toHaveAttribute('aria-expanded', 'true');
        });

        test('coche d\'une rubrique fermee : description renseignee apres saisie, retiree apres effacement', async ({ page }) => {
            await ouvrirCalculateur(page);
            const bouton = boutonAssistant(page, cell, 1);
            const champ = page.locator('#autres_primes');
            await expect(bouton, `bouton ${cell.attribut}="1" rendu a cette largeur`).toBeVisible();

            // Parcours : saisie reelle d'un champ optionnel, puis fermeture directe.
            await basculerRubrique(page, 'step-primes', true);
            await champ.fill('150');
            await expect(champ, 'saisie prise en compte').toHaveValue('150');
            await basculerRubrique(page, 'step-primes', false);

            // Contrat : la coche de la rubrique fermee et renseignee est decrite.
            await expect.soft(bouton, 'rubrique fermee renseignee : description accessible').toHaveAccessibleDescription(/renseignée/i);

            // Controle negatif : meme champ efface, rubrique refermee.
            await basculerRubrique(page, 'step-primes', true);
            await champ.clear();
            await expect(champ, 'champ efface').toHaveValue('');
            await basculerRubrique(page, 'step-primes', false);
            await expect(bouton, `bouton ${cell.attribut}="1" toujours rendu`).toBeVisible();
            await expect(bouton, 'champ efface : plus de description renseignee').not.toHaveAccessibleDescription(/renseignée/i);
        });
    });
}

// @ts-check
const path = require('path');
const { test, expect } = require('@playwright/test');
const { setLocale, stabilize } = require('../helpers/simulation');

/**
 * Assistant du formulaire accessible (issue #286).
 *
 * Contrat verifie dans le formulaire reel /calculateur, dans trois cellules :
 * francais en bureau 1440x900 (liste laterale de l'assistant), francais en
 * mobile 390x844 et arabe en mobile 390x844 (pastilles de l'assistant, page en
 * dir="rtl"). Le bouton rendu a chaque largeur est repere par son attribut
 * data-wizard-nav (bureau) ou data-wizard-mobile (mobile), dont la valeur est
 * l'index de la rubrique.
 *
 * 1. Etat initial (controle RED d'origine) : le bouton d'index 0 controle la
 *    rubrique ouverte au chargement (aria-controls="step-remuneration") et
 *    expose son etat reel (aria-expanded="true"). Controle complementaire : les
 *    boutons visibles a cette largeur correspondent un a un, par index, aux vrais
 *    details[data-step-section] lus dans la page (ids non vides et uniques) ;
 *    chacun designe sa rubrique (aria-controls) et expose son etat open
 *    (aria-expanded).
 * 2. Coche d'une rubrique fermee (controle RED d'origine) : #step-primes est
 *    ouverte par un vrai clic sur son resume, 150 est saisi dans le champ
 *    optionnel #autres_primes, puis la rubrique est refermee par le meme resume.
 *    Le bouton d'index 1 porte alors la description accessible de la locale
 *    ("renseignée" en francais, "قسم معبأ" en arabe). Controle negatif : apres
 *    effacement du meme champ et nouvelle fermeture, cette description disparait.
 *    Controle complementaire : apres la saisie et avant la fermeture, rubrique
 *    encore ouverte, le bouton n'a aucune description accessible.
 * 3. Transitions : l'ouverture directe de #step-primes par son resume natif
 *    laisse #step-remuneration ouverte et les boutons 0 et 1 exposent tous deux
 *    aria-expanded="true" ; focus sur le bouton 0 puis touche Entree :
 *    #step-remuneration redevient la seule rubrique ouverte et le bouton 1
 *    expose aria-expanded="false".
 *
 * La description est lue par le calcul d'accessibilite du navigateur
 * (toHaveAccessibleDescription), quel que soit le mecanisme retenu : aucune
 * classe CSS ni aucun detail d'implementation n'est verifie. Les etapes du
 * parcours sont des assertions bloquantes ; les assertions du contrat sont
 * souples (expect.soft), pour que chaque attribut ou description absent soit
 * rapporte sans masquer les preuves suivantes. Le commit RED initial couvrait
 * les controles 1 et 2 d'origine sur les deux cellules francaises : 4 echecs
 * attendus sur la base (main avant le correctif), par absence des attributs et
 * de la description, et non par le parcours. Les autres controles (cellule
 * arabe, controle 3, controles complementaires) ne sont pas pretendus executes
 * sur la base.
 *
 * Preuves visuelles : juste apres la description positive verifiee, la
 * navigation rendue est capturee seule dans
 * tests/browser/.output/preuves-assistant/<cellule>.png (artefact CI
 * preuves-assistant). Aucune capture n'est produite si cette description
 * echoue.
 */

const CAPTURE_DIR = path.join(__dirname, '..', '.output', 'preuves-assistant');

/**
 * Cellules : variante de l'assistant rendue a la largeur, langue, sens
 * d'ecriture attendu de la page et description attendue de la coche.
 */
const CELLS = [
    { id: 'fr-desktop-1440x900', viewport: { width: 1440, height: 900 }, attribut: 'data-wizard-nav', langue: 'fr', dir: 'ltr', description: /renseignée/i },
    { id: 'fr-mobile-390x844', viewport: { width: 390, height: 844 }, attribut: 'data-wizard-mobile', langue: 'fr', dir: 'ltr', description: /renseignée/i },
    { id: 'ar-rtl-mobile-390x844', viewport: { width: 390, height: 844 }, attribut: 'data-wizard-mobile', langue: 'ar', dir: 'rtl', description: 'قسم معبأ' },
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
 * Formulaire reel dans la langue de la cellule, rendu deterministe par le
 * harnais ; le sens d'ecriture de la page est verifie.
 * @param {import('@playwright/test').Page} page
 * @param {{ langue: string, dir: string }} cell
 */
async function ouvrirCalculateur(page, cell) {
    await setLocale(page, cell.langue);
    await page.goto('/calculateur', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('html'), `calculateur en dir="${cell.dir}"`).toHaveAttribute('dir', cell.dir);
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
        // En cas d'echec, chaque assertion souple epuise le delai d'expect (10 s) :
        // 60 s laissent rapporter chacune avant l'expiration du test.
        test.describe.configure({ timeout: 60_000 });

        test('etat initial : le bouton d\'index 0 controle la rubrique ouverte et expose son etat', async ({ page }) => {
            await ouvrirCalculateur(page, cell);

            // Parcours : rubrique ouverte au chargement, bouton rendu a cette largeur.
            await expect(page.locator('#step-remuneration'), 'rubrique step-remuneration ouverte au chargement').toHaveAttribute('open', '');
            const bouton = boutonAssistant(page, cell, 0);
            await expect(bouton, `bouton ${cell.attribut}="0" rendu a cette largeur`).toBeVisible();

            // Contrat : relation de controle et etat reel exposes par le bouton.
            await expect.soft(bouton, 'aria-controls designe la rubrique step-remuneration').toHaveAttribute('aria-controls', 'step-remuneration');
            await expect.soft(bouton, 'aria-expanded expose l\'etat ouvert de la rubrique').toHaveAttribute('aria-expanded', 'true');

            // Parcours : vrais details[data-step-section] lus dans la page, ids non vides et
            // uniques, un bouton visible par rubrique a cette largeur.
            const rubriques = await page.locator('details[data-step-section]').evaluateAll((elements) => elements.map((el) => ({
                id: el.id,
                open: el.hasAttribute('open'),
                occurrences: Array.from(document.querySelectorAll('[id]')).filter((autre) => autre.id === el.id).length,
            })));
            expect(rubriques.length, 'rubriques details[data-step-section] du formulaire').toBeGreaterThan(0);
            rubriques.forEach((rubrique, i) => {
                expect(rubrique.id, `rubrique d'index ${i} : id non vide`).not.toBe('');
                expect(rubrique.occurrences, `rubrique d'index ${i} : id "${rubrique.id}" unique dans la page`).toBe(1);
            });
            const boutonsVisibles = page.locator(`[${cell.attribut}]`).filter({ visible: true });
            await expect(boutonsVisibles, `un bouton ${cell.attribut} visible par rubrique`).toHaveCount(rubriques.length);

            // Contrat complementaire : chaque bouton visible, par index, designe sa vraie
            // rubrique et expose son etat open.
            const relations = await boutonsVisibles.evaluateAll((boutons, attribut) => boutons
                .map((b) => ({
                    index: b.getAttribute(attribut),
                    controls: b.getAttribute('aria-controls'),
                    expanded: b.getAttribute('aria-expanded'),
                }))
                .sort((x, y) => Number(x.index) - Number(y.index)), cell.attribut);
            expect.soft(relations, 'relations de tous les boutons visibles avec les vraies rubriques').toEqual(
                rubriques.map((rubrique, i) => ({ index: String(i), controls: rubrique.id, expanded: String(rubrique.open) })),
            );
        });

        test('coche d\'une rubrique fermee : description renseignee apres saisie, retiree apres effacement', async ({ page }) => {
            await ouvrirCalculateur(page, cell);
            const bouton = boutonAssistant(page, cell, 1);
            const champ = page.locator('#autres_primes');
            await expect(bouton, `bouton ${cell.attribut}="1" rendu a cette largeur`).toBeVisible();

            // Parcours : saisie reelle d'un champ optionnel, puis fermeture directe.
            await basculerRubrique(page, 'step-primes', true);
            await champ.fill('150');
            await expect(champ, 'saisie prise en compte').toHaveValue('150');
            // Contrat complementaire : avant fermeture, rubrique encore ouverte, aucune description.
            await expect.soft(bouton, 'rubrique ouverte apres saisie : aucune description avant fermeture').toHaveAccessibleDescription('');
            await basculerRubrique(page, 'step-primes', false);

            // Contrat : la coche de la rubrique fermee et renseignee est decrite.
            const erreursAvant = test.info().errors.length;
            await expect.soft(bouton, 'rubrique fermee renseignee : description accessible').toHaveAccessibleDescription(cell.description);

            // Preuve visuelle : navigation rendue, seulement si la description vient d'etre verifiee.
            if (test.info().errors.length === erreursAvant) {
                const nav = bouton.locator('xpath=ancestor::nav[1]');
                await nav.evaluate((el) => el.scrollIntoView({ block: 'center' }));
                await nav.screenshot({ path: path.join(CAPTURE_DIR, `${cell.id}.png`) });
            }

            // Controle negatif : meme champ efface, rubrique refermee.
            await basculerRubrique(page, 'step-primes', true);
            await champ.clear();
            await expect(champ, 'champ efface').toHaveValue('');
            await basculerRubrique(page, 'step-primes', false);
            await expect(bouton, `bouton ${cell.attribut}="1" toujours rendu`).toBeVisible();
            await expect(bouton, 'champ efface : plus de description renseignee').not.toHaveAccessibleDescription(cell.description);
        });

        test('transitions : resume natif puis Entree sur le bouton 0, aria-expanded suit les vraies rubriques', async ({ page }) => {
            await ouvrirCalculateur(page, cell);
            const bouton0 = boutonAssistant(page, cell, 0);
            const bouton1 = boutonAssistant(page, cell, 1);
            await expect(bouton0, `bouton ${cell.attribut}="0" rendu a cette largeur`).toBeVisible();
            await expect(bouton1, `bouton ${cell.attribut}="1" rendu a cette largeur`).toBeVisible();

            // Resume natif : step-primes s'ouvre, step-remuneration reste ouverte.
            await basculerRubrique(page, 'step-primes', true);
            await expect(page.locator('#step-remuneration'), 'step-remuneration toujours ouverte').toHaveAttribute('open', '');
            await expect.soft(bouton0, 'deux rubriques ouvertes : bouton 0').toHaveAttribute('aria-expanded', 'true');
            await expect.soft(bouton1, 'deux rubriques ouvertes : bouton 1').toHaveAttribute('aria-expanded', 'true');

            // Clavier : focus sur le bouton 0 puis Entree, step-remuneration seule ouverte.
            await bouton0.focus();
            await expect(bouton0, 'focus sur le bouton 0').toBeFocused();
            await page.keyboard.press('Enter');
            await expect(page.locator('#step-primes'), 'step-primes refermee par l\'assistant').not.toHaveAttribute('open');
            await expect(page.locator('#step-remuneration'), 'step-remuneration ouverte').toHaveAttribute('open', '');
            await expect(page.locator('#payrollForm details[open]'), 'une seule rubrique ouverte').toHaveCount(1);
            await expect.soft(bouton0, 'apres Entree : bouton 0').toHaveAttribute('aria-expanded', 'true');
            await expect.soft(bouton1, 'apres Entree : bouton 1').toHaveAttribute('aria-expanded', 'false');
        });
    });
}

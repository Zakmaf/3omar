// @ts-check
const { test, expect } = require('@playwright/test');
const { openResultPage } = require('../helpers/simulation');
const { measureColumnBands, findFragmentedAmounts } = require('../helpers/layout');

/**
 * Contrats de composition de la page resultat (issue #122).
 *
 * Ces deux contrats mesurent la defaillance structurelle decrite dans l'issue :
 * une grande zone vide qui coexiste avec une colonne comprimee, et des montants
 * casses sur plusieurs lignes faute de largeur. Ils portent sur la geometrie
 * calculee, pas sur la presence d'un titre ou d'un selecteur.
 */

const DESKTOP = { width: 1440, height: 900 };

/** Part maximale de surface non couverte toleree dans une bande multi-colonnes. */
const MAX_DEAD_RATIO = 0.15;

test.describe('Page resultat - composition desktop 1440x900 (fr)', () => {
    test.use({ viewport: DESKTOP });

    test('aucune bande multi-colonnes ne laisse une grande zone vide', async ({ page }) => {
        await openResultPage(page, { locale: 'fr' });

        const bands = await measureColumnBands(page);
        const wasteful = bands.filter((band) => band.deadRatio > MAX_DEAD_RATIO);

        expect(
            wasteful,
            `Bandes gaspillant plus de ${MAX_DEAD_RATIO * 100}% de leur surface :\n`
            + JSON.stringify(wasteful, null, 2),
        ).toEqual([]);
    });

    test('aucun montant ni pourcentage ne se fragmente sur plusieurs lignes', async ({ page }) => {
        await openResultPage(page, { locale: 'fr' });

        const fragmented = await findFragmentedAmounts(page);

        expect(
            fragmented,
            `Valeurs numeriques coupees par un retour a la ligne :\n`
            + JSON.stringify(fragmented, null, 2),
        ).toEqual([]);
    });
});

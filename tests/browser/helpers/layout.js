// @ts-check

/**
 * Primitives de mesure de mise en page.
 *
 * Ces fonctions mesurent la geometrie reellement calculee par le navigateur.
 * Elles ne connaissent aucune classe CSS du projet : un contrat reste donc
 * valable avant et apres la refonte de la vue.
 */

/**
 * Injecte les utilitaires de mesure dans la page.
 * @param {import('@playwright/test').Page} page
 */
async function installProbes(page) {
    await page.evaluate(() => {
        if (window.__layoutProbes) return;

        const isRendered = (el) => {
            const rect = el.getBoundingClientRect();
            if (rect.width < 1 || rect.height < 1) return false;
            const style = getComputedStyle(el);
            return style.visibility !== 'hidden' && style.display !== 'none';
        };

        const describe = (el) => {
            const id = el.id ? `#${el.id}` : '';
            const cls = typeof el.className === 'string' && el.className
                ? `.${el.className.trim().split(/\s+/).slice(0, 4).join('.')}`
                : '';
            return `${el.tagName.toLowerCase()}${id}${cls}`;
        };

        /** Deux boites sont cote a cote si elles se chevauchent verticalement sans se chevaucher horizontalement. */
        const sideBySide = (a, b) => {
            const verticalOverlap = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
            const horizontalOverlap = Math.min(a.right, b.right) - Math.max(a.left, b.left);
            return verticalOverlap > Math.min(a.height, b.height) * 0.5 && horizontalOverlap <= 1;
        };

        window.__layoutProbes = { isRendered, describe, sideBySide };
    });
}

/**
 * Mesure l'espace mort de chaque bande multi-colonnes du contenu principal.
 *
 * Une "bande" est un element dont au moins deux enfants directs sont rendus
 * cote a cote. L'espace mort est la part de la surface de la bande qui n'est
 * couverte par aucune colonne : c'est la mesure directe du defaut decrit dans
 * l'issue #122 ("une tres grande zone vide" a cote d'une colonne comprimee).
 *
 * @param {import('@playwright/test').Page} page
 * @param {{ minHeight?: number, minWidth?: number }} [options]
 * @returns {Promise<Array<{selector: string, width: number, height: number, deadRatio: number, columns: Array<{selector: string, width: number, height: number}>}>>}
 */
async function measureColumnBands(page, options = {}) {
    await installProbes(page);
    const minHeight = options.minHeight ?? 240;
    const minWidth = options.minWidth ?? 600;

    return page.evaluate(({ minHeight, minWidth }) => {
        const { isRendered, describe, sideBySide } = window.__layoutProbes;
        const root = document.querySelector('main') || document.body;
        const bands = [];

        for (const el of [root, ...root.querySelectorAll('*')]) {
            const children = Array.from(el.children).filter(isRendered);
            if (children.length < 2) continue;

            const boxes = children.map((c) => c.getBoundingClientRect());

            let multiColumn = false;
            for (let i = 0; i < boxes.length && !multiColumn; i++) {
                for (let j = i + 1; j < boxes.length; j++) {
                    if (sideBySide(boxes[i], boxes[j])) { multiColumn = true; break; }
                }
            }
            if (!multiColumn) continue;

            const left = Math.min(...boxes.map((b) => b.left));
            const right = Math.max(...boxes.map((b) => b.right));
            const top = Math.min(...boxes.map((b) => b.top));
            const bottom = Math.max(...boxes.map((b) => b.bottom));
            const width = right - left;
            const height = bottom - top;
            if (height < minHeight || width < minWidth) continue;

            const covered = boxes.reduce((sum, b) => sum + b.width * b.height, 0);
            const deadRatio = Math.max(0, 1 - covered / (width * height));

            bands.push({
                selector: describe(el),
                width: Math.round(width),
                height: Math.round(height),
                deadRatio: Math.round(deadRatio * 1000) / 1000,
                columns: children.map((c, i) => ({
                    selector: describe(c),
                    width: Math.round(boxes[i].width),
                    height: Math.round(boxes[i].height),
                })),
            });
        }

        return bands.sort((a, b) => b.deadRatio - a.deadRatio);
    }, { minHeight, minWidth });
}

/**
 * Repere les montants et pourcentages dont le groupe numerique est coupe
 * sur plusieurs lignes ("10 000,00" rendu "10" puis "000,00").
 *
 * On compte les lignes visuelles occupees par la sous-chaine exacte : au
 * dela d'une, le nombre a ete fragmente par un retour a la ligne.
 *
 * @param {import('@playwright/test').Page} page
 * @returns {Promise<Array<{text: string, selector: string, lines: number}>>}
 */
async function findFragmentedAmounts(page) {
    await installProbes(page);

    return page.evaluate(() => {
        const { describe } = window.__layoutProbes;
        // Nombre avec separateur de milliers ou decimales, et pourcentages.
        // Separateurs de milliers possibles : espace, insecable, insecable etroit, fin.
        const SEP = '\\u0020\\u00A0\\u202F\\u2009';
        const patterns = [
            new RegExp(`\\d{1,3}(?:[${SEP}]\\d{3})+(?:[.,]\\d+)?`, 'g'),
            /\d+[.,]\d{2}\b/g,
            new RegExp(`\\d+(?:[.,]\\d+)?[${SEP}]*%`, 'g'),
        ];
        const root = document.querySelector('main') || document.body;
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        const fragmented = [];
        const seen = new Set();

        let node;
        while ((node = walker.nextNode())) {
            const text = node.nodeValue;
            if (!text || !/\d/.test(text)) continue;

            const parent = node.parentElement;
            if (!parent || parent.closest('[hidden]')) continue;
            const style = getComputedStyle(parent);
            if (style.display === 'none' || style.visibility === 'hidden') continue;

            for (const pattern of patterns) {
                pattern.lastIndex = 0;
                let match;
                while ((match = pattern.exec(text))) {
                    const range = document.createRange();
                    range.setStart(node, match.index);
                    range.setEnd(node, match.index + match[0].length);
                    // Une valeur est fragmentee si elle occupe plusieurs LIGNES.
                    // Plusieurs rectangles sur une meme ligne proviennent du
                    // reordonnancement bidirectionnel (RTL), pas d'un retour a la ligne.
                    const lineTops = [];
                    for (const rect of range.getClientRects()) {
                        if (rect.width < 1) continue;
                        const middle = rect.top + rect.height / 2;
                        if (!lineTops.some((top) => Math.abs(top - middle) < rect.height / 2)) {
                            lineTops.push(middle);
                        }
                    }
                    // 0 ligne = non rendu (element replie), on ignore.
                    if (lineTops.length > 1) {
                        const key = `${describe(parent)}|${match[0]}`;
                        if (seen.has(key)) continue;
                        seen.add(key);
                        fragmented.push({
                            text: match[0],
                            selector: describe(parent),
                            lines: lineTops.length,
                        });
                    }
                }
            }
        }

        return fragmented;
    });
}

/**
 * Debordement horizontal du document.
 * @param {import('@playwright/test').Page} page
 */
async function measureHorizontalOverflow(page) {
    return page.evaluate(() => {
        const doc = document.documentElement;
        return {
            scrollWidth: doc.scrollWidth,
            clientWidth: doc.clientWidth,
            overflow: Math.max(0, doc.scrollWidth - doc.clientWidth),
        };
    });
}

/**
 * Ordre vertical reel des reperes de la page, tel que percu a l'ecran.
 * @param {import('@playwright/test').Page} page
 * @param {string} attribute
 */
async function readLandmarkOrder(page, attribute = 'data-result-section') {
    await installProbes(page);
    return page.evaluate((attribute) => {
        const { isRendered } = window.__layoutProbes;
        return Array.from(document.querySelectorAll(`[${attribute}]`))
            .filter(isRendered)
            .map((el) => ({ name: el.getAttribute(attribute), top: el.getBoundingClientRect().top }))
            .sort((a, b) => a.top - b.top)
            .map((entry) => entry.name);
    }, attribute);
}

/**
 * Tableaux de donnees denses et largeur reellement disponible pour eux.
 * Un tableau est dense des qu'il porte assez de colonnes et de lignes pour
 * qu'une compression horizontale nuise a la lecture.
 *
 * @param {import('@playwright/test').Page} page
 */
async function measureDenseTables(page) {
    await installProbes(page);
    return page.evaluate(() => {
        const { isRendered, describe } = window.__layoutProbes;

        return Array.from(document.querySelectorAll('table'))
            .filter(isRendered)
            .map((table) => {
                const rows = table.querySelectorAll('tbody tr').length;
                const columns = Math.max(
                    0,
                    ...Array.from(table.querySelectorAll('tr')).map((tr) => tr.children.length),
                );
                const rect = table.getBoundingClientRect();
                const scroller = table.closest('.table-responsive, [data-table-scroll]');
                const overflows = scroller ? scroller.scrollWidth > scroller.clientWidth + 1 : false;
                // Une zone defilante doit rester atteignable au clavier (WCAG 2.1.1).
                const reachable = scroller
                    ? scroller.tabIndex >= 0
                        && !!(scroller.getAttribute('aria-label') || scroller.getAttribute('aria-labelledby'))
                    : false;
                return {
                    selector: describe(table),
                    rows,
                    columns,
                    width: Math.round(rect.width),
                    dense: (columns >= 3 && rows >= 4) || (columns >= 2 && rows >= 6),
                    scrollable: !!scroller,
                    scrollerOverflows: overflows,
                    // Un debordement local n'est acceptable que s'il est utilisable au clavier.
                    scrollerAccessible: !overflows || reachable,
                };
            })
            .filter((entry) => entry.dense);
    });
}

/**
 * Longueur de ligne des blocs de prose, exprimee en caracteres.
 *
 * Utiliser toute la largeur disponible ne doit pas produire de lignes de texte
 * interminables : au dela d'environ 75 caracteres la lecture se degrade.
 * La largeur du caractere "0" dans la fonte reellement appliquee sert d'unite,
 * comme le fait l'unite CSS `ch`.
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} maxChars
 */
async function findOverlongProse(page, maxChars = 95) {
    await installProbes(page);
    return page.evaluate((maxChars) => {
        const { isRendered, describe } = window.__layoutProbes;
        const measurer = document.createElement('span');
        measurer.style.cssText = 'position:absolute;visibility:hidden;white-space:pre';
        document.body.appendChild(measurer);

        const overlong = [];
        for (const el of document.querySelectorAll('main p, main li')) {
            if (!isRendered(el)) continue;
            const text = (el.textContent || '').trim();
            // Un bloc court ne peut pas produire une ligne trop longue.
            if (text.length < maxChars) continue;

            const style = getComputedStyle(el);
            measurer.style.font = style.font || `${style.fontSize} ${style.fontFamily}`;
            measurer.textContent = '0';
            const zero = measurer.getBoundingClientRect().width;
            if (!zero) continue;

            const contentWidth = el.getBoundingClientRect().width
                - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            const chars = Math.round(contentWidth / zero);
            if (chars > maxChars) {
                overlong.push({ selector: describe(el), chars, sample: text.slice(0, 60) });
            }
        }

        measurer.remove();
        return overlong;
    }, maxChars);
}

/**
 * Parcourt la page au clavier et verifie que chaque arret porte un indicateur
 * de focus visible. Le parcours utilise de vraies frappes Tab, seule facon de
 * declencher `:focus-visible`.
 *
 * @param {import('@playwright/test').Page} page
 * @param {number} maxStops
 */
async function walkFocusOrder(page, maxStops = 80) {
    await installProbes(page);

    // Etat de repos de chaque element focusable, avant tout focus. C'est la
    // reference : un indicateur de focus est ce qui CHANGE a la prise de focus.
    // Un contour comme une bague d'ombre sont acceptables, une ombre deja
    // presente au repos ne signale rien.
    await page.evaluate(() => {
        const focusable = document.querySelectorAll(
            'a[href], button, input, select, textarea, summary, [tabindex]:not([tabindex="-1"])',
        );
        focusable.forEach((el, index) => {
            const style = getComputedStyle(el);
            el.setAttribute('data-focus-probe', String(index));
            el.setAttribute('data-focus-rest', `${style.outline}|${style.boxShadow}`);
        });
    });

    await page.evaluate(() => document.body.focus());

    const stops = [];
    for (let i = 0; i < maxStops; i++) {
        await page.keyboard.press('Tab');
        const stop = await page.evaluate(() => {
            const el = document.activeElement;
            if (!el || el === document.body) return null;
            const style = getComputedStyle(el);
            const rect = el.getBoundingClientRect();
            const resting = el.getAttribute('data-focus-rest');
            const focused = `${style.outline}|${style.boxShadow}`;
            return {
                selector: window.__layoutProbes.describe(el),
                probe: el.getAttribute('data-focus-probe'),
                inMain: !!el.closest('main'),
                width: Math.round(rect.width),
                height: Math.round(rect.height),
                // Aucune reference relevee : element focusable apparu apres coup.
                focusIndicator: resting === null ? null : focused !== resting,
                focused,
                offscreen: rect.width < 1 || rect.height < 1,
            };
        });
        if (!stop) break;
        stops.push(stop);
        // Le focus a boucle sur le premier arret : le parcours est complet.
        if (stops.length > 2 && stop.probe !== null && stop.probe === stops[0].probe) break;
    }
    return stops;
}

module.exports = {
    installProbes,
    measureColumnBands,
    findFragmentedAmounts,
    measureHorizontalOverflow,
    readLandmarkOrder,
    measureDenseTables,
    findOverlongProse,
    walkFocusOrder,
};

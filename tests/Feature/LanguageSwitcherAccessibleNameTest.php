<?php

namespace Tests\Feature;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Tests\TestCase;

/**
 * WCAG 2.5.3 (Étiquette dans le nom) sur le sélecteur de langue de l'en-tête (#248).
 *
 * axe-core 4.14 signale `label-content-name-mismatch` quand un aria-label remplace le
 * texte visible sans le contenir : un utilisateur de commande vocale qui dit « FR » ne
 * trouve alors pas le lien. Le nom accessible doit contenir le texte visible et garder
 * le nom complet de la langue pour les lecteurs d'écran.
 */
class LanguageSwitcherAccessibleNameTest extends TestCase
{
    public function test_each_language_link_accessible_name_contains_its_visible_text_and_full_label(): void
    {
        $locales = config('app.supported_locales');
        $links = $this->languageLinks($this->get('/')->assertOk()->getContent());

        $this->assertCount(count($locales), $links);

        foreach ($locales as $locale => $localeConfig) {
            $link = $links[$locale] ?? null;
            $this->assertNotNull($link, "Lien du sélecteur absent pour {$locale}");

            $visible = $this->visibleText($link);
            $name = $this->accessibleName($link);

            $this->assertSame($localeConfig['short'], $visible, "Texte visible inattendu pour {$locale}");
            $this->assertStringContainsString($visible, $name, "Le nom accessible de {$locale} ne contient pas son texte visible");
            $this->assertStringContainsString($localeConfig['label'], $name, "Le nom accessible de {$locale} perd le nom complet de la langue");
            $this->assertSame($locale, $link->getAttribute('lang'));
            $this->assertSame($localeConfig['label'], $link->getAttribute('title'));
        }
    }

    public function test_the_rule_holds_in_arabic_rtl(): void
    {
        $html = $this->withSession(['locale' => 'ar'])->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('dir="rtl"', $html);

        foreach ($this->languageLinks($html) as $locale => $link) {
            $this->assertStringContainsString($this->visibleText($link), $this->accessibleName($link), "RTL : nom accessible de {$locale}");
        }
    }

    /** @return array<string, DOMElement> */
    private function languageLinks(string $html): array
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $nodes = (new DOMXPath($dom))->query('//a[contains(concat(" ", normalize-space(@class), " "), " language-switcher-link ")]');

        $links = [];
        foreach ($nodes as $node) {
            $links[$node->getAttribute('hreflang')] = $node;
        }

        return $links;
    }

    /** Nom accessible simplifié : aria-label s'il existe, sinon le texte hors aria-hidden. */
    private function accessibleName(DOMElement $link): string
    {
        if ($link->hasAttribute('aria-label')) {
            return $this->normalize($link->getAttribute('aria-label'));
        }

        return $this->normalize($this->text($link, skipVisuallyHidden: false));
    }

    /** Texte rendu à l'écran : hors aria-hidden et hors texte masqué visuellement. */
    private function visibleText(DOMElement $link): string
    {
        return $this->normalize($this->text($link, skipVisuallyHidden: true));
    }

    private function text(DOMNode $node, bool $skipVisuallyHidden): string
    {
        if ($node instanceof DOMElement) {
            if ($node->getAttribute('aria-hidden') === 'true') {
                return '';
            }
            $classes = ' '.$node->getAttribute('class').' ';
            if ($skipVisuallyHidden && str_contains($classes, ' visually-hidden ')) {
                return '';
            }
        }

        if ($node->nodeType === XML_TEXT_NODE) {
            return $node->textContent;
        }

        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= ' '.$this->text($child, $skipVisuallyHidden);
        }

        return $text;
    }

    private function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}

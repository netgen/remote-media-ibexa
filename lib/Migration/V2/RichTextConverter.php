<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migration\V2;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use LogicException;

use function in_array;
use function iterator_to_array;
use function mb_trim;

/**
 * Converts the "ngremotemedia" custom tags of Netgen Remote Media 2 in RichText DocBook to the
 * Netgen Remote Media 3 format.
 *
 * Netgen Remote Media 2 stored the resource in an inline custom tag (eztemplateinline), mostly inside its own
 * paragraph. Netgen Remote Media 3 stores a remote resource location ID in a block custom tag (eztemplate),
 * so an inline tag takes the place of its paragraph, and the paragraph's content before and after it is kept
 * in two paragraphs around it.
 */
final class RichTextConverter
{
    public const DOCBOOK_NAMESPACE = 'http://docbook.org/ns/docbook';

    private const XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';

    private const TAG_NAME = 'ngremotemedia';

    /**
     * Inline elements which are content even when they are empty.
     */
    private const EMPTY_CONTENT_ELEMENTS = ['ezembedinline', 'eztemplateinline', 'anchor', 'inlinemediaobject'];

    /**
     * @return \Netgen\RemoteMediaIbexa\Migration\V2\RichTextTag[]
     */
    public function findTags(DOMDocument $document): array
    {
        $tags = [];

        foreach ($this->findElements($document) as $element) {
            $tags[] = RichTextTag::fromElement($element);
        }

        return $tags;
    }

    /**
     * Converts every tag for which $resolveLocationId returns a remote resource location ID. Tags for which it
     * returns null, and inline tags outside of a paragraph, are left as they are.
     *
     * @param callable(\Netgen\RemoteMediaIbexa\Migration\V2\RichTextTag): ?int $resolveLocationId
     *
     * @return int number of converted tags
     */
    public function convert(DOMDocument $document, callable $resolveLocationId): int
    {
        $converted = 0;

        foreach ($this->findElements($document) as $element) {
            $paragraph = $element->localName === 'eztemplateinline' ? $this->findParagraph($element) : null;

            if ($element->localName === 'eztemplateinline' && $paragraph === null) {
                continue;
            }

            $tag = RichTextTag::fromElement($element);
            $locationId = $resolveLocationId($tag);

            if ($locationId === null) {
                continue;
            }

            $replacement = $this->createElement($document, $locationId, $tag);

            if ($paragraph instanceof DOMElement) {
                $this->replaceParagraph($paragraph, $element, $replacement);
            } else {
                $element->parentNode?->replaceChild($replacement, $element);
            }

            ++$converted;
        }

        return $converted;
    }

    /**
     * Netgen Remote Media 2 tags: Netgen Remote Media 3 tags have a location ID instead of a resource ID.
     *
     * @return \DOMElement[]
     */
    private function findElements(DOMDocument $document): array
    {
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('docbook', self::DOCBOOK_NAMESPACE);

        $condition = '[@name="' . self::TAG_NAME . '"][docbook:ezconfig/docbook:ezvalue[@key="resourceId"]]';
        $nodes = $xpath->query('//docbook:eztemplateinline' . $condition . ' | //docbook:eztemplate' . $condition);

        $elements = [];

        foreach ($nodes === false ? [] : $nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    private function findParagraph(DOMElement $element): ?DOMElement
    {
        for ($node = $element->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node->localName === 'para') {
                return $node;
            }
        }

        return null;
    }

    private function createElement(DOMDocument $document, int $locationId, RichTextTag $tag): DOMElement
    {
        $element = $document->createElementNS(self::DOCBOOK_NAMESPACE, 'eztemplate');
        $element->setAttribute('name', self::TAG_NAME);

        $config = $document->createElementNS(self::DOCBOOK_NAMESPACE, 'ezconfig');
        $element->appendChild($config);

        $values = [
            'locationId' => (string) $locationId,
            'variationName' => $tag->getVariationName(),
            'cssClass' => $tag->getCssClass(),
            'alignment' => $tag->getAlignment(),
        ];

        foreach ($values as $key => $value) {
            if ($value === null) {
                continue;
            }

            $valueElement = $document->createElementNS(self::DOCBOOK_NAMESPACE, 'ezvalue');
            $valueElement->setAttribute('key', $key);
            $valueElement->appendChild($document->createTextNode($value));
            $config->appendChild($valueElement);
        }

        return $element;
    }

    private function replaceParagraph(DOMElement $paragraph, DOMElement $element, DOMElement $replacement): void
    {
        $parent = $paragraph->parentNode;

        if ($parent === null) {
            return;
        }

        [$before, $after] = $this->split($paragraph, $element);

        if ($this->hasContent($before)) {
            $parent->insertBefore($before, $paragraph);
        }

        $parent->insertBefore($replacement, $paragraph);

        if ($this->hasContent($after)) {
            $parent->insertBefore($after, $paragraph);
        }

        $parent->removeChild($paragraph);
    }

    /**
     * Splits $container into a copy with the content before $target and a copy with the content after it,
     * splitting the elements between them the same way. $target is in neither.
     *
     * @return array{0: \DOMElement, 1: \DOMElement}
     */
    private function split(DOMElement $container, DOMElement $target): array
    {
        $before = $container->cloneNode(false);
        $after = $container->cloneNode(false);

        if (!$before instanceof DOMElement || !$after instanceof DOMElement) {
            throw new LogicException('Cloning an element must result in an element.');
        }

        // An ID must stay unique
        $after->removeAttributeNS(self::XML_NAMESPACE, 'id');

        $current = $before;

        foreach (iterator_to_array($container->childNodes) as $child) {
            if ($child === $target) {
                $current = $after;

                continue;
            }

            if ($child instanceof DOMElement && $child->localName === 'ezattribute') {
                // Custom attributes of the container apply to both parts
                $before->appendChild($child->cloneNode(true));
                $after->appendChild($child);

                continue;
            }

            if ($child instanceof DOMElement && $this->contains($child, $target)) {
                [$childBefore, $childAfter] = $this->split($child, $target);

                if ($this->hasContent($childBefore)) {
                    $before->appendChild($childBefore);
                }

                if ($this->hasContent($childAfter)) {
                    $after->appendChild($childAfter);
                }

                $current = $after;

                continue;
            }

            $current->appendChild($child);
        }

        return [$before, $after];
    }

    private function contains(DOMElement $ancestor, DOMNode $node): bool
    {
        for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
            if ($parent === $ancestor) {
                return true;
            }
        }

        return false;
    }

    private function hasContent(DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMText && mb_trim($child->data) !== '') {
                return true;
            }

            if (!$child instanceof DOMElement || $child->localName === 'ezattribute') {
                continue;
            }

            if (in_array($child->localName, self::EMPTY_CONTENT_ELEMENTS, true) || $this->hasContent($child)) {
                return true;
            }
        }

        return false;
    }
}

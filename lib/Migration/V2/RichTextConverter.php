<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migration\V2;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use DOMXPath;
use LogicException;
use SplObjectStorage;

use function in_array;
use function is_string;
use function iterator_to_array;
use function sprintf;
use function trim;

/**
 * Converts the "ngremotemedia" custom tags of Netgen Remote Media 2 in RichText DocBook to the
 * Netgen Remote Media 3 format.
 *
 * Netgen Remote Media 2 stored the resource in an inline custom tag (eztemplateinline), mostly inside its own
 * paragraph. Netgen Remote Media 3 stores a remote resource location ID in a block custom tag (eztemplate),
 * so an inline tag takes the place of its paragraph, and the paragraph's content before and after it is kept
 * in two paragraphs around it.
 *
 * A list item can contain only paragraphs (Ibexa renders nothing else of it, and the editor removes the rest),
 * so tags in a list are moved right after the list, in their order, and the rest of the list item is kept.
 */
final class RichTextConverter
{
    public const DOCBOOK_NAMESPACE = 'http://docbook.org/ns/docbook';

    public const XHTML_NAMESPACE = 'http://ibexa.co/xmlns/dxp/docbook/xhtml';

    private const XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';

    private const TAG_NAME = 'ngremotemedia';

    /**
     * Inline elements which are content even when they are empty.
     */
    private const EMPTY_CONTENT_ELEMENTS = ['ezembedinline', 'eztemplateinline', 'anchor', 'inlinemediaobject'];

    private const LIST_ELEMENTS = ['itemizedlist', 'orderedlist'];

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
     * returns a reason (a string), tags without a resource ID and inline tags outside of a paragraph are left as
     * they are, with the reason in the result.
     *
     * @param callable(\Netgen\RemoteMediaIbexa\Migration\V2\RichTextTag): (int|string) $resolveLocationId
     */
    public function convert(DOMDocument $document, callable $resolveLocationId): RichTextConversion
    {
        $conversion = new RichTextConversion();

        /** @var \SplObjectStorage<\DOMElement, \DOMElement> $lastMovedTags last tag moved after each list */
        $lastMovedTags = new SplObjectStorage();

        foreach ($this->findElements($document) as $element) {
            $tag = RichTextTag::fromElement($element);

            if ($tag->getResourceId() === '') {
                $conversion->left[] = 'Tag without a resource ID';

                continue;
            }

            $paragraph = $element->localName === 'eztemplateinline' ? $this->findAncestor($element, ['para']) : null;

            if ($element->localName === 'eztemplateinline' && $paragraph === null) {
                $conversion->left[] = sprintf(
                    'Tag of resource "%s" is not in a paragraph (e.g. in a title, or in a table cell without a paragraph)',
                    $tag->getResourceId(),
                );

                continue;
            }

            $locationId = $resolveLocationId($tag);

            if (is_string($locationId)) {
                $conversion->left[] = $locationId;

                continue;
            }

            $replacement = $this->createElement($document, $locationId, $tag);
            $list = $this->findOutermostList($element);

            if ($list instanceof DOMElement) {
                $this->moveAfterList($list, $element, $replacement, $lastMovedTags);
            } elseif ($paragraph instanceof DOMElement) {
                $this->replaceParagraph($paragraph, $element, $replacement);
            } else {
                $element->parentNode?->replaceChild($replacement, $element);
            }

            ++$conversion->converted;
        }

        return $conversion;
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

    /**
     * @param string[] $names
     */
    private function findAncestor(DOMElement $element, array $names): ?DOMElement
    {
        for ($node = $element->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if (in_array($node->localName, $names, true)) {
                return $node;
            }
        }

        return null;
    }

    private function findOutermostList(DOMElement $element): ?DOMElement
    {
        $list = null;

        for ($node = $this->findAncestor($element, self::LIST_ELEMENTS); $node instanceof DOMElement; $node = $this->findAncestor($node, self::LIST_ELEMENTS)) {
            $list = $node;
        }

        return $list;
    }

    /**
     * Removes $element from the list and inserts $replacement after the list, after the tags moved before it.
     * The paragraph of the list item stays, even when it's empty: Ibexa renders a list item from its paragraphs.
     *
     * @param \SplObjectStorage<\DOMElement, \DOMElement> $lastMovedTags
     */
    private function moveAfterList(DOMElement $list, DOMElement $element, DOMElement $replacement, SplObjectStorage $lastMovedTags): void
    {
        $previous = $lastMovedTags->contains($list) ? $lastMovedTags[$list] : $list;
        $list->parentNode?->insertBefore($replacement, $previous->nextSibling);
        $lastMovedTags[$list] = $replacement;

        $element->parentNode?->removeChild($element);
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
            if ($child instanceof DOMText && trim($child->data) !== '') {
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

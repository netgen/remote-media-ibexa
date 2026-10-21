<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Migration\V2;

use DOMElement;

use function in_array;
use function is_array;
use function json_decode;
use function trim;

/**
 * Values of a Netgen Remote Media 2 "ngremotemedia" RichText custom tag.
 */
final class RichTextTag
{
    /**
     * Netgen Remote Media 2 "align" values mapped to CKEditor 5 alignment values.
     */
    private const ALIGNMENTS = [
        'left' => 'left',
        'right' => 'right',
        'center' => 'center',
        'middle' => 'center',
    ];

    /**
     * @param array<string, string> $values
     */
    private function __construct(
        private readonly array $values,
    ) {}

    public static function fromElement(DOMElement $element): self
    {
        $values = [];

        foreach ($element->getElementsByTagNameNS(RichTextConverter::DOCBOOK_NAMESPACE, 'ezvalue') as $valueElement) {
            $values[$valueElement->getAttribute('key')] = trim($valueElement->textContent);
        }

        return new self($values);
    }

    public function getResourceId(): string
    {
        return $this->values['resourceId'] ?? '';
    }

    public function getResourceType(): string
    {
        return $this->getValue('resourceType') ?? 'image';
    }

    /**
     * Crops as stored by Netgen Remote Media 2 (a map of variation names to {x, y, w, h}).
     *
     * @return array<mixed>
     */
    public function getCoords(): array
    {
        $coords = json_decode($this->values['coords'] ?? '', true);

        return is_array($coords) ? $coords : [];
    }

    public function getVariationName(): ?string
    {
        return $this->getValue('variation');
    }

    public function getAlignment(): ?string
    {
        return self::ALIGNMENTS[$this->getValue('align') ?? ''] ?? null;
    }

    public function getCssClass(): ?string
    {
        return $this->getValue('cssclass');
    }

    /**
     * Netgen Remote Media 3 keeps the caption on the resource, not in the custom tag.
     */
    public function getCaption(): ?string
    {
        return $this->getValue('caption');
    }

    /**
     * Value of the given key, null when it's missing or empty (Netgen Remote Media 2 also stored "null").
     */
    private function getValue(string $key): ?string
    {
        $value = $this->values[$key] ?? '';

        return in_array($value, ['', 'null'], true) ? null : $value;
    }
}

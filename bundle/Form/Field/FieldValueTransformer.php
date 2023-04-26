<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Form\Field;

use Ibexa\Contracts\Core\Repository\FieldType;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use Netgen\RemoteMediaIbexa\FieldType\Value;
use Symfony\Component\Form\DataTransformerInterface;

class FieldValueTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly FieldType $fieldType,
    ) {
    }

    public function transform($value): ?RemoteResourceLocation
    {
        if (!$value instanceof Value) {
            return null;
        }

        return $value->remoteResourceLocation;
    }

    public function reverseTransform(mixed $value): Value
    {
        if ($value instanceof RemoteResourceLocation) {
            return new Value($value);
        }

        return $this->fieldType->getEmptyValue();
    }
}

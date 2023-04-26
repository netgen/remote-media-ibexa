<?php

declare(strict_types=1);

namespace Netgen\Bundle\RemoteMediaIbexaBundle\Form\FieldDefinition;

use Netgen\RemoteMedia\API\Values\Folder;
use Symfony\Component\Form\DataTransformerInterface;

use function is_string;

class Transformer implements DataTransformerInterface
{
    public function transform($value): ?Folder
    {
        if (!is_string($value)) {
            return null;
        }

        return Folder::fromPath($value);
    }

    public function reverseTransform(mixed $value): ?string
    {
        if ($value instanceof Folder) {
            return $value->getPath();
        }

        return null;
    }
}

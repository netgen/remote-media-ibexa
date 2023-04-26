<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\FieldType\FieldStorage\Gateway;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use Ibexa\Contracts\Core\Persistence\Content\Field;
use Ibexa\Contracts\Core\Persistence\Content\VersionInfo;
use Netgen\RemoteMedia\API\Values\RemoteResourceLocation;
use Netgen\RemoteMediaIbexa\API\Values\NgRemoteMediaContentLink;
use Netgen\RemoteMediaIbexa\Converter\Hash as HashConverter;
use Netgen\RemoteMediaIbexa\FieldType\FieldStorage\Gateway;

use function array_keys;
use function count;
use function is_array;

class DoctrineStorage extends Gateway
{
    private readonly ObjectRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly HashConverter $hashConverter,
    ) {
        $this->repository = $this->entityManager->getRepository(NgRemoteMediaContentLink::class);
    }

    public function storeFieldData(VersionInfo $versionInfo, Field $field): ?bool
    {
        $hash = $field->value->externalData;

        $contentLink = $this->repository->findOneBy([
            'fieldId' => $field->id,
            'version' => $versionInfo->versionNo,
        ]);

        if (!is_array($hash) || count(array_keys($hash)) === 0) {
            if ($contentLink instanceof NgRemoteMediaContentLink) {
                $this->entityManager->remove($contentLink);
                $this->entityManager->flush();
            }

            return null;
        }

        unset($hash['remote_resource_location_id']);
        $remoteResourceLocation = $this->hashConverter->remoteResourceLocationFromHash($hash);

        if (!$remoteResourceLocation instanceof RemoteResourceLocation) {
            return null;
        }

        if (!$contentLink instanceof NgRemoteMediaContentLink) {
            $contentLink = new NgRemoteMediaContentLink(
                fieldId: $field->id,
                version: $versionInfo->versionNo,
                remoteResourceLocation: $remoteResourceLocation,
            );
        }

        $contentLink->remoteResourceLocation = $remoteResourceLocation;

        $this->entityManager->persist($contentLink);
        $this->entityManager->flush();

        return true;
    }

    public function getFieldData(VersionInfo $versionInfo, Field $field): void
    {
        $contentLink = $this->repository->findOneBy([
            'fieldId' => $field->id,
            'version' => $versionInfo->versionNo,
        ]);

        if (!$contentLink instanceof NgRemoteMediaContentLink) {
            return;
        }

        $field->value->externalData = $this->hashConverter->remoteResourceLocationToHash($contentLink->remoteResourceLocation);
        $field->value->externalData['remote_resource_location_id'] = $contentLink->remoteResourceLocation->getId();
    }

    public function deleteFieldData(VersionInfo $versionInfo, array $fieldIds): void
    {
        $contentLinks = $this->repository->findBy([
            'fieldId' => $fieldIds,
            'version' => $versionInfo->versionNo,
        ]);

        foreach ($contentLinks as $contentLink) {
            $this->entityManager->remove($contentLink);
        }

        $this->entityManager->flush();
    }
}

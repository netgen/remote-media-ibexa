<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\RichText;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use DOMDocument;
use DOMXPath;

use function array_fill;
use function array_keys;
use function array_map;
use function count;
use function implode;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function mb_trim;
use function preg_match;
use function str_contains;

use const LIBXML_NONET;

/**
 * Finds remote resource locations referenced by the "ngremotemedia" custom tag in the RichText XML.
 */
final class LocationGateway
{
    private const DOCBOOK_NAMESPACE = 'http://docbook.org/ns/docbook';

    private const TAG_NAME = 'ngremotemedia';

    public function __construct(
        private readonly Connection $connection,
    ) {}

    /**
     * Returns the IDs of the locations referenced by the RichText fields with the given IDs in the given version.
     *
     * @param int[] $fieldIds
     *
     * @return int[]
     */
    public function loadLocationIds(array $fieldIds, int $versionNo): array
    {
        if (count($fieldIds) === 0) {
            return [];
        }

        $documents = $this->connection->fetchFirstColumn(
            'SELECT data_text FROM ezcontentobject_attribute WHERE id IN (?) AND version = ?',
            [$fieldIds, $versionNo],
            [Connection::PARAM_INT_ARRAY, ParameterType::INTEGER],
        );

        $locationIds = [];
        foreach ($documents as $document) {
            foreach ($this->extractLocationIds((string) $document) ?? [] as $locationId) {
                $locationIds[$locationId] = true;
            }
        }

        return array_keys($locationIds);
    }

    /**
     * Returns the IDs of the given locations which are still referenced by a RichText field of any version and
     * content, or by a field of the Netgen Remote Media field type.
     *
     * @param int[] $locationIds
     *
     * @return int[]
     */
    public function loadReferencedLocationIds(array $locationIds): array
    {
        if (count($locationIds) === 0) {
            return [];
        }

        $referencedIds = [];

        $fieldTypeLocationIds = $this->connection->fetchFirstColumn(
            'SELECT remote_resource_location_id FROM ngrm_remote_media_content_link WHERE remote_resource_location_id IN (?)',
            [$locationIds],
            [Connection::PARAM_INT_ARRAY],
        );

        foreach ($fieldTypeLocationIds as $locationId) {
            $referencedIds[(int) $locationId] = true;
        }

        // LIKE narrows down the documents, the custom tag stores the location ID as the text of an "ezvalue" element.
        // The documents are parsed to find the actual references.
        $documents = $this->connection->fetchFirstColumn(
            'SELECT data_text FROM ezcontentobject_attribute WHERE data_type_string = ? AND data_text LIKE ? AND ('
            . implode(' OR ', array_fill(0, count($locationIds), 'data_text LIKE ?')) . ')',
            [
                'ezrichtext',
                '%' . self::TAG_NAME . '%',
                ...array_map(static fn (int $locationId): string => '%>' . $locationId . '</%', $locationIds),
            ],
        );

        foreach ($documents as $document) {
            $document = (string) $document;
            $documentLocationIds = $this->extractLocationIds($document);

            if ($documentLocationIds === null) {
                // A document which can't be parsed keeps every location it might reference
                foreach ($locationIds as $locationId) {
                    if (str_contains($document, '>' . $locationId . '</')) {
                        $referencedIds[$locationId] = true;
                    }
                }

                continue;
            }

            foreach ($documentLocationIds as $locationId) {
                $referencedIds[$locationId] = true;
            }
        }

        return array_keys($referencedIds);
    }

    /**
     * @return int[]|null null when the document can't be parsed
     */
    private function extractLocationIds(string $document): ?array
    {
        if ($document === '') {
            return [];
        }

        if (!str_contains($document, self::TAG_NAME)) {
            return [];
        }

        $domDocument = new DOMDocument();
        $useInternalErrors = libxml_use_internal_errors(true);
        $isLoaded = $domDocument->loadXML($document, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($useInternalErrors);

        if (!$isLoaded) {
            return null;
        }

        $xpath = new DOMXPath($domDocument);
        $xpath->registerNamespace('docbook', self::DOCBOOK_NAMESPACE);

        $valueNodes = $xpath->query(
            '//docbook:*[self::docbook:eztemplate or self::docbook:eztemplateinline][@name="' . self::TAG_NAME . '"]'
            . '/docbook:ezconfig/docbook:ezvalue[@key="locationId"]',
        );

        $locationIds = [];
        foreach ($valueNodes !== false ? $valueNodes : [] as $valueNode) {
            $locationId = mb_trim($valueNode->textContent);

            if (preg_match('/^[1-9]\d*$/', $locationId) === 1) {
                $locationIds[] = (int) $locationId;
            }
        }

        return $locationIds;
    }
}

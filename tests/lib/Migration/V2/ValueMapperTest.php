<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Tests\Migration\V2;

use Netgen\RemoteMediaIbexa\Migration\V2\ValueMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValueMapper::class)]
final class ValueMapperTest extends TestCase
{
    private ValueMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ValueMapper();
    }

    /**
     * @param array<string, mixed> $value
     */
    #[DataProvider('provideRemoteIdCases')]
    public function testToRemoteIdFromFieldValue(array $value, ?string $expected): void
    {
        self::assertSame($expected, $this->mapper->toRemoteIdFromFieldValue($value));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: ?string}>
     */
    public static function provideRemoteIdCases(): iterable
    {
        yield 'complete' => [['resourceId' => 'folder/file.pdf', 'resourceType' => 'raw', 'type' => 'upload'], 'upload|raw|folder/file.pdf'];

        yield 'no delivery type' => [['resourceId' => 'image', 'resourceType' => 'video', 'type' => null], 'upload|video|image'];

        yield 'empty resource ID' => [['resourceId' => '', 'resourceType' => 'image'], null];

        yield 'no resource ID' => [['resourceType' => 'image'], null];
    }

    public function testToCloudinaryData(): void
    {
        $data = $this->mapper->toCloudinaryData(
            [
                'resourceId' => 'folder/image',
                'resourceType' => 'image',
                'type' => 'upload',
                'url' => 'http://res.cloudinary.com/demo/image/upload/v1/folder/image.png',
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/folder/image.png',
                'size' => 370013,
                'mediaType' => 'image',
                'variations' => [],
                'metaData' => [
                    'version' => 1751014126,
                    'width' => 770,
                    'height' => 770,
                    'format' => 'png',
                    'created' => '2025-06-27T08:48:46Z',
                    'tags' => ['product'],
                    'signature' => '',
                    'etag' => '',
                    'overwritten' => '',
                    'alt_text' => 'Alt text',
                    'caption' => '',
                ],
            ],
        );

        self::assertSame(
            [
                'public_id' => 'folder/image',
                'resource_type' => 'image',
                'type' => 'upload',
                'url' => 'http://res.cloudinary.com/demo/image/upload/v1/folder/image.png',
                'secure_url' => 'https://res.cloudinary.com/demo/image/upload/v1/folder/image.png',
                'bytes' => 370013,
                'version' => 1751014126,
                'format' => 'png',
                'width' => 770,
                'height' => 770,
                'created_at' => '2025-06-27T08:48:46Z',
                'tags' => ['product'],
                'context' => ['custom' => ['alt_text' => 'Alt text']],
            ],
            $data,
        );
    }

    public function testToCropSettings(): void
    {
        $cropSettings = $this->mapper->toCropSettings(
            [
                'large' => ['x' => 10, 'y' => '20', 'w' => 300, 'h' => 200],
                'small' => null,
                'medium' => ['x' => 10, 'y' => 20, 'w' => 300],
            ],
        );

        self::assertCount(1, $cropSettings);
        self::assertSame(
            ['large', 10, 20, 300, 200],
            [
                $cropSettings[0]->getVariationName(),
                $cropSettings[0]->getX(),
                $cropSettings[0]->getY(),
                $cropSettings[0]->getWidth(),
                $cropSettings[0]->getHeight(),
            ],
        );
    }

    public function testToCropSettingsWithoutCrops(): void
    {
        self::assertSame([], $this->mapper->toCropSettings([]));
        self::assertSame([], $this->mapper->toCropSettings(null));
    }
}

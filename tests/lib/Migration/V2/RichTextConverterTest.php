<?php

declare(strict_types=1);

namespace Netgen\RemoteMediaIbexa\Tests\Migration\V2;

use DOMDocument;
use Netgen\RemoteMediaIbexa\Migration\V2\RichTextConverter;
use Netgen\RemoteMediaIbexa\Migration\V2\RichTextTag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function preg_replace;
use function sprintf;

#[CoversClass(RichTextConverter::class)]
#[CoversClass(RichTextTag::class)]
final class RichTextConverterTest extends TestCase
{
    private RichTextConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new RichTextConverter();
    }

    public function testConvertTagAloneInParagraph(): void
    {
        $document = $this->createDocument(
            '<para>Before</para><para>' . $this->createV2Tag('folder/image', 'aligned', 'middle', 'wide') . '</para><para>After</para>',
        );

        self::assertSame(1, $this->converter->convert($document, static fn (): int => 42)->converted);
        self::assertSame(
            '<para>Before</para>'
            . '<eztemplate name="ngremotemedia"><ezconfig>'
            . '<ezvalue key="locationId">42</ezvalue><ezvalue key="variationName">aligned</ezvalue>'
            . '<ezvalue key="cssClass">wide</ezvalue><ezvalue key="alignment">center</ezvalue>'
            . '</ezconfig></eztemplate>'
            . '<para>After</para>',
            $this->getContent($document),
        );
    }

    public function testConvertLeavesOutEmptyAndNullValues(): void
    {
        $document = $this->createDocument('<para>' . $this->createV2Tag('image', 'null', 'null', '') . '</para>');

        $this->converter->convert($document, static fn (): int => 42);

        self::assertSame(
            '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">42</ezvalue></ezconfig></eztemplate>',
            $this->getContent($document),
        );
    }

    public function testConvertSplitsParagraphAroundTag(): void
    {
        $document = $this->createDocument(
            '<para xml:id="anchor" ezxhtml:class="lead">Text before ' . $this->createV2Tag('image') . ' text after</para>',
        );

        $this->converter->convert($document, static fn (): int => 42);

        self::assertSame(
            '<para xml:id="anchor" ezxhtml:class="lead">Text before </para>'
            . '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">42</ezvalue></ezconfig></eztemplate>'
            . '<para ezxhtml:class="lead"> text after</para>',
            $this->getContent($document),
        );
    }

    public function testConvertSplitsElementsBetweenParagraphAndTag(): void
    {
        $document = $this->createDocument(
            '<para><emphasis>' . $this->createV2Tag('image') . ' caption</emphasis></para>',
        );

        $this->converter->convert($document, static fn (): int => 42);

        self::assertSame(
            '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">42</ezvalue></ezconfig></eztemplate>'
            . '<para><emphasis> caption</emphasis></para>',
            $this->getContent($document),
        );
    }

    public function testConvertMultipleTagsInParagraph(): void
    {
        $document = $this->createDocument(
            '<para>' . $this->createV2Tag('first') . 'between' . $this->createV2Tag('second') . '</para>',
        );

        $locationIds = ['first' => 1, 'second' => 2];

        self::assertSame(
            2,
            $this->converter->convert(
                $document,
                static fn (RichTextTag $tag): int => $locationIds[$tag->getResourceId()],
            )->converted,
        );

        self::assertSame(
            '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">1</ezvalue></ezconfig></eztemplate>'
            . '<para>between</para>'
            . '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">2</ezvalue></ezconfig></eztemplate>',
            $this->getContent($document),
        );
    }

    public function testConvertLeavesUnresolvedTag(): void
    {
        $document = $this->createDocument('<para>' . $this->createV2Tag('missing') . '</para>');
        $content = $this->getContent($document);

        $conversion = $this->converter->convert($document, static fn (): string => 'Resource "missing" doesn\'t exist on the remote');

        self::assertSame(0, $conversion->converted);
        self::assertSame(['Resource "missing" doesn\'t exist on the remote'], $conversion->left);
        self::assertSame($content, $this->getContent($document));
        self::assertCount(1, $this->converter->findTags($document));
    }

    public function testConvertLeavesInlineTagOutsideOfParagraph(): void
    {
        $document = $this->createDocument('<title ezxhtml:level="2">' . $this->createV2Tag('image') . '</title>');
        $content = $this->getContent($document);

        $conversion = $this->converter->convert(
            $document,
            static fn (): int => self::fail('A tag which can\'t be converted must not get a location'),
        );

        self::assertSame(0, $conversion->converted);
        self::assertSame(
            ['Tag of resource "image" is not in a paragraph (e.g. in a title, or in a table cell without a paragraph)'],
            $conversion->left,
        );
        self::assertSame($content, $this->getContent($document));
    }

    public function testConvertLeavesTagWithoutResourceId(): void
    {
        $document = $this->createDocument('<para>' . $this->createV2Tag('') . '</para>');

        $conversion = $this->converter->convert(
            $document,
            static fn (): int => self::fail('A tag without a resource must not get a location'),
        );

        self::assertSame(['Tag without a resource ID'], $conversion->left);
    }

    public function testConvertLeavesTagWithoutResourceIdValue(): void
    {
        $document = $this->createDocument(
            '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="variation">small</ezvalue></ezconfig></eztemplate>',
        );

        $conversion = $this->converter->convert(
            $document,
            static fn (): int => self::fail('A tag without a resource must not get a location'),
        );

        self::assertSame(['Tag without a resource ID'], $conversion->left);
        self::assertCount(1, $this->converter->findTags($document));
    }

    public function testConvertMovesTagsInListsAfterTheList(): void
    {
        $document = $this->createDocument(
            '<para>Before</para>'
            . '<itemizedlist>'
            . '<listitem><para>First ' . $this->createV2Tag('first') . '</para></listitem>'
            . '<listitem><para>' . $this->createV2Tag('second') . '</para>'
            . '<orderedlist><listitem><para>Nested ' . $this->createV2Tag('third') . '</para></listitem></orderedlist></listitem>'
            . '<listitem><para>' . $this->createV2Tag('fourth') . '</para></listitem>'
            . '</itemizedlist>'
            . '<para>After</para>',
        );

        $locationIds = ['first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4];

        $conversion = $this->converter->convert(
            $document,
            static fn (RichTextTag $tag): int => $locationIds[$tag->getResourceId()],
        );

        self::assertSame(4, $conversion->converted);
        // Ibexa renders only paragraphs of list items: tags follow the outermost list, in their order, the
        // paragraphs of list items with other content stay, even when they're left empty, and list items left
        // without content are removed
        self::assertSame(
            '<para>Before</para>'
            . '<itemizedlist>'
            . '<listitem><para>First </para></listitem>'
            . '<listitem><para/><orderedlist><listitem><para>Nested </para></listitem></orderedlist></listitem>'
            . '</itemizedlist>'
            . '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">1</ezvalue></ezconfig></eztemplate>'
            . '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">2</ezvalue></ezconfig></eztemplate>'
            . '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">3</ezvalue></ezconfig></eztemplate>'
            . '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">4</ezvalue></ezconfig></eztemplate>'
            . '<para>After</para>',
            $this->getContent($document),
        );
    }

    public function testConvertRemovesListLeftWithoutContent(): void
    {
        $document = $this->createDocument(
            '<itemizedlist><listitem><para>' . $this->createV2Tag('first') . '</para>'
            . '<itemizedlist><listitem><para> ' . $this->createV2Tag('second') . '</para></listitem></itemizedlist>'
            . '</listitem></itemizedlist>',
        );

        $this->converter->convert($document, static fn (RichTextTag $tag): int => $tag->getResourceId() === 'first' ? 1 : 2);

        self::assertSame(
            '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">1</ezvalue></ezconfig></eztemplate>'
            . '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">2</ezvalue></ezconfig></eztemplate>',
            $this->getContent($document),
        );
    }

    public function testConvertKeepsOrderOfTagsInListAcrossRuns(): void
    {
        $document = $this->createDocument(
            '<itemizedlist>'
            . '<listitem><para>A ' . $this->createV2Tag('a') . '</para></listitem>'
            . '<listitem><para>B ' . $this->createV2Tag('b') . '</para></listitem>'
            . '<listitem><para>C ' . $this->createV2Tag('c') . '</para></listitem>'
            . '</itemizedlist>',
        );

        $locationIds = ['a' => 1, 'b' => 'Resource "b" doesn\'t exist on the remote', 'c' => 3];

        $conversion = $this->converter->convert($document, static fn (RichTextTag $tag): int|string => $locationIds[$tag->getResourceId()]);

        self::assertSame(2, $conversion->converted);
        self::assertSame(['Resource "b" doesn\'t exist on the remote'], $conversion->left);

        // The resource is fixed for the next run
        $locationIds['b'] = 2;
        $conversion = $this->converter->convert($document, static fn (RichTextTag $tag): int => $locationIds[$tag->getResourceId()]);

        self::assertSame(1, $conversion->converted);
        self::assertSame(
            '<itemizedlist>'
            . '<listitem><para>A </para></listitem>'
            . '<listitem><para>B </para></listitem>'
            . '<listitem><para>C </para></listitem>'
            . '</itemizedlist>'
            . '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">1</ezvalue></ezconfig></eztemplate>'
            . '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">2</ezvalue></ezconfig></eztemplate>'
            . '<eztemplate name="ngremotemedia"><ezconfig><ezvalue key="locationId">3</ezvalue></ezconfig></eztemplate>',
            $this->getContent($document),
        );
    }

    public function testFindTagsSkipsConvertedTags(): void
    {
        $document = $this->createDocument('<para>' . $this->createV2Tag('image') . '</para>');

        $this->converter->convert($document, static fn (): int => 42);

        self::assertSame([], $this->converter->findTags($document));
    }

    public function testFindTags(): void
    {
        $document = $this->createDocument(
            '<para>' . $this->createV2Tag('folder/image', 'standard', 'left', 'wide', '{"small":{"x":1,"y":2,"w":3,"h":4}}') . '</para>',
        );

        $tags = $this->converter->findTags($document);

        self::assertCount(1, $tags);
        self::assertSame('folder/image', $tags[0]->getResourceId());
        self::assertSame('image', $tags[0]->getResourceType());
        self::assertSame('standard', $tags[0]->getVariationName());
        self::assertSame('left', $tags[0]->getAlignment());
        self::assertSame('wide', $tags[0]->getCssClass());
        self::assertSame(['small' => ['x' => 1, 'y' => 2, 'w' => 3, 'h' => 4]], $tags[0]->getCoords());
    }

    public function testFindTagsReadsAlloyEditorAlignment(): void
    {
        // AlloyEditor stored the alignment only as an attribute of the tag, "align" values take precedence
        $document = $this->createDocument(
            '<eztemplate name="ngremotemedia" ezxhtml:align="right"><ezconfig><ezvalue key="resourceId">first</ezvalue></ezconfig></eztemplate>'
            . '<eztemplate name="ngremotemedia" ezxhtml:align="right"><ezconfig><ezvalue key="resourceId">second</ezvalue>'
            . '<ezvalue key="align">middle</ezvalue></ezconfig></eztemplate>',
        );

        $tags = $this->converter->findTags($document);

        self::assertSame('right', $tags[0]->getAlignment());
        self::assertSame('center', $tags[1]->getAlignment());
    }

    private function createV2Tag(
        string $resourceId,
        string $variation = '',
        string $align = '',
        string $cssClass = '',
        string $coords = '[]',
    ): string {
        return sprintf(
            '<eztemplateinline name="ngremotemedia"><ezconfig><ezvalue key="caption"/>'
            . '<ezvalue key="cssclass">%s</ezvalue><ezvalue key="coords">%s</ezvalue>'
            . '<ezvalue key="resourceId">%s</ezvalue><ezvalue key="resourceType">image</ezvalue>'
            . '<ezvalue key="image_url">https://res.cloudinary.com/demo/image/upload/%s</ezvalue>'
            . '<ezvalue key="variation">%s</ezvalue><ezvalue key="align">%s</ezvalue>'
            . '</ezconfig></eztemplateinline>',
            $cssClass,
            $coords,
            $resourceId,
            $resourceId,
            $variation,
            $align,
        );
    }

    private function createDocument(string $content): DOMDocument
    {
        $document = new DOMDocument();
        $document->loadXML(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<section xmlns="http://docbook.org/ns/docbook" xmlns:xlink="http://www.w3.org/1999/xlink"'
            . ' xmlns:ezxhtml="http://ibexa.co/xmlns/dxp/docbook/xhtml" xmlns:ezcustom="http://ibexa.co/xmlns/dxp/docbook/custom"'
            . ' version="5.0-variant ezpublish-1.0">' . $content . '</section>',
        );

        return $document;
    }

    private function getContent(DOMDocument $document): string
    {
        $xml = (string) $document->saveXML($document->documentElement);

        return (string) preg_replace('#^<section[^>]*>(.*)</section>$#s', '$1', $xml);
    }
}

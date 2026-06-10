<?php

namespace yellowrobot\paperclip\tests\unit;

use PHPUnit\Framework\TestCase;
use yellowrobot\paperclip\drivers\CloudflareDriver;

class CloudflareDriverTest extends TestCase
{
    private CloudflareDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new CloudflareDriver();
    }

    // ------------------------------------------------------------------
    // Request body shape
    // ------------------------------------------------------------------

    public function testBodyUsesCamelCasePdfOptionsKey(): void
    {
        $this->driver->loadHtml('<p>Hello</p>');
        $body = $this->driver->buildRequestBody();

        $this->assertArrayHasKey('pdfOptions', $body);
        $this->assertArrayNotHasKey('pdf_options', $body);
    }

    public function testBodyWaitsForExternalResources(): void
    {
        $this->driver->loadHtml('<p>Hello</p>');
        $body = $this->driver->buildRequestBody();

        $this->assertSame(['load', 'networkidle0'], $body['gotoOptions']['waitUntil']);
    }

    public function testHtmlAndUrlAreMutuallyExclusive(): void
    {
        $this->driver->loadHtml('<p>Hello</p>');
        $body = $this->driver->buildRequestBody();
        $this->assertSame('<p>Hello</p>', $body['html']);
        $this->assertArrayNotHasKey('url', $body);

        $this->driver->loadUrl('https://example.com');
        $body = $this->driver->buildRequestBody();
        $this->assertSame('https://example.com', $body['url']);
        $this->assertArrayNotHasKey('html', $body);
    }

    // ------------------------------------------------------------------
    // Paper size
    // ------------------------------------------------------------------

    public function testStandardPaperUsesFormatEnum(): void
    {
        $this->driver->loadHtml('<p>Hello</p>');
        $this->driver->setPaper('A4', 'portrait');
        $options = $this->driver->buildRequestBody()['pdfOptions'];

        $this->assertSame('a4', $options['format']);
        $this->assertArrayNotHasKey('width', $options);
        $this->assertArrayNotHasKey('height', $options);
    }

    public function testUnknownFormatFallsBackToLetter(): void
    {
        $this->driver->loadHtml('<p>Hello</p>');
        $this->driver->setPaper('B5', 'portrait');
        $options = $this->driver->buildRequestBody()['pdfOptions'];

        $this->assertSame('letter', $options['format']);
    }

    public function testCustomSizeUsesWidthAndHeight(): void
    {
        $this->driver->loadHtml('<p>Hello</p>');
        $this->driver->setPaperSize(210, 297, 'mm');
        $options = $this->driver->buildRequestBody()['pdfOptions'];

        $this->assertArrayNotHasKey('format', $options);
        $this->assertSame(210 / 25.4 . 'in', $options['width']);
        $this->assertSame(297 / 25.4 . 'in', $options['height']);
    }

    public function testLandscapeOrientation(): void
    {
        $this->driver->loadHtml('<p>Hello</p>');
        $this->driver->setPaper('Letter', 'landscape');
        $options = $this->driver->buildRequestBody()['pdfOptions'];

        $this->assertTrue($options['landscape']);
    }

    // ------------------------------------------------------------------
    // Other options
    // ------------------------------------------------------------------

    public function testMarginsHeaderFooterAndScale(): void
    {
        $this->driver->loadHtml('<p>Hello</p>');
        $this->driver->setMargins(1, 1, 1, 1, 'in');
        $this->driver->setHeaderHtml('<span>Header</span>');
        $this->driver->setFooterHtml('<span>Footer</span>');
        $this->driver->setScale(0.8);
        $this->driver->setPages('1-2');
        $this->driver->setTagged(true);
        $options = $this->driver->buildRequestBody()['pdfOptions'];

        $this->assertSame('1in', $options['margin']['top']);
        $this->assertTrue($options['displayHeaderFooter']);
        $this->assertSame('<span>Header</span>', $options['headerTemplate']);
        $this->assertSame('<span>Footer</span>', $options['footerTemplate']);
        $this->assertSame(0.8, $options['scale']);
        $this->assertSame('1-2', $options['pageRanges']);
        $this->assertTrue($options['tagged']);
    }
}

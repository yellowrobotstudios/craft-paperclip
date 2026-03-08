<?php

namespace yellowrobot\paperclip\tests\unit;

use PHPUnit\Framework\TestCase;
use yellowrobot\paperclip\models\Settings;

class SettingsTest extends TestCase
{
    // ------------------------------------------------------------------
    // Defaults
    // ------------------------------------------------------------------

    public function testDefaultDriver(): void
    {
        $settings = new Settings();
        $this->assertSame('dompdf', $settings->driver);
    }

    public function testDefaultFormat(): void
    {
        $settings = new Settings();
        $this->assertSame('Letter', $settings->defaultFormat);
    }

    public function testDefaultTimeout(): void
    {
        $settings = new Settings();
        $this->assertSame(60, $settings->timeout);
    }

    public function testDefaultNoSandbox(): void
    {
        $settings = new Settings();
        $this->assertFalse($settings->noSandbox);
    }

    public function testNullableSettingsAreNull(): void
    {
        $settings = new Settings();

        $this->assertNull($settings->dompdfFontDir);
        $this->assertNull($settings->dompdfDefaultFont);
        $this->assertNull($settings->dompdfDpi);
        $this->assertNull($settings->nodePath);
        $this->assertNull($settings->npmPath);
        $this->assertNull($settings->chromePath);
        $this->assertNull($settings->gotenbergUrl);
        $this->assertNull($settings->cloudflareApiToken);
        $this->assertNull($settings->cloudflareAccountId);
        $this->assertNull($settings->weasyprintBinary);
    }

    // ------------------------------------------------------------------
    // Validation
    // ------------------------------------------------------------------

    public function testValidDriversPass(): void
    {
        foreach (['dompdf', 'browsershot', 'gotenberg', 'cloudflare', 'weasyprint'] as $driver) {
            $settings = new Settings();
            $settings->driver = $driver;
            $this->assertTrue($settings->validate(['driver']), "Driver '{$driver}' should be valid");
        }
    }

    public function testInvalidDriverFails(): void
    {
        $settings = new Settings();
        $settings->driver = 'wkhtmltopdf';
        $this->assertFalse($settings->validate(['driver']));
    }

    public function testEmptyDriverFails(): void
    {
        $settings = new Settings();
        $settings->driver = '';
        $this->assertFalse($settings->validate(['driver']));
    }

    public function testValidFormatsPasses(): void
    {
        foreach (['Letter', 'Legal', 'A4', 'A3', 'Tabloid'] as $format) {
            $settings = new Settings();
            $settings->defaultFormat = $format;
            $this->assertTrue($settings->validate(['defaultFormat']), "Format '{$format}' should be valid");
        }
    }

    public function testInvalidFormatFails(): void
    {
        $settings = new Settings();
        $settings->defaultFormat = 'Huge';
        $this->assertFalse($settings->validate(['defaultFormat']));
    }

    public function testDpiValidation(): void
    {
        $settings = new Settings();

        $settings->dompdfDpi = 96;
        $this->assertTrue($settings->validate(['dompdfDpi']));

        $settings->dompdfDpi = 72;
        $this->assertTrue($settings->validate(['dompdfDpi']));

        $settings->dompdfDpi = 300;
        $this->assertTrue($settings->validate(['dompdfDpi']));

        $settings->dompdfDpi = 10;
        $this->assertFalse($settings->validate(['dompdfDpi']));

        $settings->dompdfDpi = 500;
        $this->assertFalse($settings->validate(['dompdfDpi']));
    }

    public function testTimeoutValidation(): void
    {
        $settings = new Settings();

        $settings->timeout = 30;
        $this->assertTrue($settings->validate(['timeout']));

        $settings->timeout = 0;
        $this->assertFalse($settings->validate(['timeout']));

        $settings->timeout = 301;
        $this->assertFalse($settings->validate(['timeout']));
    }
}

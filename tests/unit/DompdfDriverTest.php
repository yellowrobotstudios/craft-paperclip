<?php

namespace yellowrobot\paperclip\tests\unit;

use PHPUnit\Framework\TestCase;
use yellowrobot\paperclip\drivers\DompdfDriver;
use yellowrobot\paperclip\drivers\UnsupportedDriverFeatureException;

class DompdfDriverTest extends TestCase
{
    private DompdfDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new DompdfDriver();
    }

    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../fixtures/' . $name);
    }

    // ------------------------------------------------------------------
    // Basic rendering
    // ------------------------------------------------------------------

    public function testRenderProducesValidPdf(): void
    {
        $this->driver->loadHtml($this->fixture('simple.html'));
        $output = $this->driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertGreaterThan(100, strlen($output));
    }

    public function testRenderMinimalHtml(): void
    {
        $this->driver->loadHtml('<p>Hello</p>');
        $output = $this->driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function testRenderEmptyHtml(): void
    {
        $this->driver->loadHtml('');
        $output = $this->driver->render();

        // Even empty HTML produces a valid PDF
        $this->assertStringStartsWith('%PDF-', $output);
    }

    // ------------------------------------------------------------------
    // Constructor options
    // ------------------------------------------------------------------

    public function testConstructorAcceptsOptions(): void
    {
        $driver = new DompdfDriver([
            'defaultFont' => 'Helvetica',
            'dpi' => 150,
        ]);
        $driver->loadHtml('<p>Test</p>');
        $output = $driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    // ------------------------------------------------------------------
    // URL loading
    // ------------------------------------------------------------------

    public function testLoadUrlThrowsUnsupportedException(): void
    {
        $this->expectException(UnsupportedDriverFeatureException::class);
        $this->driver->loadUrl('https://example.com');
    }

    public function testLoadUrlExceptionHasFeatureName(): void
    {
        try {
            $this->driver->loadUrl('https://example.com');
            $this->fail('Expected UnsupportedDriverFeatureException');
        } catch (UnsupportedDriverFeatureException $e) {
            $this->assertSame('url', $e->feature);
        }
    }

    // ------------------------------------------------------------------
    // Paper format
    // ------------------------------------------------------------------

    public function testSetPaperDoesNotBreakRender(): void
    {
        $this->driver->loadHtml('<p>Test</p>');
        $this->driver->setPaper('A4', 'portrait');
        $output = $this->driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function testLandscapeOrientation(): void
    {
        $this->driver->loadHtml('<p>Test</p>');
        $this->driver->setPaper('Letter', 'landscape');
        $output = $this->driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function testCustomPaperSize(): void
    {
        $this->driver->loadHtml('<p>Test</p>');
        $this->driver->setPaperSize(100.0, 200.0, 'mm');
        $output = $this->driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    // ------------------------------------------------------------------
    // Margins (injected as @page CSS)
    // ------------------------------------------------------------------

    public function testMarginsInjectPageCss(): void
    {
        $html = '<!DOCTYPE html><html><head><title>T</title></head><body><p>Test</p></body></html>';
        $this->driver->loadHtml($html);
        $this->driver->setMargins(10.0, 15.0, 10.0, 15.0, 'mm');

        // We can't inspect the private prepareHtml() directly,
        // but we can verify rendering doesn't break
        $output = $this->driver->render();
        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function testMarginsWithDifferentUnits(): void
    {
        $this->driver->loadHtml('<p>Test</p>');

        // Test each supported unit
        foreach (['mm', 'cm', 'in', 'pt'] as $unit) {
            $driver = new DompdfDriver();
            $driver->loadHtml('<p>Test</p>');
            $driver->setMargins(10.0, 10.0, 10.0, 10.0, $unit);
            $output = $driver->render();
            $this->assertStringStartsWith('%PDF-', $output, "Failed for unit: {$unit}");
        }
    }

    // ------------------------------------------------------------------
    // Header/footer
    // ------------------------------------------------------------------

    public function testHeaderHtmlDoesNotBreakRender(): void
    {
        $this->driver->loadHtml($this->fixture('simple.html'));
        $this->driver->setHeaderHtml('<div>Header</div>');
        $output = $this->driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function testFooterHtmlDoesNotBreakRender(): void
    {
        $this->driver->loadHtml($this->fixture('simple.html'));
        $this->driver->setFooterHtml('<div>Footer</div>');
        $output = $this->driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    // ------------------------------------------------------------------
    // Feature support
    // ------------------------------------------------------------------

    public function testSupportsReportsFalseForUnsupported(): void
    {
        $this->assertFalse($this->driver->supports('url'));
        $this->assertFalse($this->driver->supports('headerFooter'));
        $this->assertFalse($this->driver->supports('tagged'));
        $this->assertFalse($this->driver->supports('pages'));
        $this->assertFalse($this->driver->supports('scale'));
    }

    public function testSupportsReturnsTrueForGenericFeatures(): void
    {
        $this->assertTrue($this->driver->supports('html'));
        $this->assertTrue($this->driver->supports('margins'));
        $this->assertTrue($this->driver->supports('paper'));
    }

    // ------------------------------------------------------------------
    // Silently ignored features (should not throw)
    // ------------------------------------------------------------------

    public function testSetPagesIsSilentlyIgnored(): void
    {
        $this->driver->loadHtml('<p>Test</p>');
        $this->driver->setPages('1-3');
        $output = $this->driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function testSetScaleIsSilentlyIgnored(): void
    {
        $this->driver->loadHtml('<p>Test</p>');
        $this->driver->setScale(1.5);
        $output = $this->driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function testSetTaggedIsSilentlyIgnored(): void
    {
        $this->driver->loadHtml('<p>Test</p>');
        $this->driver->setTagged(true);
        $output = $this->driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function testSetShowBackgroundIsSilentlyIgnored(): void
    {
        $this->driver->loadHtml('<p>Test</p>');
        $this->driver->setShowBackground(false);
        $output = $this->driver->render();

        $this->assertStringStartsWith('%PDF-', $output);
    }
}

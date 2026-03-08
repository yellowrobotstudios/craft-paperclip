<?php

namespace yellowrobot\paperclip\tests\unit;

use PHPUnit\Framework\TestCase;
use yellowrobot\paperclip\PdfBuilder;
use yellowrobot\paperclip\drivers\DompdfDriver;
use yellowrobot\paperclip\drivers\PdfDriverInterface;

class PdfBuilderTest extends TestCase
{
    private function builder(string $html = '<p>Test</p>'): PdfBuilder
    {
        return PdfBuilder::html($html)->withDriver(new DompdfDriver());
    }

    // ------------------------------------------------------------------
    // Static factory methods
    // ------------------------------------------------------------------

    public function testHtmlCreatesInstance(): void
    {
        $builder = PdfBuilder::html('<p>Test</p>');
        $this->assertInstanceOf(PdfBuilder::class, $builder);
    }

    public function testUrlCreatesInstance(): void
    {
        $builder = PdfBuilder::url('https://example.com');
        $this->assertInstanceOf(PdfBuilder::class, $builder);
    }

    // ------------------------------------------------------------------
    // Fluent API returns self
    // ------------------------------------------------------------------

    public function testFluentApiReturnsSelf(): void
    {
        $builder = PdfBuilder::html('<p>Test</p>');

        $this->assertSame($builder, $builder->format('A4'));
        $this->assertSame($builder, $builder->landscape());
        $this->assertSame($builder, $builder->portrait());
        $this->assertSame($builder, $builder->paperSize(100, 200, 'mm'));
        $this->assertSame($builder, $builder->margins(10, 10, 10, 10, 'mm'));
        $this->assertSame($builder, $builder->margin(10));
        $this->assertSame($builder, $builder->headerHtml('<div>H</div>'));
        $this->assertSame($builder, $builder->footerHtml('<div>F</div>'));
        $this->assertSame($builder, $builder->pages('1-5'));
        $this->assertSame($builder, $builder->scale(1.5));
        $this->assertSame($builder, $builder->showBackground(true));
        $this->assertSame($builder, $builder->tagged(true));
        $this->assertSame($builder, $builder->withDriver(new DompdfDriver()));
    }

    // ------------------------------------------------------------------
    // pdf() output
    // ------------------------------------------------------------------

    public function testPdfReturnsValidPdfFromHtml(): void
    {
        $output = $this->builder('<h1>Hello</h1>')->pdf();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function testPdfWithAllOptions(): void
    {
        $output = $this->builder('<h1>Hello</h1>')
            ->format('Letter')
            ->landscape()
            ->margins(6.35, 12.7, 6.35, 12.7, 'mm')
            ->headerHtml('<div>Header</div>')
            ->footerHtml('<div>Footer</div>')
            ->showBackground()
            ->pdf();

        $this->assertStringStartsWith('%PDF-', $output);
    }

    public function testPdfWithoutDriverThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No PDF driver configured');
        PdfBuilder::html('<p>Test</p>')->pdf();
    }

    // ------------------------------------------------------------------
    // base64()
    // ------------------------------------------------------------------

    public function testBase64ReturnsEncodedPdf(): void
    {
        $b64 = $this->builder('<p>Test</p>')->base64();

        $decoded = base64_decode($b64, true);
        $this->assertNotFalse($decoded);
        $this->assertStringStartsWith('%PDF-', $decoded);
    }

    // ------------------------------------------------------------------
    // margin() shorthand
    // ------------------------------------------------------------------

    public function testMarginWithOneValue(): void
    {
        $builder = $this->builder()->margin(10);
        $this->assertStringStartsWith('%PDF-', $builder->pdf());
    }

    public function testMarginWithTwoValues(): void
    {
        $builder = $this->builder()->margin(10, 20);
        $this->assertStringStartsWith('%PDF-', $builder->pdf());
    }

    public function testMarginWithFourValues(): void
    {
        $builder = $this->builder()->margin(10, 20, 30, 40);
        $this->assertStringStartsWith('%PDF-', $builder->pdf());
    }

    public function testMarginWithThreeValuesThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PdfBuilder::html('<p>Test</p>')->margin(10, 20, 30);
    }

    // ------------------------------------------------------------------
    // Driver delegation with mock
    // ------------------------------------------------------------------

    public function testPdfDelegatesToDriver(): void
    {
        $mockDriver = $this->createMock(PdfDriverInterface::class);
        $mockDriver->expects($this->once())
            ->method('loadHtml')
            ->with('<p>Hello</p>');
        $mockDriver->expects($this->once())
            ->method('render')
            ->willReturn('%PDF-fake');

        $output = PdfBuilder::html('<p>Hello</p>')->withDriver($mockDriver)->pdf();
        $this->assertSame('%PDF-fake', $output);
    }

    public function testFormatDelegatesToSetPaper(): void
    {
        $mockDriver = $this->createMock(PdfDriverInterface::class);
        $mockDriver->expects($this->once())
            ->method('setPaper')
            ->with('A4', 'portrait');
        $mockDriver->method('render')->willReturn('%PDF-fake');

        PdfBuilder::html('<p>Test</p>')->withDriver($mockDriver)->format('A4')->pdf();
    }

    public function testLandscapeDelegatesToSetPaper(): void
    {
        $mockDriver = $this->createMock(PdfDriverInterface::class);
        $mockDriver->expects($this->once())
            ->method('setPaper')
            ->with($this->anything(), 'landscape');
        $mockDriver->method('render')->willReturn('%PDF-fake');

        PdfBuilder::html('<p>Test</p>')->withDriver($mockDriver)->landscape()->pdf();
    }

    public function testMarginsDelegateToSetMargins(): void
    {
        $mockDriver = $this->createMock(PdfDriverInterface::class);
        $mockDriver->expects($this->once())
            ->method('setMargins')
            ->with(6.35, 12.7, 6.35, 12.7, 'mm');
        $mockDriver->method('render')->willReturn('%PDF-fake');

        PdfBuilder::html('<p>Test</p>')->withDriver($mockDriver)->margins(6.35, 12.7, 6.35, 12.7, 'mm')->pdf();
    }

    public function testCustomPaperSizeDelegates(): void
    {
        $mockDriver = $this->createMock(PdfDriverInterface::class);
        $mockDriver->expects($this->once())
            ->method('setPaperSize')
            ->with(100.0, 200.0, 'mm');
        $mockDriver->method('render')->willReturn('%PDF-fake');

        PdfBuilder::html('<p>Test</p>')->withDriver($mockDriver)->paperSize(100, 200, 'mm')->pdf();
    }

    public function testUrlDelegatesToLoadUrl(): void
    {
        $mockDriver = $this->createMock(PdfDriverInterface::class);
        $mockDriver->expects($this->once())
            ->method('loadUrl')
            ->with('https://example.com');
        $mockDriver->expects($this->never())->method('loadHtml');
        $mockDriver->method('render')->willReturn('%PDF-fake');

        PdfBuilder::url('https://example.com')->withDriver($mockDriver)->pdf();
    }

    public function testDefaultFormatIsLetter(): void
    {
        $mockDriver = $this->createMock(PdfDriverInterface::class);
        $mockDriver->expects($this->once())
            ->method('setPaper')
            ->with('Letter', 'portrait');
        $mockDriver->method('render')->willReturn('%PDF-fake');

        PdfBuilder::html('<p>Test</p>')->withDriver($mockDriver)->pdf();
    }

    // ------------------------------------------------------------------
    // save() to filesystem
    // ------------------------------------------------------------------

    public function testSaveWritesToFile(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'paperclip_test_') . '.pdf';

        try {
            $result = $this->builder()->save($tmpFile);
            $this->assertSame($tmpFile, $result);
            $this->assertFileExists($tmpFile);
            $this->assertStringStartsWith('%PDF-', file_get_contents($tmpFile));
        } finally {
            @unlink($tmpFile);
        }
    }

    public function testSaveCreatesDirectoryIfNeeded(): void
    {
        $tmpDir = sys_get_temp_dir() . '/paperclip_test_' . uniqid();
        $tmpFile = $tmpDir . '/output.pdf';

        try {
            $this->builder()->save($tmpFile);
            $this->assertFileExists($tmpFile);
        } finally {
            @unlink($tmpFile);
            @rmdir($tmpDir);
        }
    }
}

<?php

namespace yellowrobot\paperclip;

use yellowrobot\paperclip\drivers\PdfDriverInterface;

/**
 * Fluent PDF builder with driver-based rendering
 *
 * Usage:
 *   PdfBuilder::html($htmlString)->withDriver(new DompdfDriver())->pdf();
 *   PdfBuilder::html($htmlString)->withDriver($driver)->save('/tmp/output.pdf');
 */
class PdfBuilder
{
    private ?string $html = null;
    private ?string $url = null;

    private ?string $format = null;
    private ?float $paperWidth = null;
    private ?float $paperHeight = null;
    private string $paperUnit = 'mm';
    private bool $isLandscape = false;
    private array $margins = [];
    private string $marginUnit = 'mm';
    private ?string $headerHtml = null;
    private ?string $footerHtml = null;
    private bool $showBackgroundGraphics = true;
    private ?string $pages = null;
    private ?float $pdfScale = null;
    private bool $isTagged = false;

    protected ?PdfDriverInterface $driver = null;

    // ------------------------------------------------------------------
    // Static factory methods
    // ------------------------------------------------------------------

    /**
     * Create PDF from an HTML string
     */
    public static function html(string $html): static
    {
        $instance = new static();
        $instance->html = $html;
        return $instance;
    }

    /**
     * Create PDF from a URL
     */
    public static function url(string $url): static
    {
        $instance = new static();
        $instance->url = $url;
        return $instance;
    }

    // ------------------------------------------------------------------
    // Driver configuration
    // ------------------------------------------------------------------

    /**
     * Set the PDF driver to use for rendering
     */
    public function withDriver(PdfDriverInterface $driver): static
    {
        $this->driver = $driver;
        return $this;
    }

    // ------------------------------------------------------------------
    // Page format methods
    // ------------------------------------------------------------------

    /**
     * Set page format (Letter, A4, Legal, etc.)
     */
    public function format(string $format): static
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Set custom page size
     */
    public function paperSize(float $width, float $height, string $unit = 'mm'): static
    {
        $this->paperWidth = $width;
        $this->paperHeight = $height;
        $this->paperUnit = $unit;
        return $this;
    }

    /**
     * Set landscape orientation
     */
    public function landscape(bool $landscape = true): static
    {
        $this->isLandscape = $landscape;
        return $this;
    }

    /**
     * Set portrait orientation (default)
     */
    public function portrait(): static
    {
        $this->isLandscape = false;
        return $this;
    }

    // ------------------------------------------------------------------
    // Margin methods
    // ------------------------------------------------------------------

    /**
     * Set all margins explicitly
     */
    public function margins(float $top, float $right, float $bottom, float $left, string $unit = 'mm'): static
    {
        $this->margins = [$top, $right, $bottom, $left];
        $this->marginUnit = $unit;
        return $this;
    }

    /**
     * Set margins using CSS-style shorthand
     * - 1 value: all sides
     * - 2 values: vertical, horizontal
     * - 4 values: top, right, bottom, left
     */
    public function margin(float ...$values): static
    {
        $count = count($values);

        if ($count === 1) {
            $this->margins = [$values[0], $values[0], $values[0], $values[0]];
        } elseif ($count === 2) {
            $this->margins = [$values[0], $values[1], $values[0], $values[1]];
        } elseif ($count === 4) {
            $this->margins = $values;
        } else {
            throw new \InvalidArgumentException('margin() accepts 1, 2, or 4 values');
        }

        return $this;
    }

    // ------------------------------------------------------------------
    // Header/Footer methods
    // ------------------------------------------------------------------

    /**
     * Set custom header HTML
     */
    public function headerHtml(string $html): static
    {
        $this->headerHtml = $html;
        return $this;
    }

    /**
     * Set custom footer HTML
     */
    public function footerHtml(string $html): static
    {
        $this->footerHtml = $html;
        return $this;
    }

    // ------------------------------------------------------------------
    // Other options
    // ------------------------------------------------------------------

    /**
     * Only include specific pages (e.g., '1-5, 8, 11-13')
     */
    public function pages(string $pages): static
    {
        $this->pages = $pages;
        return $this;
    }

    /**
     * Set zoom scale (0.1 to 2.0, default 1.0)
     */
    public function scale(float $scale): static
    {
        $this->pdfScale = $scale;
        return $this;
    }

    /**
     * Include background graphics
     */
    public function showBackground(bool $show = true): static
    {
        $this->showBackgroundGraphics = $show;
        return $this;
    }

    /**
     * Create tagged/accessible PDF
     */
    public function tagged(bool $tagged = true): static
    {
        $this->isTagged = $tagged;
        return $this;
    }

    // ------------------------------------------------------------------
    // Output methods
    // ------------------------------------------------------------------

    /**
     * Get raw PDF content as string
     */
    public function pdf(): string
    {
        $driver = $this->resolveDriver();
        $this->applySource($driver);
        $this->applySettings($driver);
        return $driver->render();
    }

    /**
     * Get PDF as base64 encoded string
     */
    public function base64(): string
    {
        return base64_encode($this->pdf());
    }

    /**
     * Save PDF to a file path
     *
     * @param string $path File path
     * @return string The file path
     */
    public function save(string $path): string
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $this->pdf());

        return $path;
    }

    // ------------------------------------------------------------------
    // Protected methods (override points for CraftPdfBuilder)
    // ------------------------------------------------------------------

    /**
     * Resolve the PDF driver to use for rendering
     */
    protected function resolveDriver(): PdfDriverInterface
    {
        if ($this->driver) {
            return $this->driver;
        }

        throw new \RuntimeException('No PDF driver configured. Use withDriver() to set one.');
    }

    /**
     * Get the default page format
     */
    protected function getDefaultFormat(): string
    {
        return 'Letter';
    }

    /**
     * Resolve the HTML content for rendering
     */
    protected function resolveHtml(): string
    {
        if ($this->html === null) {
            throw new \RuntimeException('No HTML content set. Use html() or url() to set content.');
        }
        return $this->html;
    }

    // ------------------------------------------------------------------
    // Internal methods
    // ------------------------------------------------------------------

    /**
     * Set the source (HTML or URL) on the driver
     */
    private function applySource(PdfDriverInterface $driver): void
    {
        if ($this->url) {
            $driver->loadUrl($this->url);
            return;
        }

        $html = $this->resolveHtml();
        $driver->loadHtml($html);
    }

    /**
     * Apply all accumulated settings to the driver
     */
    private function applySettings(PdfDriverInterface $driver): void
    {
        // Paper size
        $orientation = $this->isLandscape ? 'landscape' : 'portrait';

        if ($this->paperWidth && $this->paperHeight) {
            $driver->setPaperSize($this->paperWidth, $this->paperHeight, $this->paperUnit);
            $format = $this->format ?? $this->getDefaultFormat();
            $driver->setPaper($format, $orientation);
        } else {
            $format = $this->format ?? $this->getDefaultFormat();
            $driver->setPaper($format, $orientation);
        }

        // Margins
        if (!empty($this->margins)) {
            $driver->setMargins(
                $this->margins[0],
                $this->margins[1],
                $this->margins[2],
                $this->margins[3],
                $this->marginUnit
            );
        }

        // Header/footer
        if ($this->headerHtml) {
            $driver->setHeaderHtml($this->headerHtml);
        }

        if ($this->footerHtml) {
            $driver->setFooterHtml($this->footerHtml);
        }

        // Other options
        $driver->setShowBackground($this->showBackgroundGraphics);

        if ($this->pages) {
            $driver->setPages($this->pages);
        }

        if ($this->pdfScale) {
            $driver->setScale($this->pdfScale);
        }

        if ($this->isTagged) {
            $driver->setTagged(true);
        }
    }
}

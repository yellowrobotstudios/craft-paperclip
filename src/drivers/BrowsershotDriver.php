<?php

namespace yellowrobot\paperclip\drivers;

use yellowrobot\paperclip\Paperclip;

class BrowsershotDriver implements PdfDriverInterface
{
    private mixed $browsershot = null;
    private ?string $html = null;
    private ?string $url = null;
    private string $format = 'Letter';
    private string $orientation = 'portrait';
    private bool $landscape = false;
    private ?array $customSize = null;
    private ?array $margins = null;
    private string $marginUnit = 'mm';
    private ?string $headerHtml = null;
    private ?string $footerHtml = null;
    private bool $showBackground = true;
    private ?string $pages = null;
    private ?float $scale = null;
    private bool $tagged = false;

    public function __construct()
    {
        if (!class_exists(\Spatie\Browsershot\Browsershot::class)) {
            throw new \RuntimeException(
                'The Browsershot driver requires spatie/browsershot. Install it with: composer require spatie/browsershot'
            );
        }
    }

    public function loadHtml(string $html): void
    {
        $this->html = $html;
        $this->url = null;
    }

    public function loadUrl(string $url): void
    {
        $this->url = $url;
        $this->html = null;
    }

    public function setPaper(string $format, string $orientation): void
    {
        $this->format = $format;
        $this->orientation = $orientation;
        $this->landscape = ($orientation === 'landscape');
    }

    public function setPaperSize(float $width, float $height, string $unit): void
    {
        // Browsershot expects mm for paperSize
        $this->customSize = [
            'width' => $this->toMm($width, $unit),
            'height' => $this->toMm($height, $unit),
        ];
    }

    public function setMargins(float $top, float $right, float $bottom, float $left, string $unit): void
    {
        $this->margins = [$top, $right, $bottom, $left];
        $this->marginUnit = $unit;
    }

    public function setHeaderHtml(string $html): void
    {
        $this->headerHtml = $html;
    }

    public function setFooterHtml(string $html): void
    {
        $this->footerHtml = $html;
    }

    public function setPages(string $range): void
    {
        $this->pages = $range;
    }

    public function setScale(float $scale): void
    {
        $this->scale = $scale;
    }

    public function setShowBackground(bool $show): void
    {
        $this->showBackground = $show;
    }

    public function setTagged(bool $tagged): void
    {
        $this->tagged = $tagged;
    }

    public function render(): string
    {
        $browsershot = $this->createBrowsershot();

        // Paper size
        if ($this->customSize) {
            $browsershot->paperSize($this->customSize['width'], $this->customSize['height']);
        } else {
            $browsershot->format($this->format);
        }

        // Orientation
        if ($this->landscape) {
            $browsershot->landscape();
        }

        // Margins
        if ($this->margins) {
            $browsershot->margins(
                $this->margins[0],
                $this->margins[1],
                $this->margins[2],
                $this->margins[3],
                $this->marginUnit
            );
        }

        // Header/footer
        if ($this->headerHtml || $this->footerHtml) {
            $browsershot->showBrowserHeaderAndFooter();

            if ($this->headerHtml) {
                $browsershot->headerHtml($this->headerHtml);
            } else {
                $browsershot->hideHeader();
            }

            if ($this->footerHtml) {
                $browsershot->footerHtml($this->footerHtml);
            } else {
                $browsershot->hideFooter();
            }
        }

        // Background
        if ($this->showBackground) {
            $browsershot->showBackground();
        }

        // Pages
        if ($this->pages) {
            $browsershot->pages($this->pages);
        }

        // Scale
        if ($this->scale) {
            $browsershot->scale($this->scale);
        }

        // Tagged PDF
        if ($this->tagged) {
            $browsershot->taggedPdf();
        }

        return $browsershot->pdf();
    }

    public function supports(string $feature): bool
    {
        return match ($feature) {
            'url', 'headerFooter', 'tagged', 'pages', 'scale' => true,
            default => true,
        };
    }

    /**
     * Create and configure the Browsershot instance
     */
    private function createBrowsershot(): mixed
    {
        $class = \Spatie\Browsershot\Browsershot::class;

        if ($this->url) {
            $browsershot = $class::url($this->url);
        } else {
            $browsershot = $class::html($this->html ?? '');
        }

        // Apply plugin settings
        $settings = Paperclip::$plugin->getSettings();

        if ($settings->nodePath) {
            $browsershot->setNodeBinary($settings->nodePath);
        }

        if ($settings->npmPath) {
            $browsershot->setNpmBinary($settings->npmPath);
        }

        if ($settings->chromePath) {
            $browsershot->setChromePath($settings->chromePath);
        }

        if ($settings->noSandbox) {
            $browsershot->noSandbox();
        }

        $browsershot->timeout($settings->timeout);

        // Wait for web fonts and async resources to finish loading
        $browsershot->waitUntilNetworkIdle();

        return $browsershot;
    }

    /**
     * Convert a measurement to millimeters
     */
    private function toMm(float $value, string $unit): float
    {
        return match ($unit) {
            'mm' => $value,
            'cm' => $value * 10,
            'in' => $value * 25.4,
            'pt' => $value * 0.352778,
            default => $value,
        };
    }
}

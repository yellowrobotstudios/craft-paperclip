<?php

namespace yellowrobot\paperclip\drivers;

use Craft;
use GuzzleHttp\Client;
use yellowrobot\paperclip\Paperclip;

/**
 * Gotenberg driver — uses a Gotenberg Docker service for Chromium-based PDF rendering.
 *
 * Requires a running Gotenberg instance (typically via Docker).
 * No PHP packages needed beyond Guzzle (already included with Craft).
 *
 * @see https://gotenberg.dev/
 */
class GotenbergDriver implements PdfDriverInterface
{
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

    /**
     * Standard paper sizes in inches [width, height]
     */
    private const PAPER_SIZES = [
        'letter' => [8.5, 11],
        'legal' => [8.5, 14],
        'tabloid' => [11, 17],
        'ledger' => [17, 11],
        'a0' => [33.1, 46.8],
        'a1' => [23.4, 33.1],
        'a2' => [16.5, 23.4],
        'a3' => [11.7, 16.5],
        'a4' => [8.27, 11.7],
        'a5' => [5.83, 8.27],
        'a6' => [4.13, 5.83],
    ];

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
        $this->customSize = [
            'width' => $this->toInches($width, $unit),
            'height' => $this->toInches($height, $unit),
        ];
    }

    public function setMargins(float $top, float $right, float $bottom, float $left, string $unit): void
    {
        $this->margins = [
            'top' => $this->toInches($top, $unit),
            'right' => $this->toInches($right, $unit),
            'bottom' => $this->toInches($bottom, $unit),
            'left' => $this->toInches($left, $unit),
        ];
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
        $settings = Paperclip::$plugin->getSettings();
        $baseUrl = rtrim($settings->gotenbergUrl ?? 'http://localhost:3000', '/');

        $client = Craft::createGuzzleClient([
            'base_uri' => $baseUrl,
            'timeout' => $settings->gotenbergTimeout ?? $settings->timeout,
        ]);

        // Build multipart form data
        $multipart = [];

        // Source
        if ($this->url) {
            $multipart[] = [
                'name' => 'url',
                'contents' => $this->url,
            ];
            $endpoint = '/forms/chromium/convert/url';
        } else {
            $multipart[] = [
                'name' => 'files',
                'contents' => $this->html ?? '',
                'filename' => 'index.html',
            ];
            $endpoint = '/forms/chromium/convert/html';
        }

        // Paper size
        if ($this->customSize) {
            $multipart[] = ['name' => 'paperWidth', 'contents' => (string)$this->customSize['width']];
            $multipart[] = ['name' => 'paperHeight', 'contents' => (string)$this->customSize['height']];
        } else {
            $size = self::PAPER_SIZES[strtolower($this->format)] ?? self::PAPER_SIZES['letter'];
            $multipart[] = ['name' => 'paperWidth', 'contents' => (string)$size[0]];
            $multipart[] = ['name' => 'paperHeight', 'contents' => (string)$size[1]];
        }

        // Orientation
        if ($this->landscape) {
            $multipart[] = ['name' => 'landscape', 'contents' => 'true'];
        }

        // Margins
        if ($this->margins) {
            $multipart[] = ['name' => 'marginTop', 'contents' => (string)$this->margins['top']];
            $multipart[] = ['name' => 'marginRight', 'contents' => (string)$this->margins['right']];
            $multipart[] = ['name' => 'marginBottom', 'contents' => (string)$this->margins['bottom']];
            $multipart[] = ['name' => 'marginLeft', 'contents' => (string)$this->margins['left']];
        }

        // Header
        if ($this->headerHtml) {
            $multipart[] = [
                'name' => 'files',
                'contents' => $this->headerHtml,
                'filename' => 'header.html',
            ];
        }

        // Footer
        if ($this->footerHtml) {
            $multipart[] = [
                'name' => 'files',
                'contents' => $this->footerHtml,
                'filename' => 'footer.html',
            ];
        }

        // Background
        if ($this->showBackground) {
            $multipart[] = ['name' => 'printBackground', 'contents' => 'true'];
        }

        // Pages
        if ($this->pages) {
            $multipart[] = ['name' => 'nativePageRanges', 'contents' => $this->pages];
        }

        // Scale
        if ($this->scale) {
            $multipart[] = ['name' => 'scale', 'contents' => (string)$this->scale];
        }

        // Tagged PDF
        if ($this->tagged) {
            $multipart[] = ['name' => 'generateDocumentOutline', 'contents' => 'true'];
        }

        // Auth
        $headers = [];
        if ($settings->gotenbergUsername && $settings->gotenbergPassword) {
            $headers['Authorization'] = 'Basic ' . base64_encode(
                $settings->gotenbergUsername . ':' . $settings->gotenbergPassword
            );
        }

        $response = $client->post($endpoint, [
            'multipart' => $multipart,
            'headers' => $headers,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                'Gotenberg returned HTTP ' . $response->getStatusCode() . ': ' . $response->getBody()->getContents()
            );
        }

        return $response->getBody()->getContents();
    }

    public function supports(string $feature): bool
    {
        return match ($feature) {
            'url', 'headerFooter', 'tagged', 'pages', 'scale' => true,
            default => true,
        };
    }

    private function toInches(float $value, string $unit): float
    {
        return match ($unit) {
            'in' => $value,
            'mm' => $value / 25.4,
            'cm' => $value / 2.54,
            'pt' => $value / 72,
            default => $value,
        };
    }
}

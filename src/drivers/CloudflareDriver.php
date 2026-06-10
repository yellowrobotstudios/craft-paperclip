<?php

namespace yellowrobot\paperclip\drivers;

use Craft;
use craft\helpers\App;
use yellowrobot\paperclip\Paperclip;

/**
 * Cloudflare Browser Rendering driver — uses Cloudflare's API for PDF generation.
 *
 * No local binaries needed. Requires a Cloudflare account with Browser Rendering API access.
 *
 * @see https://developers.cloudflare.com/browser-rendering/
 */
class CloudflareDriver implements PdfDriverInterface
{
    private ?string $html = null;
    private ?string $url = null;
    private string $format = 'Letter';
    private string $orientation = 'portrait';
    private bool $landscape = false;
    private ?array $customSize = null;
    private ?array $margins = null;
    private ?string $headerHtml = null;
    private ?string $footerHtml = null;
    private bool $showBackground = true;
    private ?string $pages = null;
    private ?float $scale = null;
    private bool $tagged = false;

    /**
     * Paper formats accepted by the Cloudflare API's `pdfOptions.format` enum
     */
    private const FORMATS = [
        'letter',
        'legal',
        'tabloid',
        'ledger',
        'a0',
        'a1',
        'a2',
        'a3',
        'a4',
        'a5',
        'a6',
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
        $apiToken = App::parseEnv($settings->cloudflareApiToken);
        $accountId = App::parseEnv($settings->cloudflareAccountId);

        if (!$apiToken || !$accountId) {
            throw new \RuntimeException(
                'Cloudflare Browser Rendering requires an API token and account ID. '
                . 'Set them in the Paperclip plugin settings or config/paperclip.php.'
            );
        }

        $endpoint = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/browser-rendering/pdf',
            $accountId
        );

        $body = $this->buildRequestBody();

        $client = Craft::createGuzzleClient([
            'timeout' => (int) App::parseEnv($settings->timeout),
        ]);

        $response = $client->post($endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
            'http_errors' => false,
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(
                'Cloudflare Browser Rendering returned HTTP ' . $response->getStatusCode()
                . ': ' . $response->getBody()->getContents()
            );
        }

        return $response->getBody()->getContents();
    }

    /**
     * Builds the JSON request body for the Browser Rendering `/pdf` endpoint.
     *
     * @see https://developers.cloudflare.com/api/resources/browser_rendering/subresources/pdf/methods/create/
     */
    public function buildRequestBody(): array
    {
        // Build the PDF options payload
        $pdfOptions = [];

        // Paper size — standard formats use the API's `format` enum; explicit
        // width/height is reserved for custom sizes
        if ($this->customSize) {
            $pdfOptions['width'] = $this->customSize['width'] . 'in';
            $pdfOptions['height'] = $this->customSize['height'] . 'in';
        } else {
            $format = strtolower($this->format);
            $pdfOptions['format'] = in_array($format, self::FORMATS, true) ? $format : 'letter';
        }

        // Orientation
        $pdfOptions['landscape'] = $this->landscape;

        // Margins
        if ($this->margins) {
            $pdfOptions['margin'] = [
                'top' => $this->margins['top'] . 'in',
                'right' => $this->margins['right'] . 'in',
                'bottom' => $this->margins['bottom'] . 'in',
                'left' => $this->margins['left'] . 'in',
            ];
        }

        // Header/footer
        if ($this->headerHtml) {
            $pdfOptions['displayHeaderFooter'] = true;
            $pdfOptions['headerTemplate'] = $this->headerHtml;
        }
        if ($this->footerHtml) {
            $pdfOptions['displayHeaderFooter'] = true;
            $pdfOptions['footerTemplate'] = $this->footerHtml;
        }

        // Background
        $pdfOptions['printBackground'] = $this->showBackground;

        // Pages
        if ($this->pages) {
            $pdfOptions['pageRanges'] = $this->pages;
        }

        // Scale
        if ($this->scale) {
            $pdfOptions['scale'] = $this->scale;
        }

        // Tagged
        if ($this->tagged) {
            $pdfOptions['tagged'] = true;
        }

        // Build request body
        $body = ['pdfOptions' => $pdfOptions];

        // Wait for external resources (images, fonts) to finish loading
        // before the PDF is captured
        $body['gotoOptions'] = [
            'waitUntil' => ['load', 'networkidle0'],
        ];

        if ($this->url) {
            $body['url'] = $this->url;
        } else {
            $body['html'] = $this->html ?? '';
        }

        return $body;
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

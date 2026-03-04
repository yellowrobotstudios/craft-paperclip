<?php

namespace yellowrobot\paperclip\drivers;

use yellowrobot\paperclip\Paperclip;

/**
 * WeasyPrint driver — uses the WeasyPrint binary for PDF generation.
 *
 * Excellent CSS Paged Media support (running headers/footers, page counters).
 * Requires the WeasyPrint binary (Python-based) and pontedilana/php-weasyprint.
 *
 * @see https://weasyprint.org/
 */
class WeasyprintDriver implements PdfDriverInterface
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
    private bool $tagged = false;

    /**
     * Standard paper sizes in mm [width, height]
     */
    private const PAPER_SIZES = [
        'letter' => [215.9, 279.4],
        'legal' => [215.9, 355.6],
        'tabloid' => [279.4, 431.8],
        'ledger' => [431.8, 279.4],
        'a0' => [841, 1189],
        'a1' => [594, 841],
        'a2' => [420, 594],
        'a3' => [297, 420],
        'a4' => [210, 297],
        'a5' => [148, 210],
        'a6' => [105, 148],
    ];

    public function __construct()
    {
        if (!class_exists(\Pontedilana\PhpWeasyPrint\Pdf::class)) {
            throw new \RuntimeException(
                'The WeasyPrint driver requires pontedilana/php-weasyprint. '
                . 'Install it with: composer require pontedilana/php-weasyprint'
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
        // WeasyPrint does not support page range selection — silently ignored
    }

    public function setScale(float $scale): void
    {
        // WeasyPrint does not support zoom scaling — silently ignored
    }

    public function setShowBackground(bool $show): void
    {
        // WeasyPrint always renders backgrounds from CSS — no toggle needed
    }

    public function setTagged(bool $tagged): void
    {
        $this->tagged = $tagged;
    }

    public function render(): string
    {
        $settings = Paperclip::$plugin->getSettings();
        $binaryPath = $settings->weasyprintBinary ?? 'weasyprint';

        $weasyprint = new \Pontedilana\PhpWeasyPrint\Pdf($binaryPath);

        if ($settings->weasyprintTimeout) {
            $weasyprint->setTimeout($settings->weasyprintTimeout);
        }

        // Prepare HTML with injected CSS for page setup
        $html = $this->prepareHtml();

        // Generate PDF to a temp file, then read it
        $tempFile = tempnam(sys_get_temp_dir(), 'paperclip_');

        try {
            if ($this->url) {
                $weasyprint->generate($this->url, $tempFile);
            } else {
                $weasyprint->generateFromHtml($html, $tempFile);
            }

            $pdfContent = file_get_contents($tempFile);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        return $pdfContent;
    }

    public function supports(string $feature): bool
    {
        return match ($feature) {
            'url', 'headerFooter', 'tagged' => true,
            'pages', 'scale' => false,
            default => true,
        };
    }

    /**
     * Prepare HTML by injecting @page CSS for paper size, margins, and header/footer
     */
    private function prepareHtml(): string
    {
        $html = $this->html ?? '';
        $css = $this->buildPageCss();

        // Inject header/footer as running elements (WeasyPrint CSS Paged Media)
        if ($this->headerHtml) {
            $css .= "\n@page { @top-center { content: element(pageHeader); } }";
            $headerBlock = '<div style="position: running(pageHeader);">' . $this->headerHtml . '</div>';
            $html = $this->injectAfterBodyOpen($html, $headerBlock);
        }

        if ($this->footerHtml) {
            $css .= "\n@page { @bottom-center { content: element(pageFooter); } }";
            $footerBlock = '<div style="position: running(pageFooter);">' . $this->footerHtml . '</div>';
            $html = $this->injectAfterBodyOpen($html, $footerBlock);
        }

        if ($css) {
            $html = $this->injectCss($html, $css);
        }

        return $html;
    }

    /**
     * Build @page CSS rule for paper size and margins
     */
    private function buildPageCss(): string
    {
        $rules = [];

        // Paper size
        if ($this->customSize) {
            $w = $this->customSize['width'];
            $h = $this->customSize['height'];
            $sizeValue = "{$w}mm {$h}mm";
        } else {
            $size = self::PAPER_SIZES[strtolower($this->format)] ?? self::PAPER_SIZES['letter'];
            $sizeValue = "{$size[0]}mm {$size[1]}mm";
        }

        if ($this->landscape) {
            $sizeValue .= ' landscape';
        }

        $rules[] = "size: {$sizeValue}";

        // Margins
        if ($this->margins) {
            $u = $this->marginUnit;
            $rules[] = "margin: {$this->margins[0]}{$u} {$this->margins[1]}{$u} {$this->margins[2]}{$u} {$this->margins[3]}{$u}";
        }

        return '@page { ' . implode('; ', $rules) . '; }';
    }

    private function injectCss(string $html, string $css): string
    {
        $styleTag = "<style>{$css}</style>";

        if (stripos($html, '</head>') !== false) {
            return str_ireplace('</head>', $styleTag . '</head>', $html);
        }

        return $styleTag . $html;
    }

    private function injectAfterBodyOpen(string $html, string $content): string
    {
        if (preg_match('/<body[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            return substr($html, 0, $pos) . $content . substr($html, $pos);
        }

        return $content . $html;
    }

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

<?php

namespace yellowrobot\paperclip\drivers;

use Dompdf\Dompdf;
use Dompdf\Options;

class DompdfDriver implements PdfDriverInterface
{
    private array $driverOptions;
    private ?Dompdf $dompdf = null;
    private ?string $html = null;
    private string $format = 'letter';
    private string $orientation = 'portrait';
    private ?array $customSize = null;
    private ?array $margins = null;
    private ?string $headerHtml = null;
    private ?string $footerHtml = null;

    /**
     * @param array $options Driver options: fontDir, defaultFont, dpi
     */
    public function __construct(array $options = [])
    {
        $this->driverOptions = $options;
    }

    public function loadHtml(string $html): void
    {
        $this->html = $html;
    }

    public function loadUrl(string $url): void
    {
        throw new UnsupportedDriverFeatureException(
            'The DOMPDF driver does not support rendering URLs. Use the Browsershot driver instead.',
            'url'
        );
    }

    public function setPaper(string $format, string $orientation): void
    {
        $this->format = strtolower($format);
        $this->orientation = $orientation;
    }

    public function setPaperSize(float $width, float $height, string $unit): void
    {
        // DOMPDF expects points (1pt = 1/72 inch)
        $widthPt = $this->toPoints($width, $unit);
        $heightPt = $this->toPoints($height, $unit);
        $this->customSize = [0, 0, $widthPt, $heightPt];
    }

    public function setMargins(float $top, float $right, float $bottom, float $left, string $unit): void
    {
        $this->margins = [
            'top' => $top,
            'right' => $right,
            'bottom' => $bottom,
            'left' => $left,
            'unit' => $unit,
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
        // DOMPDF does not support page range selection — silently ignored
    }

    public function setScale(float $scale): void
    {
        // DOMPDF does not support zoom scaling — silently ignored
    }

    public function setShowBackground(bool $show): void
    {
        // DOMPDF always renders backgrounds from CSS — no toggle needed
    }

    public function setTagged(bool $tagged): void
    {
        // DOMPDF does not support tagged/accessible PDF — silently ignored
    }

    public function render(): string
    {
        $html = $this->prepareHtml();

        $dompdf = $this->createDompdf();
        $dompdf->loadHtml($html);

        if ($this->customSize) {
            $dompdf->setPaper($this->customSize, $this->orientation);
        } else {
            $dompdf->setPaper($this->format, $this->orientation);
        }

        $dompdf->render();

        return $dompdf->output();
    }

    public function supports(string $feature): bool
    {
        return match ($feature) {
            'url', 'headerFooter', 'tagged', 'pages', 'scale' => false,
            default => true,
        };
    }

    /**
     * Prepare HTML by injecting margin CSS and header/footer content
     */
    private function prepareHtml(): string
    {
        $html = $this->html ?? '';

        // Inject @page margins via CSS
        if ($this->margins) {
            $m = $this->margins;
            $u = $m['unit'];
            $marginCss = "@page { margin: {$m['top']}{$u} {$m['right']}{$u} {$m['bottom']}{$u} {$m['left']}{$u}; }";
            $html = $this->injectCss($html, $marginCss);
        }

        // Inject header/footer as fixed-position elements
        if ($this->headerHtml) {
            $headerStyle = 'position: fixed; top: 0; left: 0; right: 0;';
            $headerBlock = "<div style=\"{$headerStyle}\">{$this->headerHtml}</div>";
            $html = $this->injectAfterBodyOpen($html, $headerBlock);
        }

        if ($this->footerHtml) {
            $footerStyle = 'position: fixed; bottom: 0; left: 0; right: 0;';
            $footerBlock = "<div style=\"{$footerStyle}\">{$this->footerHtml}</div>";
            $html = $this->injectAfterBodyOpen($html, $footerBlock);
        }

        return $html;
    }

    /**
     * Create and configure the Dompdf instance
     */
    private function createDompdf(): Dompdf
    {
        $options = new Options();
        $options->setIsRemoteEnabled(true);
        $options->setIsPhpEnabled(false);

        if (!empty($this->driverOptions['fontDir'])) {
            $options->setFontDir($this->driverOptions['fontDir']);
        }

        if (!empty($this->driverOptions['defaultFont'])) {
            $options->setDefaultFont($this->driverOptions['defaultFont']);
        }

        if (!empty($this->driverOptions['dpi'])) {
            $options->setDpi($this->driverOptions['dpi']);
        }

        return new Dompdf($options);
    }

    /**
     * Inject a CSS string into the document's <head>
     */
    private function injectCss(string $html, string $css): string
    {
        $styleTag = "<style>{$css}</style>";

        // Try to inject before </head>
        if (stripos($html, '</head>') !== false) {
            return str_ireplace('</head>', $styleTag . '</head>', $html);
        }

        // No <head> — prepend
        return $styleTag . $html;
    }

    /**
     * Inject HTML content right after the opening <body> tag
     */
    private function injectAfterBodyOpen(string $html, string $content): string
    {
        if (preg_match('/<body[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            $pos = $matches[0][1] + strlen($matches[0][0]);
            return substr($html, 0, $pos) . $content . substr($html, $pos);
        }

        // No <body> — prepend
        return $content . $html;
    }

    /**
     * Convert a measurement to points
     */
    private function toPoints(float $value, string $unit): float
    {
        return match ($unit) {
            'pt' => $value,
            'mm' => $value * 2.83465,
            'cm' => $value * 28.3465,
            'in' => $value * 72,
            default => $value,
        };
    }
}

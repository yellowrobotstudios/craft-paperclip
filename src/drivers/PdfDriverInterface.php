<?php

namespace yellowrobot\paperclip\drivers;

interface PdfDriverInterface
{
    /**
     * Load HTML content for rendering
     */
    public function loadHtml(string $html): void;

    /**
     * Load a URL for rendering (not all drivers support this)
     */
    public function loadUrl(string $url): void;

    /**
     * Set paper format and orientation
     *
     * @param string $format e.g. 'Letter', 'A4', 'Legal'
     * @param string $orientation 'portrait' or 'landscape'
     */
    public function setPaper(string $format, string $orientation): void;

    /**
     * Set custom paper dimensions
     *
     * @param float $width
     * @param float $height
     * @param string $unit 'mm', 'in', 'pt', 'cm'
     */
    public function setPaperSize(float $width, float $height, string $unit): void;

    /**
     * Set page margins
     *
     * @param float $top
     * @param float $right
     * @param float $bottom
     * @param float $left
     * @param string $unit 'mm', 'in', 'pt', 'cm'
     */
    public function setMargins(float $top, float $right, float $bottom, float $left, string $unit): void;

    /**
     * Set header HTML content
     */
    public function setHeaderHtml(string $html): void;

    /**
     * Set footer HTML content
     */
    public function setFooterHtml(string $html): void;

    /**
     * Set page range to include (e.g. '1-5, 8')
     */
    public function setPages(string $range): void;

    /**
     * Set zoom scale (0.1 to 2.0)
     */
    public function setScale(float $scale): void;

    /**
     * Whether to print background graphics
     */
    public function setShowBackground(bool $show): void;

    /**
     * Generate a tagged/accessible PDF
     */
    public function setTagged(bool $tagged): void;

    /**
     * Render and return raw PDF bytes
     */
    public function render(): string;

    /**
     * Check whether this driver supports a given feature
     *
     * @param string $feature e.g. 'url', 'headerFooter', 'tagged', 'pages', 'scale'
     * @return bool
     */
    public function supports(string $feature): bool;
}

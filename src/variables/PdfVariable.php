<?php

namespace yellowrobot\paperclip\variables;

use Craft;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use yellowrobot\paperclip\CraftPdfBuilder;

/**
 * Twig variable for PDF generation
 *
 * Usage in Twig:
 *   {{ craft.paperclip.view('_pdfs/invoice', { order: order }).inline() }}
 *   {{ craft.paperclip.html(htmlString).download('document.pdf') }}
 *   {{ craft.paperclip.url('https://example.com').save('@storage/page.pdf') }}
 */
class PdfVariable
{
    public function view(string $template, array $variables = []): CraftPdfBuilder
    {
        return CraftPdfBuilder::view($template, $variables);
    }

    public function html(string $html): CraftPdfBuilder
    {
        return CraftPdfBuilder::html($html);
    }

    public function url(string $url): CraftPdfBuilder
    {
        return CraftPdfBuilder::url($url);
    }

    /**
     * Inline a file as a base64 data URL, for embedding images and fonts
     * directly in PDF templates. Useful when the rendering engine can't
     * reach your asset URLs (e.g. cloud drivers rendering against a local
     * dev site).
     *
     * @param Asset|string $file An asset element, or a file path (supports Craft aliases like @webroot)
     */
    public function dataUrl(Asset|string $file): string
    {
        if ($file instanceof Asset) {
            $mimeType = $file->getMimeType() ?? 'application/octet-stream';
            return 'data:' . $mimeType . ';base64,' . base64_encode($file->getContents());
        }

        $path = Craft::getAlias($file);

        if (!is_file($path)) {
            throw new \RuntimeException("File not found: {$path}");
        }

        $mimeType = FileHelper::getMimeType($path) ?? 'application/octet-stream';

        return 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($path));
    }
}

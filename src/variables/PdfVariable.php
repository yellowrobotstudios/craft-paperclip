<?php

namespace yellowrobot\paperclip\variables;

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
}

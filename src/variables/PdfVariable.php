<?php

namespace yellowrobot\paperclip\variables;

use yellowrobot\paperclip\PdfBuilder;

/**
 * Twig variable for PDF generation
 *
 * Usage in Twig:
 *   {{ craft.pdf.view('_pdfs/invoice', { order: order }).inline() }}
 *   {{ craft.pdf.html(htmlString).download('document.pdf') }}
 *   {{ craft.pdf.url('https://example.com').save('@storage/page.pdf') }}
 */
class PdfVariable
{
    public function view(string $template, array $variables = []): PdfBuilder
    {
        return PdfBuilder::view($template, $variables);
    }

    public function html(string $html): PdfBuilder
    {
        return PdfBuilder::html($html);
    }

    public function url(string $url): PdfBuilder
    {
        return PdfBuilder::url($url);
    }
}

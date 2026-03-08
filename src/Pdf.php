<?php

namespace yellowrobot\paperclip;

/**
 * Static facade for convenient PDF generation in PHP
 *
 * Usage:
 *   use yellowrobot\paperclip\Pdf;
 *
 *   Pdf::view('_pdfs/invoice', ['order' => $order])->save('invoice.pdf');
 *   Pdf::html($html)->download('document.pdf');
 *   Pdf::url('https://example.com')->inline();
 */
class Pdf extends CraftPdfBuilder
{
}

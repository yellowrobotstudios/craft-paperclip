# Paperclip

PDF generation for Craft CMS with multiple rendering drivers.

## Drivers

| Driver | Type | Requirements |
|---|---|---|
| **DOMPDF** (default) | Pure PHP | None — works everywhere |
| **Browsershot** | Headless Chrome | `spatie/browsershot` + Node.js + Puppeteer |
| **Gotenberg** | Docker service | Running [Gotenberg](https://gotenberg.dev/) instance |
| **Cloudflare** | Cloud API | [Cloudflare Browser Rendering](https://developers.cloudflare.com/browser-rendering/) account |
| **WeasyPrint** | Python binary | `pontedilana/php-weasyprint` + [WeasyPrint](https://weasyprint.org/) |

## Installation

```bash
composer require yellowrobot/craft-paperclip
php craft plugin/install paperclip
```

## Usage

### Twig

```twig
{# Render a template as inline PDF #}
{{ craft.paperclip.view('_pdfs/invoice', { order: order }).inline() }}

{# Download from HTML #}
{{ craft.paperclip.html(htmlString).download('document.pdf') }}

{# Save a URL to a file #}
{{ craft.paperclip.url('https://example.com').save('@storage/page.pdf') }}
```

### PHP

```php
use yellowrobot\paperclip\Pdf;

// From a Twig template
Pdf::view('_pdfs/invoice', ['order' => $order])->save('@storage/invoice.pdf');

// From HTML
Pdf::html('<h1>Hello</h1>')->download('hello.pdf');

// From a URL (requires Browsershot, Gotenberg, Cloudflare, or WeasyPrint)
Pdf::url('https://example.com')->inline();
```

### Page Configuration

```twig
{{ craft.paperclip.view('_pdfs/report')
    .format('A4')
    .landscape()
    .margins(10, 15, 10, 15, 'mm')
    .headerHtml('<div style="text-align:center; font-size:10px;">Page Header</div>')
    .footerView('_pdfs/_footer', { year: now|date('Y') })
    .inline() }}
```

**Available methods:**

| Method | Description |
|---|---|
| `format('Letter')` | Page format: Letter, Legal, Tabloid, A0–A6 |
| `paperSize(w, h, 'mm')` | Custom dimensions (mm, cm, in, pt) |
| `landscape()` / `portrait()` | Orientation |
| `margins(t, r, b, l, 'mm')` | Explicit margins |
| `margin(10)` | CSS-style shorthand (1, 2, or 4 values) |
| `headerHtml(html)` | Header from HTML string |
| `footerHtml(html)` | Footer from HTML string |
| `headerView(template, vars)` | Header from Twig template |
| `footerView(template, vars)` | Footer from Twig template |
| `pages('1-5, 8')` | Page range selection |
| `scale(1.5)` | Zoom scale (0.1–2.0) |
| `showBackground(true)` | Include background graphics |
| `tagged(true)` | Generate tagged/accessible PDF |

### Output Methods

| Method | Description |
|---|---|
| `pdf()` | Raw PDF bytes as string |
| `base64()` | Base64-encoded PDF |
| `save($path)` | Save to file (supports `@aliases`) |
| `inline($filename)` | Stream to browser |
| `download($filename)` | Stream as download |
| `toAsset($volume, $filename)` | Save as a Craft asset |

### Saving as a Craft Asset

```php
$asset = Pdf::view('_pdfs/report', $vars)
    ->toAsset('documents', 'report.pdf', 'reports/2026');
```

## Configuration

Copy `config.php` to your project's `config/paperclip.php` to override settings per environment:

```php
return [
    'driver' => 'gotenberg',
    'gotenbergUrl' => '$GOTENBERG_URL',
    'defaultFormat' => 'A4',
];
```

All settings support environment variables via the control panel or config file.

## Custom Drivers

Register your own driver via the `EVENT_REGISTER_DRIVERS` event:

```php
use yellowrobot\paperclip\Paperclip;
use yellowrobot\paperclip\events\RegisterDriversEvent;

Event::on(
    Paperclip::class,
    Paperclip::EVENT_REGISTER_DRIVERS,
    function (RegisterDriversEvent $event) {
        $event->drivers['mydriver'] = MyCustomDriver::class;
    }
);
```

Your driver must implement `yellowrobot\paperclip\drivers\PdfDriverInterface`.

## Requirements

- Craft CMS 4.0+ or 5.0+
- PHP 8.0+

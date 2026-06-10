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
| `driver('gotenberg')` | Override the configured driver for this PDF |
| `cache(3600)` | Cache the rendered PDF (seconds) |

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

### Caching

PDF generation is slow — cache the result when the same document is requested repeatedly:

```twig
{{ craft.paperclip.view('_pdfs/invoice', { order: order })
    .cache(3600)
    .inline('invoice.pdf') }}
```

The cache key covers the rendered content and every page option, so changing the template output or any setting automatically generates a fresh PDF. Cached PDFs are stored in Craft's data cache.

### Per-PDF driver

Override the globally-configured driver for a single document:

```twig
{{ craft.paperclip.view('_pdfs/receipt').driver('dompdf').inline() }}
```

### Inlining images and fonts

Embed a file as a base64 data URL — useful when the rendering engine can't reach your asset URLs (e.g. cloud drivers rendering against a local dev site):

```twig
{# An asset element #}
<img src="{{ craft.paperclip.dataUrl(entry.image.one()) }}" alt="">

{# Or a file path (supports aliases) #}
<img src="{{ craft.paperclip.dataUrl('@webroot/images/logo.png') }}" alt="">
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

### Cloudflare driver notes

The Cloudflare Browser Rendering API renders your HTML in Cloudflare's cloud, so any images, fonts, or stylesheets referenced by URL must be **publicly reachable from the internet**. Assets served from a local dev domain (e.g. `https://mysite.ddev.site`) will fail to load and images will fall back to their ALT text.

For local development, inline assets as data URLs instead:

```twig
<img src="{{ craft.paperclip.dataUrl(image) }}" alt="">
```

Paperclip asks Cloudflare to wait for the network to go idle (`networkidle0`) before capturing the PDF, so publicly-reachable images and fonts are fully loaded in the output.

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

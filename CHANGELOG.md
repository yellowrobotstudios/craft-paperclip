# Changelog

## 1.1.0 - 2026-06-10

### Added
- `cache($duration)` builder method — caches the rendered PDF in Craft's data cache, keyed on the resolved content, driver, and all page options
- `driver($handle)` builder method — override the configured driver for a single PDF from Twig or PHP
- `craft.paperclip.dataUrl()` Twig helper — inline an asset element or file path as a base64 data URL for embedding images and fonts in PDF templates

### Fixed
- `inline()` and `download()` now build the `Content-Disposition` header via Yii's response helpers, escaping quotes/control characters and adding an RFC 5987 fallback for non-ASCII filenames

## 1.0.1 - 2026-06-09

### Fixed
- Cloudflare driver: PDF options were sent under `pdf_options` instead of `pdfOptions`, which the Browser Rendering API rejects ([#1](https://github.com/yellowrobotstudios/craft-paperclip/issues/1))
- Cloudflare driver: standard paper sizes are now sent via the API's `format` enum instead of explicit `width`/`height`; explicit dimensions are still used for custom sizes ([#1](https://github.com/yellowrobotstudios/craft-paperclip/issues/1))
- Cloudflare driver: external images and fonts now finish loading before the PDF is captured (`gotoOptions.waitUntil: networkidle0`) ([#2](https://github.com/yellowrobotstudios/craft-paperclip/issues/2))
- Cloudflare driver: API error responses now surface Cloudflare's error message instead of a generic Guzzle exception

## 1.0.0 - 2026-03-09

### Added
- Five PDF rendering drivers: DOMPDF, Browsershot, Gotenberg, Cloudflare Browser Rendering, WeasyPrint
- Fluent builder API for PDF generation (`html()`, `url()`, `view()`)
- Output methods: `pdf()`, `base64()`, `save()`, `inline()`, `download()`, `toAsset()`
- Page configuration: format, orientation, custom paper sizes, margins
- Header/footer support via HTML strings or Twig templates
- Environment variable support for all settings
- Twig integration via `craft.paperclip`
- Custom driver registration via `EVENT_REGISTER_DRIVERS`
- Control panel settings page with per-driver configuration

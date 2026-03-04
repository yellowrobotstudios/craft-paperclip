# Changelog

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

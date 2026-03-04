<?php

namespace yellowrobot\paperclip\models;

use craft\base\Model;

class Settings extends Model
{
    /**
     * Active PDF driver: 'dompdf', 'browsershot', 'gotenberg', 'cloudflare', or 'weasyprint'
     */
    public string $driver = 'dompdf';

    /**
     * Default page format (Letter, A4, etc.)
     */
    public string $defaultFormat = 'Letter';

    /**
     * Timeout in seconds for PDF generation (shared across drivers)
     */
    public string|int $timeout = 60;

    // ------------------------------------------------------------------
    // DOMPDF settings
    // ------------------------------------------------------------------

    /**
     * Custom font directory for DOMPDF
     */
    public ?string $dompdfFontDir = null;

    /**
     * Default font family for DOMPDF
     */
    public ?string $dompdfDefaultFont = null;

    /**
     * DPI for DOMPDF rendering (default: 96)
     */
    public ?int $dompdfDpi = null;

    // ------------------------------------------------------------------
    // Browsershot settings
    // ------------------------------------------------------------------

    /**
     * Path to Node.js binary
     */
    public ?string $nodePath = null;

    /**
     * Path to NPM binary
     */
    public ?string $npmPath = null;

    /**
     * Path to Chrome/Chromium binary
     */
    public ?string $chromePath = null;

    /**
     * Whether to run Chrome in no-sandbox mode
     */
    public string|bool $noSandbox = false;

    // ------------------------------------------------------------------
    // Gotenberg settings
    // ------------------------------------------------------------------

    /**
     * Gotenberg service URL (e.g. http://localhost:3000)
     */
    public ?string $gotenbergUrl = null;

    /**
     * Optional basic auth username for Gotenberg
     */
    public ?string $gotenbergUsername = null;

    /**
     * Optional basic auth password for Gotenberg
     */
    public ?string $gotenbergPassword = null;

    /**
     * Timeout override for Gotenberg (uses general timeout if null)
     */
    public string|int|null $gotenbergTimeout = null;

    // ------------------------------------------------------------------
    // Cloudflare Browser Rendering settings
    // ------------------------------------------------------------------

    /**
     * Cloudflare API token with Browser Rendering permissions
     */
    public ?string $cloudflareApiToken = null;

    /**
     * Cloudflare account ID
     */
    public ?string $cloudflareAccountId = null;

    // ------------------------------------------------------------------
    // WeasyPrint settings
    // ------------------------------------------------------------------

    /**
     * Path to the WeasyPrint binary
     */
    public ?string $weasyprintBinary = null;

    /**
     * Timeout override for WeasyPrint (uses general timeout if null)
     */
    public string|int|null $weasyprintTimeout = null;

    public function rules(): array
    {
        return [
            [['driver'], 'required'],
            [['driver'], 'in', 'range' => ['dompdf', 'browsershot', 'gotenberg', 'cloudflare', 'weasyprint']],
            [['defaultFormat'], 'in', 'range' => [
                'Letter', 'Legal', 'Tabloid', 'Ledger',
                'A0', 'A1', 'A2', 'A3', 'A4', 'A5', 'A6',
            ]],
            [['dompdfFontDir', 'dompdfDefaultFont'], 'string'],
            [['nodePath', 'npmPath', 'chromePath'], 'string'],
            [['gotenbergUrl', 'gotenbergUsername', 'gotenbergPassword'], 'string'],
            [['cloudflareApiToken', 'cloudflareAccountId'], 'string'],
            [['weasyprintBinary'], 'string'],
            [['dompdfDpi'], 'integer', 'min' => 72, 'max' => 300],
            [['timeout', 'gotenbergTimeout', 'weasyprintTimeout'], 'integer', 'min' => 1, 'max' => 300],
            [['noSandbox'], 'boolean'],
        ];
    }
}

<?php

namespace yellowrobot\paperclip;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets;
use yellowrobot\paperclip\drivers\PdfDriverInterface;
use yii\base\InvalidConfigException;

/**
 * Fluent PDF builder with driver-based rendering
 *
 * Usage:
 *   Pdf::view('_pdfs/invoice', ['order' => $order])->save('invoice.pdf');
 *   Pdf::html($htmlString)->inline();
 *   Pdf::url('https://example.com')->download('page.pdf');
 */
class PdfBuilder
{
    private ?string $html = null;
    private ?string $url = null;
    private ?string $template = null;
    private array $variables = [];

    private ?string $format = null;
    private ?float $paperWidth = null;
    private ?float $paperHeight = null;
    private string $paperUnit = 'mm';
    private bool $isLandscape = false;
    private array $margins = [];
    private string $marginUnit = 'mm';
    private ?string $headerHtml = null;
    private ?string $footerHtml = null;
    private bool $showBackgroundGraphics = true;
    private ?string $pages = null;
    private ?float $pdfScale = null;
    private bool $isTagged = false;

    // ------------------------------------------------------------------
    // Static factory methods
    // ------------------------------------------------------------------

    /**
     * Create PDF from a Twig template
     */
    public static function view(string $template, array $variables = []): self
    {
        $instance = new self();
        $instance->template = $template;
        $instance->variables = $variables;
        return $instance;
    }

    /**
     * Create PDF from an HTML string
     */
    public static function html(string $html): self
    {
        $instance = new self();
        $instance->html = $html;
        return $instance;
    }

    /**
     * Create PDF from a URL
     */
    public static function url(string $url): self
    {
        $instance = new self();
        $instance->url = $url;
        return $instance;
    }

    // ------------------------------------------------------------------
    // Page format methods
    // ------------------------------------------------------------------

    /**
     * Set page format (Letter, A4, Legal, etc.)
     */
    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Set custom page size
     */
    public function paperSize(float $width, float $height, string $unit = 'mm'): self
    {
        $this->paperWidth = $width;
        $this->paperHeight = $height;
        $this->paperUnit = $unit;
        return $this;
    }

    /**
     * Set landscape orientation
     */
    public function landscape(bool $landscape = true): self
    {
        $this->isLandscape = $landscape;
        return $this;
    }

    /**
     * Set portrait orientation (default)
     */
    public function portrait(): self
    {
        $this->isLandscape = false;
        return $this;
    }

    // ------------------------------------------------------------------
    // Margin methods
    // ------------------------------------------------------------------

    /**
     * Set all margins explicitly
     */
    public function margins(float $top, float $right, float $bottom, float $left, string $unit = 'mm'): self
    {
        $this->margins = [$top, $right, $bottom, $left];
        $this->marginUnit = $unit;
        return $this;
    }

    /**
     * Set margins using CSS-style shorthand
     * - 1 value: all sides
     * - 2 values: vertical, horizontal
     * - 4 values: top, right, bottom, left
     */
    public function margin(float ...$values): self
    {
        $count = count($values);

        if ($count === 1) {
            $this->margins = [$values[0], $values[0], $values[0], $values[0]];
        } elseif ($count === 2) {
            $this->margins = [$values[0], $values[1], $values[0], $values[1]];
        } elseif ($count === 4) {
            $this->margins = $values;
        } else {
            throw new InvalidConfigException('margin() accepts 1, 2, or 4 values');
        }

        return $this;
    }

    // ------------------------------------------------------------------
    // Header/Footer methods
    // ------------------------------------------------------------------

    /**
     * Set custom header HTML
     */
    public function headerHtml(string $html): self
    {
        $this->headerHtml = $html;
        return $this;
    }

    /**
     * Set custom footer HTML
     */
    public function footerHtml(string $html): self
    {
        $this->footerHtml = $html;
        return $this;
    }

    /**
     * Set header from a Twig template
     */
    public function headerView(string $template, array $variables = []): self
    {
        $this->headerHtml = $this->renderTwigTemplate($template, $variables);
        return $this;
    }

    /**
     * Set footer from a Twig template
     */
    public function footerView(string $template, array $variables = []): self
    {
        $this->footerHtml = $this->renderTwigTemplate($template, $variables);
        return $this;
    }

    // ------------------------------------------------------------------
    // Other options
    // ------------------------------------------------------------------

    /**
     * Only include specific pages (e.g., '1-5, 8, 11-13')
     */
    public function pages(string $pages): self
    {
        $this->pages = $pages;
        return $this;
    }

    /**
     * Set zoom scale (0.1 to 2.0, default 1.0)
     */
    public function scale(float $scale): self
    {
        $this->pdfScale = $scale;
        return $this;
    }

    /**
     * Include background graphics
     */
    public function showBackground(bool $show = true): self
    {
        $this->showBackgroundGraphics = $show;
        return $this;
    }

    /**
     * Create tagged/accessible PDF
     */
    public function tagged(bool $tagged = true): self
    {
        $this->isTagged = $tagged;
        return $this;
    }

    // ------------------------------------------------------------------
    // Output methods
    // ------------------------------------------------------------------

    /**
     * Get raw PDF content as string
     */
    public function pdf(): string
    {
        $driver = $this->resolveDriver();
        $this->applySource($driver);
        $this->applySettings($driver);
        return $driver->render();
    }

    /**
     * Get PDF as base64 encoded string
     */
    public function base64(): string
    {
        return base64_encode($this->pdf());
    }

    /**
     * Save PDF to a file path
     *
     * @param string $path File path (supports Craft aliases like @storage)
     * @return string The resolved file path
     */
    public function save(string $path): string
    {
        $resolvedPath = Craft::getAlias($path);

        $dir = dirname($resolvedPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($resolvedPath, $this->pdf());

        return $resolvedPath;
    }

    /**
     * Save PDF as a Craft asset in the specified volume
     *
     * @param string $volumeHandle The volume handle
     * @param string $filename The filename for the asset
     * @param string $subpath Optional subfolder path within the volume
     * @param bool $overwrite Whether to overwrite an existing asset with the same name
     * @return Asset The created/updated asset
     */
    public function toAsset(string $volumeHandle, string $filename, string $subpath = '', bool $overwrite = true): Asset
    {
        $pdf = $this->pdf();

        $volumes = Craft::$app->getVolumes();
        $volume = $volumes->getVolumeByHandle($volumeHandle);

        if (!$volume) {
            throw new InvalidConfigException("Volume \"{$volumeHandle}\" not found.");
        }

        // Normalize subpath
        $subpath = trim($subpath, '/');
        $folderPath = $subpath ? $subpath . '/' : '';

        // Ensure the folder exists
        $assets = Craft::$app->getAssets();
        $folder = $assets->findFolder([
            'volumeId' => $volume->id,
            'path' => $folderPath,
        ]);

        if (!$folder) {
            $folder = $assets->ensureFolderByFullPathAndVolume($folderPath, $volume);
        }

        // Check for existing asset and delete if overwriting
        if ($overwrite) {
            $existing = Asset::find()
                ->volumeId($volume->id)
                ->folderId($folder->id)
                ->filename($filename)
                ->one();

            if ($existing) {
                Craft::$app->getElements()->deleteElement($existing);
            }
        }

        // Create new asset (matches Enupal's pattern)
        $tempPath = Assets::tempFilePath(pathinfo($filename, PATHINFO_EXTENSION));
        file_put_contents($tempPath, $pdf);

        $asset = new Asset();
        $asset->tempFilePath = $tempPath;
        $asset->filename = $filename;
        $asset->newFolderId = $folder->id;
        $asset->volumeId = $folder->volumeId;
        $asset->setScenario(Asset::SCENARIO_CREATE);
        $asset->avoidFilenameConflicts = true;

        Craft::$app->getElements()->saveElement($asset);

        return $asset;
    }

    /**
     * Stream PDF inline to browser (view in browser)
     */
    public function inline(?string $filename = null): void
    {
        $pdf = $this->pdf();
        $response = Craft::$app->getResponse();

        $response->headers->set('Content-Type', 'application/pdf');
        if ($filename) {
            $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');
        }
        $response->content = $pdf;

        Craft::$app->end();
    }

    /**
     * Stream PDF as download
     */
    public function download(string $filename): void
    {
        $pdf = $this->pdf();
        $response = Craft::$app->getResponse();

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->content = $pdf;

        Craft::$app->end();
    }

    // ------------------------------------------------------------------
    // Internal methods
    // ------------------------------------------------------------------

    /**
     * Resolve the active PDF driver from plugin settings
     */
    private function resolveDriver(): PdfDriverInterface
    {
        return Paperclip::$plugin->getDriver();
    }

    /**
     * Set the source (HTML, URL, or template) on the driver
     */
    private function applySource(PdfDriverInterface $driver): void
    {
        if ($this->url) {
            $driver->loadUrl($this->url);
            return;
        }

        $html = $this->html ?? $this->renderTemplate();
        $driver->loadHtml($html);
    }

    /**
     * Apply all accumulated settings to the driver
     */
    private function applySettings(PdfDriverInterface $driver): void
    {
        // Paper size
        $orientation = $this->isLandscape ? 'landscape' : 'portrait';

        if ($this->paperWidth && $this->paperHeight) {
            $driver->setPaperSize($this->paperWidth, $this->paperHeight, $this->paperUnit);
            // Still set orientation via setPaper with default format
            $format = $this->format ?? Paperclip::$plugin->getSettings()->defaultFormat;
            $driver->setPaper($format, $orientation);
        } else {
            $format = $this->format ?? Paperclip::$plugin->getSettings()->defaultFormat;
            $driver->setPaper($format, $orientation);
        }

        // Margins
        if (!empty($this->margins)) {
            $driver->setMargins(
                $this->margins[0],
                $this->margins[1],
                $this->margins[2],
                $this->margins[3],
                $this->marginUnit
            );
        }

        // Header/footer
        if ($this->headerHtml) {
            $driver->setHeaderHtml($this->headerHtml);
        }

        if ($this->footerHtml) {
            $driver->setFooterHtml($this->footerHtml);
        }

        // Other options
        $driver->setShowBackground($this->showBackgroundGraphics);

        if ($this->pages) {
            $driver->setPages($this->pages);
        }

        if ($this->pdfScale) {
            $driver->setScale($this->pdfScale);
        }

        if ($this->isTagged) {
            $driver->setTagged(true);
        }
    }

    /**
     * Render the configured Twig template
     */
    private function renderTemplate(): string
    {
        return $this->renderTwigTemplate($this->template, $this->variables);
    }

    /**
     * Render any Twig template with variables
     */
    private function renderTwigTemplate(string $template, array $variables): string
    {
        $view = Craft::$app->getView();

        // renderPageTemplate processes Yii head/body block tokens,
        // preventing CDATA placeholders from leaking into the HTML
        return $view->renderPageTemplate($template, $variables, $view::TEMPLATE_MODE_SITE);
    }
}

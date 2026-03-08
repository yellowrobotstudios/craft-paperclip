<?php

namespace yellowrobot\paperclip;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\Assets;
use yellowrobot\paperclip\drivers\PdfDriverInterface;
use yii\base\InvalidConfigException;

/**
 * PDF builder with Twig template rendering, response streaming, and asset creation
 *
 * Usage in Twig:
 *   craft.paperclip.view('_pdfs/invoice', { order: order }).inline()
 *   craft.paperclip.html(htmlString).download('document.pdf')
 */
class CraftPdfBuilder extends PdfBuilder
{
    private ?string $template = null;
    private array $variables = [];

    // ------------------------------------------------------------------
    // Static factory methods
    // ------------------------------------------------------------------

    /**
     * Create PDF from a Twig template
     */
    public static function view(string $template, array $variables = []): static
    {
        $instance = new static();
        $instance->template = $template;
        $instance->variables = $variables;
        return $instance;
    }

    // ------------------------------------------------------------------
    // Header/Footer from Twig templates
    // ------------------------------------------------------------------

    /**
     * Set header from a Twig template
     */
    public function headerView(string $template, array $variables = []): static
    {
        return $this->headerHtml($this->renderTwigTemplate($template, $variables));
    }

    /**
     * Set footer from a Twig template
     */
    public function footerView(string $template, array $variables = []): static
    {
        return $this->footerHtml($this->renderTwigTemplate($template, $variables));
    }

    // ------------------------------------------------------------------
    // Craft-specific output methods
    // ------------------------------------------------------------------

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

        // Create new asset
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

    // ------------------------------------------------------------------
    // Override: save() with Craft alias resolution
    // ------------------------------------------------------------------

    /**
     * Save PDF to a file path (supports Craft aliases like @storage)
     *
     * @param string $path File path (supports Craft aliases)
     * @return string The resolved file path
     */
    public function save(string $path): string
    {
        $resolvedPath = Craft::getAlias($path);
        return parent::save($resolvedPath);
    }

    // ------------------------------------------------------------------
    // Override: resolveDriver() from Paperclip plugin
    // ------------------------------------------------------------------

    protected function resolveDriver(): PdfDriverInterface
    {
        if ($this->driver) {
            return $this->driver;
        }

        return Paperclip::$plugin->getDriver();
    }

    // ------------------------------------------------------------------
    // Override: getDefaultFormat() from plugin settings
    // ------------------------------------------------------------------

    protected function getDefaultFormat(): string
    {
        return App::parseEnv(Paperclip::$plugin->getSettings()->defaultFormat);
    }

    // ------------------------------------------------------------------
    // Override: resolveHtml() with Twig template support
    // ------------------------------------------------------------------

    protected function resolveHtml(): string
    {
        if ($this->template) {
            return $this->renderTwigTemplate($this->template, $this->variables);
        }

        return parent::resolveHtml();
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /**
     * Render a Twig template with variables
     */
    private function renderTwigTemplate(string $template, array $variables): string
    {
        $view = Craft::$app->getView();

        // renderPageTemplate processes Yii head/body block tokens,
        // preventing CDATA placeholders from leaking into the HTML
        return $view->renderPageTemplate($template, $variables, $view::TEMPLATE_MODE_SITE);
    }
}

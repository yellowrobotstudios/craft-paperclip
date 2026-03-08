<?php

namespace yellowrobot\paperclip;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\helpers\App;
use craft\web\twig\variables\CraftVariable;
use yellowrobot\paperclip\drivers\BrowsershotDriver;
use yellowrobot\paperclip\drivers\CloudflareDriver;
use yellowrobot\paperclip\drivers\DompdfDriver;
use yellowrobot\paperclip\drivers\GotenbergDriver;
use yellowrobot\paperclip\drivers\PdfDriverInterface;
use yellowrobot\paperclip\drivers\WeasyprintDriver;
use yellowrobot\paperclip\events\RegisterDriversEvent;
use yellowrobot\paperclip\models\Settings;
use yellowrobot\paperclip\variables\PdfVariable;
use yii\base\Event;

/**
 * Paperclip - PDF generation for Craft CMS
 *
 * @property-read Settings $settings
 */
class Paperclip extends Plugin
{
    /**
     * Event fired when registering PDF drivers
     */
    public const EVENT_REGISTER_DRIVERS = 'registerDrivers';

    public static Paperclip $plugin;

    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    /**
     * @var array<string, class-string<PdfDriverInterface>> Registered driver classes
     */
    private array $drivers = [];

    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Register built-in drivers
        $this->drivers = [
            'dompdf' => DompdfDriver::class,
            'browsershot' => BrowsershotDriver::class,
            'gotenberg' => GotenbergDriver::class,
            'cloudflare' => CloudflareDriver::class,
            'weasyprint' => WeasyprintDriver::class,
        ];

        // Allow third-party driver registration
        $event = new RegisterDriversEvent(['drivers' => $this->drivers]);
        $this->trigger(self::EVENT_REGISTER_DRIVERS, $event);
        $this->drivers = $event->drivers;

        // Register Twig variable
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                $variable = $event->sender;
                $variable->set('paperclip', PdfVariable::class);
            }
        );

        Craft::info('Paperclip plugin loaded', __METHOD__);
    }

    /**
     * Create a new instance of the configured PDF driver
     */
    public function getDriver(?string $handle = null): PdfDriverInterface
    {
        $handle = $handle ?? App::parseEnv($this->getSettings()->driver);

        if (!isset($this->drivers[$handle])) {
            throw new \RuntimeException(
                "Unknown PDF driver \"{$handle}\". Available drivers: " . implode(', ', array_keys($this->drivers))
            );
        }

        $class = $this->drivers[$handle];

        if ($handle === 'dompdf') {
            $settings = $this->getSettings();
            return new $class([
                'fontDir' => App::parseEnv($settings->dompdfFontDir),
                'defaultFont' => App::parseEnv($settings->dompdfDefaultFont),
                'dpi' => $settings->dompdfDpi,
            ]);
        }

        return new $class();
    }

    /**
     * Get all registered driver handles
     *
     * @return string[]
     */
    public function getDriverHandles(): array
    {
        return array_keys($this->drivers);
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'paperclip/settings',
            [
                'settings' => $this->getSettings(),
                'driverOptions' => $this->getDriverOptions(),
            ]
        );
    }

    /**
     * Get driver options for the settings dropdown
     */
    private function getDriverOptions(): array
    {
        $options = [];
        foreach ($this->drivers as $handle => $class) {
            $options[] = [
                'label' => match ($handle) {
                    'dompdf' => 'DOMPDF',
                    'browsershot' => 'Browsershot',
                    'gotenberg' => 'Gotenberg',
                    'cloudflare' => 'Cloudflare',
                    'weasyprint' => 'WeasyPrint',
                    default => ucfirst($handle),
                },
                'value' => $handle,
            ];
        }
        return $options;
    }
}

<?php

namespace yellowrobot\paperclip\events;

use yii\base\Event;

/**
 * Event for registering third-party PDF drivers
 *
 * Usage in a module or plugin:
 *   Event::on(
 *       Paperclip::class,
 *       Paperclip::EVENT_REGISTER_DRIVERS,
 *       function (RegisterDriversEvent $event) {
 *           $event->drivers['mydriver'] = MyCustomDriver::class;
 *       }
 *   );
 */
class RegisterDriversEvent extends Event
{
    /**
     * @var array<string, class-string> Map of driver handle => driver class name
     */
    public array $drivers = [];
}

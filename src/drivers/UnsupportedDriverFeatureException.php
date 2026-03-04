<?php

namespace yellowrobot\paperclip\drivers;

use RuntimeException;

class UnsupportedDriverFeatureException extends RuntimeException
{
    public string $feature;

    public function __construct(string $message, string $feature, int $code = 0, ?\Throwable $previous = null)
    {
        $this->feature = $feature;
        parent::__construct($message, $code, $previous);
    }
}

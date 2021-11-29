<?php

namespace Grsu\SamlSpService\Exception;

use Exception;
use Throwable;


class SamlConfigException extends Exception
{
    public function __construct($message = "", Throwable $previous = null)
    {
        parent::__construct($message, 500, $previous);
    }
}
<?php

namespace Grsu\SamlSpService\Exception;

use Exception;
use Throwable;


class InvalidSamlResponseException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
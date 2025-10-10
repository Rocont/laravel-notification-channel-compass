<?php

namespace Rocont\CompassChannel\Exceptions;

use Exception;

class CompassException extends Exception
{
    protected ?int $compassCode;

    protected array $response;

    public function __construct(
        string     $message,
        ?int       $compassCode = null,
        array      $response = [],
        int        $code = 0,
        ?Exception $previous = null
    )
    {
        parent::__construct($message, $code, $previous);

        $this->compassCode = $compassCode;
        $this->response = $response;
    }

    public function getCompassCode(): ?int
    {
        return $this->compassCode;
    }

    public function getResponse(): array
    {
        return $this->response;
    }
}
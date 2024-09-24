<?php

namespace App\HTTP;

class Request
{
    protected array $parameters;

    public function __construct(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function get(string $key): mixed
    {
        return $this->parameters[$key] ?? null;
    }


}

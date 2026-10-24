<?php

namespace Dromos\Controllers;

use Dromos\Services\HelloWorldService;
use Dromos\Http\Request;
use Dromos\Http\Response;

class HelloWorldController
{
    protected HelloWorldService $service;

    public function __construct()
    {
        $this->service = new HelloWorldService();
    }
}

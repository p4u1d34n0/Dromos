<?php

namespace Dromos\Controllers;

use Dromos\Services\HelloWorldService;
use Dromos\HTTP\Request;
use Dromos\HTTP\Response;

class HelloWorldController
{
    protected HelloWorldService $service;

    public function __construct()
    {
        $this->service = new HelloWorldService();
    }

    public function handle(Request $request, Response $response): void
    {
        $message = $this->service->sayHello();
        $response->json(data: ['message' => $message]);
    }
}

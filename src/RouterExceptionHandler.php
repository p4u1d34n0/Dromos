<?php

namespace App;

use Exception;
use Throwable;

class RouterExceptionHandler extends Exception
{

    public static function handle(Throwable $e, $responseCode = 500, $args = null): void
    {
        http_response_code($responseCode);

        // get all superglobals
        $globals = [
            '$_GET' => $_GET,
            '$_POST' => $_POST,
            '$_COOKIE' => $_COOKIE,
            '$_FILES' => $_FILES,
            '$_SERVER' => $_SERVER,
            '$_ENV' => $_ENV,
            '$_REQUEST' => $_REQUEST,
            '$_SESSION' => $_SESSION ?? [],
        ];

        // Render a custom error page
        echo self::renderErrorPage($e, $globals, $args);
    }

    private static function renderErrorPage(Throwable $e, array $globals, array $args): string
    {
        $globalsHTML = '';
        foreach ($globals as $key => $value) {
            $globalsHTML .= '
                <li style="border-bottom:1px dashed red">
                    <h3 style="color:red;">' . $key . '</h3>
                    <div style="max-width:80vw;overflow:scroll;">
                        <pre>' . htmlspecialchars(print_r($value, true)) . '</pre>
                    </div>
                </li>';
        }

        $argsHTML = '';
        if (!empty($args)) {
            foreach ($args as $key => $value) {
                $argsHTML .= '
                <li style="border-bottom:1px dashed red">
                    <h3 style="color:red;">' . $key . '</h3>
                    <div style="max-width:80vw;overflow:scroll;">
                        <pre>' . htmlspecialchars(print_r($value, true)) . '</pre>
                    </div>
                </li>';
            }
        }

        
        return '<!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Exception</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            background-color: black;
                            color: #bf2e00;
                            margin: 0;
                            padding: 0;
                            display: flex;
                            flex-direction: column;
                            justify-content: center;
                            align-items: center;
                            min-height: 100vh;
                        }
                        .container {
                            text-align: left;
                            background-color: beige;
                            padding: 40px;
                            margin-top: 20px;
                            border-radius: 8px;
                            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
                            min-width: 80vw;
                        }
                        .warning-sign {
                            font-size: 32px;
                            color: red;
                        }

                        h1, h2, h3 {
                            color: red;
                        }
                        h3:after {
                            content: " >> ";
                        }
                        p {
                            font-size: 18px;
                        }
                        pre {
                            font-family: monospace;
                            font-size: 14px;
                            color: green;
                            line-height: 1.4rem;
                            border-left: solid 2px green;
                            padding-left: 20px;
                        }
                        .details {
                            margin-top: 20px;
                            cursor: pointer;
                            font-weight: bold;
                            color: #007bff;
                            user-select: none;
                        }
                        .details.shown {
                            display: block !important;
                        }
                        .details:hover {
                            color:black;
                        }
                        ul {
                            list-style-type: none;
                            padding: 0;
                            margin: 0;
                        }
                        li {
                            margin-bottom: 10px;
                            border-bottom: 1px dashed red;
                        }
                        
                    </style>
                </head>
                <body>
                
                    <div class="container">
                        <h1><span class="warnignsign">&#x26A0;</span> Exception</h1>
                        <div class="details shown">
                            <strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '<br>
                            <strong>File:</strong> ' . htmlspecialchars($e->getFile()) . '<br>
                            <strong>Line:</strong> ' . htmlspecialchars($e->getLine()) . '<br>
                            <strong>Trace:</strong> <pre>' . htmlspecialchars(print_r($e->getTrace(), true)) . '</pre>
                        </div>
                    </div>

                    <div class="container">
                        <h2>Extra Information</h2>
                        <div class="details">
                            <ul style="text-align:left">
                                ' . $argsHTML . '
                            </ul>
                        </div>
                    </div>

                    <div class="container">
                        <h2>Superglobals</h2>
                        <div class="details">
                            <ul style="text-align:left">
                                ' . $globalsHTML . '
                            </ul>
                        </div>
                    </div>

                    <script>
                        document.querySelectorAll(".details").forEach(detail => {
                            detail.addEventListener("click", (e) => {
                                const content = e.target.nextElementSibling;
                                if (content && content.style.display === "none") {
                                    content.style.display = "block";
                                } else if (content) {
                                    content.style.display = "none";
                                }
                            });
                        });
                    </script>
                    
                </body>
                </html>
            ';
    }
}

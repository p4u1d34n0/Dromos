<?php

namespace App;

use Exception;
use Throwable;

/**
 * RouterExceptionHandler class
 *
 * This class extends the base Exception class and provides methods to handle exceptions
 * by setting the HTTP response code and rendering a custom error page with detailed 
 * information about the exception, including superglobals and additional arguments.
 *
 * Methods:
 * - handle(Throwable $e, int $responseCode = 500, mixed $args = null): void
 *   Handles exceptions by setting the HTTP response code and rendering a custom error page.
 *
 * - renderErrorPage(Throwable $e, array $globals, array $args): string
 *   Renders an error page with detailed information about an exception, including superglobals
 *   and additional arguments.
 *
 * Usage:
 * RouterExceptionHandler::handle($exception);
 *
 * Example:
 * try {
 *     // Some code that may throw an exception
 * } catch (Throwable $e) {
 *     RouterExceptionHandler::handle($e);
 * }
 *
 * @package Router
 * @subpackage ExceptionHandler
 * @version 1.0
 * @since 2023-10
 */
class RouterExceptionHandler extends Exception
{

    /**
     * Handles exceptions by setting the HTTP response code and rendering a custom error page.
     *
     * @param Throwable $e The exception to handle.
     * @param int $responseCode The HTTP response code to set (default is 500).
     * @param mixed $args Additional arguments to pass to the error page renderer (default is null).
     *
     * @return void
     */
    public static function handle(Throwable $e, $responseCode = 500, $args = null): void
    {
        http_response_code(response_code: $responseCode);

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
        throw new RouterException(message: $e->getMessage(), code: $responseCode, previous: $args);
        //echo self::renderErrorPage(e: $e, globals: $globals, args: $args);
    }

    /**
     * Renders an error page with detailed information about an exception.
     *
     * This method generates an HTML page displaying the exception message, file, line, and stack trace.
     * It also includes additional information from the provided globals and args arrays.
     *
     * @param Throwable $e The exception to be displayed.
     * @param array $globals An associative array of global variables to be displayed.
     * @param array $args An associative array of additional arguments to be displayed.
     * @return string The generated HTML content as a string.
     */
    private static function renderErrorPage(Throwable $e, array $globals, array $args): string
    {
        $globalsHTML = '';
        foreach ($globals as $key => $value) {
            $globalsHTML .= '
                <li style="border-bottom:1px dashed red">
                    <h3 style="color:red;">' . $key . '</h3>
                    <div style="max-width:80vw;overflow:scroll;">
                        <pre>' . htmlspecialchars(string: print_r(value: $value, return: true)) . '</pre>
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
                        <pre>' . htmlspecialchars(string: print_r(value: $value, return: true)) . '</pre>
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
                            <strong>Error:</strong> ' . htmlspecialchars(string: $e->getMessage()) . '<br>
                            <strong>File:</strong> ' . htmlspecialchars(string: $e->getFile()) . '<br>
                            <strong>Line:</strong> ' . htmlspecialchars(string: $e->getLine()) . '<br>
                            <strong>Trace:</strong> <pre>' . htmlspecialchars(string: print_r(value: $e->getTrace(), return: true)) . '</pre>
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

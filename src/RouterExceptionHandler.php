<?php

namespace Dromos;

use Exception;
use Throwable;


/**
 * Class RouterExceptionHandler
 *
 * This class extends the base Exception class and provides a static method to handle exceptions
 * by setting the HTTP response status code and displaying an appropriate error page.
 *
 * @package Router
 */
class RouterExceptionHandler extends Exception
{

    /**
     * Handles exceptions by setting the response status code and displaying an appropriate error page.
     *
     * @param Throwable $e The exception that was thrown.
     * @param int $responseCode The HTTP response code to set. Defaults to 500.
     * @param mixed $args Additional arguments that may be used in the future. Defaults to null.
     *
     * @return void
     */

    public static function handle(Throwable $e, $errorcode = 500, $args = []): void
    {

        // Set the response status code
        http_response_code(response_code: $errorcode);

        // Determine the path of the error page based on the response code
        $errorPage = __DIR__ . '/../views/errors/error.php';

        // Check if the error page exists, otherwise show a generic error page
        if (file_exists(filename: $errorPage)) {
            include($errorPage);
        } else {
            // Fallback to a generic error message
            throw new RouterException(message: 'An error occurred', code: $errorcode, previous: $e);
        }
        exit; // Stop further execution

    }
}

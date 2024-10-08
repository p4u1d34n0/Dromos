<?php

namespace App\Middleware;

class RequestParameters
{

    /**
     * Handles the extraction of parameters from a given URL based on a specified route.
     *
     * This method takes a route with parameter placeholders and an actual URL, then extracts
     * the parameter values from the URL based on the placeholders in the route.
     *
     * @param string $route The route pattern containing parameter placeholders (e.g., '/user/{id}').
     * @param string $url The actual URL to extract parameters from (e.g., '/user/123').
     * @return array An associative array of parameter names and their corresponding values.
     *               Returns an empty array if the URL does not match the route pattern.
     */
    public static function handle(string $route, string $url): array
    {

        // Extract parameter names from the route
        $paramNames = self::extractParameterNames(route: $route);

        // Create a regex pattern for the route with parameters
        $routePattern = self::createRoutePattern(route: $route);

        // Match the actual URL against the pattern
        if (preg_match(pattern: "#^$routePattern$#", subject: $url, matches: $urlMatches)) {

            // Remove the full match from the array
            array_shift(array: $urlMatches);

            // Combine parameter names with matched values
            $params = array_combine(keys: $paramNames, values: $urlMatches);

            // Return the parameters
            return $params;
        }

        // No match found
        return [];
    }

    /**
     * Extracts parameter names from a given route string.
     *
     * This method uses a regular expression to find all parameter names enclosed
     * in curly braces within the provided route string. It returns an array of
     * these parameter names.
     *
     * @param string $route The route string containing parameter placeholders.
     * @return array An array of parameter names extracted from the route.
     */
    private static function extractParameterNames(string $route): array
    {
        preg_match_all(
            pattern: '/\{([^}]+)\}/',
            subject: $route,
            matches: $matches
        );
        return $matches[1];
    }

    /**
     * Converts a route pattern with placeholders into a regex pattern.
     *
     * This method takes a route pattern containing placeholders in curly braces
     * (e.g., "/user/{id}") and converts it into a regular expression pattern
     * that can be used to match actual routes (e.g., "/user/123").
     *
     * @param string $route The route pattern with placeholders.
     * @return string The converted regex pattern.
     */
    private static function createRoutePattern(string $route): string
    {
        return preg_replace(
            pattern: '/\{([^}]+)\}/',
            replacement: '([^/]+)',
            subject: $route
        );
    }
}

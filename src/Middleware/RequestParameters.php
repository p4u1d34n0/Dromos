<?php   

namespace App\Middleware;

class RequestParameters {
    
    public static function handle(string $route, string $url): array {

        // Extract parameter names from the route
        $paramNames = self::extractParameterNames($route);
    
        // Create a regex pattern for the route with parameters
        $routePattern = self::createRoutePattern($route);
    
        // Match the actual URL against the pattern
        if (preg_match("#^$routePattern$#", $url, $urlMatches)) {

            // Remove the full match from the array
            array_shift($urlMatches); 
            
            // Combine parameter names with matched values
            $params = array_combine($paramNames, $urlMatches); 

            // Return the parameters
            return $params; 
        }
    
        // No match found
        return []; 
    }

    private static function extractParameterNames(string $route): array {
        preg_match_all('/\{([^}]+)\}/', $route, $matches);
        return $matches[1];
    }

    private static function createRoutePattern(string $route): string {
        return preg_replace('/\{([^}]+)\}/', '([^/]+)', $route);
    }

}


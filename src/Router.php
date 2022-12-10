<?php

namespace DeLoachTech\Router;

class Router
{
    private static $routes = [];
    private static $pathNotFound = null;
    private static $methodNotAllowed = null;


    /**
     * Function used to add a new route
     * @param string $expression Route string or expression
     * @param callable $function Function to call if route with allowed method is found
     * @param string|array $method Either a string of allowed method or an array with string values
     *
     */
    public static function add(string $expression, callable $function, $method = 'get')
    {
        self::$routes[] = [
            'expression' => $expression,
            'function' => $function,
            'method' => $method
        ];
    }


    public static function getAll(): array
    {
        return self::$routes;
    }


    public static function pathNotFound($function)
    {
        self::$pathNotFound = $function;
    }


    public static function methodNotAllowed($function)
    {
        self::$methodNotAllowed = $function;
    }


    /**
     * @param string $basePath
     * @param bool $case_matters
     * @param bool $trailing_slash_matters
     * @param bool $multimatch
     * @return void
     */
    public static function run(string $basePath = '', bool $case_matters = false, bool $trailing_slash_matters = false, bool $multimatch = false)
    {

        $basePath = rtrim($basePath, '/');
        $parsed_url = parse_url($_SERVER['REQUEST_URI']);
        $path = '/';

        // If there is a path available
        if (isset($parsed_url['path'])) {

            // If the trailing slash matters
            if ($trailing_slash_matters) {
                $path = $parsed_url['path'];
            }
            else {

                // If the path is not equal to the base path (including a trailing slash)
                if ($basePath . '/' != $parsed_url['path']) {

                    // Cut the trailing slash away because it does not matters
                    $path = rtrim($parsed_url['path'], '/');
                }
                else {
                    $path = $parsed_url['path'];
                }
            }
        }

        $path = urldecode($path);

        // Get current request method
        $method = $_SERVER['REQUEST_METHOD'];

        $path_match_found = false;

        $route_match_found = false;

        foreach (self::$routes as $route) {

            // If the method matches check the path

            // Add basepath to matching string
            if ($basePath != '' && $basePath != '/') {
                $route['expression'] = '(' . $basePath . ')' . $route['expression'];
            }

            // Add 'find string start' automatically
            $route['expression'] = '^' . $route['expression'];

            // Add 'find string end' automatically
            $route['expression'] = $route['expression'] . '$';

            // Check path match
            if (preg_match('#' . $route['expression'] . '#' . ($case_matters ? '' : 'i') . 'u', $path, $matches)) {
                $path_match_found = true;

                // Cast allowed method to array if it's not one already, then run through all methods
                foreach ((array)$route['method'] as $allowedMethod) {
                    // Check method match
                    if (strtolower($method) == strtolower($allowedMethod)) {
                        array_shift($matches); // Always remove first element. This contains the whole string

                        if ($basePath != '' && $basePath != '/') {
                            array_shift($matches); // Remove basepath
                        }

                        if ($return_value = call_user_func_array($route['function'], $matches)) {
                            echo $return_value;
                        }

                        $route_match_found = true;

                        // Do not check other routes
                        break;
                    }
                }
            }

            // Break the loop if the first found route is a match
            if ($route_match_found && !$multimatch) {
                break;
            }

        }

        // No matching route was found
        if (!$route_match_found) {
            // But a matching path exists
            if ($path_match_found) {
                if (self::$methodNotAllowed) {
                    if ($return_value = call_user_func_array(self::$methodNotAllowed, [$path, $method])) {
                        echo $return_value;
                    }
                }
            }
            else {
                if (self::$pathNotFound) {
                    if ($return_value = call_user_func_array(self::$pathNotFound, [$path])) {
                        echo $return_value;
                    }
                }
            }
        }
    }
}
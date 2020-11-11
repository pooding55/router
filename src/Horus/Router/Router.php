<?php
namespace Horus\Router;

use Horus\Router\Exceptions\RouteNotFoundException;
use Horus\Router\Exceptions\RouteRegularExpressionCompilationException;
use Psr\Http\Message\RequestInterface;

/**
 * Class Router
 * @package Horus\Router
 */
class Router
{

    /**
     * A collection of all declared routes
     * @var array
     */
    public static array $routesCollection;

    /**
     * @var array
     */
    public static array $currentRoute;

    /**
     * @var string
     */
    protected static string $basePath = '';

    /**
     * @var array
     */
    protected static array $defaultMiddlewares = [];

    /**
     * @var array
     */
    protected static array $defaultAfterMiddlewares = [];

    /**
     * @var string
     */
    protected static string $defaultGroup = '';

    /**
     * Templates for variables in url
     * @var array
     */
    protected static array $placeholders = [
        'i' => '[0-9]++',
        'a' => '[0-9A-Za-z]++',
        'h' => '[0-9A-Fa-f]++',
        '*' => '.+?',
        '**' => '.++',
        '' => '[^/\.]++'
    ];

    /**
     * Sets the base route, all routes can be written ignoring the prefix. For example /en/base
     * @param string $basePath
     */
    static function setBasePath(string $basePath)
    {
        if($basePath === '/') {
            $basePath = '';
        }
        self::$basePath = $basePath;
    }


    /**
     * Add route to to collection
     * @param string $methods
     * @param string $patch
     * @param mixed $target
     * @param mixed $name
     * @return Router
     */
    public static function map(string $methods, string $patch, $target, $name = null)
    {
        self::$currentRoute = [
            'methods' => $methods,
            'patch' => self::$basePath . $patch,
            'target' => $target,
            'name' => $name,
            'group' => self::$defaultGroup,
            'middlewares' => self::$defaultMiddlewares,
            'after_middlewares' => self::$defaultAfterMiddlewares
        ];

        self::$routesCollection[] = self::$currentRoute;

        return new self();
    }


    /**
     * Feature spied from Laravel
     * @param string $namespace
     * @param string $controller
     * @param string $name
     * @return Router
     */
    public static function crud(string $namespace, string $controller, string $name)
    {
        return self::group($namespace, function () use ($controller, $name) {
            Router::map('GET', '', $controller . '@index', $name . '@index'); // index - get all records
            Router::map('POST', '', $controller . '@store', $name . '@store'); // store - add record to DB
            Router::map('GET', '/[i:id]', $controller . '@show', $name . '@show'); // show - get record by ID
            Router::map('PUT|PATCH', '/[i:id]', $controller . '@update', $name . '@update'); // update - update record in DB
            Router::map('DELETE', '/[i:id]', $controller . '@destroy', $name . '@destroy'); // destroy - delete record
            Router::map('GET', '/create', $controller . '@create', $name . '@create'); // create - get creating form
            Router::map('GET', '/[i:id]/edit', $controller . '@edit', $name . '@edit'); // edit - get editing form
        });
    }

    /**
     * Compiles the path, substituting a regular expression instead of a placeholder
     * @param $route
     * @return string
     * @throws RouteRegularExpressionCompilationException
     */
    protected static function compile($route)
    {
        if (preg_match_all('`(/|\.|)\[([^:\]]*+)(?::([^:\]]*+))?\](\?|)`', $route, $matches, PREG_SET_ORDER)) {
            $matchTypes = self::$placeholders;
            foreach ($matches as $match) {
                list($block, $pre, $type, $param, $optional) = $match;

                if (isset($matchTypes[$type])) {
                    $type = $matchTypes[$type];
                }
                if ($pre === '.') {
                    $pre = '\.';
                }

                $optional = $optional !== '' ? '?' : null;

                $pattern = '(?:'
                    . ($pre !== '' ? $pre : null)
                    . '('
                    . ($param !== '' ? "?P<$param>" : null)
                    . $type
                    . ')'
                    . $optional
                    . ')'
                    . $optional;

                $route = str_replace($block, $pattern, $route);
            }
        }
        $regex = "`^$route$`u";
        self::validateRegularExpression($regex);
        return $regex;
    }


    /**
     * @param string $regex
     * @return bool
     * @throws RouteRegularExpressionCompilationException
     */
    public static function validateRegularExpression(string $regex)
    {
        $error_string = null;

        set_error_handler(
            function ($errno, $errstr) use (&$error_string) {
                $error_string = $errstr;
            },
            E_NOTICE | E_WARNING
        );

        if (false === preg_match($regex, null) || !empty($error_string)) {
            restore_error_handler();

            throw new RouteRegularExpressionCompilationException(
                $error_string,
                preg_last_error()
            );
        }
        restore_error_handler();
        return true;
    }


    /**
     * Looks for a route that matches the current link
     * @param RequestInterface $request
     * @return array
     * @throws RouteNotFoundException|RouteRegularExpressionCompilationException
     */
    public static function match(RequestInterface $request)
    {
        $params = [];

        $requestUrl = isset($request->getServerParams()['REQUEST_URI']) ? $request->getServerParams()['REQUEST_URI'] : '/';


        // Remove GET-parameters from URL
        if (($strpos = strpos($requestUrl, '?')) !== false) {
            $requestUrl = substr($requestUrl, 0, $strpos);
        }
        //  Убирает последний слеш в строке
        if(mb_substr($requestUrl, -1) == '/' && $requestUrl != '/') {
            $requestUrl = mb_substr($requestUrl, 0, -1);
        }

        echo "URL запроса " . $requestUrl . '<hr>';

        // выбор метода запроса
        $requestMethod = isset($request->getServerParams()['REQUEST_METHOD']) ? $request->getServerParams()['REQUEST_METHOD'] : 'GET';

        // Сверяет список сохранённых роутов с URL
        foreach (self::$routesCollection as $item) {
            $methods = $item['methods'];

            if(mb_substr($item['patch'], -1) == '/' && $item['patch'] != '/') {
                $route = mb_substr($item['patch'], 0, -1);
            }

            $method_match = (stripos($methods, $requestMethod) !== false);

            // Метод не соответствует, пробуем следующий
            if (!$method_match) {
                continue;
            }

            if ($route === '*') {
                // * super-route перекрывает все пути абсолютно
                $match = true;
            } elseif (isset($route[0]) && $route[0] === '@') {
                // TODO понять, що здесь происходит
                // @ regex delimiter
                $pattern = '`' . substr($route, 1) . '`u';
                $match = preg_match($pattern, $requestUrl, $params) === 1;
            } elseif (($position = strpos($route, '[')) === false) {
                // Нативный роут, без параметров
                $match = strcmp($requestUrl, $route) === 0;
            } else {
                $regex = self::compile($route);
                $match = preg_match($regex, $requestUrl, $params) === 1;
            }
            echo 'Возможный маршрут ' . $item['patch'] . '<br>';
            if ($match) {
                if ($params) {
                    foreach ($params as $key => $value) {
                        if (is_numeric($key)) {
                            unset($params[$key]);
                        }
                    }
                }

                $item['params'] = $params;
                return $item;
            } else {
                throw new RouteNotFoundException('Route not found, check your routes map');
            }
        }
    }

    /**
     * Используется для создания группы рулов с одним общим namespace
     * @param string $namespace
     * @param callable $routes
     * @return Router
     */
    static function group(string $namespace, callable $routes)
    {
        // Временно ставим базовый путь = базовый путь + нэймспэйс
        $basePatchGeneral = self::$basePath;
        $baseDefaultGroup = self::$defaultGroup;


        self::setBasePath($basePatchGeneral.$namespace);
        self::$defaultGroup = $namespace;

        if (is_callable($routes)) {
            call_user_func($routes, $namespace);
        }

        // Возвращаем дефолтный базовый путь.
        self::setBasePath($basePatchGeneral);
        self::$defaultGroup = $baseDefaultGroup;
        return new self();
    }


    /**
     * @param array $middlewares
     */
    public static function setDefaultMiddlewares(array $middlewares)
    {
        self::$defaultMiddlewares = $middlewares;
    }

    /**
     * @param array $middlewares
     */
    public static function setDefaultAfterMiddlewares(array $middlewares)
    {
        self::$defaultAfterMiddlewares = $middlewares;
    }


    /**
     * добавляет посредника к маршруту/группе маршрутов
     * @param array $middlewares
     * @return Router
     */
    public static function middleware(array $middlewares)
    {
        return self::addMiddleware($middlewares, '');
    }

    /**
     * добавляет посредника к маршруту/группе маршрутов
     * @param array $middlewares
     * @return Router
     */
    public static function afterMiddleware(array $middlewares)
    {
        return self::addMiddleware($middlewares, 'after_');
    }

    /**
     * @param array $middlewares
     * @param string $type
     * @return Router
     */
    private static function addMiddleware(array $middlewares, string $type)
    {
        $type = $type . 'middlewares';
        if(self::$currentRoute['group'] != self::$defaultGroup)
        {
            // для группы
            foreach (self::$routesCollection as $key => $route) {
                if($route['group'] == self::$currentRoute['group']) {
                    $route[$type] = array_unique(array_merge($route[$type], $middlewares));;
                    unset(self::$routesCollection[$key]);
                    self::$routesCollection[$key] = $route;
                }
            }

        } else {
            // для маршрута
            array_pop(self::$routesCollection);
            self::$currentRoute[$type] = array_unique(array_merge(self::$currentRoute[$type], $middlewares));
            self::$routesCollection[] = self::$currentRoute;
        }

        return new self();
    }




    /**
     * Удаляет посредника для маршрута/группы.
     * @param array $middlewares
     * @return Router
     */
    public static function withoutMiddleware(array $middlewares)
    {
        return self::removeMiddleware($middlewares, '');
    }

    /**
     * Удаляет посредника для маршрута/группы.
     * @param array $middlewares
     * @return Router
     */
    public static function withoutAfterMiddleware(array $middlewares)
    {
        return self::removeMiddleware($middlewares, 'after_');
    }

    /**
     * @param array $middlewares
     * @param string $type
     * @return Router
     */
    public static function removeMiddleware(array $middlewares, string $type)
    {
        $type = $type . 'middlewares';
        if(self::$currentRoute['group'] != self::$defaultGroup) {
            // Мидлвери должен отпработать для группы

            foreach (self::$routesCollection as $key => $route) {
                if($route['group'] == self::$currentRoute['group']) {
                    $route[$type] = array_diff($route[$type], $middlewares);
                    sort($route[$type]);
                    unset(self::$routesCollection[$key]);
                    self::$routesCollection[$key] = $route;
                }
            }

        } else {
            array_pop(self::$routesCollection);
            self::$currentRoute[$type] = array_diff(self::$currentRoute[$type], $middlewares);
            sort(self::$currentRoute[$type]);
            self::$routesCollection[] = self::$currentRoute;
        }

        return new self();
    }

}
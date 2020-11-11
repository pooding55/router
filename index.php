<?php
require 'vendor/autoload.php';

use Horus\Router\Router;
use Laminas\Diactoros\ServerRequestFactory;

//Initial router setup
Router::setBasePath('/');
Router::setDefaultMiddlewares(['BodyParams','Auth','BeforeLogs']);
Router::setDefaultAfterMiddlewares(['Logs']);

// Simple Route
Router::map('GET','/','HomeController@index','home-page');
Router::map('GET','/hello-[:name]/','HelloController@index','hello-page');

// Routing group
Router::group('/auth', function () {
    Router::map('GET','/','Auth\LoginController@home','login-home');

    Router::map('GET','/login','Auth\LoginController@login','login-page');
    Router::map('POST','/logout','Auth\LoginController@logout','logout-action');
    Router::map('GET','/register','Auth\RegisterController@register','registration-form');
});


// Find a route suitable for the URL
$request = ServerRequestFactory::fromGlobals();

try {
    $route = Router::match($request);
    var_dump($route);
} catch (\Horus\Router\Exceptions\RouteNotFoundException $e) {
    die("RouteNotFoundException");
} catch (\Horus\Router\Exceptions\RouteRegularExpressionCompilationException $e) {
    die("RouteRegularExpressionCompilationException");
}














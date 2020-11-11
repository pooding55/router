# Horus Router
Simple, but powerful router. Use psr-7 request object

Router is inspired by https://github.com/klein/klein.php.  You can use it to write your own frameworks.


### Examples

#### 1. Simple route
```php
Router::map('GET','/','HomeController@index','home-page');
```

#### 2. Route with parameters
```php
// sitename.com/hello-alex
Router::map('GET','/hello-[a:name]','HelloController@index','hello-page');
// sitename.com/users/4
Router::map('GET','/users/[i:id]','User@show','user-page');
// sitename.com/any/13dd4
Router::map('GET','/any/[:id]','any@show','any-page');
```
#### 3. Group

Routes belonging to one model are very convenient to group

All routes in the group will look like

sitename.com/groupname/patch

```php
Router::group('/auth', function () {
    Router::map('GET','/','Auth\LoginController@home','login-home');
    Router::map('GET','/login','Auth\LoginController@login','login-page');
    Router::map('POST','/logout','Auth\LoginController@logout','logout-action');
    Router::map('GET','/register','Auth\RegisterController@register','registration-form');
});
```

#### 3. REST/CRUD/RESOURCES routes groups
For objects that imply a standard set of logic, you can use CRUD routing.
```php
Router::crud('/roles', 'RolesController', 'roles');
```

This code can be turned into this:

```php
Router::map('GET',       '/roles',             'RolesController@index',   'roles@index');   // index - get all records
Router::map('POST',      '/roles',             'RolesController@store',   'roles@store');   // store - add record to DB
Router::map('GET',       '/roles/[i:id]',      'RolesController@show',    'roles@show');    // show - get record by ID
Router::map('PUT|PATCH', '/roles/[i:id]',      'RolesController@update',  'roles@update');  // update - update record in DB
Router::map('DELETE',    '/roles/[i:id]',      'RolesController@destroy', 'roles@destroy'); // destroy - delete record
Router::map('GET',       '/roles/create',      'RolesController@create',  'roles@create');  // create - get creating form
Router::map('GET',       '/roles/[i:id]/edit', 'RolesController@edit',    'roles@edit');    // edit - get editing form
```

# Usage

```php
<?php
require 'vendor/autoload.php';

use Horus\Router\Router;
use Laminas\Diactoros\ServerRequestFactory;

//Initial router setup
Router::setBasePath('/');

// Simple Route
Router::map('GET','/','HomeController@index','home-page');

// Routing group
Router::group('/auth', function () {
    Router::map('GET','/','Auth\LoginController@home','login-home');
    Router::map('GET','/login','Auth\LoginController@login','login-page');
    Router::map('POST','/logout','Auth\LoginController@logout','logout-action');
    Router::map('GET','/register','Auth\RegisterController@register','registration-form');
})::withoutMiddleware(['CheckAuth']);


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
```

# Middlewares
The router has the ability to specify middleware specific to the established route.
```php
<?php
require 'vendor/autoload.php';

use Horus\Router\Router;

//Initial router setup
Router::setBasePath('/');

// Actions to be taken BEFORE execution route target
Router::setDefaultMiddlewares(['CheckAuth']);
// Actions to be taken AFTER execution route target
Router::setDefaultAfterMiddlewares(['WriteLogs']);

// There is no need to check authorization for this route
Router::map('GET','/','HomeController@index','home-page')::withoutMiddleware(['CheckAuth']);

```



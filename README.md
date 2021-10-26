# Routing library for PHP

This library allows you to create static or dynamic routes. This library was inspired
by [PHP Slim framework](https://www.slimframework.com/)

[![PHP Tests](https://github.com/gigili/PHP-routing/actions/workflows/php.yml/badge.svg?branch=main)](https://github.com/gigili/PHP-routing/actions/workflows/php.yml)
[![License](https://poser.pugx.org/gac/routing/license)](https://packagist.org/packages/gac/routing)
[![Total Downloads](https://poser.pugx.org/gac/routing/downloads)](https://packagist.org/packages/gac/routing)

## Install via composer

```shell
composer require gac/routing
```

## Manual install

Download the latest release from the [Releases page](https://github.com/gigili/PHP-routing/releases).

Don't forget to add these include statements to your php files:

```php
include_once "./Exceptions/CallbackNotFound.php";
include_once "./Exceptions/RouteNotFoundException.php";
include_once "./Request.php";
include_once "./Routes.php";
```

## Post install

To use this library properly you will need to create a `.htaccess` file at the root of the project.

Example of the `.htaccess` file would look like this:

```apacheconf
RewriteEngine On

RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

RewriteRule ^(.+)$ index.php [QSA,L]
```

### Note

If you've named your main file differently, replace `index.php` in the `.htaccess` file with that ever your main
application file is.

## Quick start

Sample code to allow you to quickly start with your development.

```php
use Gac\Routing\Exceptions\CallbackNotFound;
use Gac\Routing\Exceptions\RouteNotFoundException;
use Gac\Routing\Request;
use Gac\Routing\Routes;

include_once "vendor/autoload.php"; # IF YOU'RE USING composer

$routes = new Routes();
try {
    $routes->add('/', function (Request $request) {
        $request
            ->status(200, "OK")
            ->send(["message" => "Welcome"]);
    });
    
    $routes->route('/', function (Request $request) {
        $request
            ->status(201, "OK")
            ->send(["message" => "Welcome"]);
    }, [Routes::POST])->save();

    $routes->route();
} catch (RouteNotFoundException $ex) {
    $routes->request->status(404, "Route not found")->send(["error" => ["message" => $ex->getMessage()]]);
} catch (CallbackNotFound $ex) {
    $routes->request->status(404, "Callback method not found")->send(["error" => ["message" => $ex->getMessage()]]);
} catch (Exception $ex) {
    $code = $ex->getCode() ?? 500;
    $routes->request->status($code)->send(["error" => ["message" => $ex->getMessage()]]);
}
```

## Examples

### Dynamic routes example

```php
$routes->add('/test/{int:userID}-{username}/{float:amount}/{bool:valid}', function (
    Request $request,
    int $userID,
    string $username,
    float $amount,
    bool $valid
) {
    echo 'Dynamic route content here';
});
```

### Chained routes

Using chained method to wrap multiple routes with a same middleware or a route prefix When using chained method either
use `save()` or `add()` as the last method to indicate the end of a chain;

**NOTE**

`save()`  method can still be chained on to if needed, but `add()` method **CAN'T** be chained on, so it needs to be the
last one in the chain.

#### Chained routes with add at the end

```php
$routes
    ->prefix('/user') // all the routes added will have the /user prefix
    ->middleware([ 'verify_token' ]) // all the routes added will have the verify_token middleware applied
    ->route('/', [ HomeController::class, 'getUsers' ], Routes::GET)
    ->route('/', [ HomeController::class, 'addUser' ], Routes::POST)
    ->route('/', [ HomeController::class, 'updateUser' ], Routes::PATCH)
    ->route('/', [ HomeController::class, 'replaceUser' ], Routes::PUT)
    ->add('/test', [ HomeController::class, 'deleteUser' ], Routes::DELETE);
```

#### Chained routes with save at the end

```php
$routes
    ->prefix("/test")
    ->middleware(['decode_token'])
    ->route("/t0", function(Request $request){})
    ->get("/t1", function (){})
    ->post("/t2", function (){})
    ->put("/t3", function (){})
    ->patch("/t4", function (){})
    ->delete("/t5", function (){})
    ->save();
```

#### Chained routes with multiple chains in one call

```php 
$routes
    ->prefix("/test")
    ->middleware([ 'decode_token' ])
    ->get("/t1", function () { }) // route would be: /test/t1
    ->get("/t2", function () { }) // route would be: /test/t2
    ->get("/t3", function () { }) // route would be: /test/t3
    ->save(false) // by passing the false argument here, we keep all the previous shared data from the chain (previous prefix(es) and middlewares)
    ->prefix("/test2")
    ->middleware([ "verify_token" ])
    ->get("/t4", function () { }) // route would be: /test/test2/t4
    ->get("/t5", function () { }) // route would be: /test/test2/t5
    ->get("/t6", function () { }) // route would be: /test/test2/t6
    ->save() // by not passing the false argument here, we are removing all shared data from the previous chains (previous prefix(es) and middlewares)
    ->prefix("/test3")
    ->middleware([ "verify_token" ])
    ->get("/t7", function () { }) // route would be: /test3/t7
    ->get("/t8", function () { }) // route would be: /test3/t8
    ->get("/t9", function () { }) // route would be: /test3/t9
    ->add(); //using save or add at the end makes the chaining stop and allows for other independent routes to be added
```

### Passing arguments to middleware methods

When working with middlewares you can also pass them arguments if you need to

```php
$routes
    ->middleware([
        'test_middleware',
        'has_roles' => 'admin,user',
        [ Middleware::class, 'test_method' ],
        [ Middleware::class, 'has_role', 'Admin', 'Moderator', [ 'User', 'Bot' ] ],
    ])
    ->add('/test', function (Request $request) {
        $request->send([ 'msg' => 'testing' ]);
    });
```

Every middleware function can also accept an argument of type `Gac\Routing\Request` at any position as long as it has
the proper type specified.

For more examples look in the [sample folder](/sample) `index.php` file

## Documentation

Source code documentation can be found at [PHP Routing documentation](https://gigili.github.io/PHP-routing/) page

## Features

* [x] Static routes
* [x] Dynamic routes
* [x] Middlewares
* [x] Prefixing routes

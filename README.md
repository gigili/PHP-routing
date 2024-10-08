# Routing library for PHP

This library allows you to create static or dynamic routes. This library was inspired
by [PHP Slim framework](https://www.slimframework.com/)

[![PHP Tests](https://github.com/gigili/PHP-routing/actions/workflows/php-pest-tests.yml/badge.svg)](https://github.com/gigili/PHP-routing/actions/workflows/php-pest-tests.yml)
[![License](https://poser.pugx.org/gac/routing/license)](https://packagist.org/packages/gac/routing)
[![Total Downloads](https://poser.pugx.org/gac/routing/downloads)](https://packagist.org/packages/gac/routing)

## Install via composer

```shell
composer require gac/routing
```

## Manual install

Download the latest release from the [Releases page](https://github.com/gigili/PHP-routing/releases).

Don't forget to add these `include_once` statements to your php files:

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

If you've named your main file differently, replace `index.php` in the `.htaccess` file with whatever your main
application file is.

## Quick start

Sample code to allow you to quickly start with your development.

```php
use Gac\Routing\Exceptions\CallbackNotFound;
use Gac\Routing\Exceptions\RouteNotFoundException;
use Gac\Routing\Request;
use Gac\Routing\Response;
use Gac\Routing\Routes;

include_once "vendor/autoload.php"; # IF YOU'RE USING composer

$routes = new Routes();
try {
    $routes->add('/', function (Request $request) {
        // Old way of doing it, still supported until v4
        $request
            ->status(200, "OK")
            ->send(["message" => "Welcome"]);

        // New way of doing it
        Response::
        withHeader("Content-Type", "application/json")::
        withStatus(200, 'OK')::
        withBody([ "message" => "Welcome" ])::
        send();
    });
    
    $routes->route('/', function (Request $request) {
        // Old way of doing it, still supported until v4
        $request
            ->status(200, "OK")
            ->send(["message" => "Welcome"]);

        // New way of doing it
        Response::
        withHeader("Content-Type", "application/json")::
        withStatus(200, 'OK')::
        withBody([ "message" => "Welcome" ])::
        send();
    }, [Routes::POST])->save();

    $routes->route();
} catch (RouteNotFoundException $ex) {
    // Old way of doing it, still supported until v4
    $routes->request->status(404, "Route not found")->send(["error" => ["message" => $ex->getMessage()]]);
    
    // New way of doing it
    Response::withStatus(404, 'Route not found')::send(["error" => [ "message" => $ex->getMessage() ]]);
} catch (CallbackNotFound $ex) {
    // Old way of doing it, still supported until v4
    $routes->request->status(404, "Callback not found")->send(["error" => ["message" => $ex->getMessage()]]);
    
    // New way of doing it
    Response::withStatus(404, 'Callback not found')::send(["error" => [ "message" => $ex->getMessage() ]]);
} catch (Exception $ex) {
    $code = $ex->getCode() ?? 500;
    
    // Old way of doing it, still supported until v4
    $routes->request->status($code)->send(["error" => ["message" => $ex->getMessage()]]);
    
    // New way of doing it
    Response::withStatus($code)::send(["error" => [ "message" => $ex->getMessage() ]]);
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

When using chained methods either use `->save()` or `->add()` as the last method to indicate the end of a chain

**NOTE**

* `->save(true|false)` method can still be chained onto if needed
  * Passing `false` (the default value is `true`) to the `->save()` method will preserve all the previous prefixes and middlewares in that chain
* `->add()` **CAN NOT** be chained onto and should be the last call in chain

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
use Gac\Routing\Response;

$routes
    ->middleware([
        'test_middleware',
        'has_roles' => 'admin,user',
        [ Middleware::class, 'test_method' ],
        [ Middleware::class, 'has_role', 'Admin', 'Moderator', [ 'User', 'Bot' ] ],
    ])
    ->add('/test', function (Request $request) {
        // Old way of doing it, still supported until v4
        $request->send([ 'msg' => 'testing' ]);
        
        //New way of doing it
        Response::send([ "msg" => "testing" ]);
    });
```

Every middleware function can also accept an argument of type `Gac\Routing\Request` at any position as long as it has
the proper type specified.

### Optional parameters

```php
$routes->add(
    '/demo/{id?}',
    function($id = 'defaultValue'){
    	echo "ID: . $id";
    },
    Routes::GET
);
```

When calling this endpoint with `/demo` it will output `ID: defaultValue` and with `/demo/123` it will output `ID: 123`

### Dependency injection on route classes

When using classes to handle your route callback, and those classes have some dependencies that need to be injected
through a constructor, you can specify them as an array of arguments to be injected or
let the library try to auto-inject classes.

```php
$routes->add(
    '/demo',
    [ 
        HomeController::class, 
        'dependency_injection_test', 
        [ new InjectedClass() ] 
    ],
    Routes::GET
);
```

You can also use named arguments or mix and match them

```php
$routes->add(
    '/demo',
    [ 
        HomeController::class, 
        'dependency_injection_test', 
        [ "injected_var" => new InjectedClass(), new Middleware ] 
    ],
    Routes::GET
);
```

Letting the library auto-inject classes into the constructor

```php
$routes->add(
    '/demo',
    [ InjectController::class ],
    Routes::GET
);
```

**NOTE**

The library will always try to auto-inject classes (***will skip ones with null as default value***) if non are
provided, and you're using a class for callbacks.

### Use `__invoke` instead for single method classes

```php
$routes->add(
    '/invoke',
    [ HomeController::class ],
    Routes::GET
);
```

You can also use `__invoke` with dependency injection as well:

```php
$routes->add(
    '/invoke',
    [ 
        HomeController::class, 
        [ new InjectedClass() ] 
    ],
    Routes::GET
);
```

For more examples look in the [sample folder](/sample) `index.php` file

## Documentation

Source code documentation can be found at [PHP Routing documentation](https://gigili.github.io/PHP-routing/) page

## Features

* [x] Static routes
* [x] Dynamic routes
* [x] Dynamic routes with optional parameters
* [x] Middlewares
  * [x] Pass arguments to middlewares
* [x] Route prefixes
* [x] Method chaining
* [x] Dependency injection on classes
    * [x] Manual injection
    * [x] Auto-injection
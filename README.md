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

## After install

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

## Features

* [x] Static routes
* [x] Dynamic routes
* [x] Middlewares
* [x] Prefixing routes 

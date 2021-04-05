# Routing library for PHP

This library allows you to create static or dynamic routes. This library was inspired by [PHP Slim framework](https://www.slimframework.com/)

[![License](https://poser.pugx.org/gac/routing/license)](//packagist.org/packages/gac/routing) [![Total Downloads](https://poser.pugx.org/gac/routing/downloads)](//packagist.org/packages/gac/routing)

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

If you've named your main file differently, replace `index.php` in the `.htaccess` file with that ever your main application file is.

# Features

* [x] Static routes
* [x] Dynamic routes
* [x] Middleware
* [x] Prefixing routes 

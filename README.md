# Custom routing class for PHP
This class allows you to create static or dynamic routes. This class was inspired by [PHP Slim framework](https://www.slimframework.com/)

[![License](https://poser.pugx.org/gac/routing/license)](//packagist.org/packages/gac/routing) [![Total Downloads](https://poser.pugx.org/gac/routing/downloads)](//packagist.org/packages/gac/routing)

# Install via composer

`
composer require gac/routing
`

# Manual install 
Download the `Routes.php` file and include it.

# Example

```php
use Gac\Routing\Exceptions\RouteNotFoundException;
use Gac\Routing\Routes;

function verify_token(?string $token = "") {
    echo "Token: $token<br/>";
}

# include_once "../Routes.php"; IF NOT USING composer

try {
    $routes = new Routes();

    $routes->add('/', function () {
        echo "Welcome";
    })->middleware([
        ["verify_token", "test"]
    ]);

    $routes->add('/test', function () {
        echo "Welcome to test route";
    });

    $routes->add('/test_middleware', function () {
        echo "This will call middleware function without passing the parameter";
    })->middleware(["verify_token"]);

    $routes->route();
} catch (RouteNotFoundException $ex) {
    header("HTTP/1.1 404");
    echo "Route not found";
} catch (Exception $ex) {
    die("API Error: {$ex->getMessage()}");
}
```


To use this class properly you will need to create a `.htaccess` file at the root of the project.

Example of the `.htaccess` file would look like this:

```
RewriteEngine On

RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

RewriteRule ^(.+)$ index.php?myUri=$1 [QSA,L]
```

Do **NOT** change the `?myUri=$1` part in the `.htaccess` file as that will prevent the class from working.


## Note ##
When using middleware make sure the middleware function has benn declared before the Routes class import.  

# Features

* [x] Static routes
* [x] Dynamic routes
* [x] Middleware 
* [] Prefixing routes 

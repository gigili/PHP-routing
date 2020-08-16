# Custom routing class for PHP
This class allows you to create static or dynamic routes. This class was inspired by [PHP Slim framework](https://www.slimframework.com/)

# Example

```php
include_once("Routes.php");
try{
	$routes = new Routes();
	$input = json_decode(file_get_contents("php://input"));

	$routes->add("/", function(){ 
		echo "Welcome";
	});

	$routes->add("/blog/:id", function($request){ 
        echo "Post id: {$request['id']}"; 
    }, $input, ["POST", "PATCH"])->middleware(["verify_token"]); // Only allow POST or PATCH requests to this route
    
    

    $routes->route();
}catch( Exception $ex){
  echo "Error: {$ex->getMessage()}";
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

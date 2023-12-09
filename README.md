# PHP Framework

This is a very simple PHP framework to make implementig PHP websites easier and faster.

## Functionality

TODO: write
## Todo

- [ ] Write better documentation
- [ ] Write tests
- [ ] Redo how GET and POST parameters are handled
- [ ] Router is inconsistent with trailing slash (css and js includes break) - it should redirect to always have trailing slash
- [ ] Problem if using COMPONENT_ID when replacing some component with a chunk, ID could duplicate

## Publishing

https://medium.com/@mazraara/create-a-composer-package-and-publish-3683596dec45

## Example of use


`composer require anzeblabla/simplite`

Folder structure:
```
app/
    pages/
        index.php
        users/
            [id]/
                index.php // user profile
            index.php // list of users
    components/ # reusable components (\AnzeBlaBla\Simplite\Renderer::component($componentName))
        header.php
        footer.php
    
index.php
```


index.php:
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

use \AnzeBlaBla\Simplite\Application;

$app = new Application([
    'root_directory' => __DIR__ . '/app',
    'db' => [
        'host' => $_ENV['MYSQL_HOST'],
        'dbname' => $_ENV['MYSQL_DATABASE'],
        'username' => $_ENV['MYSQL_USER'],
        'password' => $_ENV['MYSQL_PASSWORD'],
    ]
]);

$app->addContext([
    'logged_in' => isset($_SESSION['user_id']),
    'user_id' => $_SESSION['user_id'] ?? null
]);
if (isset($_SESSION['user_id'])) {
    $app->addContext([
        'user' => [] // TODO: get user from database
    ]);
}

$app->render();
```

# PHP Framework

This is a very simple PHP framework to make implementig PHP websites easier and faster.

## Functionality

TODO: write

## Todo

- [ ] Write better documentation
- [ ] Write tests
- [ ] Redo how GET and POST parameters are handled
- [ ] Router is inconsistent with trailing slash (css and js includes break) - it should redirect to always have trailing slash
- [x] Problem if using COMPONENT_ID when replacing some component with a chunk, ID could duplicate
- [ ] Problem where page is rendered before layout. Could potentially result in vulns or errors where user is used before it's checked if user is logged in at all.
- [ ] Autoloader

## Publishing

https://medium.com/@mazraara/create-a-composer-package-and-publish-3683596dec45


https://packagist.org/


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
    models/ # Database models, inheriting from \AnzeBlaBla\Simplite\ModelBase
        User.php
    
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
    ],
    'component_comments' => true, // wrap components in HTML comments
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


Example model:
```php
<?php

use AnzeBlaBla\Simplite\ModelBase;
use PHPMailer\PHPMailer\PHPMailer;

class User extends ModelBase
{
    /**
     * @SimpliteProp int
     * @SimplitePK
     * @SimpliteAutoIncrement
     */
    public int $id;
    /**
     * @SimpliteProp
     */
    public string $username;
    /**
     * @SimpliteProp
     */
    public string $password;
    /**
     * @SimpliteProp
     */
    public string $email;

    /**
     * Email confirmation code
     * @SimpliteProp
     */
    public string $confirm_code;

    /**
     * If user has confirmed their email address
     * @SimpliteProp
     * @SimpliteDefault 0
     */
    public int $confirmed_email;

    /**
     * @SimpliteProp timestamp
     * @SimpliteDefault CURRENT_TIMESTAMP
     */
    public string $created_at;
    /**
     * @SimpliteProp timestamp
     * @SimpliteDefault CURRENT_TIMESTAMP
     * @SimpliteOnUpdate CURRENT_TIMESTAMP
     */
    public string $updated_at;


    public function get_read_books($limit = null)
    {
        $limit_str = $limit ? "LIMIT $limit" : "";
        $books = ReadBook::find("user_id = ? ORDER BY created_at DESC $limit_str", [$this->id]);
        $ids = array_map(function ($book) {
            return $book->book_id;
        }, $books);
        return Book::constructMany($ids);
    }

    public function update_read_book($book_id, $read = true)
    {
        if ($read) {
            if (count(ReadBook::find("user_id = ? AND book_id = ?", [$this->id, $book_id])) > 0) {
                return;
            }
            $rb = new ReadBook([
                'user_id' => $this->id,
                'book_id' => $book_id,
            ]);
            $rb->create();
        } else {
            $rb = ReadBook::find("user_id = ? AND book_id = ?", [$this->id, $book_id]);
            if ($rb) {
                $rb[0]->delete();
            }
        }
    }

    public function has_read_book($book_id)
    {
        return count(ReadBook::find("user_id = ? AND book_id = ?", [$this->id, $book_id])) > 0;
    }
}

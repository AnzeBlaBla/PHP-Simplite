<?php

/**
 * Handles new command
 * @param Arguments $arguments
 * @return void
 */
function handle_new_command($arguments)
{
    if (!$arguments->hasMore()) {
        echo get_help_text('new');
        exit(1);
    }

    $type = $arguments->consume();
    $name = $arguments->consume();

    switch ($type) {
        case 'component':
            create_file($name, 'component', 'components');
            break;
        case 'page':
            create_file($name, 'page', 'pages');
            break;
        case 'api':
            create_file($name, 'api', 'api');
            break;
        case 'layout':
            create_file($name, 'layout', 'layouts');
            break;
        default:
            echo "Unknown type: $type\n";
            echo get_help_text('new');
            exit(1);
    }
}


/**
 * Creates a new file from the TEMPLATES
 * @param string $name
 * @param string $template
 * @param string $folder
 * @return void
 */
function create_file($name, $template, $folder)
{
    if (file_exists(BASE_PATH . "/$folder/$name.php")) {
        echo "File already exists: $name\n";
        exit(1);
    }

    $template = TEMPLATES[$template];
    $template = str_replace('{TEMPLATE_NAME}', $name, $template);

    file_put_contents(BASE_PATH . "/$folder/$name.php", $template);
    echo "Created file: $name\n";
}
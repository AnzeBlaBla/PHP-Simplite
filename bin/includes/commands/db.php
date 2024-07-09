<?php

use AnzeBlaBla\Simplite\ModelBase;

function get_db_creation_sql($models_folder)
{
    $models = array_diff(scandir($models_folder), ['.', '..']);
    // register autoloader (to prevent class not found errors)
    spl_autoload_register(function ($class) use ($models_folder) {
        $path = "$models_folder/$class.php";
        if (is_file($path) && is_readable($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            require_once $path;
        }
    });
    // include all models
    foreach ($models as $model) {
        $path = "$models_folder/$model";
        // if readable PHP file
        if (is_file($path) && is_readable($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            // if class not already loaded
            if (!class_exists(pathinfo($path, PATHINFO_FILENAME))) {
                require_once $path;
            }
        }
    }

    // Find all subclasses of ModelBase
    $children = array_filter(get_declared_classes(), fn ($class) => is_subclass_of($class, ModelBase::class));

    echo "Found " . count($children) . " models\n";

    $statements = [];
    $alter_statements = [];

    foreach ($children as $child) {
        /**
         * @var ModelBase $child
         */
        $out_statements = $child::_getCreateTableStatement();

        $alter_statements = array_merge($alter_statements, $out_statements['alter_statements']);
        $statements[] = $out_statements['create'];
    }

    // Add alter table statements for foreign keys
    foreach ($alter_statements as $alter_statement) {
        $statements[] = $alter_statement;
    }

    $statements = implode(";\n", $statements) . ";";

    return $statements;
}

/**
 * Handles db command
 * @param Arguments $arguments
 * @return void
 */
function handle_db_command($arguments)
{
    if (!$arguments->hasMore()) {
        echo get_help_text('db');
        exit(1);
    }

    /**
     * Actions:
     * - create: Handles DB creation (printing SQL, creating SQL file or executing SQL)
     * - migrate: // TODO: implement migration
     */
    $action = $arguments->consume();

    $models_folder = $arguments->consume();

    switch ($action) {
        case 'create':
            echo "Creating DB CREATE TABLE statements. Using models from: $models_folder\n";

            $statements = get_db_creation_sql($models_folder);


            if ($arguments->hasOption('output')) {
                $output = $arguments->getOption('output');
                file_put_contents($output, $statements);
                echo "Statements written to: $output\n";
            } else if ($arguments->hasOption('connection')) {
                $connection = $arguments->getOption('connection');
                echo "Executing statements on connection: $connection\n";
                $db = new PDO($connection);
                $db->exec($statements);
                echo "Statements executed\n";
            } else {
                echo "----- Statements: -----\n";
                echo $statements;
                echo "\n----- End of statements -----\n";
            }
            break;
        default:
            echo "Unknown action: $action\n";
            echo get_help_text('db');
            exit(1);
    }
}

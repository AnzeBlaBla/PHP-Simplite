<?php

use AnzeBlaBla\Simplite\ModelBase;

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

    $action = $arguments->consume(); // create

    $models_folder = $arguments->consume();

    switch ($action) {
        case 'create':
            echo "Creating DB CREATE TABLE statements. Using models from: $models_folder\n";
            $models = array_diff(scandir($models_folder), ['.', '..']);
            // include all models
            foreach ($models as $model) {
                include "$models_folder/$model";
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

            echo "----- Statements: -----\n";
            echo $statements;
            echo "\n----- End of statements -----\n";

            if ($arguments->hasOption('output')) {
                $output = $arguments->getOption('output');
                file_put_contents($output, $statements);
                echo "Statements written to: $output\n";
            }
            break;
        default:
            echo "Unknown action: $action\n";
            echo get_help_text('db');
            exit(1);
    }
}

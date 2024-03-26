<?php

namespace AnzeBlaBla\Simplite;

use AnzeBlaBla\Simplite\Application;

define('TYPE_DOC_PROP', 'SimpliteProp');
define('PK_DOC_PROP', 'SimplitePK');
define('DEFAULT_DOC_PROP', 'SimpliteDefault');
define('ON_UPDATE_DOC_PROP', 'SimpliteOnUpdate');
define('PK_TYPE', 'INT');

function phpTypeToMySQLType($type)
{
    switch ($type) {
        case 'int':
            return 'INT';
        case 'string':
            return 'TEXT';
        case 'float':
            return 'FLOAT';
        case 'bool':
            return 'BOOLEAN';
        default:
            // TODO: warning
            return $type;
    }
}

function simpliteTypeToMySQLType($type)
{
    switch ($type) {
        case 'int':
            return 'INT';
        case 'string':
            return 'TEXT';
        case 'float':
            return 'FLOAT';
        case 'bool':
            return 'BOOLEAN';
        case 'date':
            return 'DATE';
        case 'datetime':
            return 'DATETIME';
        case 'time':
            return 'TIME';
        case 'timestamp':
            return 'TIMESTAMP';
        default:
            // TODO: warning
            return $type;
    }
}

/**
 * Parses a docblock for @ annotations (can either contain options or not)
 * Example 1:
 * @SimpliteType int
 * @SimplitePK
 * 
 * Example 2:
 * @SimpliteType
 */
function parseDocBlock($doc)
{
    $matches = [];
    preg_match_all('/@([a-zA-Z]+)\s*([a-zA-Z_-]*)/', $doc, $matches);
    $out = [];
    foreach ($matches[1] as $i => $key) {
        $value = $matches[2][$i];
        if ($value === '') {
            $value = true;
        }
        $out[$key] = $value;
    }
    return $out;
}


class ModelBase
{
    protected $app;

    /**
     * The table name
     */
    static $_TABLE = null;
    static function getTable()
    {
        // If table is not null, return it
        if (static::$_TABLE !== null) {
            return static::$_TABLE;
        }
        // If table is null, return the class name in snake_case, plural // TODO: make this more robust
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', static::class)) . 's';
    }

    /**
     * The columns in the table
     * @var string[]
     */
    static $_COLUMNS = null;
    static function getColumns()
    {
        // If columns is not null, return it
        if (static::$_COLUMNS !== null) {
            return static::$_COLUMNS;
        }
        // If columns is null, loop all class properties and return the ones that contain the @SimpliteType annotation
        $columns = [];
        $reflection = new \ReflectionClass(static::class);
        foreach ($reflection->getProperties() as $property) {
            $doc = $property->getDocComment();
            if ($doc) {
                $parsed = parseDocBlock($doc);
                if (isset($parsed[TYPE_DOC_PROP])) {
                    $columns[] = $property->getName();
                }
            }
        }

        return $columns;
    }

    /**
     * The columns that should not be returned in toArray()
     * @var string[]
     */
    static $_SENSITIVE = [];

    /**
     * Auto increment column
     * @var string
     */
    static $_AUTO_INCREMENT = null;
    public static function getAutoIncrementColumn()
    {
        if (static::$_AUTO_INCREMENT === null) {
            return 'id';
        }

        // Try to find property with PK_DOC_PROP
        $reflection = new \ReflectionClass(static::class);
        foreach ($reflection->getProperties() as $property) {
            $doc = $property->getDocComment();
            if ($doc) {
                $parsed = parseDocBlock($doc);
                if (isset($parsed[PK_DOC_PROP])) {
                    // Set the auto increment column to the property name
                    static::$_AUTO_INCREMENT = $property->getName();
                    return static::$_AUTO_INCREMENT;
                }
            }
        }

        return static::$_AUTO_INCREMENT;
    }




    /**
     * Get all objects
     */
    public static function all()
    {
        $table = static::getTable();
        $app = Application::getInstance();
        $data = $app->db->fetchAll("SELECT * FROM $table");

        return static::constructMany($data);
    }

    /**
     * Get object by id
     * @param int $id
     * @return static|null
     */
    public static function get($id)
    {
        // must be numeric
        if (!is_numeric($id)) {
            return null;
        }

        $table = static::getTable();
        $app = Application::getInstance();

        $id_column = static::getAutoIncrementColumn();
        $data = $app->db->fetchOne("SELECT * FROM $table WHERE $id_column = ?", [$id]);

        if (!$data) {
            return null;
        }
        return new static($data);
    }

    /**
     * Get objects by query
     */
    public static function find($query, $params = [])
    {
        $app = Application::getInstance();

        $table = static::getTable();
        $data = $app->db->fetchAll("SELECT * FROM $table WHERE $query", $params);

        return static::constructMany($data);
    }

    /**
     * Construct array of objects from array of data
     * @param array $data
     * @return static[]
     */
    public static function constructMany($data)
    {
        $objects = [];
        foreach ($data as $row) {
            $objects[] = new static($row);
        }
        return $objects;
    }

    /**
     * Get the CREATE TABLE statement for this model. Not meant to be called directly (use db create command instead)
     * @return string
     * @throws \Exception
     */
    public static function _getCreateTableStatement()
    {
        $table = static::getTable();
        $columns = static::getColumns();
        $id_column = static::getAutoIncrementColumn();

        $statements = [];
        foreach ($columns as $column) {
            // Find property of the same name
            $type = 'TEXT';
            $extra = [];
            $rp = new \ReflectionProperty(static::class, $column);
            if ($rp) {
                // If property exists, get type from docblock (@SimpliteType)
                $doc = $rp->getDocComment();
                $parsed = parseDocBlock($doc);
                if ($doc && isset($parsed[TYPE_DOC_PROP])) {
                    if ($parsed[TYPE_DOC_PROP] === true) {
                        // If not specified directly, try to guess type from PHP type
                        $type = phpTypeToMySQLType($rp->getType()->getName());
                    } else {
                        $type = simpliteTypeToMySQLType($parsed[TYPE_DOC_PROP]);
                    }
                }

                // If property is not optional, add NOT NULL
                if (!$rp->hasType() || !$rp->getType()->allowsNull()) {
                    $extra[] = 'NOT NULL';
                }

                // If property has a default value, add DEFAULT
                if (isset($parsed[DEFAULT_DOC_PROP])) {
                    $extra[] = 'DEFAULT ' . $parsed[DEFAULT_DOC_PROP];
                }

                // Add ON UPDATE
                if (isset($parsed[ON_UPDATE_DOC_PROP])) {
                    $extra[] = 'ON UPDATE ' . $parsed[ON_UPDATE_DOC_PROP];
                }
            }

            if ($column === $id_column) {
                // if type is not PK_TYPE, throw an error
                if ($type !== PK_TYPE) {
                    throw new \Exception("Auto increment column must be of type " . PK_TYPE . ", got $type");
                }
                $extra[] = 'PRIMARY KEY AUTO_INCREMENT';
            }

            if (count($extra) > 0) {
                $extra = implode(' ', $extra);
            } else {
                $extra = '';
            }

            $statements[] = "$column $type $extra";

        }

        return "CREATE TABLE $table (" . implode(', ', $statements) . ")";
    }


    public int $id;

    public function __construct($data = [])
    {
        $this->app = Application::getInstance();

        if (is_array($data)) {
            $this->constructFromArray($data);
        } else {
            $this->constructFromId($data);
        }
    }

    private function constructFromArray($data)
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    private function constructFromId($id)
    {
        $table = static::getTable();
        $app = Application::getInstance();
        $id_column = static::getAutoIncrementColumn();
        $data = $app->db->fetchOne("SELECT * FROM $table WHERE $id_column = ?", [$id]);

        if (!$data) {
            throw new \Exception("Object with id $id does not exist");
        }

        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }


    /**
     * Creates a new object in the database from the values in this instance
     */
    public function create()
    {
        $table = static::getTable();
        $app = Application::getInstance();

        $columns = [];
        $values = [];
        $placeholders = [];

        foreach (static::getColumns() as $column) {
            if ($column === static::getAutoIncrementColumn()) {
                continue;
            }

            if (isset($this->$column)) {
                $columns[] = $column;
                $values[] = $this->$column;
                $placeholders[] = '?';
            }
        }

        $columns = implode(', ', $columns);
        $placeholders = implode(', ', $placeholders);

        $app->db->execute("INSERT INTO $table ($columns) VALUES ($placeholders)", $values);
        $this->{static::getAutoIncrementColumn()} = $app->db->lastInsertId();
        // Get the object from the database
        $this->constructFromId($this->{static::getAutoIncrementColumn()});

        return $this;
    }

    /**
     * Updates the object in the database from the values in this instance
     */
    public function update()
    {

        $table = static::getTable();
        $app = Application::getInstance();

        $columns = [];
        $values = [];

        foreach (static::getColumns() as $column) {
            if ($column === static::getAutoIncrementColumn()) {
                continue;
            }

            if (isset($this->$column)) {
                $columns[] = $column . ' = ?';
                $values[] = $this->$column;
            }
        }

        $values[] = $this->{static::getAutoIncrementColumn()};

        $columns = implode(', ', $columns);

        $id_column = static::getAutoIncrementColumn();
        $app->db->execute("UPDATE $table SET $columns WHERE $id_column = ?", $values);

        return $this;
    }

    /**
     * Deletes the object from the database
     */
    public function delete()
    {
        $table = static::getTable();
        $app = Application::getInstance();

        $id_column = static::getAutoIncrementColumn();
        $app->db->execute("DELETE FROM $table WHERE $id_column = ?", [$this->{$id_column}]);

        return $this;
    }

    /**
     * Returns a safe representation of this object (hiding sensitive data)
     */
    public function toArray()
    {
        $out = [];
        foreach (static::getColumns() as $key) {
            if (!in_array($key, static::$_SENSITIVE)) {
                $out[$key] = $this->$key;
            }
        }
        return $out;
    }
}

<?php

namespace AnzeBlaBla\Simplite;

use AnzeBlaBla\Simplite\Application;

define('TYPE_DOC_PROP', 'SimpliteProp');
define('PK_DOC_PROP', 'SimplitePK');
define('FK_DOC_PROP', 'SimpliteFK'); // TODO: implement FK on query level
define('FK_ON_DELETE_DOC_PROP', 'SimpliteFKOnDelete');
define('FK_ON_UPDATE_DOC_PROP', 'SimpliteFKOnUpdate');
define('AUTO_INCREMENT_DOC_PROP', 'SimpliteAutoIncrement');
define('DEFAULT_DOC_PROP', 'SimpliteDefault');
define('ON_UPDATE_DOC_PROP', 'SimpliteOnUpdate');

// TODO: implement upsert

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
    preg_match_all('/@([a-zA-Z]+)\s*([0-9a-zA-Z_\-\(\)]*)/', $doc, $matches);
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


class ModelBase implements \JsonSerializable
{
    protected $app;

    /**
     * The table name
     */
    // TODO: static:: will always reference the same value (of the parent class),
    // so we need to make an array and save things there to bypass this limitation,
    // like https://stackoverflow.com/questions/2751719/inherit-static-properties-in-subclass-without-redeclaration

    static $_TABLE = null;
    static function getTable()
    {
        // If table is not null, return it
        if (static::$_TABLE !== null) {
            return static::$_TABLE;
        }
        // If table is null, return the class name in snake_case, plural 
        $tablename_singular = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', static::class));

        // TODO: make this more robust
        $out_tablename = "";

        $last_char = substr($tablename_singular, -1);
        if ($last_char === 's' || $last_char === 'x' || $last_char === 'z' || $last_char === 'o' || $last_char === 'ch' || $last_char === 'sh') {
            $out_tablename = $tablename_singular . 'es';
        } else if ($last_char === 'y' && !in_array(substr($tablename_singular, -2, 1), ['a', 'e', 'i', 'o', 'u'])) {
            $out_tablename = substr($tablename_singular, 0, -1) . 'ies';
        } else {
            $out_tablename = $tablename_singular . 's';
        }

        static::$_TABLE = $out_tablename;
        return $out_tablename;
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

        static::$_COLUMNS = $columns;
        return $columns;
    }

    /**
     * The columns that should not be returned in toArray()
     * @var string[]
     */
    static $_SENSITIVE = [];

    /**
     * Primary key column
     * @var string
     */
    static $_PRIMARY_KEY_COLUMN = null;
    public static function getPrimaryKeyColumn()
    {

        // If set, return it
        if (static::$_PRIMARY_KEY_COLUMN !== null) {
            return static::$_PRIMARY_KEY_COLUMN;
        }

        // If not set, try to find property with PK_DOC_PROP
        $reflection = new \ReflectionClass(static::class);
        foreach ($reflection->getProperties() as $property) {
            $doc = $property->getDocComment();
            if ($doc) {
                $parsed = parseDocBlock($doc);
                if (isset($parsed[PK_DOC_PROP])) {
                    // Set the primary key column to the property name
                    static::$_PRIMARY_KEY_COLUMN = $property->getName();
                    return static::$_PRIMARY_KEY_COLUMN;
                }
            }
        }

        // If not set and not found, default to 'id'
        return 'id';
    }

    /**
     * Auto increment column
     * @var string
     */
    static $_AUTO_INCREMENT_COLUMN = null;
    public static function getAutoIncrementColumn()
    {

        if (static::$_AUTO_INCREMENT_COLUMN !== null) {
            return static::$_AUTO_INCREMENT_COLUMN;
        }

        // Try to find property with AUTO_INCREMENT_DOC_PROP
        $reflection = new \ReflectionClass(static::class);
        foreach ($reflection->getProperties() as $property) {
            $doc = $property->getDocComment();
            if ($doc) {
                $parsed = parseDocBlock($doc);
                if (isset($parsed[AUTO_INCREMENT_DOC_PROP])) {
                    // Set the auto increment column to the property name
                    static::$_AUTO_INCREMENT_COLUMN = $property->getName();
                    return static::$_AUTO_INCREMENT_COLUMN;
                }
            }
        }
    }


    /**
     * Get all objects
     * @return static[]
     */
    public static function all()
    {
        $table = static::getTable();
        $app = Application::getInstance();
        $data = $app->db->fetchAll("SELECT * FROM $table");

        return static::constructMany($data);
    }

    /**
     * Get object by primary key
     * @param int $pk
     * @return static|null
     */
    public static function get($pk)
    {
        $table = static::getTable();
        $app = Application::getInstance();

        $pk_column = static::getPrimaryKeyColumn();
        $data = $app->db->fetchOne("SELECT * FROM $table WHERE $pk_column = ?", [$pk]);

        if (!$data) {
            return null;
        }
        return new static($data);
    }

    /**
     * Select objects by query
     * @param string $query
     * @param array $params
     * @param string $extra
     * @return static[]
     */
    public static function find($query, $params = [], $extra = '')
    {
        $app = Application::getInstance();

        $table = static::getTable();
        $data = $app->db->fetchAll("SELECT * FROM $table WHERE $query $extra", $params);

        return static::constructMany($data);
    }

    /**
     * Select one object by query
     * @param string $query
     * @param array $params
     * @param string $extra
     * @return static|null
     */
    public static function findOne($query, $params = [], $extra = '')
    {
        $app = Application::getInstance();

        $table = static::getTable();
        $data = $app->db->fetchOne("SELECT * FROM $table WHERE $query $extra", $params);

        if (!$data) {
            return null;
        }

        return new static($data);
    }

    /**
     * Check if exists
     * @param string $query
     * @param array $params
     * @param string $extra
     * @return bool
     */
    public static function exists($query, $params = [], $extra = '')
    {
        return count(static::find($query, $params, $extra)) > 0;
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
     * Creates many objects from an array of data
     * @param array $data
     * @return static[]
     */
    public static function createMany($data)
    {
        $objects = static::constructMany($data);
        foreach ($objects as $object) {
            $object->create();
        }
        return $objects;
    }

    /**
     * Get the CREATE TABLE statement for this model. Not meant to be called directly (use db create command instead).
     * Returns an object with the following structure:
     * [
     *    'create' => 'CREATE TABLE ...',
     *   'alter_statements' => ['FOREIGN KEY ...', ...]
     * ]
     * @return array
     * @throws \Exception
     */
    public static function _getCreateTableStatement()
    {
        $table = static::getTable();
        $columns = static::getColumns();
        $pk_column = static::getPrimaryKeyColumn();
        $ai_column = static::getAutoIncrementColumn();

        $statements = [];
        $alter_statements = [];
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

                    // If property is foreign key, add FOREIGN KEY
                    if (isset($parsed[FK_DOC_PROP])) {
                        $fk_value = $parsed[FK_DOC_PROP];
                        // Value is in the syntax ClassName{.column} where column is optional, if not specified, use primary key
                        $fk_parts = explode('.', $fk_value);
                        $fk_table = $fk_parts[0];
                        $fk_column = $fk_parts[1] ?? null;
                        // Get static instance of the foreign table using reflection
                        try {
                            $fk_class = new \ReflectionClass($fk_table);
                        } catch (\ReflectionException $e) {
                            // Throw exception with current class name
                            throw new \Exception("Foreign key class $fk_table does not exist or is not a subclass of " . ModelBase::class . " in " . static::class);
                        }
                        if (!$fk_class->isSubclassOf(ModelBase::class)) {
                            throw new \Exception("Foreign key class $fk_table does not exist or is not a subclass of " . ModelBase::class);
                        }

                        $fk_table = $fk_class->getMethod('getTable')->invoke(null);
                        $fk_column = $fk_column ?? $fk_class->getMethod('getPrimaryKeyColumn')->invoke(null);

                        // On delete and on update
                        $deleteUpdate = [];

                        if (isset($parsed[FK_ON_DELETE_DOC_PROP])) {
                            $deleteUpdate[] = 'ON DELETE ' . $parsed[FK_ON_DELETE_DOC_PROP];
                        }

                        if (isset($parsed[FK_ON_UPDATE_DOC_PROP])) {
                            $deleteUpdate[] = 'ON UPDATE ' . $parsed[FK_ON_UPDATE_DOC_PROP];
                        }

                        if (count($deleteUpdate) > 0) {
                            $deleteUpdate = ' ' . implode(' ', $deleteUpdate);
                        } else {
                            $deleteUpdate = '';
                        }

                        $alter_statements[] = "ALTER TABLE $table ADD FOREIGN KEY ($column) REFERENCES $fk_table($fk_column)$deleteUpdate";
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

            if ($column === $pk_column) {
                $extra[] = 'PRIMARY KEY';
            }

            if ($column === $ai_column) {
                // TODO: check type
                $extra[] = 'AUTO_INCREMENT';
            }


            if (count($extra) > 0) {
                $extra = implode(' ', $extra);
            } else {
                $extra = '';
            }

            $statements[] = "$column $type $extra";
        }


        return [
            'create' => "CREATE TABLE $table (" . implode(', ', $statements) . ")",
            'alter_statements' => $alter_statements
        ];
    }


    public function __construct($data = [])
    {
        $this->app = Application::getInstance();

        if (is_array($data)) {
            $this->constructFromArray($data);
        } else {
            $this->constructFromPK($data);
        }
    }

    private function constructFromArray($data)
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    private function constructFromPK($pk)
    {
        $table = static::getTable();
        $app = Application::getInstance();
        $pk_column = static::getPrimaryKeyColumn();
        $data = $app->db->fetchOne("SELECT * FROM $table WHERE $pk_column = ?", [$pk]);

        if (!$data) {
            throw new \Exception("Object with primary key $pk does not exist");
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
            if (isset($this->$column)) {
                $columns[] = $column;
                $values[] = $this->$column;
                $placeholders[] = '?';
            }
        }

        $columns = implode(', ', $columns);
        $placeholders = implode(', ', $placeholders);

        $app->db->execute("INSERT INTO $table ($columns) VALUES ($placeholders)", $values);

        // If our auto increment column was set (and inserted), lastInsertId will NOT return it (it will return 0)
        // We need to get it from the object
        $last_id = $app->db->lastInsertId();

        $ai_column = static::getAutoIncrementColumn();
        if ($ai_column && isset($this->{$ai_column})) {
            $last_id = $this->{$ai_column};
        }

        if ($ai_column)
            $this->{$ai_column} = $last_id;
        
        // Get the object from the database
        try {
            $this->constructFromPK($this->{static::getPrimaryKeyColumn()});
        } catch (\Exception $e) {
            throw new \Exception("Could not retrieve object from database after creation (primary key: " . $this->{static::getPrimaryKeyColumn()} . ", last insert id: $last_id)");
        }

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
            if ($column === static::getPrimaryKeyColumn()) {
                continue;
            }

            if (isset($this->$column)) {
                $columns[] = $column . ' = ?';
                $values[] = $this->$column;
            }
        }

        $values[] = $this->{static::getPrimaryKeyColumn()};

        $columns = implode(', ', $columns);

        $pk_column = static::getPrimaryKeyColumn();
        $app->db->execute("UPDATE $table SET $columns WHERE $pk_column = ?", $values);

        return $this;
    }

    /**
     * Upserts the object in the database from the values in this instance
     * @return static
     */
    public function upsert()
    {
        // Construct INSERT + ON DUPLICATE KEY UPDATE statement

        $table = static::getTable();
        $app = Application::getInstance();

        $columns = [];
        $values = [];
        $placeholders = [];

        foreach (static::getColumns() as $column) {
            if (isset($this->$column)) {
                $columns[] = $column;
                $values[] = $this->$column;
                $placeholders[] = '?';
            }
        }

        $columns = implode(', ', $columns);
        $placeholders = implode(', ', $placeholders);

        $on_duplicate = [];
        foreach (static::getColumns() as $column) {
            if ($column === static::getPrimaryKeyColumn()) {
                continue;
            }

            if (isset($this->$column)) {
                $on_duplicate[] = "$column = ?";
                $values[] = $this->$column;
            }
        }

        $on_duplicate = implode(', ', $on_duplicate);

        $app->db->execute("INSERT INTO $table ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $on_duplicate", $values);

        // If our auto increment column was set (and inserted), lastInsertId will NOT return it (it will return 0)
        // We need to get it from the object

        $ai_column = static::getAutoIncrementColumn();
        if ($ai_column && isset($this->{$ai_column})) {
            $last_id = $this->{$ai_column};
        } else {
            $last_id = $app->db->lastInsertId();
        }

        if ($ai_column)
            $this->{$ai_column} = $last_id;

        // Get the object from the database
        try {
            $this->constructFromPK($this->{static::getPrimaryKeyColumn()});
        } catch (\Exception $e) {
            throw new \Exception("Could not retrieve object from database after creation (primary key: " . $this->{static::getPrimaryKeyColumn()} . ", last insert id: $last_id)");
        }

        return $this;
    }

    /**
     * Deletes the object from the database
     */
    public function delete()
    {
        $table = static::getTable();
        $app = Application::getInstance();

        $pk_column = static::getPrimaryKeyColumn();
        $app->db->execute("DELETE FROM $table WHERE $pk_column = ?", [$this->{$pk_column}]);

        return $this;
    }

    /**
     * Returns a safe representation of this object (hiding sensitive data)
     */
    public function toArray(): array
    {
        $out = [];
        foreach (static::getColumns() as $key) {
            if (!in_array($key, static::$_SENSITIVE)) {
                $out[$key] = $this->$key;
            }
        }
        return $out;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}

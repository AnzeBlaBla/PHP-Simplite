<?php

namespace AnzeBlaBla\Simplite;

use AnzeBlaBla\Simplite\Application;

class ModelBase
{
    protected $app;

    /**
     * The table name
     */
    static $_TABLE = null;

    /**
     * The columns in the table
     * @var string[]
     */
    static $_COLUMNS = null;

    /**
     * The columns that should not be returned in toArray()
     * @var string[]
     */
    static $_SENSITIVE = [];

    /**
     * Auto increment columns
     * @var string[]
     */
    static $_AUTO_INCREMENT = ['id'];



    static function checkSchemaVars($caller)
    {
        if ($caller::$_TABLE === null) {
            throw new \Exception('Please define $_TABLE in your model for ' . $caller);
        }
        if ($caller::$_COLUMNS === null) {
            throw new \Exception('Please define $_COLUMNS in your model for ' . $caller);
        }
    }

    /**
     * Get all objects
     * @return object[]
     */
    public static function all(): array
    {
        $caller = get_called_class();
        self::checkSchemaVars($caller);
        $table = $caller::$_TABLE;
        $class = get_called_class(); // To create the child class
        $app = Application::getInstance();
        $data = $app->db->fetchAll("SELECT * FROM $table");

        return self::constructMany($data);
    }

    /**
     * Get object by id
     * @param int $id
     * @return object|null
     */
    public static function get($id): ?object
    {
        $caller = get_called_class();
        self::checkSchemaVars($caller);
        $table = $caller::$_TABLE;
        $class = get_called_class(); // To create the child class
        $app = Application::getInstance();
        $data = $app->db->fetchOne("SELECT * FROM $table WHERE id = ?", [$id]);

        if (!$data) {
            return null;
        }
        return new $class($data);
    }

    /**
     * Get objects by query
     */
    public static function find($query, $params = []): array
    {
        $caller = get_called_class();
        self::checkSchemaVars($caller);
        $table = $caller::$_TABLE;
        
        $app = Application::getInstance();
        $data = $app->db->fetchAll("SELECT * FROM $table WHERE $query", $params);

        return self::constructMany($data);
    }

    /**
     * Construct array of objects from array of data
     */
    public static function constructMany($data): array
    {
        $class = get_called_class(); // To create the child class

        $objects = [];
        foreach ($data as $row) {
            $objects[] = new $class($row);
        }
        return $objects;
    }


    public int $id;

    public function __construct($data = [])
    {
        $this->app = Application::getInstance();
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Creates a new object in the database from the values in this instance
     */
    public function create()
    {
        $caller = get_called_class();
        self::checkSchemaVars($caller);
        $caller = get_called_class();
        $table = $caller::$_TABLE;
        $app = Application::getInstance();

        $columns = [];
        $values = [];
        $placeholders = [];

        foreach ($caller::$_COLUMNS as $column) {
            if (in_array($column, $caller::$_AUTO_INCREMENT)) {
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
        $this->id = $app->db->lastInsertId();

        return $this;
    }

    /**
     * Updates the object in the database from the values in this instance
     */
    public function update()
    {
        $caller = get_called_class();
        self::checkSchemaVars($caller);
        $caller = get_called_class();
        $table = $caller::$_TABLE;
        $app = Application::getInstance();

        $columns = [];
        $values = [];

        foreach ($caller::$_COLUMNS as $column) {
            if (in_array($column, $caller::$_AUTO_INCREMENT)) {
                continue;
            }

            if (isset($this->$column)) {
                $columns[] = $column . ' = ?';
                $values[] = $this->$column;
            }
        }

        $values[] = $this->id;

        $columns = implode(', ', $columns);

        $app->db->execute("UPDATE $table SET $columns WHERE id = ?", $values);

        return $this;
    }

    /**
     * Returns a safe representation of this object (hiding sensitive data)
     */
    public function toArray()
    {
        $caller = get_called_class();
        self::checkSchemaVars($caller);

        $out = [];
        foreach ($caller::$_COLUMNS as $key) {
            if (!in_array($key, $caller::$_SENSITIVE)) {
                $out[$key] = $this->$key;
            }
        }
        return $out;
    }
}

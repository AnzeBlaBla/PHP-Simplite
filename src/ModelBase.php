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



    /**
     * Check if the schema variables are defined
     * @throws \Exception
     * @return void
     */
    static function checkSchemaVars()
    {
        if (static::$_TABLE === null) {
            throw new \Exception('Please define $_TABLE in your model for ' . static::class);
        }
        if (static::$_COLUMNS === null) {
            throw new \Exception('Please define $_COLUMNS in your model for ' . static::class);
        }
    }

    /**
     * Get all objects
     */
    public static function all()
    {
        static::checkSchemaVars();

        $table = static::$_TABLE;
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
        static::checkSchemaVars();
        $table = static::$_TABLE;
        $app = Application::getInstance();
        $data = $app->db->fetchOne("SELECT * FROM $table WHERE id = ?", [$id]);

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
        static::checkSchemaVars();

        $app = Application::getInstance();

        $table = static::$_TABLE;
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
        static::checkSchemaVars();

        $table = static::$_TABLE;
        $app = Application::getInstance();

        $columns = [];
        $values = [];
        $placeholders = [];

        foreach (static::$_COLUMNS as $column) {
            if (in_array($column, static::$_AUTO_INCREMENT)) {
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
        static::checkSchemaVars();

        $table = static::$_TABLE;
        $app = Application::getInstance();

        $columns = [];
        $values = [];

        foreach (static::$_COLUMNS as $column) {
            if (in_array($column, static::$_AUTO_INCREMENT)) {
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
        static::checkSchemaVars();

        $out = [];
        foreach (static::$_COLUMNS as $key) {
            if (!in_array($key, static::$_SENSITIVE)) {
                $out[$key] = $this->$key;
            }
        }
        return $out;
    }
}

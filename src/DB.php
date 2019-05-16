<?php
namespace dicr\dao;

use PDO;

/**
 * Работа с базой данных
 *
 * @author Igor (Dicr) Tarasov <develop@dicr.org>
 * @version 2019
 */
class DB
{
    /** @var \PDO */
    private $pdo;

    /** @var static */
    private $instance;

    /**
     * Конструктор
     *
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string $db
     * @param string $charset
     * @throws \PDOException
     */
    public function construct(string $host, string $user, string $pass, string $db, string $charset = 'utf8')
    {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $db, $charset);

        $opt = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false
        ];

        $this->pdo = new PDO($dsn, $user, $pass, $opt);

        self::$instance = $this;
    }

    /**
     * Возвращает экземпляр.
     *
     * @return static
     */
    public static function instance()
    {
        return self::$instance;
    }

    /**
     * Перегрузка статических методов.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic(string $method, array $args)
    {
        return call_user_func_array([self::instance(), $method], $args);
    }

    /**
     * Возвращает соединение с базой
     *
     * @return \PDO
     */
    public function pdo()
    {
        return $this->pdo;
    }

    /**
     * Экранирование строки
     *
     * @param mixed $value
     * @param int $parameter_type
     * @return string
     */
    public function esc(string $value, int $parameter_type = PDO::PARAM_STR_NATL)
    {
        if (!isset($value)) {
            $parameter_type = PDO::PARAM_NULL;
        }

        return $this->pdo->quote($value, $parameter_type);
    }

    /**
     * Экранирование для Opencart.
     * Синоним esc.
     *
     * @param mixed $value
     * @return string
     */
    public function encode($value)
    {
        return $this->esc((string)$value);
    }

    /**
     * Выполняет запрос и возвращает \PDOStatement
     * @param string $sql SQL
     * @param array $params парамеры для выполнения
     * @throws \PDOException
     * @return \PDOStatement
     */
    public function queryRes(string $sql, array $params=[])
    {
        if (empty($params)) {
            $stmt = $this->pdo->query($sql);
        } else {
            $stmt = $this->pdo->prepare($sql);
            $stmt->exec($params);
        }

        return $stmt;
    }

    /**
     * Запрос для OpenCart.
     *
     * @param string $sql
     * @return \PDOStatement|\stdClass
     */
    public function query(string $sql)
    {
        $stmt = $this->queryRes($sql);

        $ret = new \stdClass();
        $ret->num_rows = $stmt->rowCount();
        $ret->rows = [];
        $ret->row = null;

        if ($ret->num_rows > 0 && $stmt->columnCount() > 0) {
            $ret->rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (isset($ret->rows[0])) {
                $ret->row = $ret->rows[0];
            }
        }

        $stmt->closeCursor();

        return $ret;
    }

    /**
     * Выполняет запрос и возвращает все результаты
     *
     * @param string $sql
     * @param array $params
     * @param string $class
     * @return string[][]|object[]
     */
    public function queryAll(string $sql, array $params = [], string $class = '')
    {
        $stmt = $this->queryRes($sql, $params);

        $ret = [];
        if ($stmt->rowCount() > 0) {
            $ret = !empty($class) ? $stmt->fetchAll(PDO::FETCH_CLASS, $class) : $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt->closeCursor();
        return $ret ?: [];
    }

    /**
     * Возвращает колонку данных.
     *
     * @param string $sql
     * @param array $params
     * @param int $column
     * @return string[]
     */
    public function queryColumn(string $sql, array $params = [], int $column = null)
    {
        $stmt = $this->queryRes($sql, $params);
        $ret = $stmt->rowCount() > 0 ? $stmt->fetchColumn($column) : [];
        $stmt->closeCursor();
        return $ret ?: [];
    }

    /**
     * Возвращает индексированных массив данных.
     *
     * @param string $sql
     * @param array $params
     * @return string[] ассоциативный массив, в котором включ - первая колонка, значение - вторая
     */
    public function queryKeyPair(string $sql, array $params = [])
    {
        $stmt = $this->queryRes($sql, $params);
        $ret = $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_KEY_PAIR) : [];
        $stmt->closeCursor();
        return $ret ?: [];
    }

    /**
     * Выполняет запрос и возвращает один результат.
     *
     * @param string $sql
     * @param array $params
     * @param string $class
     * @return string[]|object|null
     */
    public function queryOne(string $sql, array $params = [], string $class = null)
    {
        $stmt = $this->queryRes($sql, $params);

        $ret = null;
        if ($stmt->rowCount() > 0) {
            $ret = !empty($class) ? $stmt->fetchObject($class) : $stmt->fetch(PDO::FETCH_ASSOC);
        }

        $stmt->closeCursor();
        return $ret ?: null;
    }

    /**
     * Возвращает одно значение колонки.
     *
     * @param string $sql
     * @param array $params
     * @param int $column
     * @return string|null
     */
    public function queryScalar(string $sql, array $params = [], int $column = null)
    {
        $stmt = $this->queryRes($sql, $params);
        $ret = $stmt->rowCount() > 0 ? $stmt->fetch(PDO::FETCH_NUM) : null;
        $stmt->closeCursor();

        $column = (int)$column;
        return !empty($ret) && array_key_exists($column, $ret) ? $ret[$column] : null;
    }

    /**
     * Возвращает количество результатов в запросе.
     *
     * @param string $sql
     * @param array $params
     * @return int|null
     */
    public function queryCount(string $sql, array $params = [])
    {
        $sql = sprintf('select count(*) from (%s) T', $sql);
        return (int)$this->queryScalar($sql, $params, 0);
    }

    /**
     * {@inheritDoc}
     * @see PDO::lastInsertId()
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
}
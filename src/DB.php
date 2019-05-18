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
    private static $instance;

    /**
     * Конструктор
     *
     * @param string $dsn
     * @param string $user
     * @param string $pass
     * @param array|false $opts
     */
    public function __construct(string $dsn, string $user=null, string $pass=null, $opts = [])
    {
        if ($opts !== false) {
            $opts = array_merge([
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ], $opts);
        }

        $this->pdo = new \PDO($dsn, $user, $pass, $opts ?: []);

        if ($opts === false) {
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }

        self::$instance = $this;
    }

    /**
     * Создает DNS по имени хоста и базы данных.
     *
     * @param string $driver
     * @param string $host
     * @param string $db
     * @param string $charset
     */
    public static function createDSN(string $driver, string $host, string $db, string $charset = 'utf8')
    {
        return sprintf('%s:host=%s;dbname=%s;charset=%s', $driver, $host, $db, $charset);
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

        // если запрос select, то должно быь columnCout
        $ret->rows = $stmt->columnCount() > 0 ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
        $ret->row = $ret->rows[0] ?? null;
        $ret->num_rows = count($ret->rows);

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
        $ret = !empty($class) ? $stmt->fetchAll(PDO::FETCH_CLASS, $class) : $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        $ret = $stmt->fetchAll(PDO::FETCH_COLUMN, $column);
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
        $ret = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
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
        $ret = !empty($class) ? $stmt->fetchObject($class) : $stmt->fetch(PDO::FETCH_ASSOC);
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
        $column = (int)$column;
        $stmt = $this->queryRes($sql, $params);
        $ret = $stmt->fetchColumn($column);
        $stmt->closeCursor();
        return $ret !== false ? $ret : null;
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
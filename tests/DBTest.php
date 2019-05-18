<?php
namespace dicr\dao\tests;

use PHPUnit\Framework\TestCase;
use dicr\dao\DB;

/**
 * Test DB
 *
 * @author Igor (Dicr) Tarasov <develop@dicr.org>
 * @version 2019
 */
class DBTest extends TestCase
{
    /** @var \dicr\dao\DB */
    protected static $db;

    /**
     * Setup
     */
    public static function setUpBeforeClass()
    {
        self::$db = new DB('sqlite::memory:', null, null, false);

        self::$db->queryRes(
            'create table test (
                id integer primary key not null,
                name text
            )'
        );

        self::$db->pdo()->exec(
            'insert into test (id, name) values(1, "Иван")'
        );
    }

    /**
     * Test statement
     */
    public function testQueryRes()
    {
        $expected = 'select * from test';
        $res = self::$db->queryRes('select * from test');
        self::assertEquals($expected, $res->queryString);
        self::assertEquals(2, $res->columnCount());
        $res->closeCursor();

        self::expectException(\PDOException::class);
        $res = self::$db->queryRes('select * from test2');
    }

    /**
     * Test Opencart query
     */
    public function testQuery()
    {
        $expected = new \stdClass();
        $expected->rows = [['id' => 1, 'name' => 'Иван']];
        $expected->row = ['id' => 1, 'name' => 'Иван'];
        $expected->num_rows = 1;
        self::assertEquals($expected, self::$db->query('select * from test'));

        $expected = new \stdClass();
        $expected->rows = [];
        $expected->row = null;
        $expected->num_rows = 0;
        self::assertEquals($expected, self::$db->query('select * from test where id=-1'));
    }

    public function testQueryAll()
    {
        $expected = [['id' => 1, 'name' => 'Иван']];
        self::assertEquals($expected, self::$db->queryAll('select * from test'));

        $obj = new \stdClass();
        $obj->id = 1;
        $obj->name = 'Иван';
        $expected = [$obj];
        self::assertEquals($expected, self::$db->queryAll('select * from test', [], \stdClass::class));

        $expected = [];
        self::assertEquals($expected, self::$db->queryAll('select * from test where id=0'));

    }

    public function testQueryColumn()
    {
        $expected = ['Иван'];
        self::assertEquals($expected, self::$db->queryColumn('select * from test', [], 1));

        $expected = [];
        self::assertEquals($expected, self::$db->queryColumn('select * from test where id=0', [], 1));
    }

    public function testQueryKeyPair()
    {
        $expected = [1 => 'Иван'];
        self::assertEquals($expected, self::$db->queryKeyPair('select * from test', [], 1));

        $expected = [];
        self::assertEquals($expected, self::$db->queryKeyPair('select * from test where id=0', [], 1));
    }

    public function testQueryOne()
    {
        $expected = ['id' => 1, 'name' => 'Иван'];
        self::assertEquals($expected, self::$db->queryOne('select * from test'));

        $expected = new \stdClass();
        $expected->id = 1;
        $expected->name = 'Иван';
        self::assertEquals($expected, self::$db->queryOne('select * from test', [], \stdClass::class));

        $expected = null;
        self::assertEquals($expected, self::$db->queryOne('select * from test where id=0', []));
    }

    public function testQueryScalar()
    {
        $expected = 1;
        self::assertEquals($expected, self::$db->queryScalar('select * from test'));

        $expected = null;
        self::assertEquals($expected, self::$db->queryScalar('select * from test where id=0'));
    }

    public function testQueryCount()
    {
        $expected = 1;
        self::assertEquals($expected, self::$db->queryCount('select * from test'));

        $expected = 0;
        self::assertEquals($expected, self::$db->queryCount('select * from test where id=0'));
    }
}

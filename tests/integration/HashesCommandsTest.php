<?php
/**
 * This file is part of RedisClient.
 * git: https://github.com/cheprasov/php-redis-client
 *
 * (C) Alexander Cheprasov <cheprasov.84@ya.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Test\Integration;

include_once(__DIR__. '/AbstractCommandsTest.php');

use RedisClient\Exception\ErrorResponseException;

/**
 * @see HashesCommandsTrait
 */
class HashesCommandsTest extends AbstractCommandsTest {

    /**
     * @inheritdoc
     */
    protected function setUp() {
        static::$Redis->flushall();
        self::$fields = [
            'string'  => 'value',
            'integer' => 42,
            'true'    => true,
            'false'   => false,
            'float'   => 3.14159265,
            'e'       => '5.0e3',
            'null'    => null,
            ''        => 'empty',
            'empty'   => '',
            'bin'     => call_user_func_array('pack', ['N*'] + range(0, 255))
        ];
        static::$Redis->hmset('hash', self::$fields);
        static::$Redis->hmset('', self::$fields);
        static::$Redis->set('string', 'value');
    }

    public function test_hdel() {
        $Redis = static::$Redis;

        $this->assertSame(0, $Redis->hdel('key-does-not-exist', 'field'));

        $this->assertSame(1, $Redis->hdel('hash', 'string'));
        $this->assertSame(2, $Redis->hdel('hash', ['integer', 'true']));
        $this->assertSame(1, $Redis->hdel('hash', ['true', 'false']));
        $this->assertSame(1, $Redis->hdel('hash', ['float', 'float']));
        $this->assertSame(2, $Redis->hdel('hash', ['', 'null','', 'null']));
        $this->assertSame(1, $Redis->hdel('hash', 'bin'));

        $this->assertSame(1, $Redis->hdel('', ['']));
        $this->assertSame(0, $Redis->hdel('', ''));
        $this->assertSame(0, $Redis->hdel('', ''));

        try {
            $Redis->hdel('string', 'field');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hexists() {
        $Redis = static::$Redis;

        $this->assertSame(0, $Redis->hexists('key-does-not-exist', 'field'));
        $this->assertSame(0, $Redis->hexists('hash', 'field-does-not-exist'));

        $this->assertSame(1, $Redis->hexists('hash', 'string'));
        $this->assertSame(1, $Redis->hexists('hash', 'float'));
        $this->assertSame(1, $Redis->hexists('hash', 'null'));
        $this->assertSame(1, $Redis->hexists('hash', 'empty'));
        $this->assertSame(1, $Redis->hexists('hash', 'integer'));
        $this->assertSame(1, $Redis->hexists('hash', ''));
        $this->assertSame(1, $Redis->hexists('hash', 'bin'));

        $this->assertSame(1, $Redis->hexists('', ''));
        $this->assertSame(1, $Redis->hexists('', 'null'));
        $this->assertSame(1, $Redis->hexists('', 'bin'));

        try {
            $Redis->hexists('string', 'value');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hget() {
        $Redis = static::$Redis;

        $this->assertSame(null, $Redis->hget('key-does-not-exist', 'field'));
        $this->assertSame(null, $Redis->hget('hash', 'field-does-not-exist'));

        $this->assertSame('value', $Redis->hget('hash', 'string'));
        $this->assertSame('42', $Redis->hget('hash', 'integer'));
        $this->assertSame('1', $Redis->hget('hash', 'true'));
        $this->assertSame('', $Redis->hget('hash', 'false'));
        $this->assertSame('3.14159265', $Redis->hget('hash', 'float'));
        $this->assertSame('', $Redis->hget('hash', 'null'));
        $this->assertSame('empty', $Redis->hget('hash', ''));
        $this->assertSame('', $Redis->hget('hash', 'empty'));
        $this->assertSame(self::$fields['bin'], $Redis->hget('hash', 'bin'));

        try {
            $Redis->hget('string', 'some-field');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hgetall() {
        $Redis = static::$Redis;

        $this->assertSame([], $Redis->hgetall('key-does-not-exist'));

        $hash = $Redis->hgetall('hash');
        ksort($hash);

        $hash2 = $Redis->hgetall('');
        ksort($hash2);

        $fields = self::$fields;
        ksort($fields);

        $this->assertEquals($fields, $hash);
        $this->assertSame($hash, $hash2);

        try {
            $Redis->hgetall('string');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hincrby() {
        $Redis = static::$Redis;

        $this->assertSame(11, $Redis->hincrby('key-does-not-exist', 'field', 11));
        $this->assertSame(11, $Redis->hincrby('hash', 'field-does-not-exist', 11));
        $this->assertSame(-11, $Redis->hincrby('key-does-not-exist-2', 'field', -11));
        $this->assertSame(-11, $Redis->hincrby('hash', 'field-does-not-exist-2', -11));

        try {
            $this->assertSame(2, $Redis->hincrby('hash', 'string', 2));
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_HASH_NOT_INTEGER, $Ex->getMessage());
        }

        try {
            $this->assertSame(1, $Redis->hincrby('hash', 'float', 3));
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_HASH_NOT_INTEGER, $Ex->getMessage());
        }

        try {
            // I don't know why it happens, but it is real Redis behavior
            $this->assertSame(3, $Redis->hincrby('hash', 'bin', 3));
            //$this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_HASH_NOT_INTEGER, $Ex->getMessage());
        }

        $this->assertSame(4, $Redis->hincrby('hash', 'null', 4));
        $this->assertSame(3, $Redis->hincrby('hash', 'null', -1));
        $this->assertSame(5, $Redis->hincrby('hash', 'empty', 5));
        $this->assertSame(-10, $Redis->hincrby('hash', 'empty', -15));
        $this->assertSame(48, $Redis->hincrby('hash', 'integer', 6));
        $this->assertSame(0, $Redis->hincrby('hash', 'integer', -48));
        $this->assertSame(2, $Redis->hincrby('', 'null', 2));
        $this->assertSame(0, $Redis->hincrby('', 'null', -2));

        try {
            $Redis->hincrby('string', 'value', 2);
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hincrbyfloat() {
        $Redis = static::$Redis;

        $this->assertSame('1.1', $Redis->hincrbyfloat('key-does-not-exist', 'field', 1.1));
        $this->assertSame('1.1', $Redis->hincrbyfloat('hash', 'field-does-not-exist', '1.1'));
        $this->assertSame('-1.1', $Redis->hincrbyfloat('key-does-not-exist-2', 'field', -1.1));
        $this->assertSame('-1.1', $Redis->hincrbyfloat('hash', 'field-does-not-exist-2', '-1.1'));

        try {
            $this->assertSame('2.2', $Redis->hincrbyfloat('hash', 'string', 2.2));
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_HASH_NOT_FLOAT, $Ex->getMessage());
        }

        $this->assertSame('4.25159265', $Redis->hincrbyfloat('hash', 'float', 1.11));
        $this->assertSame('4.4', $Redis->hincrbyfloat('hash', 'null', 4.4));
        $this->assertSame('3.2', $Redis->hincrbyfloat('hash', 'null', -1.2));
        $this->assertSame('5.1', $Redis->hincrbyfloat('hash', 'empty', 5.1));
        $this->assertSame('3.6', $Redis->hincrbyfloat('hash', 'empty', -1.5));
        $this->assertSame('48.2', $Redis->hincrbyfloat('hash', 'integer', 6.2));
        $this->assertSame('44.4', $Redis->hincrbyfloat('hash', 'integer', -3.8));
        $this->assertSame('2.2', $Redis->hincrbyfloat('', 'null', 2.2));
        $this->assertSame('0', $Redis->hincrbyfloat('', 'null', -2.2));
        $this->assertSame('-2.2', $Redis->hincrbyfloat('', 'null', -2.2));
        $this->assertSame('5200', $Redis->hincrbyfloat('', 'e', '2.0e2'));

        try {
            $Redis->hincrbyfloat('string', 'value', 2.2);
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hkeys() {
        $Redis = static::$Redis;

        $this->assertSame([], $Redis->hkeys('key-does-not-exist'));

        $keys = array_keys(self::$fields);
        sort($keys);

        $storedKeys = $Redis->hkeys('hash');
        sort($storedKeys);

        $storedKeys2 = $Redis->hkeys('');
        sort($storedKeys2);

        $this->assertSame($keys, $storedKeys);
        $this->assertSame($keys, $storedKeys2);

        try {
            $Redis->hkeys('string');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hlen() {
        $Redis = static::$Redis;

        $this->assertSame(0, $Redis->hlen('key-does-not-exist'));
        $this->assertSame(count(self::$fields), $Redis->hlen('hash'));
        $this->assertSame(count(self::$fields), $Redis->hlen(''));

        try {
            $Redis->hlen('string');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hmget() {
        $Redis = static::$Redis;

        $this->assertSame([null], $Redis->hmget('key-does-not-exist', 'field'));
        $this->assertSame([null], $Redis->hmget('hash', ['field-does-not-exist']));

        $this->assertSame(['value'], $Redis->hmget('hash', 'string'));
        $this->assertSame(['42'], $Redis->hmget('hash', ['integer']));
        $this->assertSame(['1', ''], $Redis->hmget('hash', ['true', 'false']));
        $this->assertSame(
            ['3.14159265', '5.0e3', '', '', 'empty',],
            $Redis->hmget('hash', ['float', 'e', 'null', 'empty', ''])
        );
        $this->assertEquals(array_values(self::$fields), $Redis->hmget('hash', array_keys(self::$fields)));

        try {
            $Redis->hmget('string', 'some-field');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hmset() {
        $Redis = static::$Redis;

        $this->assertSame(true, $Redis->hmset('hash', ['some-field' => 'good']));
        $this->assertSame(true, $Redis->hmset('hash', ['some-field' => 'good']));

        try {
            $Redis->hmset('string', ['field' => 'test']);
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hset() {
        $Redis = static::$Redis;

        $this->assertSame(1, $Redis->hset('hash', 'some-field', 'good'));
        $this->assertSame('good', $Redis->hget('hash', 'some-field'));
        $this->assertSame(0, $Redis->hset('hash', 'some-field', 'good'));
        $this->assertSame(0, $Redis->hset('hash', 'some-field', 'super good'));
        $this->assertSame('super good', $Redis->hget('hash', 'some-field'));

        try {
            $Redis->hset('string', 'field', 'test');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hsetnx() {
        $Redis = static::$Redis;

        $this->assertSame(1, $Redis->hsetnx('hash', 'some-field', 'good'));
        $this->assertSame('good', $Redis->hget('hash', 'some-field'));
        $this->assertSame(0, $Redis->hsetnx('hash', 'some-field', 'good'));
        $this->assertSame(0, $Redis->hsetnx('hash', 'some-field', 'super good'));
        $this->assertSame('good', $Redis->hget('hash', 'some-field'));

        try {
            $Redis->hsetnx('string', 'field', 'test');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hstrlen() {
        $Redis = static::$Redis;

        if (static::$version < '3.2') {
            $this->assertTrue(true);
            return;
        }

        $this->assertSame(0, $Redis->hstrlen('hash', 'some-field'));
        $this->assertSame(1, $Redis->hsetnx('hash', 'some-field', 'good'));
        $this->assertSame(4, $Redis->hstrlen('hash', 'some-field'));

        try {
            $Redis->hstrlen('string', 'field');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hvals() {
        $Redis = static::$Redis;

        $this->assertSame([], $Redis->hvals('key-does-not-exist'));

        $values = array_values(self::$fields);
        array_walk($values, function(&$a) {
           $a = (string) $a;
        });
        sort($values);

        $storedValues = $Redis->hvals('hash');
        sort($storedValues);

        $storedValues2 = $Redis->hvals('');
        sort($storedValues2);

        $this->assertEquals($values, $storedValues);
        $this->assertEquals($values, $storedValues2);

        try {
            $Redis->hvals('string');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

    public function test_hscan() {
        $Redis = static::$Redis;

        $this->assertSame(['0', []], $Redis->hscan('key-does-not-exist', 0));

        $hscan = $Redis->hscan('hash', 0);
        $this->assertSame(count(self::$fields) * 2, count($hscan[1]));

        for ($i = 0; $i < count($hscan[1]) ; $i += 2) {
            $key = $hscan[1][$i];
            $value = $hscan[1][$i + 1];
            $this->assertSame((string) self::$fields[$key], $value);
        }

        try {
            $Redis->hscan('string', 'field');
            $this->assertTrue(false);
        } catch (ErrorResponseException $Ex) {
            $this->assertSame(static::REDIS_RESPONSE_ERROR_WRONGTYPE, $Ex->getMessage());
        }
    }

}

<?php
namespace sevenNt\custodx;

/**
 *
 * @brief An simple Interface of Redis Client  
 *
 */
class RedisClient 
{
    private static $_instance = [];
    private $_redis = false;

    /**
     * @brief Get a single Instance of redis client 
     * @param $confFile file of configuration
     * @param $serverName master or slave
     * @return false or string
     */
    public static function getInstance($confFile=__DIR__ . 'redis.conf.php', $serverName='master') 
    {
        if (isset(self::$_instance[$serverName])) {
            return self::$_instance[$serverName];
        }
        require_once($confFile);
        if (!isset($redis_conf[$serverName])) {
            throw new Exception("param serverName Or redis config error...");
        }
        $conf = $redis_conf[$serverName];
        if (!class_exists('Redis')) {
            throw new Exception("Class Redis not exists, please install the php Redis extension...");
        }
        if (isset($conf['host']) && isset($conf['port']) && isset($conf['passwd']) && isset($conf['timeout'])) {
            self::$_instance[$serverName] = new self($conf['host'], $conf['port'], $conf['passwd'], $conf['timeout']);
            return self::$_instance[$serverName];
        }
        return false;
    }

    private function __construct($host, $port, $passwd, $timeout) 
    {
        $this->_redis = new Redis();
        if ($this->_redis->connect($host, $port, $timeout)) {
            if (!empty($passwd)) {
                $this->_redis->auth($passwd);
            }
        }
    }

    private function __clone()
    {
    }

    /**
     * @brief call method of redis 
     * @return mixed
     */
    public function __call($method, $args) 
    {
        if (!$this->_redis || !$method) {
            return false;
        }
        if (!method_exists($this->_redis, $method)) {
            throw new Exception("Class RedisCli not have method ($method) ");
        }
        return call_user_func_array([$this->_redis, $method], $args);
    }
}

<?php
namespace sevenNt\custodx; 

/**
 *
 * @brief Client of custodx  
 * @author Leslie Zheng <lesliezheng0105@yahoo.com>
 * @date 2015-12-09 14:23:59  
 * 
 */
class MonitorClient
{
    const NAME_SET = 'CUSTODX_NAMESET'; 

    private static $_instances = [];
    private $_itemName;
    private $_ratio;

    /**
    * @brief Get a sigle instance of client 
    *        A full item name add to custodx:_ratio#name@hostname
    * @param $name brief item name
    * @param $_ratio default value 0, (0,99] means (0,99] percentile response time
    * @param $hostname 
    */
    public static function getInstance($name, $_ratio = 0, $hostname = '')
    {
        $hasErr = false;
        if (!preg_match('/^[0-9a-zA-Z_\.-]+$/', $name) ||
            !(is_int($_ratio) && $_ratio >= 0 && $_ratio < 100)) {
            $hasErr = true;
        } else {
            $name = $_ratio. '#' . $name;
            if (!empty($hostname)) {
                $name .= '@' . $hostname;
            }

            $nameset = [];
            if ($err = self::getNameSet($nameset) || !is_array($nameset)) {
                $hasErr = true;
            } else {
                if ($name && !in_array($name, $nameset)) {
                    $nameset[] = $name;
                    if ($err = self::addNameSet($nameset)) {
                        $hasErr = true;
                    }
                }
            }
        }

        if ($hasErr) {
            $name = '';
        }
        if (!isset(self::$_instances[$name])) {
            self::$_instances[$name] = new self($name, $_ratio); 
        }
        return self::$_instances[$name];
    }

    private function __construct($_itemName, $_ratio)
    {
        $this->_itemName = $_itemName;
        $this->_ratio = $_ratio;
    }

    private function __clone()
    {
    }

    /**
    * @brief Get items' full name from the set
    * @param &$nameset array
    * @return false or string
    */
    public static function getNameSet(&$nameset)
    {
        try {
            $nameset = RedisClient::getInstance('finance')->smembers(self::NAME_SET);
        } catch (Exception $e) {
            return 'GETNAMESET_ERROR';
        }
        return false;
    }

    /**
    * @brief Add An full item name to the set 
    * @param $name 
    * @return false or string
    */
    public static function addNameSet($name)
    {
        try {
            RedisClient::getInstance('finance')->sadd(self::NAME_SET, $name);
        } catch (Exception $e) {
            return 'ADDNAMESET_ERROR';
        }
        return false;
    }

    /**
    * @brief Delete An item full name from the set 
    * @param $name 
    * @return false or string
    */
    public static function delNameSet($name)
    {
        try {
            RedisClient::getInstance('finance')->srem(self::NAME_SET, $name);
        } catch (Exception $e) {
            return 'DELNAMESET_ERROR';
        }
        return false;
    }

    /**
    * @brief Increase value of an item 
    * @param $value Value of an item 
    * @return false or string
    */
    public function incr($value)
    {
        if (empty($this->_itemName) || !is_numeric($value)) {
            return 'PARAM_ERROR';
        }
        try {
            $redis = RedisClient::getInstance('finance');
            $time = date('Y-m-d H:i', time() + 60);
            $key = $time . '#' . $this->_itemName;
            $redis->incrbyfloat($key, $value);
            $redis->expire($key, 2*60); 
        } catch (Exception $e) {
            return 'REDIS_ERROR';
        }
        return false;
    }

    /**
    * @brief Insert time need to monitor into redis 
    * @param $value Value of time. e.g.The response time when call an interface 
    * @return false or string
    */
    public function insert($value)
    {
        if (empty($this->_itemName) || !is_numeric($value) || $this->_ratio <= 0) {
            return 'PARAM_ERROR';
        }
        try {
            $redis = RedisClient::getInstance('finance');
            $time = date('Y-m-d H:i', time() + 60);
            $key = $time . '#' . $this->_itemName;
            if ($redis->rpush($key, $value)) {
                $redis->expire($key, 2*60); 
            }
        } catch (Exception $e) {
            return 'REDIS_ERROR';
        }
        return false;
    }

}

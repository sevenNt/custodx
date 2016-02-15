<?php
namespace sevenNt\custodx;

/**
 *
 * @brief Server of custodx                                                                                                                   
 * @author Leslie Zheng <lesliezheng0105@yahoo.com>
 * @date 2015-12-09 16:12:29  
 * 
 */
class MonitorServer
{
    // address you want to send monitor data
    const MONITOR_URL = '';

    /**
    * @brief Get value of item that monitor percentile response time
    * @param $itemName
    * @param $ratio
    * @return null or integer
    */
    private static function getT9x($itemName, $ratio)
    {
        try {
            $redis = RedisClient::getInstance('finance');
            $time = date('Y-m-d H:i');
            $key = $time . '#' . $itemName;
            $values = $redis->lrange($key, 0, -1);
            $value = 0;
            if (!empty($values)) {
                sort($values);
                $count = count($values);
                $idx = intval(floor($ratio * $count / 100));
                if (isset($values[$idx])) {
                    $value = $values[$idx]; 
                }
            }
            return $value;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
    * @brief Get value by item name
    * @param $itemName
    * @return null or integer
    */
    private static function get($itemName)
    {
        try {
            $redis = RedisClient::getInstance('finance');
            $time = date('Y-m-d H:i');
            $key = $time . '#' . $itemName;
            $value = $redis->get($key);
            empty($value) && $value = 0;
            return $value;
        } catch (Exception $e) {
            return null;
        }
    }

    /*
     * @brief Send itmes 
     * @return false or string 
     */
    public static function send()
    {
        $nameset = [];
        if ($err = MonitorClient::getNameSet($nameset)) {
            return $err; 
        }

        $sendItems = [];
        foreach($nameset as $itemName) {
            // ratio#name@hostname
            $nameArr = explode('#', $itemName);
            if (empty($nameArr)) {
                continue;
            }
            if ($nameArr[0] == 0) {
                $value = self::get($itemName);
            } else {
                $value = self::getT9x($itemName, $nameArr[0]);
            }
            //TODO: delete item which value equals to 0 for a long time
            !is_null($value) && $sendItems[$itemName] = $value;
        }

        $payload = [];
        foreach ($sendItems as $itemName => $itemValue) {
            $item = [
                'time' => time(),
                'value' => round($itemValue, 2),
            ]; 
            $payload[$itemName] = $item;
        }

        $payloadArr = array_chunk($payload, 20, true);
        foreach ($payloadArr as $payloads) {
            $content = '';
            $curl = new CUrlHttp();
            $params = [ 
                'query_id' => '33',
                'data' => $payloads,
            );
            $result = $curl->httpPost(self::MONITOR_URL, $params, $content);
            if ($result !== false) {
            }
            $data = json_decode($content, true);
            if (isset($data['error'])) {
            }
        }
        return false;
    }

}

<?php

namespace yii2invalidcache\base;

use Yii;
use yii\db\ActiveRecord;

class BaseActiveRecord extends ActiveRecord
{

    /** 日志的分类 */
    const CACHE_LOG_CATEGORY = "cacheDb";

    /**
     * $data = $this->rdCache($key, function() use ($start, $limit, $styleId){
     * 			$sql = "SELECT a.id FROM `post` a order by a.sort desc limit {$start},{$limit}";
     * 			$data = $this->rows($sql);
     * 			return $data
     * }, 300);
     * 缓存数据库中的值
     * @param string   $key   键
     * @param callable $func  回调的函数
     * @param int $cacheTime   缓存的时间
     * @param bool $flush 是否清除缓存
     *
     * @return mixed
     */
    public static function rdCache($key, callable $func, $cacheTime = 0, $flush = false)
    {
        if ($flush)
            Yii::$app->cache->delete($key);

        $cachedData = Yii::$app->cache->get($key);
        if ($cachedData !== false) {
            YII_DEBUG && Yii::info("single|all_hit|{$cacheTime}|{$key}", self::CACHE_LOG_CATEGORY);// 分类到 相应的日志中
            return $cachedData;
        }

        YII_DEBUG && Yii::info("single|not_hit|{$cacheTime}|{$key}", self::CACHE_LOG_CATEGORY);

        $data = call_user_func($func);//若 缓存中没有,则到数据库中查找
        if ($data !== null)// 若缓存不为空,则 缓存起来
            Yii::$app->cache->set($key, $data, self::getRandomTime($cacheTime));

        return $data;
    }

    /**
     * $data = $this->rdMultiCache("hot_song_list_%s", $songIds, function($ids){
     * 			$str = implode(',', $ids);
     * 			$sql = "SELECT * FROM song WHERE id IN ({$str})";
     * 			$data = $this->rows($sql);
     * 			return empty($data) ? array() : $data;
     * }, 300);
     *
     * 根据一组 ids 来取值
     * @param string $keyFormat  key的标准格式
     * @param array $ids  一组 id
     * @param callable $func 回调的函数
     * @param int $cacheTime  缓存的时间  默认 秒
     * @param bool $flush 是否清除缓存
     * @param string $primaryKey
     *
     * @return array
     */
    public static function rdMultiCache($keyFormat, array $ids, callable $func, $cacheTime = 0, $flush = false, $primaryKey = 'id'){
        $keys = [];
        $key2id = [];
        foreach ($ids as $val) {
            $key = sprintf($keyFormat, $val);//返回一个格式化的字符串
            $keys[] = $key;//键的集
            $key2id[$key] = $val;// 让键值对 配对.
        }

        if ($flush) {
            $cachedData = [];
            array_map(function($v){
                Yii::$app->cache->delete($v);
            },$keys);
        } else {
            $allCachedData = Yii::$app->cache->multiGet($keys);
            $cachedData = [];
            foreach($allCachedData as $key=>$value){
                if($value!==false){//若取出来的数组中不包含 false 的话
                    $cachedData = array_merge($cachedData,[$key=>$value]);
                }
            }
        }

        $noCachedKeys = array_diff($keys, array_keys($cachedData));// 获取  未缓存的 key
        if (empty($noCachedKeys)) {
            YII_DEBUG && Yii::info("multi |all_hit|{$cacheTime}|" . implode(',', $keys), self::CACHE_LOG_CATEGORY);
            return $cachedData;
        }

        YII_DEBUG && Yii::info("multi |no_hit|{$cacheTime}|" . implode(',', $noCachedKeys), self::CACHE_LOG_CATEGORY);

        $noCachedIds = [];
        foreach ($noCachedKeys as $val) {
            $noCachedIds[] = $key2id[$val];
        }

        $data = call_user_func($func, $noCachedIds);

        $cacheData = [];
        foreach ($data as $val) {
            if (empty($val[$primaryKey]))
                continue;
            $key = sprintf($keyFormat, $val[$primaryKey]);
            $cacheData[$key] = $val;
        }

        if ($data)
            Yii::$app->cache->multiSet($cacheData, $cacheTime);

        return array_values(array_merge($data, $cachedData));
    }


    /**
     * @param $key
     * @param callable $func
     * @param int $cacheTime
     * @param bool $flush
     * @return mixed
     */
    public static function rdObjCache($key, callable $func, $cacheTime = 0, $flush = false)
    {
        if ($flush)
            Yii::$app->cache->delete($key);

        $cachedData = Yii::$app->cache->get($key);
        if ($cachedData !== false) {
            YII_DEBUG && Yii::info("single|all_hit|{$cacheTime}|{$key}", self::CACHE_LOG_CATEGORY);// 分类到 相应的日志中
            return unserialize($cachedData);
        }

        YII_DEBUG && Yii::info("single|not_hit|{$cacheTime}|{$key}", self::CACHE_LOG_CATEGORY);

        $data = call_user_func($func);//若 缓存中没有,则到数据库中查找
        if ($data !== null)// 若缓存不为空,则 缓存起来
            Yii::$app->cache->set($key, serialize($data), self::getRandomTime($cacheTime));

        return $data;
    }


    /**
     * @param $keyFormat
     * @param array $ids
     * @param callable $func
     * @param int $cacheTime
     * @param bool $flush
     * @param string $primaryKey
     * @return array
     */
    public static function rdMultiObjCache($keyFormat, array $ids, callable $func, $cacheTime = 0, $flush = false, $primaryKey = 'id'){
        $keys = [];
        $key2id = [];
        foreach ($ids as $val) {
            $key = sprintf($keyFormat, $val);//返回一个格式化的字符串
            $keys[] = $key;//键的集
            $key2id[$key] = $val;// 让键值对 配对.
        }

        if ($flush) {
            $cachedData = [];
            array_map(function($v){
                Yii::$app->cache->delete($v);
            },$keys);
        } else {
            $allCachedData = Yii::$app->cache->multiGet($keys);
            $cachedData = [];
            foreach($allCachedData as $key=>$value){
                if($value!==false){//若取出来的数组中不包含 false 的话
                    $cachedData = array_merge($cachedData,[$key=>unserialize($value)]);
                }
            }
        }

        $noCachedKeys = array_diff($keys, array_keys($cachedData));// 获取  未缓存的 key
        if (empty($noCachedKeys)) {
            YII_DEBUG && Yii::info("multi |all_hit|{$cacheTime}|" . implode(',', $keys), self::CACHE_LOG_CATEGORY);
            return $cachedData;
        }

        YII_DEBUG && Yii::info("multi |no_hit|{$cacheTime}|" . implode(',', $noCachedKeys), self::CACHE_LOG_CATEGORY);

        $noCachedIds = [];
        foreach ($noCachedKeys as $val) {
            $noCachedIds[] = $key2id[$val];
        }

        $data = call_user_func($func, $noCachedIds);//从闭包中获取数据

        $cacheData = [];
        foreach ($data as $val) {
            if (empty($val->$primaryKey))
                continue;
            $key = sprintf($keyFormat, $val->$primaryKey);
            $cacheData[$key] = serialize($val);//序列化 存入数据库中
        }

        if ($data)
            Yii::$app->cache->multiSet($cacheData, $cacheTime);

        return array_values(array_merge($data, $cachedData));
    }

    /**
     * 给一个随机的时间
     * @param $time
     *
     * @return int
     */
    public static function getRandomTime($time){
        $time = intval($time);
        if ($time <= 0)
            return 1;

        return $time + mt_rand(1, 5);
    }

}

<?php

namespace yii2invalidcache\behaviors;


use Yii;
use yii\base\Behavior;
use yii\db\ActiveRecord;

class InvalidCacheBehavior extends Behavior
{

    /** cache component ID
     * @var string
     */
    public $cache = 'cache';

    /** 分割符号
     * @var string
     */
    public $delimiter = '||';

    /**
     * @var array
     * 只要是 更新,或者 删除 则删除对应的缓存
     *
     * ```php
     * [// id 为字段的属性名, * 为全局
     *     'rd_key1_%s||id',
     *     'rd_key2_%s', // 直接删除.
     *     'rd_key3_%s||*',
     *     'rd_key4_%s_%s||uid,id',// 通过 uid, id 替换 %s . 然后刷新
     * ]
     * ```
     */
    public $key_formats = [];

    /**
     * @return array
     */
    public function events()
    {
        return [
            ActiveRecord::EVENT_AFTER_DELETE => 'invalidCache',
            ActiveRecord::EVENT_AFTER_UPDATE => 'invalidCache',
        ];
    }


    /** 删除 / 修改之后,就要清除相应的缓存
     * @param $event
     */
    public function invalidCache($event) {
        if(!empty($this->key_formats)) {
            $attributes = (array) $this->key_formats;
            foreach ($attributes as $attribute) {
                if (strpos($attribute, $this->delimiter)) {
                    list($key, $flag) = explode($this->delimiter, $attribute);
                    if ($flag == "*") {
                        $this->clearAllCache(sprintf($key,"*"));
                    } else {
                        if (strpos($flag, ',')) {
                            $arr = explode(',',$flag);
                            $data = [];
                            foreach ($arr as $value) {
                                $data[] = $this->owner->$value;
                            }

                            $args = array_merge([$key], $data);
                            $this->clearSingleCache(call_user_func_array("sprintf", $args));
                        } else {
                            $str = $this->owner->$flag;//获取对应的值
                            $this->clearSingleCache(sprintf($key,$str));
                        }
                    }
                } else {
                    Yii::$app->cache->redis->del($attribute);
                }
            }
        }
    }

    /** 类似于  prefix_* 获取 key 的数据集合
     * @param $key
     */
    protected function clearAllCache($key) {
//		$keys = Yii::$app->cache->redis->keys($key);
        $keys = $this->scanMatchPattern($key);
        foreach ($keys as $k) {
            Yii::$app->cache->redis->del($k);
        }
    }

    /** 利用 redis 中的 scan 命令 进行扫描. 降低 keys 命令带来的风险
     * @param $str
     * @param int $cursor
     * @param int $count
     *
     * @return array
     */
    private function scanMatchPattern($str,$cursor=0,$count=3000){
        $collects = [];
        do {
            $collect = Yii::$app->cache->redis->executeCommand("SCAN",[$cursor,"match",$str,"count",$count]);
            $collects = array_merge($collects,$collect[1]);
            $cursor = $collect[0];
        } while ($collect[0] != '0');//当这个值为0时,则查找结束

        return $collects;
    }

    /** 清除单个 缓存
     * @param $key
     */
    protected function clearSingleCache($key) {
        Yii::$app->cache->redis->del($key);
    }

}


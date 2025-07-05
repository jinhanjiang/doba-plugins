<?php

namespace Doba\Plugin;

use Doba\Util;
use Doba\RedisClient;
use Doba\Plugin\Throttle\ThrottleRequest;

/**
 *  接口请求频率限制
 *
 *   $authIdentifier = $userId.'_'.$api; // 用户编号和API做唯一标识
 *   $maxAttempts = 300; // 每分钟请求不超过300
 *   $throttle = $GLOBALS['plugin']->call('throttle', 'getThrottleRequest');
 *   try{
 *       $throttle->handle([
 *           'maxAttempts' => $maxAttempts,
 *           'authIdentifier' => $authIdentifier,
 *       ]);
 *   } catch(\Exception $ex) {
 *       if('10000' == $ex->getCode()) {
 *           $throttle->responeHeader();
 *           $remaining = $throttle->getRemaining();
 *           $maxAttempts = $throttle->getMaxAttempts();
 *           echo json_encode([
 *               'code'=>'100', 
 *               'message'=>"接口一分钟调用频率超过({$maxAttempts})次限制，接口名称（{$api}）"
 *           ], 256); exit; 
 *       }
 *   }
 *
 *   api处理逻辑
 *   ......
 *
 *
 *   if($throttle) $throttle->responeHeader(); // 输出json结果前带上响应头部信息
 *   echo json_encode($result, JSON_UNESCAPED_UNICODE); exit;
 */
class ThrottlePlugin extends BasePlugin {
    
    private $redis = null;
    private $maxAttempts = 60;
    private $key = '';

    public function __construct(&$plugin){ 
        $this->_install($plugin, $this);
    }

    /**
     * 初始化流量请求
     */
    public function getThrottleRequest($redis=null){
        if(is_null($redis)) {
            $redis = RedisClient::me()->getRedis();
        }
        return new ThrottleRequest($redis);
    }
}
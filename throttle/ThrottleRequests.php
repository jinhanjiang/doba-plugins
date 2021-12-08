<?php

use \Exception;

/**
 * 流量请求限制
 */
class ThrottleRequests
{
    private $redis = null;
    private $maxAttempts = 60;
    private $key = '';
    public function __construct($redis) {
        $this->redis = $redis;
    }

    /**
     * @param $options = []
     * maxAttempts 最大尝试次数，默认60次
     * decayMinutes 衰减时间, 默认1分钟
     * keyPrefix 缓存键值前缀，默认空
     * authIdentifier 认证标识，必传
     */
    public function handle($options = [])
    {
        // 最大尝试次数，默认60次
        $this->maxAttempts = isset($options['maxAttempts']) ? (int)$options['maxAttempts'] : 60;
        // 衰减时间, 默认1分钟
        $decayMinutes = isset($options['decayMinutes']) ? (int)$options['decayMinutes'] : 1;
        // 缓存键值前缀，默认空
        $keyPrefix = isset($options['keyPrefix']) ? $options['keyPrefix'] : '';
        // 认证标识必填
        $authIdentifier = $options['authIdentifier'];
        if(empty($authIdentifier)) throw new Exception('Invalid authentication ID', 10001);

        // 生成缓存键值
        $this->key = $keyPrefix.sha1($authIdentifier);

        if($this->tooManyAttempts($this->key, $this->maxAttempts)) {
            throw new Exception('Request exceeds traffic limit', 10000);
        }
        $this->hit($this->key, $decayMinutes * 60);
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return bool
     */
    protected function tooManyAttempts($key, $maxAttempts) 
    {
        if ($this->attempts($key) >= $maxAttempts) {
            if ($this->has($key.':timer')) {
                return true;
            }

            $this->resetAttempts($key);
        }

        return false;
    }

    /**
     * Increment the counter for a given key for a given decay time.
     *
     * @param  string  $key
     * @param  int  $decaySeconds
     * @return int
     */
    protected function hit($key, $decaySeconds = 60)
    {
        $this->add(
            $key.':timer', $this->availableAt($decaySeconds), $decaySeconds
        );

        $added = $this->add($key, 0, $decaySeconds);

        $hits = (int) $this->increment($key);

        if (! $added && $hits == 1) {
            $this->put($key, 1, $decaySeconds);
        }

        return $hits;
    }
    
    /**
     * Response limit header
     *
     * @return
     */
    public function responeHeader() {
        foreach($this->getHeaders() as $key=>$val) {
            header("{$key}: {$val}");
        }
    }

    /**
     * Get the limit headers information.
     *
     * @param  int  $maxAttempts
     * @param  int  $remainingAttempts
     * @param  int|null  $retryAfter
     * @return array
     */
    public function getHeaders($maxAttempts, $remainingAttempts, $retryAfter = null)
    {
        $headers = [
            'X-RateLimit-Limit' => $this->maxAttempts,
            'X-RateLimit-Remaining' => $this->calculateRemainingAttempts($this->key, $this->maxAttempts),
        ];

        if (! is_null($retryAfter)) {
            $headers['Retry-After'] = $retryAfter;
            $headers['X-RateLimit-Reset'] = $this->availableAt($retryAfter);
        }

        return $headers;
    }


    public function getMaxAttempts() {
        return $this->maxAttempts;
    }

    public function getRemaining() {
        return $this->calculateRemainingAttempts($this->key, $this->maxAttempts);
    }

    /**
     * Calculate the number of remaining attempts.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int|null  $retryAfter
     * @return int
     */
    protected function calculateRemainingAttempts($key, $maxAttempts, $retryAfter = null)
    {
        if (is_null($retryAfter)) {
            return $this->retriesLeft($key, $maxAttempts);
        }

        return 0;
    }

    /**
     * Get the number of attempts for the given key.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function attempts($key)
    {
        return $this->get($key, 0);
    }

    /**
     * Reset the number of attempts for the given key.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function resetAttempts($key)
    {
        return $this->forget($key);
    }

    /**
     * Get the number of retries left for the given key.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @return int
     */
    protected function retriesLeft($key, $maxAttempts)
    {
        $attempts = $this->attempts($key);

        return $maxAttempts - $attempts;
    }

    /**
     * Get the "available at" UNIX timestamp.
     *
     * @param  int  $delay
     * @return int
     */
    protected function availableAt($delay = 0)
    {
        return strtotime("+{$delay} sec");
    }

    /**
     * Get the number of seconds until the given DateTime.
     *
     * @param  int  $delay
     * @return int
     */
    protected function secondsUntil($delay)
    {
        return (int) $delay;
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string|array  $key
     * @return mixed
     */
    protected function get($key)
    {
        $value = $this->connection()->get($key);

        return ! is_null($value) ? $this->unserialize($value) : null;
    }

    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     * @return bool
     */
    protected function add($key, $value, $seconds)
    {
        $lua = "return redis.call('exists',KEYS[1])<1 and redis.call('setex',KEYS[1],ARGV[2],ARGV[1])";

        return (bool) $this->connection()->eval(
            $lua, 1, $key, $this->serialize($value), (int) max(1, $seconds)
        );
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @param  int  $seconds
     * @return bool
     */
    protected function put($key, $value, $seconds)
    {
        return (bool) $this->connection()->setex(
            $key, (int) max(1, $seconds), $this->serialize($value)
        );
    }

    /**
     * Determine if an item exists in the cache.
     *
     * @param  string  $key
     * @return bool
     */
    protected function has($key)
    {
        return ! is_null($this->get($key));
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int
     */
    protected function increment($key, $value = 1)
    {
        return $this->connection()->incrby($this->prefix.$key, $value);
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return int
     */
    protected function decrement($key, $value = 1)
    {
        return $this->connection()->decrby($this->prefix.$key, $value);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return bool
     */
    protected function forever($key, $value)
    {
        return (bool) $this->connection()->set($this->prefix.$key, $this->serialize($value));
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    protected function forget($key)
    {
        return (bool) $this->connection()->del($this->prefix.$key);
    }

    /**
     * Serialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function serialize($value)
    {
        return is_numeric($value) && ! in_array($value, [INF, -INF]) && ! is_nan($value) ? $value : serialize($value);
    }

    /**
     * Unserialize the value.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function unserialize($value)
    {
        return is_numeric($value) ? $value : unserialize($value);
    }

    /**
     * Get the Redis connection instance.
     *
     * @return object $redis
     */
    protected function connection()
    {
        return $this->redis;
    }

    /**
     * Set the connection name to be used.
     *
     * @param  object  $redis
     * @return void
     */
    protected function setConnection($redis)
    {
        $this->redis = $redis;
    }
}
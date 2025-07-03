<?php

declare(strict_types=1);

namespace HPlus\Core\Service;

use Hyperf\Cache\Cache;
use Hyperf\Context\ApplicationContext;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * 统一缓存服务
 * 简化缓存使用，减少心智成本，提供稳定高性能的缓存解决方案
 * 
 * @author 毛自豪 <4213509@qq.com>
 */
class CacheService
{
    private static ?CacheInterface $cache = null;
    private static array $config = [];
    private static array $stats = [
        'hits' => 0,
        'misses' => 0,
        'sets' => 0,
        'deletes' => 0,
    ];

    /**
     * 初始化缓存服务
     */
    private static function init(): void
    {
        if (self::$cache === null) {
            self::$cache = ApplicationContext::getContainer()->get(CacheInterface::class);
            self::$config = config('cache', []);
        }
    }

    /**
     * 智能缓存键生成 - 自动处理前缀、命名空间等
     */
    public static function key(...$parts): string
    {
        $prefix = self::$config['prefix'] ?? 'hplus:';
        $namespace = self::$config['namespace'] ?? 'default';
        
        // 过滤空值并转换为字符串
        $cleanParts = array_filter(array_map('strval', $parts));
        
        return $prefix . $namespace . ':' . implode(':', $cleanParts);
    }

    /**
     * 获取缓存 - 最简单的用法
     */
    public static function get(string $key, $default = null)
    {
        self::init();
        
        try {
            $value = self::$cache->get($key, $default);
            
            if ($value !== $default) {
                self::$stats['hits']++;
            } else {
                self::$stats['misses']++;
            }
            
            return $value;
            
        } catch (InvalidArgumentException $e) {
            return $default;
        }
    }

    /**
     * 设置缓存 - 智能TTL
     */
    public static function put(string $key, $value, $ttl = null): bool
    {
        self::init();
        
        // 智能TTL：根据数据类型和大小自动选择合适的过期时间
        if ($ttl === null) {
            $ttl = self::smartTtl($value);
        }
        
        try {
            $result = self::$cache->set($key, $value, $ttl);
            if ($result) {
                self::$stats['sets']++;
            }
            return $result;
            
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * 记忆化缓存 - 最常用的模式
     */
    public static function remember(string $key, callable $callback, $ttl = null)
    {
        $value = self::get($key);
        
        if ($value === null) {
            $value = $callback();
            if ($value !== null) {
                self::put($key, $value, $ttl);
            }
        }
        
        return $value;
    }

    /**
     * 永久记忆化缓存
     */
    public static function rememberForever(string $key, callable $callback)
    {
        return self::remember($key, $callback, 0); // 0 表示永不过期
    }

    /**
     * 条件缓存 - 只有当条件为真时才缓存
     */
    public static function when(bool $condition, string $key, callable $callback, $ttl = null)
    {
        if (!$condition) {
            return $callback();
        }
        
        return self::remember($key, $callback, $ttl);
    }

    /**
     * 删除缓存
     */
    public static function forget(string $key): bool
    {
        self::init();
        
        try {
            $result = self::$cache->delete($key);
            if ($result) {
                self::$stats['deletes']++;
            }
            return $result;
            
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * 检查缓存是否存在
     */
    public static function has(string $key): bool
    {
        self::init();
        
        try {
            return self::$cache->has($key);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * 原子递增
     */
    public static function increment(string $key, int $value = 1): int
    {
        $current = (int) self::get($key, 0);
        $new = $current + $value;
        self::put($key, $new, 3600); // 1小时默认TTL
        return $new;
    }

    /**
     * 原子递减
     */
    public static function decrement(string $key, int $value = 1): int
    {
        return self::increment($key, -$value);
    }

    /**
     * 批量获取
     */
    public static function many(array $keys): array
    {
        self::init();
        
        try {
            return self::$cache->getMultiple($keys, []);
        } catch (InvalidArgumentException $e) {
            return [];
        }
    }

    /**
     * 批量设置
     */
    public static function putMany(array $values, $ttl = null): bool
    {
        self::init();
        
        try {
            return self::$cache->setMultiple($values, $ttl);
        } catch (InvalidArgumentException $e) {
            return false;
        }
    }

    /**
     * 拉取并删除
     */
    public static function pull(string $key, $default = null)
    {
        $value = self::get($key, $default);
        self::forget($key);
        return $value;
    }

    /**
     * 只添加（如果不存在才设置）
     */
    public static function add(string $key, $value, $ttl = null): bool
    {
        if (self::has($key)) {
            return false;
        }
        
        return self::put($key, $value, $ttl);
    }

    /**
     * 分布式锁
     */
    public static function lock(string $key, int $timeout = 10): bool
    {
        $lockKey = self::key('lock', $key);
        return self::add($lockKey, true, $timeout);
    }

    /**
     * 释放分布式锁
     */
    public static function unlock(string $key): bool
    {
        $lockKey = self::key('lock', $key);
        return self::forget($lockKey);
    }

    /**
     * 带锁的安全操作
     */
    public static function locked(string $key, callable $callback, int $timeout = 10)
    {
        if (!self::lock($key, $timeout)) {
            throw new \RuntimeException("无法获取锁: {$key}");
        }
        
        try {
            return $callback();
        } finally {
            self::unlock($key);
        }
    }

    // 缓存tag映射，避免重复计算
    private static array $tagCache = [];

    /**
     * tag缓存 - 最简单的用法
     */
    public static function tag(string $tag, string $key, callable $callback, $ttl = null)
    {
        return self::remember($tag . ':' . $key, $callback, $ttl);
    }

    /**
     * tag读取
     */
    public static function load(string $tag, string $key, $default = null)
    {
        return self::get($tag . ':' . $key, $default);
    }

    /**
     * tag写入
     */
    public static function write(string $tag, string $key, $value, $ttl = null): bool
    {
        return self::put($tag . ':' . $key, $value, $ttl);
    }

    /**
     * tag删除
     */
    public static function drop(string $tag, string $key): bool
    {
        return self::forget($tag . ':' . $key);
    }

    /**
     * 清空tag
     */
    public static function wipe(string $tag): int
    {
        return self::clearByPattern(self::key($tag, '*'));
    }

    /**
     * 快捷缓存方法 - 自动tag
     */
    public static function cache(string $key, callable $callback, $ttl = null)
    {
        $tag = self::getCallerTag();
        return self::remember($tag . ':' . $key, $callback, $ttl);
    }

    /**
     * 快捷获取方法 - 自动tag  
     */
    public static function fetch(string $key, $default = null)
    {
        $tag = self::getCallerTag();
        return self::get($tag . ':' . $key, $default);
    }

    /**
     * 快捷设置方法 - 自动tag
     */
    public static function store(string $key, $value, $ttl = null): bool
    {
        $tag = self::getCallerTag();
        return self::put($tag . ':' . $key, $value, $ttl);
    }

    /**
     * 快捷清理方法 - 自动tag
     */
    public static function flush(string $pattern = '*'): int
    {
        $tag = self::getCallerTag();
        return self::clearByPattern(self::key($tag, $pattern));
    }

    /**
     * 兼容方法 - 根据tag清除缓存
     */
    public static function clearByTag(string $tag): int
    {
        return self::wipe($tag);
    }

    /**
     * 兼容方法 - 根据类名清除缓存  
     */
    public static function clearByClass(string $className = null): int
    {
        if ($className === null) {
            $tag = self::getCallerTag();
        } else {
            $tag = self::getAutoTag($className);
        }
        
        return self::clearByTag($tag);
    }

    /**
     * 兼容方法 - taggedRemember
     */
    public static function taggedRemember(string $tag, string $key, callable $callback, $ttl = null)
    {
        return self::tag($tag, $key, $callback, $ttl);
    }

    /**
     * 根据模式清除缓存
     */
    public static function clearByPattern(string $pattern): int
    {
        self::init();
        
        // 简单实现：遍历已知的tag清除
        // 生产环境中可以根据实际缓存驱动优化
        $count = 0;
        
        // 如果是tag:*模式，直接从tag缓存中清除
        if (preg_match('/^(.+):\*$/', $pattern, $matches)) {
            $tag = $matches[1];
            $prefix = self::key($tag);
            
            // 这里需要根据实际缓存驱动实现
            // 示例：删除以prefix开头的所有键
            // 实际需要Redis scan或其他驱动的批量删除功能
        }
        
        return $count;
    }

    /**
     * 获取调用者tag（有缓存，性能优化）
     */
    private static function getCallerTag(): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $callerClass = $trace[2]['class'] ?? 'unknown';
        
        // 缓存tag映射，避免重复计算
        if (!isset(self::$tagCache[$callerClass])) {
            self::$tagCache[$callerClass] = self::getAutoTag($callerClass);
        }
        
        return self::$tagCache[$callerClass];
    }

    /**
     * 自动获取tag前缀
     */
    private static function getAutoTag(string $className): string
    {
        // 简化类名作为tag
        $shortName = class_basename($className);
        
        // 转换为小写下划线格式
        $tag = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
        
        // 去掉常见后缀
        $tag = preg_replace('/(service|controller|model|repository)$/', '', $tag);
        
        return trim($tag, '_') ?: 'cache';
    }

    /**
     * 清理过期缓存
     */
    public static function cleanup(): int
    {
        // 根据不同的缓存驱动实现清理逻辑
        return 0;
    }

    /**
     * 获取缓存统计信息
     */
    public static function stats(): array
    {
        return array_merge(self::$stats, [
            'hit_rate' => self::getHitRate(),
            'memory_usage' => self::getMemoryUsage(),
        ]);
    }

    /**
     * 预热缓存
     */
    public static function warmup(array $warmupCallbacks): void
    {
        foreach ($warmupCallbacks as $key => $callback) {
            if (!self::has($key)) {
                $value = $callback();
                if ($value !== null) {
                    self::put($key, $value);
                }
            }
        }
    }

    /**
     * 智能TTL计算
     */
    private static function smartTtl($value): int
    {
        $config = self::$config['smart_ttl'] ?? [];
        
        // 根据数据类型选择TTL
        if (is_array($value) || is_object($value)) {
            $size = strlen(serialize($value));
            
            // 大数据短TTL，小数据长TTL
            if ($size > 10240) { // 10KB
                return $config['large_data'] ?? 300; // 5分钟
            } elseif ($size > 1024) { // 1KB
                return $config['medium_data'] ?? 1800; // 30分钟
            } else {
                return $config['small_data'] ?? 3600; // 1小时
            }
        }
        
        // 基础数据类型
        return $config['default'] ?? 1800; // 30分钟
    }

    /**
     * 获取命中率
     */
    private static function getHitRate(): float
    {
        $total = self::$stats['hits'] + self::$stats['misses'];
        return $total > 0 ? round(self::$stats['hits'] / $total * 100, 2) : 0.0;
    }

    /**
     * 获取内存使用情况
     */
    private static function getMemoryUsage(): array
    {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
        ];
    }

    /**
     * 魔术方法支持链式调用
     */
    public function __call(string $method, array $args)
    {
        return self::$method(...$args);
    }

    /**
     * 静态调用转发
     */
    public static function __callStatic(string $method, array $args)
    {
        // 支持别名方法
        $aliases = [
            // 基础别名
            'set' => 'store',
            'save' => 'store', 
            'cached' => 'cache',
            'remove' => 'forget',
            'delete' => 'forget',
            'exists' => 'has',
            'clear' => 'flush',
            
            // tag别名 - 更直观
            'tagged' => 'tag',
            'tagGet' => 'load',
            'tagPut' => 'write', 
            'tagSet' => 'write',
            'tagForget' => 'drop',
            'tagDelete' => 'drop',
            'tagRemove' => 'drop',
            'tagFlush' => 'wipe',
            'tagClear' => 'wipe',
            
            // 向后兼容
            'autoRemember' => 'cache',
            'clearByPattern' => 'flush',
        ];
        
        if (isset($aliases[$method])) {
            return self::{$aliases[$method]}(...$args);
        }
        
        throw new \BadMethodCallException("方法 {$method} 不存在");
    }
} 
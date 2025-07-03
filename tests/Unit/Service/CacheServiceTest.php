<?php

declare(strict_types=1);

namespace HPlus\Core\Tests\Unit\Service;

use HPlus\Core\Service\CacheService;
use Hyperf\Cache\CacheManager;
use Hyperf\Contract\ConfigInterface;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

/**
 * 重构后的CacheService测试用例
 * 测试新的简化缓存API：tag()、load()、write()、drop()、wipe()
 * 
 * @author 毛自豪 4213509@qq.com 微信bbhkxd
 */
class CacheServiceTest extends TestCase
{
    private CacheService $cacheService;
    private MockInterface $mockCacheManager;
    private MockInterface $mockConfig;
    private MockInterface $mockCache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockCacheManager = Mockery::mock(CacheManager::class);
        $this->mockConfig = Mockery::mock(ConfigInterface::class);
        $this->mockCache = Mockery::mock('cache');
        
        $this->cacheService = new CacheService($this->mockCacheManager, $this->mockConfig);
        
        // Mock默认缓存实例
        $this->mockCacheManager->shouldReceive('getDriver')
            ->andReturn($this->mockCache);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试tag方法 - 缓存命中
     */
    public function testTagCacheHit(): void
    {
        $tag = 'test_tag';
        $key = 'test_key';
        $cachedValue = 'cached_data';
        $ttl = 3600;
        
        // Mock缓存命中
        $this->mockCache->shouldReceive('get')
            ->once()
            ->with("{$tag}:{$key}")
            ->andReturn($cachedValue);
        
        $callback = function() {
            return 'fresh_data';
        };
        
        $result = CacheService::tag($tag, $key, $callback, $ttl);
        
        $this->assertEquals($cachedValue, $result);
    }

    /**
     * 测试tag方法 - 缓存未命中
     */
    public function testTagCacheMiss(): void
    {
        $tag = 'test_tag';
        $key = 'test_key';
        $freshValue = 'fresh_data';
        $ttl = 3600;
        
        // Mock缓存未命中
        $this->mockCache->shouldReceive('get')
            ->once()
            ->with("{$tag}:{$key}")
            ->andReturn(null);
        
        // Mock写入缓存
        $this->mockCache->shouldReceive('set')
            ->once()
            ->with("{$tag}:{$key}", $freshValue, $ttl)
            ->andReturn(true);
        
        $callback = function() use ($freshValue) {
            return $freshValue;
        };
        
        $result = CacheService::tag($tag, $key, $callback, $ttl);
        
        $this->assertEquals($freshValue, $result);
    }

    /**
     * 测试tag方法 - 使用默认TTL
     */
    public function testTagWithDefaultTtl(): void
    {
        $tag = 'test_tag';
        $key = 'test_key';
        $value = 'test_value';
        
        // Mock配置默认TTL
        $this->mockConfig->shouldReceive('get')
            ->with('cache.default.ttl', 3600)
            ->andReturn(1800);
        
        $this->mockCache->shouldReceive('get')
            ->once()
            ->andReturn(null);
        
        $this->mockCache->shouldReceive('set')
            ->once()
            ->with("{$tag}:{$key}", $value, 1800)
            ->andReturn(true);
        
        $callback = function() use ($value) {
            return $value;
        };
        
        $result = CacheService::tag($tag, $key, $callback);
        
        $this->assertEquals($value, $result);
    }

    /**
     * 测试load方法
     */
    public function testLoad(): void
    {
        $tag = 'test_tag';
        $key = 'test_key';
        $cachedValue = 'cached_data';
        
        $this->mockCache->shouldReceive('get')
            ->once()
            ->with("{$tag}:{$key}")
            ->andReturn($cachedValue);
        
        $result = CacheService::load($tag, $key);
        
        $this->assertEquals($cachedValue, $result);
    }

    /**
     * 测试load方法 - 返回默认值
     */
    public function testLoadWithDefault(): void
    {
        $tag = 'test_tag';
        $key = 'test_key';
        $defaultValue = 'default_value';
        
        $this->mockCache->shouldReceive('get')
            ->once()
            ->with("{$tag}:{$key}")
            ->andReturn(null);
        
        $result = CacheService::load($tag, $key, $defaultValue);
        
        $this->assertEquals($defaultValue, $result);
    }

    /**
     * 测试write方法
     */
    public function testWrite(): void
    {
        $tag = 'test_tag';
        $key = 'test_key';
        $value = 'test_value';
        $ttl = 3600;
        
        $this->mockCache->shouldReceive('set')
            ->once()
            ->with("{$tag}:{$key}", $value, $ttl)
            ->andReturn(true);
        
        $result = CacheService::write($tag, $key, $value, $ttl);
        
        $this->assertTrue($result);
    }

    /**
     * 测试write方法 - 使用默认TTL
     */
    public function testWriteWithDefaultTtl(): void
    {
        $tag = 'test_tag';
        $key = 'test_key';
        $value = 'test_value';
        
        $this->mockConfig->shouldReceive('get')
            ->with('cache.default.ttl', 3600)
            ->andReturn(1800);
        
        $this->mockCache->shouldReceive('set')
            ->once()
            ->with("{$tag}:{$key}", $value, 1800)
            ->andReturn(true);
        
        $result = CacheService::write($tag, $key, $value);
        
        $this->assertTrue($result);
    }

    /**
     * 测试drop方法
     */
    public function testDrop(): void
    {
        $tag = 'test_tag';
        $key = 'test_key';
        
        $this->mockCache->shouldReceive('delete')
            ->once()
            ->with("{$tag}:{$key}")
            ->andReturn(true);
        
        $result = CacheService::drop($tag, $key);
        
        $this->assertTrue($result);
    }

    /**
     * 测试wipe方法 - 清空标签下所有缓存
     */
    public function testWipe(): void
    {
        $tag = 'test_tag';
        
        // Mock获取标签下的所有键
        $tagKeys = [
            'test_tag:key1',
            'test_tag:key2', 
            'test_tag:key3'
        ];
        
        // 由于不同缓存驱动实现不同，这里模拟Redis的情况
        $this->mockCache->shouldReceive('keys')
            ->once()
            ->with("{$tag}:*")
            ->andReturn($tagKeys);
        
        // Mock删除所有相关键
        foreach ($tagKeys as $fullKey) {
            $this->mockCache->shouldReceive('delete')
                ->once()
                ->with($fullKey)
                ->andReturn(true);
        }
        
        $result = CacheService::wipe($tag);
        
        $this->assertTrue($result);
    }

    /**
     * 测试性能 - 批量操作
     */
    public function testPerformanceBatchOperations(): void
    {
        $tag = 'perf_test';
        $iterations = 100;
        
        // Mock批量缓存操作
        for ($i = 0; $i < $iterations; $i++) {
            $key = "key_{$i}";
            $value = "value_{$i}";
            
            $this->mockCache->shouldReceive('get')
                ->with("{$tag}:{$key}")
                ->andReturn(null);
            
            $this->mockCache->shouldReceive('set')
                ->with("{$tag}:{$key}", $value, Mockery::any())
                ->andReturn(true);
        }
        
        $startTime = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $key = "key_{$i}";
            $value = "value_{$i}";
            
            CacheService::tag($tag, $key, function() use ($value) {
                return $value;
            });
        }
        
        $totalTime = microtime(true) - $startTime;
        
        // 性能断言 - 100次操作应该在合理时间内完成
        $this->assertLessThan(1.0, $totalTime, "100次缓存操作应该在1秒内完成，实际用时: {$totalTime}秒");
    }

    /**
     * 测试错误处理 - 缓存驱动异常
     */
    public function testCacheDriverException(): void
    {
        $tag = 'test_tag';
        $key = 'test_key';
        
        // Mock缓存驱动抛出异常
        $this->mockCache->shouldReceive('get')
            ->once()
            ->andThrow(new \Exception('Cache driver error'));
        
        $callback = function() {
            return 'fallback_data';
        };
        
        // 应该返回回调函数的结果（缓存失败时的降级处理）
        $result = CacheService::tag($tag, $key, $callback);
        
        $this->assertEquals('fallback_data', $result);
    }

    /**
     * 测试键名生成规则
     */
    public function testKeyGeneration(): void
    {
        $tag = 'user';
        $key = 'profile:123';
        $expectedFullKey = 'user:profile:123';
        
        $this->mockCache->shouldReceive('get')
            ->once()
            ->with($expectedFullKey)
            ->andReturn('test_data');
        
        $result = CacheService::load($tag, $key);
        
        $this->assertEquals('test_data', $result);
    }

    /**
     * 测试特殊字符处理
     */
    public function testSpecialCharacterHandling(): void
    {
        $tag = 'test-tag_123';
        $key = 'key:with:colons';
        $value = 'test_value';
        
        $expectedFullKey = 'test-tag_123:key:with:colons';
        
        $this->mockCache->shouldReceive('set')
            ->once()
            ->with($expectedFullKey, $value, Mockery::any())
            ->andReturn(true);
        
        $result = CacheService::write($tag, $key, $value);
        
        $this->assertTrue($result);
    }

    /**
     * 测试TTL边界值
     */
    public function testTtlBoundaryValues(): void
    {
        $tag = 'test_tag';
        $key = 'test_key';
        $value = 'test_value';
        
        // 测试最小TTL（1秒）
        $this->mockCache->shouldReceive('set')
            ->once()
            ->with("{$tag}:{$key}", $value, 1)
            ->andReturn(true);
        
        $result = CacheService::write($tag, $key, $value, 1);
        $this->assertTrue($result);
        
        // 测试最大TTL（30天）
        $maxTtl = 30 * 24 * 3600; // 30天
        $this->mockCache->shouldReceive('set')
            ->once()
            ->with("{$tag}:{$key}", $value, $maxTtl)
            ->andReturn(true);
        
        $result = CacheService::write($tag, $key, $value, $maxTtl);
        $this->assertTrue($result);
    }
}

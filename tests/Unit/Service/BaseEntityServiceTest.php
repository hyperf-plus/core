<?php

declare(strict_types=1);

namespace HPlus\Core\Tests\Unit\Service;

use HPlus\Core\Service\BaseEntityService;
use HPlus\Core\Service\CacheService;
use HPlus\Core\Exception\BusinessException;
use Hyperf\Database\Model\Model;
use PHPUnit\Framework\TestCase;
use Mockery;
use Mockery\MockInterface;

/**
 * BaseEntityService 重构后测试用例
 * 测试新的缓存系统、验证机制、时间过滤等核心功能
 * 
 * @author 毛自豪 4213509@qq.com 微信bbhkxd
 */
class BaseEntityServiceTest extends TestCase
{
    private BaseEntityService $service;
    private MockInterface $mockCacheService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 创建测试用的具体服务类
        $this->service = new class extends BaseEntityService {
            protected const TAG = 'test';
            
            protected function getModelClass(): string
            {
                return TestModel::class;
            }
        };
        
        // Mock CacheService
        $this->mockCacheService = Mockery::mock(CacheService::class);
        $this->service->cacheService = $this->mockCacheService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * 测试获取缓存标签
     */
    public function testGetCacheTag(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getCacheTag');
        $method->setAccessible(true);
        
        $tag = $method->invoke($this->service);
        $this->assertEquals('test', $tag);
    }

    /**
     * 测试时间范围过滤 - 前端组件标准格式
     */
    public function testApplyTimeRangeFilter(): void
    {
        $query = Mockery::mock('stdClass');
        $filters = [
            'created_at' => ['2024-01-01 00:00:00', '2024-01-31 23:59:59']
        ];
        
        // 期望查询条件
        $query->shouldReceive('where')
            ->twice()
            ->withArgs(['created_at', '>=', '2024-01-01 00:00:00'])
            ->andReturnSelf();
        
        $query->shouldReceive('where')
            ->twice()
            ->withArgs(['created_at', '<=', '2024-01-31 23:59:59'])
            ->andReturnSelf();
        
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('applyTimeRangeFilter');
        $method->setAccessible(true);
        
        $method->invoke($this->service, $query, $filters);
        
        $this->assertTrue(true); // 断言测试通过
    }
}

/**
 * 测试用的模型类
 */
class TestModel extends Model
{
    protected $table = 'test_models';
    protected $fillable = ['name'];
    
    public function getKeyName()
    {
        return 'id';
    }
}

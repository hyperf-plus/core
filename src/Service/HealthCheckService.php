<?php

declare(strict_types=1);

namespace HPlus\Core\Service;

use Hyperf\DbConnection\Db;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Redis\Redis;
use Psr\Log\LoggerInterface;

/**
 * 系统健康检查服务
 * 
 * @author 毛自豪 4213509@qq.com 微信bbhkxd
 */
class HealthCheckService
{
    #[Inject]
    protected Redis $redis;
    
    #[Inject]
    protected LoggerInterface $logger;
    
    /**
     * 执行完整健康检查
     */
    public function checkHealth(): array
    {
        $startTime = microtime(true);
        $checks = [];
        $overallStatus = 'healthy';
        
        // 数据库连接检查
        $dbCheck = $this->checkDatabase();
        $checks['database'] = $dbCheck;
        if ($dbCheck['status'] !== 'healthy') {
            $overallStatus = 'unhealthy';
        }
        
        // Redis连接检查
        $redisCheck = $this->checkRedis();
        $checks['redis'] = $redisCheck;
        if ($redisCheck['status'] !== 'healthy') {
            $overallStatus = 'unhealthy';
        }
        
        // 磁盘空间检查
        $diskCheck = $this->checkDiskSpace();
        $checks['disk'] = $diskCheck;
        if ($diskCheck['status'] !== 'healthy') {
            $overallStatus = $overallStatus === 'healthy' ? 'degraded' : 'unhealthy';
        }
        
        // 内存使用检查
        $memoryCheck = $this->checkMemoryUsage();
        $checks['memory'] = $memoryCheck;
        if ($memoryCheck['status'] !== 'healthy') {
            $overallStatus = $overallStatus === 'healthy' ? 'degraded' : 'unhealthy';
        }
        
        // 服务依赖检查
        $servicesCheck = $this->checkServices();
        $checks['services'] = $servicesCheck;
        if ($servicesCheck['status'] !== 'healthy') {
            $overallStatus = $overallStatus === 'healthy' ? 'degraded' : 'unhealthy';
        }
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        return [
            'status' => $overallStatus,
            'timestamp' => time(),
            'duration_ms' => $duration,
            'checks' => $checks,
            'version' => $this->getVersion(),
            'uptime' => $this->getUptime(),
        ];
    }
    
    /**
     * 快速健康检查（只检查关键组件）
     */
    public function quickHealthCheck(): array
    {
        $startTime = microtime(true);
        
        $dbHealthy = $this->isDatabaseHealthy();
        $redisHealthy = $this->isRedisHealthy();
        
        $status = ($dbHealthy && $redisHealthy) ? 'healthy' : 'unhealthy';
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        return [
            'status' => $status,
            'timestamp' => time(),
            'duration_ms' => $duration,
            'database' => $dbHealthy,
            'redis' => $redisHealthy,
        ];
    }
    
    /**
     * 检查数据库连接
     */
    protected function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            
            // 执行简单查询测试连接
            $result = Db::select('SELECT 1 as test');
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            
            if (!empty($result) && $result[0]->test == 1) {
                return [
                    'status' => 'healthy',
                    'response_time_ms' => $responseTime,
                    'message' => '数据库连接正常',
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'response_time_ms' => $responseTime,
                    'message' => '数据库查询结果异常',
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->error('数据库健康检查失败', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'unhealthy',
                'response_time_ms' => 0,
                'message' => '数据库连接失败: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * 检查Redis连接
     */
    protected function checkRedis(): array
    {
        try {
            $startTime = microtime(true);
            
            // 执行Redis ping测试
            $result = $this->redis->ping();
            
            $endTime = microtime(true);
            $responseTime = round(($endTime - $startTime) * 1000, 2);
            
            if ($result === 'PONG') {
                return [
                    'status' => 'healthy',
                    'response_time_ms' => $responseTime,
                    'message' => 'Redis连接正常',
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'response_time_ms' => $responseTime,
                    'message' => 'Redis ping响应异常',
                ];
            }
        } catch (\Throwable $e) {
            $this->logger->error('Redis健康检查失败', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'unhealthy',
                'response_time_ms' => 0,
                'message' => 'Redis连接失败: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * 检查磁盘空间
     */
    protected function checkDiskSpace(): array
    {
        try {
            $path = BASE_PATH;
            $totalBytes = disk_total_space($path);
            $freeBytes = disk_free_space($path);
            $usedBytes = $totalBytes - $freeBytes;
            $usagePercent = round(($usedBytes / $totalBytes) * 100, 2);
            
            // 磁盘使用率超过90%认为不健康，超过80%认为降级
            if ($usagePercent >= 90) {
                $status = 'unhealthy';
                $message = '磁盘空间严重不足';
            } elseif ($usagePercent >= 80) {
                $status = 'degraded';
                $message = '磁盘空间不足';
            } else {
                $status = 'healthy';
                $message = '磁盘空间充足';
            }
            
            return [
                'status' => $status,
                'usage_percent' => $usagePercent,
                'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
                'free_gb' => round($freeBytes / 1024 / 1024 / 1024, 2),
                'used_gb' => round($usedBytes / 1024 / 1024 / 1024, 2),
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('磁盘空间检查失败', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'unknown',
                'message' => '无法检查磁盘空间: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * 检查内存使用情况
     */
    protected function checkMemoryUsage(): array
    {
        try {
            $memoryLimit = ini_get('memory_limit');
            $memoryUsage = memory_get_usage(true);
            $peakMemoryUsage = memory_get_peak_usage(true);
            
            // 转换内存限制为字节
            $limitBytes = $this->convertToBytes($memoryLimit);
            
            $usagePercent = round(($memoryUsage / $limitBytes) * 100, 2);
            $peakPercent = round(($peakMemoryUsage / $limitBytes) * 100, 2);
            
            // 内存使用率超过90%认为不健康，超过80%认为降级
            if ($usagePercent >= 90) {
                $status = 'unhealthy';
                $message = '内存使用率过高';
            } elseif ($usagePercent >= 80) {
                $status = 'degraded';
                $message = '内存使用率偏高';
            } else {
                $status = 'healthy';
                $message = '内存使用正常';
            }
            
            return [
                'status' => $status,
                'usage_percent' => $usagePercent,
                'peak_percent' => $peakPercent,
                'limit_mb' => round($limitBytes / 1024 / 1024, 2),
                'current_mb' => round($memoryUsage / 1024 / 1024, 2),
                'peak_mb' => round($peakMemoryUsage / 1024 / 1024, 2),
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('内存使用检查失败', ['error' => $e->getMessage()]);
            
            return [
                'status' => 'unknown',
                'message' => '无法检查内存使用情况: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * 检查关键服务状态
     */
    protected function checkServices(): array
    {
        $services = [];
        $overallStatus = 'healthy';
        
        // 检查队列服务
        $queueStatus = $this->checkQueueService();
        $services['queue'] = $queueStatus;
        if ($queueStatus['status'] !== 'healthy') {
            $overallStatus = 'degraded';
        }
        
        // 检查缓存服务
        $cacheStatus = $this->checkCacheService();
        $services['cache'] = $cacheStatus;
        if ($cacheStatus['status'] !== 'healthy') {
            $overallStatus = 'degraded';
        }
        
        return [
            'status' => $overallStatus,
            'services' => $services,
            'message' => $overallStatus === 'healthy' ? '所有服务正常' : '部分服务异常',
        ];
    }
    
    /**
     * 检查队列服务
     */
    protected function checkQueueService(): array
    {
        try {
            // 检查队列连接
            $queueLength = $this->redis->lLen('default_queue');
            
            return [
                'status' => 'healthy',
                'queue_length' => $queueLength,
                'message' => '队列服务正常',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => '队列服务异常: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * 检查缓存服务
     */
    protected function checkCacheService(): array
    {
        try {
            // 测试缓存读写
            $testKey = 'health_check_' . time();
            $testValue = 'test_value';
            
            $this->redis->setex($testKey, 60, $testValue);
            $retrievedValue = $this->redis->get($testKey);
            $this->redis->del($testKey);
            
            if ($retrievedValue === $testValue) {
                return [
                    'status' => 'healthy',
                    'message' => '缓存服务正常',
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => '缓存读写测试失败',
                ];
            }
        } catch (\Throwable $e) {
            return [
                'status' => 'unhealthy',
                'message' => '缓存服务异常: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * 快速检查数据库是否健康
     */
    protected function isDatabaseHealthy(): bool
    {
        try {
            $result = Db::select('SELECT 1');
            return !empty($result);
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /**
     * 快速检查Redis是否健康
     */
    protected function isRedisHealthy(): bool
    {
        try {
            return $this->redis->ping() === 'PONG';
        } catch (\Throwable $e) {
            return false;
        }
    }
    
    /**
     * 获取应用版本
     */
    protected function getVersion(): string
    {
        // 从composer.json或配置文件获取版本信息
        return '1.0.0';
    }
    
    /**
     * 获取系统运行时间
     */
    protected function getUptime(): array
    {
        // 获取系统启动时间
        $uptime = time() - $_SERVER['REQUEST_TIME'];
        
        return [
            'seconds' => $uptime,
            'formatted' => $this->formatUptime($uptime),
        ];
    }
    
    /**
     * 格式化运行时间
     */
    protected function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        return sprintf('%d天 %d小时 %d分钟 %d秒', $days, $hours, $minutes, $secs);
    }
    
    /**
     * 转换内存大小字符串为字节数
     */
    protected function convertToBytes(string $size): int
    {
        $size = trim($size);
        $last = strtolower($size[strlen($size) - 1]);
        $size = (int) $size;
        
        switch ($last) {
            case 'g':
                $size *= 1024;
            case 'm':
                $size *= 1024;
            case 'k':
                $size *= 1024;
        }
        
        return $size;
    }
}

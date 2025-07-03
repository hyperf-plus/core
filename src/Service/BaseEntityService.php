<?php

declare(strict_types=1);

namespace HPlus\Core\Service;

use HPlus\Core\Exception\BusinessException;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Database\Model\Model;

/**
 * 通用实体服务基类
 * 封装常见的CRUD操作，减少重复代码
 * 全平台通用，支持约定大于配置
 * 
 * @author 毛自豪 4213509@qq.com 微信bbhkxd
 */
abstract class BaseEntityService extends AbstractService
{
    #[Inject]
    protected CacheService $cacheService;

    /**
     * 获取模型类名（子类必须实现）
     */
    abstract protected function getModelClass(): string;

    /**
     * 获取实体名称（从模型类名自动推断）
     */
    protected function getEntityName(): string
    {
        $modelClass = $this->getModelClass();
        $className = class_basename($modelClass);
        
        // 通用的实体名称映射，子类可以重写扩展
        $nameMap = $this->getEntityNameMap();
        
        return $nameMap[$className] ?? $className;
    }

    /**
     * 获取实体名称映射表（子类可以重写扩展）
     */
    protected function getEntityNameMap(): array
    {
        return [
            // 组织架构
            'Corp' => '企业',
            'Department' => '部门', 
            'Employee' => '员工',
            
            // 权限系统
            'Role' => '角色',
            'Permission' => '权限',
            'UserRole' => '用户角色',
            
            // 消息系统
            'Message' => '消息',
            'Notification' => '通知',
            
            // 其他通用
            'User' => '用户',
            'Log' => '日志',
            'Config' => '配置',
        ];
    }

    /**
     * 获取实体类型（用于缓存key）
     */
    protected function getEntityType(): string
    {
        $modelClass = $this->getModelClass();
        return strtolower(class_basename($modelClass));
    }





    /**
     * 创建实体
     */
    public function create(array $data): Model
    {
        // 验证并允许修改原始数据
        $this->validateBeforeCreate($data);
        $entityData = $this->prepareDataForCreate($data);
        
        $model = $this->getModelClass()::create($entityData);
        
        $this->afterCreate($model, $data);
        $this->clearCache($model);
        
        return $model;
    }

    /**
     * 更新实体
     */
    public function update(int $id, array $data): Model
    {
        $model = $this->findOrFail($id);
        $this->validateBeforeUpdate($model, $data);
        
        $updateData = $this->prepareDataForUpdate($model, $data);
        
        $model->update($updateData);
        
        $this->afterUpdate($model, $data);
        $this->clearCache($model);
        
        return $model->fresh();
    }

    /**
     * 删除实体
     */
    public function delete(int $id): bool
    {
        $model = $this->findOrFail($id);
        $this->validateBeforeDelete($model);
        
        $model->delete();
        
        $this->afterDelete($model);
        $this->clearCache($model);
        
        return true;
    }

    /**
     * 根据ID查找
     */
    public function findById(int $id): ?Model
    {
        // 使用最简单的tag缓存
        $tag = $this->getCacheTag();
        return CacheService::tag($tag, "find_{$id}", function () use ($id) {
            return $this->getModelClass()::find($id);
        }, $this->getCacheTtl());
    }

    /**
     * 根据ID查找（找不到抛出异常）
     */
    public function findOrFail(int $id): Model
    {
        $model = $this->findById($id);
        
        if (!$model) {
            throw new BusinessException($this->getEntityName() . '不存在');
        }
        
        return $model;
    }

    /**
     * 获取列表
     */
    public function getList(array $filters = []): array
    {
        $query = $this->getModelClass()::query();
        
        // 应用通用过滤器
        $this->applyCommonFilters($query, $filters);
        
        // 应用自定义过滤器
        $this->applyCustomFilters($query, $filters);
        
        $limit = $filters['limit'] ?? 20;
        $offset = $filters['offset'] ?? 0;
        
        return $query->offset($offset)
            ->limit($limit)
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 获取总数
     */
    public function getCount(array $filters = []): int
    {
        $query = $this->getModelClass()::query();
        
        $this->applyCommonFilters($query, $filters);
        $this->applyCustomFilters($query, $filters);
        
        return $query->count();
    }

    /**
     * 批量更新状态
     */
    public function batchUpdateStatus(array $ids, int $status): int
    {
        $count = $this->getModelClass()::query()
            ->whereIn($this->getPrimaryKey(), $ids)
            ->update(['status' => $status]);
            
        // 清除批量缓存
        foreach ($ids as $id) {
            $this->clearCacheById($id);
        }
        
        return $count;
    }

    // ============ 钩子方法，子类可以重写处理业务逻辑 ============

    /**
     * 创建前验证（子类重写处理业务验证）
     * 注意：$data是引用传递，可以直接修改原始数据
     */
    protected function validateBeforeCreate(array &$data): void
    {
        // 子类可以重写，可以修改$data数据
    }

    /**
     * 更新前验证（子类重写处理业务验证）
     * 注意：$data是引用传递，可以直接修改原始数据
     */
    protected function validateBeforeUpdate(Model $model, array &$data): void
    {
        // 子类可以重写，可以修改$data数据
    }

    /**
     * 删除前验证（子类重写处理依赖检查）
     */
    protected function validateBeforeDelete(Model $model): void
    {
        // 子类可以重写
    }

    /**
     * 准备创建数据（子类重写处理数据预处理）
     */
    protected function prepareDataForCreate(array $data): array
    {
        return $data;
    }

    /**
     * 准备更新数据（子类重写处理数据预处理）
     */
    protected function prepareDataForUpdate(Model $model, array $data): array
    {
        return $data;
    }

    /**
     * 创建后处理（子类重写处理后续业务）
     */
    protected function afterCreate(Model $model, array $originalData): void
    {
        // 子类可以重写
    }

    /**
     * 更新后处理（子类重写处理后续业务）
     */
    protected function afterUpdate(Model $model, array $originalData): void
    {
        // 子类可以重写
    }

    /**
     * 删除后处理（子类重写处理清理工作）
     */
    protected function afterDelete(Model $model): void
    {
        // 子类可以重写
    }

    // ============ 辅助方法 ============

    /**
     * 应用通用过滤器
     */
    protected function applyCommonFilters($query, array $filters): void
    {
        // 状态过滤
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // 关键词搜索
        if (!empty($filters['keyword'])) {
            $this->applyKeywordSearch($query, $filters['keyword']);
        }
        
        // 时间范围过滤 - 支持前端组件标准格式
        $this->applyTimeRangeFilter($query, $filters);
        
        // ID范围过滤
        if (isset($filters['ids']) && is_array($filters['ids'])) {
            $query->whereIn($this->getPrimaryKey(), $filters['ids']);
        }
    }

    /**
     * 应用时间范围过滤 - 前端组件标准格式
     */
    protected function applyTimeRangeFilter($query, array $filters): void
    {
        // 检查各种可能的时间字段
        $timeFields = ['created_at', 'updated_at', 'time', 'date'];
        
        foreach ($timeFields as $field) {
            if (isset($filters[$field]) && is_array($filters[$field])) {
                $this->applyTimeRange($query, $field, $filters[$field]);
            }
        }
        
        // 兼容旧格式
        if (isset($filters['created_from']) || isset($filters['created_to'])) {
            $this->applyLegacyTimeFilter($query, $filters);
        }
    }

    /**
     * 应用时间范围 - [开始时间, 结束时间]
     */
    protected function applyTimeRange($query, string $field, array $timeRange): void
    {
        $startTime = $timeRange[0] ?? null;
        $endTime = $timeRange[1] ?? null;
        
        // 如果开始时间为空，默认为30天前
        if (empty($startTime) && !empty($endTime)) {
            $startTime = date('Y-m-d H:i:s', strtotime('-30 days'));
        }
        
        // 如果结束时间为空，默认为当前时间
        if (!empty($startTime) && empty($endTime)) {
            $endTime = date('Y-m-d H:i:s');
        }
        
        // 应用过滤条件
        if (!empty($startTime)) {
            $query->where($field, '>=', $startTime);
        }
        
        if (!empty($endTime)) {
            $query->where($field, '<=', $endTime);
        }
    }

    /**
     * 兼容旧的时间过滤格式
     */
    protected function applyLegacyTimeFilter($query, array $filters): void
    {
        if (isset($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        
        if (isset($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to']);
        }
    }

    /**
     * 应用关键词搜索（子类应该重写这个方法）
     */
    protected function applyKeywordSearch($query, string $keyword): void
    {
        // 子类应该重写这个方法实现具体的搜索逻辑
    }

    /**
     * 应用自定义过滤器（子类可以重写）
     */
    protected function applyCustomFilters($query, array $filters): void
    {
        // 子类可以重写
    }

    /**
     * 获取主键字段名（从模型自动获取）
     */
    protected function getPrimaryKey(): string
    {
        $model = new ($this->getModelClass());
        return $model->getKeyName();
    }

    /**
     * 缓存标签常量 - 子类可以重写
     */
    protected const TAG = '';

    /**
     * 获取缓存标签 - 优先使用常量
     */
    protected function getCacheTag(): string
    {
        // 优先使用子类定义的常量
        if (!empty(static::TAG)) {
            return static::TAG;
        }
        
        // fallback: 自动生成
        static $autoTag = null;
        if ($autoTag === null) {
            $className = static::class;
            $shortName = class_basename($className);
            $autoTag = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName));
            $autoTag = preg_replace('/service$/', '', $autoTag);
            $autoTag = trim($autoTag, '_') ?: 'cache';
        }
        
        return $autoTag;
    }

    /**
     * 获取缓存TTL
     */
    protected function getCacheTtl(): int
    {
        return 1800; // 30分钟
    }

    /**
     * 清除实体缓存 - 超简洁
     */
    protected function clearCache(Model $model): void
    {
        // 一行清空，简单粗暴
        CacheService::wipe($this->getCacheTag());
        
        // 清除额外的缓存
        $id = $model->getAttribute($this->getPrimaryKey());
        $this->clearAdditionalCache($id);
    }

    /**
     * 根据ID清除缓存（保留兼容性）
     */
    protected function clearCacheById(int $id): void
    {
        // 一行清空
        CacheService::wipe($this->getCacheTag());
        $this->clearAdditionalCache($id);
    }

    /**
     * 清除额外缓存（子类可以重写）
     */
    protected function clearAdditionalCache(int $id): void
    {
        // 子类可以重写，比如清除关联数据的缓存
    }
} 
<?php

declare(strict_types=1);

namespace HPlus\Core\Model;

use Hyperf\Context\Context;
use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\SoftDeletes;

/**
 * 企业级基础模型
 * 提供企业数据隔离和通用功能
 * 
 * @author 毛自豪 4213509@qq.com 微信bbhkxd
 */
abstract class CorpBaseModel extends Model
{
    use SoftDeletes;

    /**
     * 启用时间戳
     */
    public bool $timestamps = true;

    /**
     * 软删除字段
     */
    protected array $dates = ['deleted_at'];

    /**
     * 隐藏字段
     */
    protected array $hidden = ['deleted_at'];

    /**
     * 类型转换
     */
    protected array $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * 模型启动
     */
    protected static function booted(): void
    {
        // 自动填充企业ID
        static::creating(function ($model) {
            if (!isset($model->corp_id) && $model->fillable && in_array('corp_id', $model->fillable)) {
                $corpId = Context::get('corp_id', 0);
                if ($corpId > 0) {
                    $model->corp_id = $corpId;
                }
            }
        });

        // 自动添加企业ID查询条件
        static::addGlobalScope('corp', function ($builder) {
            $corpId = Context::get('corp_id');
            if ($corpId > 0) {
                $builder->where('corp_id', $corpId);
            }
        });
    }

    /**
     * 获取当前企业ID
     */
    public function getCorpId(): int
    {
        return Context::get('corp_id', 0);
    }

    /**
     * 设置企业ID
     */
    public function setCorpId(int $corpId): self
    {
        if (in_array('corp_id', $this->fillable)) {
            $this->corp_id = $corpId;
        }
        return $this;
    }

    /**
     * 忽略企业隔离查询
     */
    public function newQueryWithoutScope($scope = null): \Hyperf\Database\Model\Builder
    {
        if ($scope === null) {
            return $this->newQueryWithoutScopes();
        }
        
        return parent::newQueryWithoutScope($scope);
    }

    /**
     * 全局查询（忽略企业隔离）
     */
    public static function withoutCorp(): \Hyperf\Database\Model\Builder
    {
        return (new static)->newQueryWithoutScope('corp');
    }

    /**
     * 指定企业查询
     */
    public static function forCorp(int $corpId): \Hyperf\Database\Model\Builder
    {
        return (new static)->newQueryWithoutScope('corp')->where('corp_id', $corpId);
    }

    /**
     * 检查是否属于当前企业
     */
    public function belongsToCurrentCorp(): bool
    {
        $currentCorpId = Context::get('corp_id', 0);
        return $this->corp_id === $currentCorpId;
    }

    /**
     * 获取创建人信息
     */
    public function getCreatedByAttribute($value): ?array
    {
        if (!$value) {
            return null;
        }
        
        // 这里可以扩展为获取完整的用户信息
        return [
            'id' => $value,
            'name' => '未知用户', // 实际项目中应该查询用户表
        ];
    }

    /**
     * 获取更新人信息
     */
    public function getUpdatedByAttribute($value): ?array
    {
        if (!$value) {
            return null;
        }
        
        return [
            'id' => $value,
            'name' => '未知用户',
        ];
    }

    /**
     * 格式化创建时间
     */
    public function getCreatedAtFormattedAttribute(): string
    {
        return $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : '';
    }

    /**
     * 格式化更新时间
     */
    public function getUpdatedAtFormattedAttribute(): string
    {
        return $this->updated_at ? $this->updated_at->format('Y-m-d H:i:s') : '';
    }

    /**
     * 获取模型摘要信息
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'corp_id' => $this->corp_id ?? null,
            'created_at' => $this->created_at_formatted,
            'updated_at' => $this->updated_at_formatted,
        ];
    }

    /**
     * 批量更新时自动设置updated_by
     */
    public function save(array $options = []): bool
    {
        if (in_array('updated_by', $this->fillable)) {
            $userId = Context::get('user_id');
            if ($userId && $this->isDirty() && !$this->isDirty('updated_by')) {
                $this->updated_by = $userId;
            }
        }

        if (!$this->exists && in_array('created_by', $this->fillable)) {
            $userId = Context::get('user_id');
            if ($userId && !$this->created_by) {
                $this->created_by = $userId;
            }
        }

        return parent::save($options);
    }
}

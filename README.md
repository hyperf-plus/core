# HPlus Core 核心基础包

## 简介

HPlus Core 是 HPlus 微服务架构的核心基础包，提供公共的基础组件和抽象类。

## 功能特性

- **异常处理**: 统一的业务异常处理机制
- **服务基类**: 简单的服务抽象基类

## 安装

```bash
composer require hyperf-plus/core
```

## 使用方法

### Model基类

```php
<?php

namespace YourApp\Model;

use HPlus\Core\Model\Model;

class YourModel extends Model
{
    protected string $table = 'your_table';
    
    protected array $fillable = ['field1', 'field2'];
}
```

### 异常处理

```php
<?php

use HPlus\Core\Exception\BusinessException;

// 抛出业务异常
throw new BusinessException('业务错误信息');
```

### 服务基类

```php
<?php

namespace YourApp\Service;

use HPlus\Core\Service\AbstractService;

class YourService extends AbstractService
{
    public function doSomething()
    {
        // 业务逻辑
    }
}
```

## 依赖关系

- 继承现有的 `YC\Open\Model\Model`
- 继承现有的 `YC\Core\Exception\ApiException`
- 完全兼容现有的异常处理机制

## 许可证

MIT 
<?php

declare(strict_types=1);

namespace HPlus\Core\Exception;

use Hyperf\Server\Exception\ServerException;

/**
 * 业务异常类
 * 用于处理业务逻辑中的错误
 */
class BusinessException extends ServerException
{
    /**
     * 错误代码
     */
    protected string $errorCode;

    /**
     * 错误详情
     */
    protected array $errorDetails;

    public function __construct(
        string $message = '',
        int $code = 400,
        string $errorCode = 'BUSINESS_ERROR',
        array $errorDetails = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->errorDetails = $errorDetails;
    }

    /**
     * 获取错误代码
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * 获取错误详情
     */
    public function getErrorDetails(): array
    {
        return $this->errorDetails;
    }

    /**
     * 参数验证错误
     */
    public static function validation(string $message, array $errors = []): static
    {
        return new static($message, 422, 'VALIDATION_ERROR', $errors);
    }

    /**
     * 资源不存在错误
     */
    public static function notFound(string $resource = '资源'): static
    {
        return new static("{$resource}不存在", 404, 'NOT_FOUND');
    }

    /**
     * 权限不足错误
     */
    public static function forbidden(string $message = '权限不足'): static
    {
        return new static($message, 403, 'FORBIDDEN');
    }

    /**
     * 未授权错误
     */
    public static function unauthorized(string $message = '未授权访问'): static
    {
        return new static($message, 401, 'UNAUTHORIZED');
    }

    /**
     * 业务逻辑错误
     */
    public static function logic(string $message): static
    {
        return new static($message, 400, 'BUSINESS_LOGIC_ERROR');
    }

    /**
     * 系统繁忙错误
     */
    public static function busy(string $message = '系统繁忙，请稍后重试'): static
    {
        return new static($message, 503, 'SYSTEM_BUSY');
    }

    /**
     * 请求频率限制
     */
    public static function rateLimit(string $message = '请求过于频繁'): static
    {
        return new static($message, 429, 'RATE_LIMIT');
    }

    /**
     * 转换为数组格式
     */
    public function toArray(): array
    {
        $result = [
            'error' => $this->errorCode,
            'error_description' => $this->getMessage(),
        ];

        if (!empty($this->errorDetails)) {
            $result['error_details'] = $this->errorDetails;
        }

        return $result;
    }
} 
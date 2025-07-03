<?php

declare(strict_types=1);

namespace HPlus\Core\Controller;

use HPlus\Core\Service\ApiResponse;
use HPlus\Core\Exception\BusinessException;
use Hyperf\Context\Context;
use Hyperf\Di\Annotation\Inject;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\Validation\Contract\ValidatorFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * 统一控制器基类
 * 提供通用的API响应功能、验证和依赖注入
 */
abstract class AbstractController
{
    #[Inject]
    protected RequestInterface $request;

    #[Inject]
    protected ResponseInterface $response;

    #[Inject]
    protected ValidatorFactoryInterface $validatorFactory;

    #[Inject]
    protected ApiResponse $apiResponse;

    /**
     * 成功响应
     */
    protected function success(mixed $data = null, string $message = '操作成功', int $code = 200): PsrResponseInterface
    {
        return $this->apiResponse->success($data, $message, $code);
    }

    /**
     * 错误响应
     */
    protected function error(string $message, int $code = 500, mixed $data = null): PsrResponseInterface
    {
        return $this->apiResponse->error($message, $code, $data);
    }

    /**
     * 分页响应
     */
    protected function paginate(mixed $data, string $message = '获取成功'): PsrResponseInterface
    {
        return $this->apiResponse->paginate($data, $message);
    }

    /**
     * 验证失败响应
     */
    protected function validationError(array $errors, string $message = '参数验证失败'): PsrResponseInterface
    {
        return $this->apiResponse->validationError($errors, $message);
    }

    /**
     * 获取请求体数据
     */
    protected function getRequestData(): array
    {
        return $this->request->getParsedBody() ?? [];
    }

    /**
     * 获取查询参数
     */
    protected function getQueryParams(): array
    {
        return $this->request->getQueryParams();
    }

    /**
     * 获取单个查询参数
     */
    protected function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->request->query($key, $default);
    }

    /**
     * 获取路径参数
     */
    protected function getPathParam(string $key, mixed $default = null): mixed
    {
        return $this->request->route($key, $default);
    }

    /**
     * 获取请求头
     */
    protected function getHeader(string $name): array
    {
        return $this->request->getHeader($name);
    }

    /**
     * 获取单个请求头
     */
    protected function getHeaderLine(string $name): string
    {
        return $this->request->getHeaderLine($name);
    }

    /**
     * 获取分页参数
     */
    protected function getPaginationParams(): array
    {
        $page = max(1, (int) $this->getQueryParam('page', 1));
        $size = min(100, max(1, (int) $this->getQueryParam('size', 20)));
        $offset = ($page - 1) * $size;

        return compact('page', 'size', 'offset');
    }

    /**
     * 获取排序参数
     */
    protected function getSortParams(array $allowedFields = []): array
    {
        $sortBy = $this->getQueryParam('sort_by', 'id');
        $sortOrder = strtolower($this->getQueryParam('sort_order', 'desc'));

        // 验证排序字段
        if (!empty($allowedFields) && !in_array($sortBy, $allowedFields)) {
            $sortBy = $allowedFields[0] ?? 'id';
        }

        // 验证排序方向
        if (!in_array($sortOrder, ['asc', 'desc'])) {
            $sortOrder = 'desc';
        }

        return compact('sortBy', 'sortOrder');
    }

    /**
     * 获取当前用户ID
     */
    protected function getCurrentUserId(): ?int
    {
        return Context::get('user_id');
    }

    /**
     * 获取当前租户ID
     */
    protected function getCurrentTenantId(): ?int
    {
        return Context::get('tenant_id');
    }

    /**
     * 获取当前企业ID
     */
    protected function getCurrentCorpId(): ?int
    {
        return Context::get('corp_id');
    }

    /**
     * 检查是否为Ajax请求
     */
    protected function isAjax(): bool
    {
        return $this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
    }

    /**
     * 检查是否为JSON请求
     */
    protected function isJson(): bool
    {
        return str_contains($this->request->getHeaderLine('Content-Type'), 'application/json');
    }

    /**
     * 获取客户端IP
     */
    protected function getClientIp(): string
    {
        $headers = [
            'X-Forwarded-For',
            'X-Real-IP',
            'X-Client-IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP'
        ];

        foreach ($headers as $header) {
            $ip = $this->request->getHeaderLine($header);
            if (!empty($ip) && $ip !== 'unknown') {
                // 如果有多个IP，取第一个
                return explode(',', $ip)[0];
            }
        }

        return $this->request->getServerParams()['remote_addr'] ?? '127.0.0.1';
    }

    /**
     * 获取用户代理
     */
    protected function getUserAgent(): string
    {
        return $this->request->getHeaderLine('User-Agent');
    }

    /**
     * 获取Bearer Token
     */
    protected function getBearerToken(): ?string
    {
        $authorization = $this->request->getHeaderLine('Authorization');
        
        if (str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        return null;
    }

    /**
     * 验证请求参数
     */
    protected function validate(array $data, array $rules, array $messages = []): array
    {
        $validator = $this->validatorFactory->make($data, $rules, $messages);
        
        if ($validator->fails()) {
            throw new BusinessException(
                '参数验证失败: ' . implode('; ', $validator->errors()->all()),
                422,
                $validator->errors()->toArray()
            );
        }
        
        return $validator->validated();
    }

    /**
     * 安全地获取数组值
     */
    protected function getValue(array $data, string $key, mixed $default = null): mixed
    {
        return $data[$key] ?? $default;
    }

    /**
     * 过滤允许的字段
     */
    protected function filterAllowed(array $data, array $allowed): array
    {
        return array_intersect_key($data, array_flip($allowed));
    }

    /**
     * 记录操作日志
     */
    protected function logAction(string $action, array $context = []): void
    {
        logger()->info("Controller action: {$action}", array_merge([
            'controller' => static::class,
            'user_id' => $this->getCurrentUserId(),
            'tenant_id' => $this->getCurrentTenantId(),
            'ip' => $this->getClientIp(),
            'user_agent' => $this->getUserAgent(),
        ], $context));
    }

    /**
     * 抛出业务异常
     */
    protected function throwBusinessException(string $message, int $code = 400, mixed $data = null): never
    {
        throw new BusinessException($message, $code, $data);
    }

    /**
     * 检查必需参数
     */
    protected function requireParams(array $data, array $required): void
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            $this->throwBusinessException('缺少必需参数: ' . implode(', ', $missing), 422);
        }
    }

    /**
     * 构建查询条件
     */
    protected function buildQuery($query, array $filters): mixed
    {
        foreach ($filters as $field => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }
} 
<?php

declare(strict_types=1);

namespace HPlus\Core\Service;

use Hyperf\HttpServer\Contract\ResponseInterface;
use Hyperf\HttpMessage\Stream\SwooleStream;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;

/**
 * 统一API响应服务
 * 提供标准的RESTful响应格式
 */
class ApiResponse
{
    public function __construct(
        protected ResponseInterface $response
    ) {}

    /**
     * 资源创建成功响应 (201)
     */
    public function created(mixed $data, ?string $location = null): PsrResponseInterface
    {
        $response = $this->response->withStatus(201);
        
        if ($location) {
            $response = $response->withHeader('Location', $location);
        }
        
        return $response->json($data);
    }

    /**
     * 请求成功响应 (200)
     */
    public function success(mixed $data = null): PsrResponseInterface
    {
        return $this->response->json($data);
    }

    /**
     * 资源更新成功响应 (200)
     */
    public function updated(mixed $data): PsrResponseInterface
    {
        return $this->response->json($data);
    }

    /**
     * 资源删除成功响应 (204)
     */
    public function deleted(): PsrResponseInterface
    {
        return $this->response->withStatus(204);
    }

    /**
     * 无内容响应 (204)
     */
    public function noContent(): PsrResponseInterface
    {
        return $this->response->withStatus(204);
    }

    /**
     * 接受请求响应 (202) - 用于异步处理
     */
    public function accepted(mixed $data = null): PsrResponseInterface
    {
        return $this->response->withStatus(202)->json($data);
    }

    /**
     * 分页响应
     */
    public function paginated(array $data, array $meta): PsrResponseInterface
    {
        return $this->response->json([
            'data' => $data,
            'meta' => $meta
        ]);
    }

    /**
     * 错误响应 (自定义状态码)
     */
    public function error(string $message, int $code = 400, array $details = []): PsrResponseInterface
    {
        $errorData = ['message' => $message];
        
        if (!empty($details)) {
            $errorData['details'] = $details;
        }
        
        return $this->response->withStatus($code)->json($errorData);
    }

    /**
     * 验证失败响应 (422)
     */
    public function validationFailed(array $errors): PsrResponseInterface
    {
        return $this->response->withStatus(422)->json([
            'message' => '数据验证失败',
            'errors' => $errors
        ]);
    }

    /**
     * 未找到响应 (404)
     */
    public function notFound(string $message = '资源不存在'): PsrResponseInterface
    {
        return $this->response->withStatus(404)->json([
            'message' => $message
        ]);
    }

    /**
     * 未授权响应 (401)
     */
    public function unauthorized(string $message = '未授权访问'): PsrResponseInterface
    {
        return $this->response->withStatus(401)->json([
            'message' => $message
        ]);
    }

    /**
     * 禁止访问响应 (403)
     */
    public function forbidden(string $message = '禁止访问'): PsrResponseInterface
    {
        return $this->response->withStatus(403)->json([
            'message' => $message
        ]);
    }

    /**
     * 自动生成Location头
     */
    public function generateLocation(string $resource, int|string $id): string
    {
        return "/{$resource}/{$id}";
    }
} 
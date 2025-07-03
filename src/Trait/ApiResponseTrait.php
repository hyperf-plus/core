<?php

declare(strict_types=1);

namespace HPlus\Core\Trait;

use HPlus\Core\Service\ApiResponse;
use Hyperf\Di\Annotation\Inject;
use Psr\Http\Message\ResponseInterface;

/**
 * API响应Trait
 * 为控制器提供统一的响应方法
 */
trait ApiResponseTrait
{
    #[Inject]
    protected ApiResponse $apiResponse;

    /**
     * 资源创建成功响应 (201)
     */
    protected function created(mixed $data, ?string $location = null): ResponseInterface
    {
        return $this->apiResponse->created($data, $location);
    }

    /**
     * 请求成功响应 (200)
     */
    protected function success(mixed $data = null): ResponseInterface
    {
        return $this->apiResponse->success($data);
    }

    /**
     * 资源更新成功响应 (200)
     */
    protected function updated(mixed $data): ResponseInterface
    {
        return $this->apiResponse->updated($data);
    }

    /**
     * 资源删除成功响应 (204)
     */
    protected function deleted(): ResponseInterface
    {
        return $this->apiResponse->deleted();
    }

    /**
     * 无内容响应 (204)
     */
    protected function noContent(): ResponseInterface
    {
        return $this->apiResponse->noContent();
    }

    /**
     * 接受请求响应 (202)
     */
    protected function accepted(mixed $data = null): ResponseInterface
    {
        return $this->apiResponse->accepted($data);
    }

    /**
     * 分页响应
     */
    protected function paginated(array $data, array $meta): ResponseInterface
    {
        return $this->apiResponse->paginated($data, $meta);
    }

    /**
     * 创建资源并自动生成Location
     */
    protected function createdResource(mixed $data, string $resource, int|string $id): ResponseInterface
    {
        $location = $this->apiResponse->generateLocation($resource, $id);
        return $this->apiResponse->created($data, $location);
    }
} 
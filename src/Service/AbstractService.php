<?php

declare(strict_types=1);

namespace HPlus\Core\Service;

/**
 * 抽象服务基类
 * 提供通用的服务方法
 */
abstract class AbstractService
{
    /**
     * 验证数组中的必填字段
     */
    protected function validateRequired(array $data, array $required): void
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new \HPlus\Core\Exception\BusinessException('缺少必填字段: ' . implode(', ', $missing));
        }
    }

    /**
     * 过滤数组中的允许字段
     */
    protected function filterAllowed(array $data, array $allowed): array
    {
        return array_intersect_key($data, array_flip($allowed));
    }

    /**
     * 安全地获取数组值
     */
    protected function getValue(array $data, string $key, mixed $default = null): mixed
    {
        return $data[$key] ?? $default;
    }

    /**
     * 批量获取数组值
     */
    protected function getValues(array $data, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $result[$key] = $data[$key];
            }
        }
        return $result;
    }

    /**
     * 生成唯一字符串
     */
    protected function generateUniqueString(string $prefix = '', int $length = 32): string
    {
        $random = bin2hex(random_bytes($length / 2));
        return $prefix ? $prefix . '_' . $random : $random;
    }

    /**
     * 验证邮箱格式
     */
    protected function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * 验证手机号格式
     */
    protected function validateMobile(string $mobile): bool
    {
        return preg_match('/^1[3-9]\d{9}$/', $mobile);
    }

    /**
     * 验证URL格式
     */
    protected function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * 获取当前时间戳
     */
    protected function getCurrentTimestamp(): int
    {
        return time();
    }

    /**
     * 获取当前日期时间
     */
    protected function getCurrentDateTime(): \DateTime
    {
        return new \DateTime();
    }

    /**
     * 格式化数组为字符串
     */
    protected function arrayToString(array $data, string $separator = ','): string
    {
        return implode($separator, $data);
    }

    /**
     * 字符串转数组
     */
    protected function stringToArray(string $str, string $separator = ','): array
    {
        return empty($str) ? [] : explode($separator, $str);
    }

    /**
     * 记录服务日志
     */
    protected function log(string $message, array $context = [], string $level = 'info'): void
    {
        logger()->log($level, $message, $context);
    }

    /**
     * 记录错误日志
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->log($message, $context, 'error');
    }

    /**
     * 记录调试日志
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $this->log($message, $context, 'debug');
    }
} 
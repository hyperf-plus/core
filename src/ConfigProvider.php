<?php

declare(strict_types=1);

namespace HPlus\Core;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                \HPlus\Core\Service\ApiResponse::class => \HPlus\Core\Service\ApiResponse::class,
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
        ];
    }
} 
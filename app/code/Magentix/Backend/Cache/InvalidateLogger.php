<?php

declare(strict_types=1);

namespace Magentix\Backend\Cache;

use Magento\Framework\Cache\InvalidateLogger as BaseInvalidateLogger;

class InvalidateLogger extends BaseInvalidateLogger
{
    /**
     * Logger invalidate cache
     * @override Do not log cache_invalidate
     *
     * @param mixed $invalidateInfo
     * @return void
     */
    public function execute($invalidateInfo): void
    {
    }
}

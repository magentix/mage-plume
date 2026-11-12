<?php
/**
 * Magentix Search Module
 *
 * @author    Magentix
 * @copyright Copyright © 2026 Magentix. All rights reserved.
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @link      https://www.magentix.fr/
 */
declare(strict_types=1);

namespace Magentix\Search\Setup\Patch\Data;

use Magentix\Search\Model\Cache\Type\TypoDictionary;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * New cache types are disabled after installation: enable the dictionary cache
 */
class EnableDictionaryCache implements DataPatchInterface
{
    protected Manager $cacheManager;

    /**
     * @param Manager $cacheManager
     */
    public function __construct(
        Manager $cacheManager
    ) {
        $this->cacheManager = $cacheManager;
    }

    /**
     * @return EnableDictionaryCache
     */
    public function apply(): EnableDictionaryCache
    {
        $this->cacheManager->setEnabled([TypoDictionary::TYPE_IDENTIFIER], true);

        return $this;
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}

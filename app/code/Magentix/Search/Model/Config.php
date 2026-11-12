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

namespace Magentix\Search\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    public const string XML_PATH_ENGINE = 'catalog/search/engine';
    public const string XML_PATH_ENABLED = 'catalog/search/magentix_typo_enabled';
    public const string XML_PATH_MIN_LENGTH = 'catalog/search/magentix_typo_min_length';

    public const string ENGINE = 'lmysql';

    protected ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig
    ) {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Typo tolerance is enabled and the MySQL Legacy engine is in use
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ENGINE) === self::ENGINE
            && $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Words shorter than this length are never corrected
     *
     * @param int|null $storeId
     * @return int
     */
    public function getMinLength(?int $storeId = null): int
    {
        $length = (int)$this->scopeConfig->getValue(self::XML_PATH_MIN_LENGTH, ScopeInterface::SCOPE_STORE, $storeId);

        return max(3, $length ?: 4);
    }

    /**
     * Maximum number of typos accepted for a word (same rule as Elasticsearch/OpenSearch "fuzziness: AUTO")
     *
     * @param int $length
     * @param int|null $storeId
     * @return int
     */
    public function getMaxDistance(int $length, ?int $storeId = null): int
    {
        if ($length < $this->getMinLength($storeId)) {
            return 0;
        }

        return $length >= 6 ? 2 : 1;
    }
}

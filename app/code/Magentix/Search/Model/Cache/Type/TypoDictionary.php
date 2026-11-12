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

namespace Magentix\Search\Model\Cache\Type;

use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\Cache\Frontend\Decorator\TagScope;

/**
 * "Search Typo Dictionary" cache type (System > Cache Management)
 */
class TypoDictionary extends TagScope
{
    public const string TYPE_IDENTIFIER = 'magentix_search';

    public const string CACHE_TAG = 'MAGENTIX_SEARCH';

    /**
     * @param FrontendPool $cacheFrontendPool
     */
    public function __construct(
        FrontendPool $cacheFrontendPool
    ) {
        parent::__construct($cacheFrontendPool->get(self::TYPE_IDENTIFIER), self::CACHE_TAG);
    }
}

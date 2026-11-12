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

namespace Magentix\Search\Plugin;

use Magentix\Search\Model\Cache\Type\TypoDictionary;
use Magentix\Search\Model\Dictionary;
use Magento\Backend\App\Action;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\TypeListInterface;

/**
 * Rebuild the dictionary when its cache type is refreshed, and after every cache flush
 *
 * - Cache Management "Refresh" and bin/magento cache:clean call TypeListInterface::cleanType().
 * - bin/magento cache:flush calls Cache\Manager::flush().
 * - "Flush Magento Cache" and "Flush Cache Storage" buttons empty the cache storage directly.
 *
 * A flush empties the whole cache storage, whatever the selected types: the dictionary is always rebuilt.
 */
class RefreshDictionary
{
    protected Dictionary $dictionary;

    protected StateInterface $cacheState;

    /**
     * @param Dictionary $dictionary
     * @param StateInterface $cacheState
     */
    public function __construct(
        Dictionary $dictionary,
        StateInterface $cacheState
    ) {
        $this->dictionary = $dictionary;
        $this->cacheState = $cacheState;
    }

    /**
     * Cache Management "Refresh", bin/magento cache:clean
     *
     * @param TypeListInterface $subject
     * @param mixed $result
     * @param string $typeCode
     * @return mixed
     */
    public function afterCleanType(TypeListInterface $subject, $result, $typeCode)
    {
        if ($typeCode === TypoDictionary::TYPE_IDENTIFIER) {
            $this->refresh();
        }

        return $result;
    }

    /**
     * bin/magento cache:flush
     *
     * @param Manager $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterFlush(Manager $subject, $result)
    {
        $this->refresh();

        return $result;
    }

    /**
     * Cache Management "Flush Magento Cache" and "Flush Cache Storage"
     *
     * @param Action $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterExecute(Action $subject, $result)
    {
        $this->refresh();

        return $result;
    }

    /**
     * Rebuild the dictionary when its cache type is enabled
     *
     * The state is checked first: the dictionary (proxy) is not loaded when the cache type is disabled.
     *
     * @return void
     */
    protected function refresh(): void
    {
        if ($this->cacheState->isEnabled(TypoDictionary::TYPE_IDENTIFIER)) {
            $this->dictionary->refresh();
        }
    }
}

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

use Magentix\Search\Preprocessor\Typo;
use Magento\Search\Model\Query;
use Magento\Search\Model\ResourceModel\Query as QueryResource;

/**
 * Do not record search terms containing a typo in the search history (Marketing > Search Terms)
 */
class SkipTypoSearchTerm
{
    protected Typo $typo;

    /**
     * @param Typo $typo
     */
    public function __construct(
        Typo $typo
    ) {
        $this->typo = $typo;
    }

    /**
     * Skip popularity increment
     *
     * @param QueryResource $subject
     * @param callable $proceed
     * @param Query $query
     * @return void
     */
    public function aroundSaveIncrementalPopularity(QueryResource $subject, callable $proceed, Query $query): void
    {
        if (!$this->hasTypo($query)) {
            $proceed($query);
        }
    }

    /**
     * Skip number of results update
     *
     * @param QueryResource $subject
     * @param callable $proceed
     * @param Query $query
     * @return void
     */
    public function aroundSaveNumResults(QueryResource $subject, callable $proceed, Query $query): void
    {
        if (!$this->hasTypo($query)) {
            $proceed($query);
        }
    }

    /**
     * Check whether the query text contains a word corrected by typo tolerance
     *
     * @param Query $query
     * @return bool
     */
    protected function hasTypo(Query $query): bool
    {
        $storeId = $query->getStoreId();

        return $this->typo->hasTypo(
            (string)$query->getQueryText(),
            $storeId !== null ? (int)$storeId : null
        );
    }
}

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

use Magentix\Search\Model\Cache\Type\TypoDictionary;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Indexer\ScopeResolver\IndexScopeResolver;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Search\Request\Dimension;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Words of the catalog search index, grouped by length: [length => [word => product count]]
 *
 * Contains every word the MySQL Legacy engine can find: all searchable attributes are read, so a word
 * existing in the catalog is never corrected. Words shorter than 3 characters or containing a digit are ignored.
 *
 * Built from the MySQL Legacy index tables (catalogsearch_fulltext_scope<storeId>) and stored in the
 * "magentix_search" cache type, one entry per word length, so that a search only loads the lengths it needs.
 * The cache is not affected by a reindex: it is rebuilt when it is refreshed in Cache Management or with
 * bin/magento cache:clean magentix_search (recommended hourly by server cron).
 */
class Dictionary
{
    public const string INDEX_NAME = 'catalogsearch_fulltext';

    public const string LOCK_NAME = 'magentix_search_dictionary_';

    public const int MIN_WORD_LENGTH = 3;

    public const int BATCH_SIZE = 1000;

    protected ResourceConnection $resourceConnection;

    protected IndexScopeResolver $indexScopeResolver;

    protected TypoDictionary $cache;

    protected SerializerInterface $serializer;

    protected LockManagerInterface $lockManager;

    protected StoreManagerInterface $storeManager;

    protected Normalizer $normalizer;

    protected LoggerInterface $logger;

    /**
     * Words loaded during the request: [store id => [length => [word => product count]]]
     *
     * @var array<int, array<int, array<string, int>>>
     */
    protected array $dictionaries = [];

    /**
     * Lengths available in the cache: [store id => int[]]
     *
     * @var array<int, int[]>
     */
    protected array $lengths = [];

    /**
     * @param ResourceConnection $resourceConnection
     * @param IndexScopeResolver $indexScopeResolver
     * @param TypoDictionary $cache
     * @param SerializerInterface $serializer
     * @param LockManagerInterface $lockManager
     * @param StoreManagerInterface $storeManager
     * @param Normalizer $normalizer
     * @param LoggerInterface $logger
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        IndexScopeResolver $indexScopeResolver,
        TypoDictionary $cache,
        SerializerInterface $serializer,
        LockManagerInterface $lockManager,
        StoreManagerInterface $storeManager,
        Normalizer $normalizer,
        LoggerInterface $logger
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->indexScopeResolver = $indexScopeResolver;
        $this->cache = $cache;
        $this->serializer = $serializer;
        $this->lockManager = $lockManager;
        $this->storeManager = $storeManager;
        $this->normalizer = $normalizer;
        $this->logger = $logger;
    }

    /**
     * Retrieve the words of a store with the given lengths: [length => [word => product count]]
     *
     * Each length is stored in its own cache entry: a search only loads the lengths it needs.
     *
     * The cache is normally built by a refresh or flush. When it is missing (storage emptied outside Magento,
     * cache type disabled), a single request rebuilds it; concurrent requests search without typo correction meanwhile.
     *
     * @param int $storeId
     * @param int[] $lengths
     * @return array<int, array<string, int>>
     */
    public function get(int $storeId, array $lengths): array
    {
        $result = [];

        foreach (array_unique($lengths) as $length) {
            $words = $this->load($storeId, (int)$length);
            if ($words === null) {
                return array_intersect_key($this->rebuildMissing($storeId), array_flip($lengths));
            }
            if (!empty($words)) {
                $result[(int)$length] = $words;
            }
        }

        return $result;
    }

    /**
     * Rebuild the dictionary of all stores after a cache refresh or flush
     *
     * Never throws: a cache refresh or flush must not fail. On error, the dictionary is rebuilt on the next search.
     *
     * @return void
     */
    public function refresh(): void
    {
        try {
            $this->rebuild();
        } catch (Throwable $exception) {
            $this->logger->error('Magentix_Search: dictionary rebuild failed. ' . $exception->getMessage());
        }
    }

    /**
     * Rebuild the dictionary of all stores
     *
     * @return void
     */
    public function rebuild(): void
    {
        $this->dictionaries = [];
        $this->lengths = [];

        foreach ($this->storeManager->getStores() as $store) {
            $this->save((int)$store->getId());
        }
    }

    /**
     * Load the words of a length from the cache
     *
     * @param int $storeId
     * @param int $length
     * @return array<string, int>|null null when the dictionary is missing from the cache
     */
    protected function load(int $storeId, int $length): ?array
    {
        if (isset($this->dictionaries[$storeId][$length])) {
            return $this->dictionaries[$storeId][$length];
        }

        if (!isset($this->lengths[$storeId])) {
            $cached = $this->cache->load($this->getCacheKey($storeId));
            if (!is_string($cached) || $cached === '') {
                return null;
            }
            $this->lengths[$storeId] = array_map('intval', (array)$this->serializer->unserialize($cached));
        }

        if (!in_array($length, $this->lengths[$storeId], true)) {
            return [];
        }

        $cached = $this->cache->load($this->getCacheKey($storeId, $length));
        if (!is_string($cached) || $cached === '') {
            return null;
        }

        return $this->dictionaries[$storeId][$length] = (array)$this->serializer->unserialize($cached);
    }

    /**
     * Rebuild a dictionary missing from the cache, by a single request at a time
     *
     * @param int $storeId
     * @return array<int, array<string, int>> empty when another request is rebuilding it
     */
    protected function rebuildMissing(int $storeId): array
    {
        $lockName = self::LOCK_NAME . $storeId;
        if (!$this->lockManager->lock($lockName, 0)) {
            return [];
        }

        try {
            $dictionary = $this->save($storeId);
        } finally {
            $this->lockManager->unlock($lockName);
        }

        $this->dictionaries[$storeId] = $dictionary;
        $this->lengths[$storeId] = array_keys($dictionary);

        return $dictionary;
    }

    /**
     * Build and cache the dictionary of a store: one entry per word length, then the list of lengths
     *
     * An empty dictionary (index not built yet) is not cached, so that the next refresh or search retries.
     *
     * @param int $storeId
     * @return array<int, array<string, int>>
     */
    protected function save(int $storeId): array
    {
        $dictionary = $this->build($storeId);

        if (!empty($dictionary)) {
            foreach ($dictionary as $length => $words) {
                $this->cache->save(
                    $this->serializer->serialize($words),
                    $this->getCacheKey($storeId, $length),
                    [TypoDictionary::CACHE_TAG]
                );
            }
            $this->cache->save(
                $this->serializer->serialize(array_keys($dictionary)),
                $this->getCacheKey($storeId),
                [TypoDictionary::CACHE_TAG]
            );
        }

        return $dictionary;
    }

    /**
     * Cache key of a store dictionary: list of lengths, or words of a length
     *
     * @param int $storeId
     * @param int|null $length
     * @return string
     */
    protected function getCacheKey(int $storeId, ?int $length = null): string
    {
        return $length !== null
            ? 'MAGENTIX_SEARCH_DICTIONARY_' . $storeId . '_' . $length
            : 'MAGENTIX_SEARCH_LENGTHS_' . $storeId;
    }

    /**
     * Read the words of all searchable attributes in the store search index
     *
     * The index is read in batches of rows (keyset pagination on the primary key): memory usage does not
     * depend on the catalog size, only on the number of distinct words.
     *
     * @param int $storeId
     * @return array<int, array<string, int>>
     */
    protected function build(int $storeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->indexScopeResolver->resolve(
            self::INDEX_NAME,
            [new Dimension('scope', (string)$storeId)]
        );

        if (!$connection->isTableExists($table)) {
            return [];
        }

        /* Number of products containing each word (a word repeated in several attributes counts once) */
        $dictionary = [];
        $productId = 0;
        $productWords = [];
        $lastEntityId = 0;
        $lastAttributeId = 0;

        do {
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($table, ['entity_id', 'attribute_id', 'data_index'])
                    ->where(sprintf(
                        'entity_id > %1$d OR (entity_id = %1$d AND attribute_id > %2$d)',
                        $lastEntityId,
                        $lastAttributeId
                    ))
                    ->order(['entity_id', 'attribute_id'])
                    ->limit(self::BATCH_SIZE)
            );

            foreach ($rows as $row) {
                $lastEntityId = (int)$row['entity_id'];
                $lastAttributeId = (int)$row['attribute_id'];

                if ($lastEntityId !== $productId) {
                    $this->countWords($dictionary, $productWords);
                    $productId = $lastEntityId;
                    $productWords = [];
                }
                foreach ($this->normalizer->getWords((string)$row['data_index']) as $word) {
                    if (strlen($word) >= self::MIN_WORD_LENGTH && !preg_match('/\d/', $word)) {
                        $productWords[$word] = true;
                    }
                }
            }
        } while (count($rows) === self::BATCH_SIZE);

        $this->countWords($dictionary, $productWords);

        return $dictionary;
    }

    /**
     * Add the words of a product to the dictionary
     *
     * @param array<int, array<string, int>> $dictionary
     * @param array<string, bool> $productWords
     * @return void
     */
    protected function countWords(array &$dictionary, array $productWords): void
    {
        foreach (array_keys($productWords) as $word) {
            $word = (string)$word;
            $length = strlen($word);
            $dictionary[$length][$word] = ($dictionary[$length][$word] ?? 0) + 1;
        }
    }
}

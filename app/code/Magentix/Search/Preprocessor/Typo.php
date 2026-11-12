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

namespace Magentix\Search\Preprocessor;

use Magentix\Search\Model\Config;
use Magentix\Search\Model\Dictionary;
use Magentix\Search\Model\Normalizer;
use Magento\Framework\Search\Adapter\Preprocessor\PreprocessorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

/**
 * Replace unknown words of a full-text query with the closest word of the catalog
 *
 * Known words, short words and words containing a digit (SKU, sizes...) are left untouched.
 * The corrected query is then processed by the MySQL Legacy engine as usual: relevance,
 * filters, aggregations and visibility are kept.
 */
class Typo implements PreprocessorInterface
{
    /**
     * Maximum number of words corrected in a query, as the MySQL Legacy engine keeps 32 words
     * (bounds the CPU time when the maximum query length is raised)
     */
    public const int MAX_WORDS = 32;

    protected Config $config;

    protected Dictionary $dictionary;

    protected Normalizer $normalizer;

    protected StoreManagerInterface $storeManager;

    /**
     * @var array<string, string>
     */
    protected array $corrections = [];

    /**
     * @param Config $config
     * @param Dictionary $dictionary
     * @param Normalizer $normalizer
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Config $config,
        Dictionary $dictionary,
        Normalizer $normalizer,
        StoreManagerInterface $storeManager
    ) {
        $this->config = $config;
        $this->dictionary = $dictionary;
        $this->normalizer = $normalizer;
        $this->storeManager = $storeManager;
    }

    /**
     * Correct the query
     *
     * @param string $query
     * @return string
     */
    public function process($query)
    {
        return $this->apply((string)$query, null);
    }

    /**
     * Check whether the query contains a word which would be corrected
     *
     * @param string $query
     * @param int|null $storeId
     * @return bool
     */
    public function hasTypo(string $query, ?int $storeId = null): bool
    {
        $corrected = false;
        $this->apply($query, $storeId, $corrected);

        return $corrected;
    }

    /**
     * Replace unknown words of the query
     *
     * @param string $query
     * @param int|null $storeId
     * @param bool $corrected set to true when at least one word is replaced
     * @return string
     */
    protected function apply(string $query, ?int $storeId, bool &$corrected = false): string
    {
        try {
            $storeId = $storeId ?? (int)$this->storeManager->getStore()->getId();
            if (trim($query) === '' || !$this->config->isEnabled($storeId)) {
                return $query;
            }

            $tokens = preg_split('/\s+/u', trim($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $remaining = self::MAX_WORDS;

            foreach ($tokens as $key => $token) {
                if ($remaining <= 0) {
                    break;
                }

                $words = $this->normalizer->getWords($token);
                $changed = false;

                foreach ($words as $index => $word) {
                    if ($remaining-- <= 0) {
                        break;
                    }
                    $correction = $this->correct($word, $storeId);
                    if ($correction !== $word) {
                        $words[$index] = $correction;
                        $changed = true;
                    }
                }

                if ($changed) {
                    $tokens[$key] = implode(' ', $words);
                    $corrected = true;
                }
            }
        } catch (Throwable) {
            $corrected = false;

            return $query;
        }

        return $corrected ? implode(' ', $tokens) : $query;
    }

    /**
     * Retrieve the closest dictionary word, or the word itself
     *
     * Only the words of the same length are loaded to recognize a known word. Words of the neighbouring
     * lengths are loaded for unknown words only.
     *
     * @param string $word
     * @param int $storeId
     * @return string
     */
    protected function correct(string $word, int $storeId): string
    {
        $length = strlen($word);

        if (preg_match('/\d/', $word)) {
            return $word;
        }

        $maxDistance = $this->config->getMaxDistance($length, $storeId);
        if ($maxDistance === 0) {
            return $word;
        }

        $known = $this->dictionary->get($storeId, [$length]);
        if (isset($known[$length][$word])) {
            return $word;
        }

        $cacheKey = $storeId . '_' . $word;
        if (isset($this->corrections[$cacheKey])) {
            return $this->corrections[$cacheKey];
        }

        $dictionary = $this->dictionary->get($storeId, range(max(1, $length - $maxDistance), $length + $maxDistance));

        $best = $word;
        $bestDistance = $maxDistance + 1;
        $bestCount = 0;

        for ($size = max(1, $length - $maxDistance); $size <= $length + $maxDistance; $size++) {
            foreach ($dictionary[$size] ?? [] as $candidate => $count) {
                $candidate = (string)$candidate;
                /* A transposition costs 2 with levenshtein(): native pre-filter, then exact distance */
                if (levenshtein($word, $candidate) > $maxDistance * 2) {
                    continue;
                }
                $distance = $this->getDistance($word, $candidate);
                if ($distance < $bestDistance || ($distance === $bestDistance && $count > $bestCount)) {
                    $best = $candidate;
                    $bestDistance = $distance;
                    $bestCount = $count;
                }
            }
        }

        return $this->corrections[$cacheKey] = $best;
    }

    /**
     * Damerau-Levenshtein distance (optimal string alignment): an adjacent transposition counts as one typo
     *
     * @param string $source
     * @param string $target
     * @return int
     */
    protected function getDistance(string $source, string $target): int
    {
        $sourceLength = strlen($source);
        $targetLength = strlen($target);

        $matrix = [];
        for ($i = 0; $i <= $sourceLength; $i++) {
            $matrix[$i][0] = $i;
        }
        for ($j = 0; $j <= $targetLength; $j++) {
            $matrix[0][$j] = $j;
        }

        for ($i = 1; $i <= $sourceLength; $i++) {
            for ($j = 1; $j <= $targetLength; $j++) {
                $cost = $source[$i - 1] === $target[$j - 1] ? 0 : 1;
                $matrix[$i][$j] = min(
                    $matrix[$i - 1][$j] + 1,
                    $matrix[$i][$j - 1] + 1,
                    $matrix[$i - 1][$j - 1] + $cost
                );
                if ($i > 1 && $j > 1
                    && $source[$i - 1] === $target[$j - 2]
                    && $source[$i - 2] === $target[$j - 1]
                ) {
                    $matrix[$i][$j] = min($matrix[$i][$j], $matrix[$i - 2][$j - 2] + 1);
                }
            }
        }

        return $matrix[$sourceLength][$targetLength];
    }
}

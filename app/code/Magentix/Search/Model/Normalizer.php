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

use Magento\Framework\Filter\Translit;

class Normalizer
{
    protected Translit $translit;

    /**
     * @param Translit $translit
     */
    public function __construct(
        Translit $translit
    ) {
        $this->translit = $translit;
    }

    /**
     * Split a text into lowercase ASCII words (accents removed, punctuation as separator)
     *
     * Levenshtein is computed on bytes: words must be ASCII for the distance to count characters.
     *
     * @param string $text
     * @return string[]
     */
    public function getWords(string $text): array
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strtolower((string)$this->translit->filter($text));

        return preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
}

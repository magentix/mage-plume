# Magentix Search

Typo tolerance for the Magento [MySQL Legacy search engine](https://github.com/swissup/module-search-mysql-legacy).

Before the MySQL `MATCH ... AGAINST`, every unknown word of the search query is replaced with the closest word
of the catalog (Damerau-Levenshtein distance). For example, `tshrit` becomes `shirt` and `mondail` becomes `mondial`.

The corrected query is then processed by the MySQL Legacy engine as usual: relevance, layered navigation filters,
visibility, product count and pagination keep working. Category pages are not affected (they do not run any
full-text query).

## Requirements

- Magento 2.4.7 to 2.4.9 (Open Source, Adobe Commerce, Mage-OS): first versions supporting PHP 8.3
- PHP >= 8.3
- `swissup/module-search-mysql-legacy`, selected as search engine (`catalog/search/engine` = `lmysql`)

## How it works

### Dictionary

The dictionary contains every word the MySQL Legacy engine can find: the words of all searchable attributes, read
from its index tables (`catalogsearch_fulltext_scope<storeId>`). A word existing in the catalog is therefore never
corrected. Words are lowercased, accents are removed, and words shorter than 3 characters or containing a digit are
ignored. Each word comes with the number of products containing it.

Searchable attributes are set in *Stores > Attributes > Product* (Use in Search). Avoid making technical attributes
searchable (status, tax class): their values become search terms, with or without this module.

It is stored in a dedicated cache type, **Search Typo Dictionary** (`magentix_search`), in
*System > Tools > Cache Management*:

- Product changes do not affect the dictionary: words of new or updated products are not corrected until the
  cache is refreshed. The cache type is never marked as invalidated (the catalog search index changes on every
  price or category update, which would keep the admin notification displayed).
- **Refresh** rebuilds the dictionary of all stores. From the command line:

  ```bash
  bin/magento cache:clean magentix_search
  ```

- A cache flush also rebuilds it right away, whatever the selected types (a flush empties the whole cache
  storage): `bin/magento cache:flush`, *Flush Magento Cache* and *Flush Cache Storage*.

- It is recommended to refresh it with the server cron, every hour for example (as the Magento file owner):

  ```
  0 * * * * cd /path/to/magento && php bin/magento cache:clean magentix_search > /dev/null 2>&1
  ```

- If the dictionary is missing anyway (cache storage emptied outside Magento, e.g. `rm -rf var/cache` or Redis
  `FLUSHALL`), the first search rebuilds it. A lock prevents concurrent rebuilds: other searches run without typo
  correction meanwhile.
- When the cache type is disabled, the dictionary is rebuilt on every search. Use it for testing only.

### Correction

The `Magentix\Search\Preprocessor\Typo` preprocessor is added to the MySQL Legacy `MatchBuilder`, next to the
synonyms preprocessor. For each word of the query:

- a known word, a word containing a digit (SKU, size...) or a word shorter than the minimum length is kept;
- otherwise, it is replaced with the dictionary word at the lowest distance. As with the Elasticsearch/OpenSearch
  `fuzziness: AUTO` rule, 1 typo is accepted up to 5 characters and 2 typos from 6 characters. A typo is an
  inserted, deleted or replaced letter, or a swap of two adjacent letters. On equal distance, the word found in
  the most products wins.
- if no word is close enough, the word is kept.

Only the first 32 words of a query are corrected, as the MySQL Legacy engine keeps 32 words. The query is also
truncated beforehand to the *Maximum Query Length* (128 characters by default) by the engine, for every entry point
(search page, REST API): the correction time of a query is bounded.

The dictionary is stored with one cache entry per word length, and a search only loads what it needs: the length
of each query word to recognize a known word, and the neighbouring lengths (±1 or ±2) for an unknown word only.
The index is read in batches when the dictionary is built: memory usage depends on the number of distinct words,
not on the catalog size.

For example, with a dictionary of 100 000 words (PHP 8.5):

| Query | Words loaded | Time | Memory |
|---|---|---|---|
| Known word (`shirt`, `pantalon`) | 2 600 to 9 800 | 1 to 2 ms | ≤ 1.5 MB |
| One unknown word (`tshrit`, `chausure`) | 26 000 to 47 000 | 8 to 15 ms | 3 to 4 MB |
| Four unknown words | 63 000 | 35 ms | 6 MB |

### Search terms

A query containing a corrected word is not recorded in the search history (Marketing > Search Terms):
neither its popularity nor its number of results is saved. The typo therefore never shows up in the reports
or in the search suggestions.

## Configuration

*Stores > Configuration > Catalog > Catalog > Catalog Search* (MySQL Legacy engine only)

| Field | Path | Default |
|---|---|---|
| Typo Tolerance (MySQL Legacy) | `catalog/search/magentix_typo_enabled` | Yes |
| Typo Tolerance Minimum Word Length | `catalog/search/magentix_typo_min_length` | 4 |

## License

BSD 2-Clause, see [LICENCE](LICENCE).

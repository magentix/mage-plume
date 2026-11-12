### [100.0.0] - 2026-11-12

* [Add] Typo tolerance for the MySQL Legacy search engine: unknown query words are replaced with the closest catalog word (Damerau-Levenshtein distance)
* [Add] "Search Typo Dictionary" cache type, rebuilt on refresh and cache flush (hourly server cron recommended)
* [Add] Search terms containing a typo are not recorded in the search history

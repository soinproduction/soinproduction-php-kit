# WordPress integration tests

These tests require a running WordPress fixture site, ACF and the configured PHP Kit relationship module. They are not standalone PHP scripts and are intentionally separate from the tests/*.php CI suite.

Use a disposable fixture site configured with leadership/news_insights relationships and news_insights_category splitting. The taxonomy split test needs at least two enabled categories. Both scripts create temporary records and clean them up in finally blocks.

```sh
wp eval-file /path/to/php-kit/tests/integration/post-relationships-taxonomy-wp.php
wp eval-file /path/to/php-kit/tests/integration/post-relationships-taxonomy-toggle-wp.php
```

Running these scripts without WP-CLI remains an error. A fixture-backed CI job is needed before these can run in GitHub Actions.

## Archive relationship filters

```sh
wp eval-file /path/to/php-kit/tests/integration/archive-relationships-wp.php
```

Requires this PHP Kit version, ACF, and a theme with compatible archive select
and pagination templates plus a writable `php/cards/` directory. The test
creates its own private post types and relationships; it does not require the
leadership/news fixture above. Temporary posts, terms, template files and
archive transients are removed in `finally`.

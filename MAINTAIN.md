# Maintenance guide

## Code checks

ALL-E follows the [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle).
The GitHub workflow `.github/workflows/ci.yml` runs
[moodle-plugin-ci](https://moodlehq.github.io/moodle-plugin-ci/) on every push:
PHP lint, code checker, PHPDoc, validation, Mustache and JavaScript linting,
the `grunt amd` build check, PHPUnit and Behat.

To run the code checker locally:

```bash
composer global require moodlehq/moodle-cs
phpcs --standard=moodle-extra --ignore=*/ignore/*,*/node_modules/* .
```

## JavaScript

Sources are in `amd/src`. After changing them, rebuild `amd/build` from a
Moodle checkout where the plugin is installed in `blocks/alle`:

```bash
npm install
npx grunt amd --root=blocks/alle
```

Commit the rebuilt files together with the sources: the CI fails when they differ.

## Tests

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite block_alle_testsuite
php admin/tool/behat/cli/init.php
vendor/bin/behat --config <behat config> --tags=@block_alle
```

The unit tests replace the AI services with the fakes of
`tests/fixtures/fakes.php`, so they need no network access or API key.

## Releases

1. Update `version.php`: `$plugin->version` (YYYYMMDDXX) and `$plugin->release`.
2. Describe the changes in `CHANGES.md`.
3. Tag the release, for instance `v1.0.1`.

## Strings

Only `lang/en/block_alle.php` is part of the plugin. Keep the strings in
alphabetical order. Translations are contributed through AMOS.

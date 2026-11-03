# Snapshot Trait

To use snapshot tests, use the `SnapshotTrait` in your test class.

Optionally modify the default path of the `__snapshots__` directories appropriate to your
PSR-4 autoload configuration in `composer.json` by overwriting the `snapshotPath()` method.

```php
// FunctionalTestCase.php

use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FunctionalTestCase extends WebTestCase
{
    use SnapshotTrait;

    /**
     * Overwrite Snapshot::snapshotPath() to locate __snapshots__ directory next to the test classes.
     */
    private function snapshotPath(): string
    {
        // parent directory is the base path of all tests    
        $basePath = dirname(__DIR__);

        // convert namespace to path
        $path = preg_replace('/\\\\[^\\\\]+$/', '', static::class);
        $path = str_replace('\\', '/', $path);
        
        // strip 'App/Tests' from path
        $path = substr($path, strlen('App/Tests/'));

        return $basePath . '/' . $path;
    }
}
```

Call assertions to verify snapshot. The snapshot file is created automatically on the first run.

```php
public function testJsonFactoryCreatesJsonCorrectly()
{
    $jsonFactory = new JsonFactory();

    $json = $jsonFactory->createJson();

    $this->assertMatchesJsonSnapshot($json);
}
```

## Snapshot Assertions

Each assertion has a named variant (e.g. `assertMatchesNamedJsonSnapshot($actual, 'suffix')`) for
several snapshots of the same type in one test.

* Array `assertMatchesArraySnapshot()` (stored as JSON)
* JSON `assertMatchesJsonSnapshot()`
* JSON-LD `assertMatchesJsonLdSnapshot()` (blank node ids below `/.well-known/genid/` are normalized)
* XML `assertMatchesXmlSnapshot()`
* HTML `assertMatchesHtmlSnapshot()` (formatted with tidy)
* Text `assertMatchesTextSnapshot()`

## Environment variables

The variables are read from `getenv()` first, then from `$_SERVER` and `$_ENV`, so values from
`phpunit.xml` and Symfony's `.env.test` are picked up too. `0`, `false`, `no` and `off` (case-insensitive)
count as disabled, an empty value counts as not set.

| Variable | Effect |
|---|---|
| `UPDATE_SNAPSHOTS` | Rewrite existing snapshot files with the actual values. |
| `CREATE_SNAPSHOTS` | Set to `0` or `false` to fail instead of creating missing snapshots, or to `auto` to fail only when the `CI` variable is set. Snapshots are created by default. |
| `SNAPSHOT_CREATED_WARNING` | Emit a PHPUnit warning for every snapshot that was created, so new snapshots don't go unnoticed. |
| `SKIP_SNAPSHOT_TESTS` | Mark tests as skipped when they reach a snapshot assertion. |

## Recreate/Update Snapshots

To recreate/update snapshot files run PHPUnit with the environment variable `UPDATE_SNAPSHOTS`.

```shell
UPDATE_SNAPSHOTS=1 phpunit
UPDATE_SNAPSHOTS=1 phpunit tests/Functional/MyTest.php
```

## Usage in CI

When running in continuous integration you probably want to disable snapshot generation by setting
`CREATE_SNAPSHOTS` to `0` or `false`. A missing snapshot then fails the test instead of being created silently.

```shell
CREATE_SNAPSHOTS=0 phpunit
CREATE_SNAPSHOTS=false phpunit tests/Functional/MyTest.php
```

With `CREATE_SNAPSHOTS=auto` in `phpunit.xml.dist`, snapshots are created locally but not in CI. Only the `CI`
variable is checked. GitHub Actions, GitLab CI and most other CI systems set it, Jenkins and Azure Pipelines don't,
so set `CI=true` there yourself:

```xml
<env name="CREATE_SNAPSHOTS" value="auto"/>
```

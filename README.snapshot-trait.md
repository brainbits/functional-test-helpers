# Snapshot Trait

To use snapshot tests, use the `SnapshotTrait` in your test class.

Optionally modify the default path of the `__snapshot__` directories appropriate to your
PSR-4 autoload configuration in `composer.json` by overwriting the `snapshotPath()` method.

```php
// FunctionalTestCase.php

use Brainbits\FunctionalTestHelpers\Snapshot\SnapshotTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class FunctionalTestCase extends WebTestCase
{
    use SnapshotTrait;

    /**
     * Overwrite Snapshot::snapshotPath() to locate __snapshot__ directory next to the test classes.
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

## Recreate/Update Snapshots

To recreate/update snapshot files run PHPUnit with the environment variable `UPDATE_SNAPSHOTS`.

### Snapshot Assertions
* Array `assertMatchesArraySnapshot()`
* JSON `assertMatchesJsonSnapshot()`
* XML `assertMatchesXmlSnapshot()`

```shell
UPDATE_SNAPSHOTS=1 phpunit
UPDATE_SNAPSHOTS=1 phpunit tests/Functinoal/MyTest.php
```

## Usage in CI

When running in continuous integration you maybe want to disable snapshot generation by setting `CREATE_SNAPSHOTS` to `0` or `false`.

```shell
CREATE_SNAPSHOTS=0 phpunit
CREATE_SNAPSHOTS=false phpunit tests/Functinoal/MyTest.php
```
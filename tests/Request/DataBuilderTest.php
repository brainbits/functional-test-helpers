<?php

declare(strict_types=1);

namespace Brainbits\FunctionalTestHelpers\Tests\Request;

use Brainbits\FunctionalTestHelpers\Request\DataBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Brainbits\FunctionalTestHelpers\Request
 */
final class DataBuilderTest extends TestCase
{
    public function testItEncapsulatesDataAsArray(): void
    {
        $dataProvider = DataBuilder::from(['data']);

        self::assertSame(['data'], $dataProvider());
    }

    public function testRemovesNumberIndex(): void
    {
        $dataProvider = DataBuilder::from(['zero', 'one'])
            ->without(1);

        self::assertEquals(['zero'], $dataProvider());
    }

    public function testRemovesNamedIndex(): void
    {
        $dataProvider = DataBuilder::from(['one' => 'one', 'two' => 'two'])
            ->without('two');

        self::assertEquals(['one' => 'one'], $dataProvider());
    }

    public function testRemovesNestedIndex(): void
    {
        $dataProvider = DataBuilder::from(['root' => ['one' => 'one', 'two' => 'two']])
            ->without('root', 'two');

        self::assertEquals(['root' => ['one' => 'one']], $dataProvider());
    }

    public function testRemovesDeeplyNestedIndex(): void
    {
        $dataProvider = DataBuilder::from(['one' => ['two' => ['three' => ['four' => ['five' => 'xxx']]]]])
            ->without('one', 'two', 'three', 'four', 'five');

        self::assertEquals(['one' => ['two' => ['three' => ['four' => []]]]], $dataProvider());
    }

    public function testSetsNumberIndex(): void
    {
        $dataProvider = DataBuilder::from(['root' => ['old value']])
            ->with('new value', 'root', 1);

        self::assertEquals(['root' => ['old value', 'new value']], $dataProvider());
    }

    public function testSetsNamedIndex(): void
    {
        $dataProvider = DataBuilder::from(['root' => ['old index' => 'old value']]);

        self::assertEquals(
            ['root' => ['old index' => 'old value', 'new index' => 'new value']],
            $dataProvider->with('new value', 'root', 'new index')()
        );
    }

    public function testCreatesMissingHierarchiesOnSettingValues(): void
    {
        $dataProvider = DataBuilder::from([])
            ->with('value', 'level1', 'level2', 'level3');

        self::assertEquals(['level1' => ['level2' => ['level3' => 'value']]], $dataProvider());
    }

    public function testAddsValues(): void
    {
        $dataProvider = DataBuilder::from([])
            ->add('value1');

        self::assertEquals(['value1'], $dataProvider());
    }

    public function testAddsNestedValues(): void
    {
        $dataProvider = DataBuilder::from(['level1' => []])
            ->add('value1', 'level1');

        self::assertEquals(['level1' => ['value1']], $dataProvider());
    }

    public function testCreatesMissingHierarchiesOnAddingValues(): void
    {
        $dataProvider = DataBuilder::from([])
            ->add('value1', 'level1', 'level2', 'level3')
            ->add('value2', 'level1', 'level2', 'level3');

        self::assertEquals(['level1' => ['level2' => ['level3' => ['value1', 'value2']]]], $dataProvider());
    }

    public function testModifiersAreChainable(): void
    {
        $dataProvider = DataBuilder::from([])
            ->with('value1', 'key1')
            ->with('value2', 'key2')
            ->without('key1');

        self::assertEquals(['key2' => 'value2'], $dataProvider());
    }
}

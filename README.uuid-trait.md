# brainbits Functional Test Helpers

## Uuid Trait

To use the uuid helpers, use the `UuidTrait` in your test class.

The uuid trait provides several methods for handling UUIDs in tests.

### nextUuid()

This method always provides predictable UUIDs on subsequent calls.

```php
    self::nextUuid(); # 00000000-0000-0000-0000-000000000001
    self::nextUuid(); # 00000000-0000-0000-0000-000000000002
    self::nextUuid(); # 00000000-0000-0000-0000-000000000003
```

### assertIsUuid()

This assertion fails on invalid UUIDs.

```php
    self::assertIsUuid('6b471a56-faf2-11ed-ac67-afd829470994'); # success
    self::assertIsUuid('foo'); # failure
```

### assertAndReplaceUuidInJson()

This assertion validates a UUID in a simple array structure, and replaces the UUID 
with a predictable value. Recommended for example in combination with snapshot tests.

```php
    $data = '{"id": "6b471a56-faf2-11ed-ac67-afd829470994", "name": "test"}'; 
    
    $predictableData = self::assertAndReplaceUuidInJson($data, 'id');

    // $predictableData = '{"id": "00000000-0000-0000-0000-000000000001", "name": "test"}';
```

### assertAndReplaceUuidInArray()

This assertion validates a UUID in a simple array structure, and replaces the UUID 
with a predictable value. Recommended for example in combination with snapshot tests.

```php
    $data = [
        'id' => '6b471a56-faf2-11ed-ac67-afd829470994', 
        'name' => 'test',
    ];
    
    $predictableData = self::assertAndReplaceUuidInArray($data, 'id');

    // $predictableData = [
    //     'id' => '00000000-0000-0000-0000-000000000001', 
    //     'name' => 'test',
    // ];
```

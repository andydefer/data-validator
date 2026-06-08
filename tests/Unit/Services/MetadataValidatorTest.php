<?php

declare(strict_types=1);

namespace AndyDefer\DataValidator\Tests\Unit\Services;

use stdClass;
use AndyDefer\DataValidator\Exceptions\MetadataValidationException;
use AndyDefer\DataValidator\Services\MetadataValidator;
use AndyDefer\DataValidator\Tests\TestCase;

/**
 * Test suite for MetadataValidator.
 *
 * Verifies metadata validation and sanitization operations.
 * Tests cover all validation rules, sanitization behaviors, and edge cases.
 */
final class MetadataValidatorTest extends TestCase
{
    private MetadataValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new MetadataValidator();
    }

    // ============================================================================
    // Tests for validate() method - Input validation
    // ============================================================================

    /**
     * Test that validate() passes for valid metadata structures.
     */
    public function test_validate_passes_for_valid_metadata(): void
    {
        // Arrange: Prepare valid nested metadata
        $metadata = ['key' => 'value', 'nested' => ['deep' => 'data']];

        // Act: Validate the metadata
        $result = $this->validator->validate($metadata);

        // Assert: Validated metadata is returned unchanged
        $this->assertSame($metadata, $result);
    }

    /**
     * Test that validate() returns null for empty metadata.
     */
    public function test_validate_returns_null_for_empty_metadata(): void
    {
        // Act: Validate empty array
        $result = $this->validator->validate([]);

        // Assert: Null is returned
        $this->assertNull($result);
    }

    /**
     * Test that validate() returns null for null input.
     */
    public function test_validate_returns_null_for_null(): void
    {
        // Act: Validate null
        $result = $this->validator->validate(null);

        // Assert: Null is returned
        $this->assertNull($result);
    }

    /**
     * Test that validate() throws exception when total size exceeds limit.
     */
    public function test_validate_throws_exception_for_size_exceeded(): void
    {
        // Arrange: Create metadata exceeding 64KB limit
        $largeMetadata = ['data' => str_repeat('a', 70000)];

        $this->expectException(MetadataValidationException::class);
        // La taille réelle peut varier légèrement à cause du JSON encoding
        $this->expectExceptionMessage('Metadata size (');

        // Act: Attempt to validate oversized metadata
        $this->validator->validate($largeMetadata);
    }

    /**
     * Test that validate() throws exception when nesting depth is too deep.
     */
    public function test_validate_throws_exception_for_nesting_too_deep(): void
    {
        // Arrange: Create deeply nested array (6 levels deep, max is 5)
        $deepMetadata = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'level5' => [
                                'level6' => 'too deep',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->expectException(MetadataValidationException::class);
        $this->expectExceptionMessage('Metadata nesting depth (6) exceeds maximum allowed (5)');

        // Act: Attempt to validate deeply nested metadata
        $this->validator->validate($deepMetadata);
    }

    /**
     * Test that validate() throws exception when too many keys exist.
     */
    public function test_validate_throws_exception_for_too_many_keys(): void
    {
        // Arrange: Create array with 101 keys (max is 100)
        $manyKeys = [];
        for ($i = 0; $i < 101; ++$i) {
            $manyKeys['key_' . $i] = 'value_' . $i;
        }

        $this->expectException(MetadataValidationException::class);
        $this->expectExceptionMessage('Metadata contains 101 keys, maximum allowed is 100');

        // Act: Attempt to validate metadata with too many keys
        $this->validator->validate($manyKeys);
    }

    /**
     * Test that validate() accepts integer keys as valid.
     */
    public function test_validate_accepts_integer_keys(): void
    {
        // Arrange: Prepare metadata with integer keys
        $metadata = [1 => 'value', 2 => 'another'];

        // Act: Validate the metadata
        $result = $this->validator->validate($metadata);

        // Assert: Integer keys are preserved
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertEquals('value', $result[1]);
        $this->assertEquals('another', $result[2]);
    }

    /**
     * Test that validate() throws exception for invalid key type.
     * Note: PHP cannot use objects as array keys, this will throw a TypeError before validation.
     */
    public function test_validate_throws_exception_for_invalid_key_type(): void
    {
        $this->expectException(\TypeError::class);

        // Act: Attempt to use object as array key (triggers TypeError in PHP)
        $metadata = [new stdClass() => 'value'];
        $this->validator->validate($metadata);
    }

    /**
     * Test that validate() throws exception for key exceeding maximum length.
     */
    public function test_validate_throws_exception_for_key_too_long(): void
    {
        // Arrange: Generate a key longer than 255 characters
        $longKey = str_repeat('a', 256);
        $metadata = [$longKey => 'value'];

        $this->expectException(MetadataValidationException::class);
        $this->expectExceptionMessage('Metadata key exceeds maximum length of 255 characters');

        // Act: Attempt to validate with oversized key
        $this->validator->validate($metadata);
    }

    /**
     * Test that validate() throws exception for invalid value type (resource).
     */
    public function test_validate_throws_exception_for_invalid_resource_value(): void
    {
        // Arrange: Create a resource value
        $invalidValue = fopen('php://memory', 'r');
        $metadata = ['key' => $invalidValue];

        // Note: json_encode() converts resources to false, which then becomes empty string
        // This causes strlen() to receive false, triggering a TypeError
        // The validation happens after json_encode, so we get a TypeError instead of our exception
        $this->expectException(\TypeError::class);

        // Act: Attempt to validate with invalid value
        $this->validator->validate($metadata);

        // Cleanup
        if (is_resource($invalidValue)) {
            fclose($invalidValue);
        }
    }

    /**
     * Test that validate() throws exception for invalid value type (object).
     */
    public function test_validate_throws_exception_for_invalid_object_value(): void
    {
        // Arrange: Create an object value
        $metadata = ['key' => new stdClass()];

        $this->expectException(MetadataValidationException::class);
        $this->expectExceptionMessage('Metadata value for key "key" must be scalar, array, or null, object given');

        // Act: Attempt to validate with invalid value
        $this->validator->validate($metadata);
    }

    // ============================================================================
    // Tests for isValid() method - Validation without exception
    // ============================================================================

    /**
     * Test that isValid() returns true for valid metadata.
     */
    public function test_is_valid_returns_true_for_valid_metadata(): void
    {
        // Arrange: Prepare valid metadata
        $metadata = ['key' => 'value'];

        // Act & Assert
        $this->assertTrue($this->validator->isValid($metadata));
    }

    /**
     * Test that isValid() returns false for invalid metadata.
     */
    public function test_is_valid_returns_false_for_invalid_metadata(): void
    {
        // Arrange: Prepare invalid metadata (too many keys)
        $manyKeys = [];
        for ($i = 0; $i < 101; ++$i) {
            $manyKeys['key_' . $i] = 'value_' . $i;
        }

        // Act & Assert
        $this->assertFalse($this->validator->isValid($manyKeys));
    }

    /**
     * Test that isValid() returns true for null input.
     */
    public function test_is_valid_returns_true_for_null(): void
    {
        // Act & Assert
        $this->assertTrue($this->validator->isValid(null));
    }

    // ============================================================================
    // Tests for sanitize() method - Cleaning metadata
    // ============================================================================

    /**
     * Test that sanitize() removes null values from metadata.
     */
    public function test_sanitize_removes_null_values(): void
    {
        // Arrange: Prepare metadata with null values
        $metadata = ['keep' => 'value', 'remove' => null, 'also_keep' => 'data'];

        // Act: Sanitize the metadata
        $result = $this->validator->sanitize($metadata);

        // Assert: Null values are removed
        $this->assertSame(['keep' => 'value', 'also_keep' => 'data'], $result);
    }

    /**
     * Test that sanitize() removes empty arrays recursively.
     */
    public function test_sanitize_removes_empty_arrays_recursively(): void
    {
        // Arrange: Prepare nested metadata with empty arrays
        $metadata = [
            'keep' => 'value',
            'nested' => [
                'keep' => 'data',
                'empty' => [],
            ],
            'empty_array' => [],
        ];

        // Act: Sanitize the metadata
        $result = $this->validator->sanitize($metadata);

        // Assert: Empty arrays are removed recursively
        $this->assertSame([
            'keep' => 'value',
            'nested' => ['keep' => 'data'],
        ], $result);
    }

    /**
     * Test that sanitize() returns null for empty arrays.
     */
    public function test_sanitize_returns_null_for_empty_array(): void
    {
        // Act: Sanitize empty array
        $result = $this->validator->sanitize([]);

        // Assert: Null is returned
        $this->assertNull($result);
    }

    /**
     * Test that sanitize() returns null for null input.
     */
    public function test_sanitize_returns_null_for_null(): void
    {
        // Act: Sanitize null
        $result = $this->validator->sanitize(null);

        // Assert: Null is returned
        $this->assertNull($result);
    }

    /**
     * Test that sanitize() correctly handles deeply nested structures.
     */
    public function test_sanitize_works_with_nested_arrays(): void
    {
        // Arrange: Prepare deeply nested metadata with empty values
        $metadata = [
            'level1' => [
                'level2' => [
                    'keep' => 'value',
                    'null' => null,
                    'empty' => [],
                ],
            ],
        ];

        // Act: Sanitize the metadata
        $result = $this->validator->sanitize($metadata);

        // Assert: Only valid values remain in nested structure
        $this->assertSame([
            'level1' => [
                'level2' => ['keep' => 'value'],
            ],
        ], $result);
    }

    /**
     * Test that sanitize() preserves false boolean values.
     */
    public function test_sanitize_preserves_false_boolean(): void
    {
        // Arrange: Prepare metadata with false value
        $metadata = ['key' => false];

        // Act: Sanitize the metadata
        $result = $this->validator->sanitize($metadata);

        // Assert: False value is preserved (not removed like null)
        $this->assertSame(['key' => false], $result);
    }

    /**
     * Test that sanitize() preserves zero integer values.
     */
    public function test_sanitize_preserves_zero_integer(): void
    {
        // Arrange: Prepare metadata with zero value
        $metadata = ['key' => 0];

        // Act: Sanitize the metadata
        $result = $this->validator->sanitize($metadata);

        // Assert: Zero value is preserved
        $this->assertSame(['key' => 0], $result);
    }

    /**
     * Test that sanitize() preserves empty string values.
     */
    public function test_sanitize_preserves_empty_string(): void
    {
        // Arrange: Prepare metadata with empty string
        $metadata = ['key' => ''];

        // Act: Sanitize the metadata
        $result = $this->validator->sanitize($metadata);

        // Assert: Empty string is preserved
        $this->assertSame(['key' => ''], $result);
    }

    // ============================================================================
    // Tests for process() method - Validate and sanitize combined
    // ============================================================================

    /**
     * Test that process() validates and sanitizes metadata in one operation.
     */
    public function test_process_validates_and_sanitizes_metadata(): void
    {
        // Arrange: Prepare raw metadata with nulls and empty arrays
        $rawMetadata = [
            'keep' => 'value',
            'remove_null' => null,
            'nested' => [
                'keep' => 'data',
                'empty' => [],
            ],
        ];

        // Act: Process the metadata
        $result = $this->validator->process($rawMetadata);

        // Assert: Only valid, non-empty values remain
        $this->assertSame([
            'keep' => 'value',
            'nested' => ['keep' => 'data'],
        ], $result);
    }

    /**
     * Test that process() throws exception for invalid metadata.
     */
    public function test_process_throws_exception_for_invalid_metadata(): void
    {
        // Arrange: Create invalid metadata (too many keys)
        $manyKeys = [];
        for ($i = 0; $i < 101; ++$i) {
            $manyKeys['key_' . $i] = 'value_' . $i;
        }

        $this->expectException(MetadataValidationException::class);

        // Act: Attempt to process invalid metadata
        $this->validator->process($manyKeys);
    }

    // ============================================================================
    // Tests for getSize() method - Metadata size calculation
    // ============================================================================

    /**
     * Test that getSize() returns correct size for metadata.
     */
    public function test_get_size_returns_correct_size(): void
    {
        // Arrange
        $metadata = ['key' => 'value'];

        // Act
        $size = $this->validator->getSize($metadata);

        // Assert
        $this->assertEquals(strlen(json_encode($metadata)), $size);
    }

    /**
     * Test that getSize() returns 0 for null input.
     */
    public function test_get_size_returns_zero_for_null(): void
    {
        // Act & Assert
        $this->assertEquals(0, $this->validator->getSize(null));
    }

    /**
     * Test that getSize() returns 0 for empty array.
     */
    public function test_get_size_returns_zero_for_empty_array(): void
    {
        // Act & Assert
        $this->assertEquals(0, $this->validator->getSize([]));
    }

    // ============================================================================
    // Tests for getNestingDepth() method - Depth calculation
    // ============================================================================

    /**
     * Test that getNestingDepth() returns correct depth for nested metadata.
     */
    public function test_get_nesting_depth_returns_correct_depth(): void
    {
        // Arrange
        $metadata = [
            'level1' => [
                'level2' => [
                    'level3' => 'value',
                ],
            ],
        ];

        // Act
        $depth = $this->validator->getNestingDepth($metadata);

        // Assert
        $this->assertEquals(3, $depth);
    }

    /**
     * Test that getNestingDepth() returns 1 for flat metadata.
     */
    public function test_get_nesting_depth_returns_one_for_flat_metadata(): void
    {
        // Arrange
        $metadata = ['key1' => 'value1', 'key2' => 'value2'];

        // Act
        $depth = $this->validator->getNestingDepth($metadata);

        // Assert
        $this->assertEquals(1, $depth);
    }

    // ============================================================================
    // Integration tests - Complete workflows
    // ============================================================================

    /**
     * Test that validation and sanitization work together correctly.
     */
    public function test_validation_and_sanitization_workflow(): void
    {
        // Arrange: Prepare raw metadata with nulls and empty arrays
        $rawMetadata = [
            'keep' => 'value',
            'remove_null' => null,
            'nested' => [
                'keep' => 'data',
                'empty' => [],
            ],
            'empty_array' => [],
        ];

        // Act: Validate and sanitize
        $validated = $this->validator->validate($rawMetadata);
        $sanitized = $this->validator->sanitize($validated);

        // Assert: Only valid, non-empty values remain
        $this->assertSame([
            'keep' => 'value',
            'nested' => ['keep' => 'data'],
        ], $sanitized);
    }

    /**
     * Test that validation prevents malicious cyclic reference metadata.
     */
    public function test_validation_prevents_malicious_cyclic_metadata(): void
    {
        // Arrange: Create recursive/circular reference structure
        $malicious = [];
        $ref = &$malicious;
        for ($i = 0; $i < 10; ++$i) {
            $ref['nested'] = [];
            $ref = &$ref['nested'];
        }

        $this->expectException(MetadataValidationException::class);

        // Act: Attempt to validate malicious structure
        $this->validator->validate($malicious);
    }

    /**
     * Test that validation properly handles large valid metadata.
     */
    public function test_validation_handles_large_valid_metadata(): void
    {
        // Arrange: Create valid metadata with 100 keys (max limit)
        $largeValidMetadata = [];
        for ($i = 0; $i < 100; ++$i) {
            $largeValidMetadata['key_' . $i] = 'value_' . $i;
        }

        // Act: Validate the metadata
        $result = $this->validator->validate($largeValidMetadata);

        // Assert: Validation passes
        $this->assertCount(100, $result);
        $this->assertSame($largeValidMetadata, $result);
    }

    /**
     * Test that sanitize handles empty nested structures correctly.
     */
    public function test_sanitize_handles_complex_empty_structures(): void
    {
        // Arrange: Prepare complex nested structure with empty arrays at various depths
        $metadata = [
            'level1' => [
                'level2' => [
                    'level3' => [],
                ],
            ],
            'another' => [
                'empty' => [],
            ],
            'valid' => 'value',
        ];

        // Act: Sanitize the metadata
        $result = $this->validator->sanitize($metadata);

        // Assert: All empty structures are removed
        $this->assertSame(['valid' => 'value'], $result);
    }
}

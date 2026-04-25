<?php

declare(strict_types=1);

namespace Kani\DataValidator\Services;

use Kani\DataValidator\Exceptions\MetadataValidationException;

/**
 * Service for validating and sanitizing structured metadata.
 *
 * Handles validation of metadata arrays with security constraints including:
 * - Size limits (max 64KB)
 * - Nesting depth limits (max 5 levels)
 * - Key count limits (max 100 keys)
 * - Key length limits (max 255 characters)
 * - Type validation for keys and values
 * - Recursive sanitization (removes null values and empty arrays)
 *
 * @example
 * // Basic validation
 * use Kani\DataValidator\Services\MetadataValidator;
 *
 * $validated = MetadataValidator::validate([
 *     'user_agent' => 'Mozilla/5.0',
 *     'preferences' => ['theme' => 'dark'],
 *     'null_value' => null // Will be removed during sanitization
 * ]);
 *
 * // Sanitize only
 * $cleaned = MetadataValidator::sanitize($metadata);
 *
 * // Check if metadata is valid without throwing exception
 * if (MetadataValidator::isValid($metadata)) {
 *     // Process metadata
 * }
 */
final class MetadataValidator
{
    /**
     * Maximum allowed size of metadata in bytes (64KB).
     */
    private const MAX_METADATA_SIZE = 65536;

    /**
     * Maximum nesting depth for metadata arrays.
     */
    private const MAX_NESTING_DEPTH = 5;

    /**
     * Maximum number of keys in metadata.
     */
    private const MAX_KEYS = 100;

    /**
     * Maximum length for a metadata key in characters.
     */
    private const MAX_KEY_LENGTH = 255;

    // ============================================================================
    // Public API Methods
    // ============================================================================

    /**
     * Validate metadata structure.
     *
     * Performs validation checks:
     * - Empty/null validation
     * - Total serialized size
     * - Nesting depth
     * - Number of keys
     * - Key types and lengths
     * - Value types
     *
     * @param array|null $metadata The metadata to validate
     * @return array|null The validated metadata or null if empty
     *
     * @throws MetadataValidationException When validation fails
     */
    public static function validate(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        self::validateTotalSize($metadata);
        self::validateNestingDepth($metadata);
        self::validateKeyCount($metadata);
        self::validateAllKeysAndValues($metadata);

        return $metadata;
    }

    /**
     * Check if metadata is valid without throwing exception.
     *
     * @param array|null $metadata The metadata to validate
     * @return bool True if metadata is valid, false otherwise
     */
    public static function isValid(?array $metadata): bool
    {
        try {
            self::validate($metadata);
            return true;
        } catch (MetadataValidationException) {
            return false;
        }
    }

    /**
     * Sanitize metadata by removing null values and empty arrays.
     *
     * Recursively cleans the metadata structure:
     * - Removes entries with null values
     * - Removes empty arrays
     * - Returns null for completely empty metadata
     *
     * @param array|null $metadata The metadata to sanitize
     * @return array|null Sanitized metadata or null if empty
     */
    public static function sanitize(?array $metadata): ?array
    {
        if ($metadata === null || $metadata === []) {
            return null;
        }

        $sanitized = [];

        foreach ($metadata as $key => $value) {
            // Skip null values
            if ($value === null) {
                continue;
            }

            // Recursively sanitize arrays
            if (is_array($value)) {
                $value = self::sanitize($value);
                // Skip empty arrays
                if ($value === null || $value === []) {
                    continue;
                }
            }

            $sanitized[$key] = $value;
        }

        return $sanitized === [] ? null : $sanitized;
    }

    /**
     * Validate and sanitize metadata in one operation.
     *
     * @param array|null $metadata The metadata to validate and sanitize
     * @return array|null Validated and sanitized metadata, or null if empty
     *
     * @throws MetadataValidationException When validation fails
     */
    public static function process(?array $metadata): ?array
    {
        $validated = self::validate($metadata);
        return self::sanitize($validated);
    }

    /**
     * Get size of metadata in bytes (JSON serialized).
     *
     * @param array|null $metadata The metadata to measure
     * @return int Size in bytes, or 0 if metadata is null/empty
     */
    public static function getSize(?array $metadata): int
    {
        if ($metadata === null || $metadata === []) {
            return 0;
        }

        return strlen(json_encode($metadata));
    }

    /**
     * Get nesting depth of metadata.
     *
     * @param array $metadata The metadata to analyze
     * @param int $currentDepth Current depth (used internally for recursion)
     * @return int Maximum nesting depth
     */
    public static function getNestingDepth(array $metadata, int $currentDepth = 1): int
    {
        $maxDepth = $currentDepth;

        foreach ($metadata as $value) {
            if (is_array($value)) {
                $depth = self::getNestingDepth($value, $currentDepth + 1);
                $maxDepth = max($maxDepth, $depth);
            }
        }

        return $maxDepth;
    }

    // ============================================================================
    // Validation Methods
    // ============================================================================

    /**
     * Validate the total serialized size of metadata.
     *
     * @param array $metadata The metadata to check
     *
     * @throws MetadataValidationException When size exceeds limit
     */
    private static function validateTotalSize(array $metadata): void
    {
        $jsonSize = self::getSize($metadata);

        if ($jsonSize > self::MAX_METADATA_SIZE) {
            throw MetadataValidationException::sizeExceeded($jsonSize, self::MAX_METADATA_SIZE);
        }
    }

    /**
     * Validate the nesting depth of metadata arrays.
     *
     * @param array $metadata The metadata to check
     * @param int $currentDepth Current depth in recursion
     *
     * @throws MetadataValidationException When depth exceeds limit
     */
    private static function validateNestingDepth(array $metadata, int $currentDepth = 1): void
    {
        if ($currentDepth > self::MAX_NESTING_DEPTH) {
            throw MetadataValidationException::nestingTooDeep($currentDepth, self::MAX_NESTING_DEPTH);
        }

        foreach ($metadata as $value) {
            if (is_array($value)) {
                self::validateNestingDepth($value, $currentDepth + 1);
            }
        }
    }

    /**
     * Validate that the number of metadata keys does not exceed the limit.
     *
     * @param array $metadata The metadata to check
     *
     * @throws MetadataValidationException When key count exceeds limit
     */
    private static function validateKeyCount(array $metadata): void
    {
        $keyCount = count($metadata);

        if ($keyCount > self::MAX_KEYS) {
            throw MetadataValidationException::tooManyKeys($keyCount, self::MAX_KEYS);
        }
    }

    /**
     * Validate all keys and their associated values.
     *
     * @param array $metadata The metadata to validate
     *
     * @throws MetadataValidationException When any key or value is invalid
     */
    private static function validateAllKeysAndValues(array $metadata): void
    {
        foreach ($metadata as $key => $value) {
            self::validateKeyType($key);

            $keyString = (string) $key;
            self::validateKeyLength($keyString);
            self::validateValueType($value, $keyString);
        }
    }

    /**
     * Validate that a key is of an acceptable type (string or int).
     *
     * @param mixed $key The key to validate
     *
     * @throws MetadataValidationException When key type is invalid
     */
    private static function validateKeyType(mixed $key): void
    {
        if (!is_string($key) && !is_int($key)) {
            throw MetadataValidationException::invalidKeyType(gettype($key));
        }
    }

    /**
     * Validate that a key does not exceed maximum length.
     *
     * @param string $key The key to validate
     *
     * @throws MetadataValidationException When key exceeds maximum length
     */
    private static function validateKeyLength(string $key): void
    {
        $length = strlen($key);

        if ($length > self::MAX_KEY_LENGTH) {
            throw MetadataValidationException::keyTooLong($key, $length, self::MAX_KEY_LENGTH);
        }
    }

    /**
     * Validate that a value is of an acceptable type.
     *
     * @param mixed $value The value to validate
     * @param string|null $key Optional key name for error context
     *
     * @throws MetadataValidationException When value type is invalid
     */
    private static function validateValueType(mixed $value, ?string $key = null): void
    {
        if (!self::isValidValue($value)) {
            throw MetadataValidationException::invalidValueType($key, gettype($value));
        }
    }

    /**
     * Check if a value is of a valid type.
     *
     * Valid types: scalar (string, int, float, bool), array, or null.
     *
     * @param mixed $value The value to check
     * @return bool True if value type is valid, false otherwise
     */
    private static function isValidValue(mixed $value): bool
    {
        return is_scalar($value) || is_array($value) || $value === null;
    }
}

<?php

namespace Spaseossr\UnifiedApi\Testing;

use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Assert;

/**
 * Shape-only contract snapshots for the unified envelope.
 *
 * The resolved props of a unified page ARE the API contract for native
 * clients. This helper freezes their shape (never their values) so a web
 * refactor that renames, retypes or drops a prop fails CI loudly instead of
 * breaking shipped mobile apps silently.
 */
class EnvelopeSnapshot
{
    protected static ?string $snapshotPath = null;

    /**
     * Override where snapshots are stored; null restores the default.
     */
    public static function usingSnapshotPath(?string $path): void
    {
        static::$snapshotPath = $path;
    }

    /**
     * Directory holding the snapshot files.
     */
    public static function snapshotPath(): string
    {
        return static::$snapshotPath ?? base_path('tests/Snapshots/UnifiedApi');
    }

    /**
     * Run a unified request and compare its envelope shape to the snapshot.
     *
     * @param  callable(): TestResponse|JsonResponse  $request
     */
    public function __invoke(string $name, callable $request): void
    {
        $response = $request();
        $status = $this->statusOf($response);

        if ($status < 200 || $status >= 300) {
            Assert::fail(
                "envelopeSnapshot expects a successful (2xx) response, got [{$status}]. ".
                'Arrange authentication and state inside the request callable — '.
                'error envelopes are not contracts.'
            );
        }

        $shape = $this->normalize($this->payloadOf($response));
        $file = static::snapshotPath().'/'.$this->fileName($name);

        if ($this->shouldUpdate() || ! is_file($file)) {
            $this->write($file, $shape);

            return;
        }

        /** @var array<string, mixed>|null $expected */
        $expected = json_decode((string) file_get_contents($file), true);

        Assert::assertEquals(
            $expected,
            $shape,
            "Envelope shape mismatch for snapshot [{$name}] ({$file}).\n".
            "Additive change (new keys only)? Regenerate:\n".
            "    ENVELOPE_SNAPSHOT_UPDATE=1 vendor/bin/phpunit\n".
            'Removing/renaming/retyping keys is BREAKING: bump UNIFIED_API_VERSION, '.
            'update consumers, and regenerate in the same commit.'
        );
    }

    /**
     * Reduce a decoded envelope to its shape: nested key trees with JSON
     * type names, lists collapsed to their first element's shape.
     *
     * @return array<string, mixed>|string
     */
    protected function normalize(mixed $value): array|string
    {
        if (is_array($value)) {
            if ($value === []) {
                return 'empty';
            }

            if (array_is_list($value)) {
                return [$this->normalize($value[0])];
            }

            return array_map($this->normalize(...), $value);
        }

        return match (gettype($value)) {
            'integer' => 'int',
            'double' => 'float',
            'boolean' => 'bool',
            'NULL' => 'null',
            'string' => 'string',
            default => 'mixed',
        };
    }

    protected function fileName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $name).'.json';
    }

    protected function shouldUpdate(): bool
    {
        return filter_var(getenv('ENVELOPE_SNAPSHOT_UPDATE'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $shape
     */
    protected function write(string $file, array $shape): void
    {
        $directory = dirname($file);

        if (! is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        file_put_contents($file, json_encode(
            $shape,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ).PHP_EOL);
    }

    protected function statusOf(TestResponse|JsonResponse $response): int
    {
        return $response->status();
    }

    /**
     * @return array<string, mixed>
     */
    protected function payloadOf(TestResponse|JsonResponse $response): array
    {
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getContent(), true);

        Assert::assertIsArray(
            $payload,
            'envelopeSnapshot requires a JSON response body.'
        );

        return $payload;
    }
}

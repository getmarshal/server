<?php

declare(strict_types=1);

namespace Marshal\Server\Response;

use RuntimeException;
use Psr\Http\Message\StreamInterface;

/**
 * A PSR-7 stream for Server-Sent Events that serves two roles simultaneously:
 *
 *  1. **Live output** — every write is forwarded to php://output so bytes
 *     reach the client immediately. Call {@see flush()} after each event to
 *     drain PHP's output buffers and the SAPI send-buffer.
 *
 *  2. **In-memory buffer** — every write is also appended to an internal
 *     string buffer, making the stream readable and seekable. This satisfies
 *     PSR-7 middleware that calls getBody()->getContents() or
 *     (string) $response->getBody() after emission.
 *
 * The two roles are not in conflict because php://output is append-only:
 * seeking applies only to the in-memory buffer (for readback), while all
 * writes always go to both destinations.
 *
 * Lifecycle:
 *   - Instantiated by {@see SseResponse} as the PSR-7 body placeholder.
 *   - {@see \Marshal\Server\Response\SseEmitter} calls write() + flush() for
 *     each serialised {@see \Marshal\Server\Response\SseEvent} during emission.
 *   - After emission, middleware may call getContents() / read() / rewind()
 *     on the same instance to inspect the full body.
 */
class SseStream implements StreamInterface
{
    /** Mirror of everything written, used for PSR-7 readback. */
    private string $buffer = '';

    /** Read cursor into $buffer; does not affect php://output. */
    private int $position = 0;

    private bool $closed = false;

    /** @var resource|null php://output handle */
    private mixed $resource;

    /**
     * @param resource|null $resource Output resource (defaults to php://output).
     */
    public function __construct(mixed $resource = null)
    {
        if ($resource === null) {
            $resource = fopen('php://output', 'wb');

            if ($resource === false) {
                throw new RuntimeException('Unable to open php://output for SSE streaming.');
            }
        }

        $this->resource = $resource;
    }

    // =========================================================================
    // SSE-specific helper
    // =========================================================================

    /**
     * Flush all PHP output-buffer levels and the underlying SAPI send-buffer
     * so the bytes written since the last flush reach the client immediately.
     *
     * Safe to call even after close() — it simply becomes a no-op.
     */
    public function flush(): void
    {
        if ($this->closed || $this->resource === null) {
            return;
        }

        while (\ob_get_level() > 0) {
            \ob_end_flush();
        }

        \flush();

        if (\is_resource($this->resource)) {
            \fflush($this->resource);
        }
    }

    // =========================================================================
    // PSR-7 StreamInterface — writes go to both output and buffer
    // =========================================================================

    /**
     * Write $string to php://output and append it to the internal buffer.
     *
     * Returns the number of bytes written to the output resource.
     */
    public function write(string $string): int
    {
        $this->assertOpen();

        // 1. Forward to php://output (live client delivery).
        $bytes = \fwrite($this->resource, $string);

        if ($bytes === false) {
            throw new RuntimeException('Failed to write to SseStream.');
        }

        // 2. Append to in-memory buffer (PSR-7 readback).
        $this->buffer   .= $string;
        $this->position += \strlen($string);

        return $bytes;
    }

    public function isWritable(): bool
    {
        return !$this->closed && $this->resource !== null;
    }

    // =========================================================================
    // PSR-7 StreamInterface — reads come from the in-memory buffer
    // =========================================================================

    public function isReadable(): bool
    {
        return ! $this->closed;
    }

    public function read(int $length): string
    {
        $this->assertOpen();

        $chunk          = \substr($this->buffer, $this->position, $length);
        $this->position += \strlen($chunk);

        return $chunk;
    }

    public function getContents(): string
    {
        $this->assertOpen();

        $contents       = \substr($this->buffer, $this->position);
        $this->position = \strlen($this->buffer);

        return $contents;
    }

    public function __toString(): string
    {
        return $this->buffer;
    }

    // =========================================================================
    // PSR-7 StreamInterface — seeking applies to the buffer read cursor only
    // =========================================================================

    public function isSeekable(): bool
    {
        return ! $this->closed;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->assertOpen();

        $length = \strlen($this->buffer);

        $this->position = match ($whence) {
            SEEK_SET => $offset,
            SEEK_CUR => $this->position + $offset,
            SEEK_END => $length + $offset,
            default  => throw new RuntimeException("Invalid seek whence: {$whence}."),
        };

        if ($this->position < 0) {
            $this->position = 0;
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    // =========================================================================
    // PSR-7 StreamInterface — lifecycle
    // =========================================================================

    public function close(): void
    {
        if ($this->resource !== null && \is_resource($this->resource)) {
            \fclose($this->resource);
        }

        $this->resource = null;
        $this->closed   = true;
    }

    public function detach(): mixed
    {
        $resource       = $this->resource;
        $this->resource = null;
        $this->closed   = true;

        return $resource;
    }

    public function eof(): bool
    {
        return $this->closed || $this->position >= \strlen($this->buffer);
    }

    public function getSize(): ?int
    {
        // Returns the number of bytes buffered so far; grows with each write.
        return \strlen($this->buffer);
    }

    public function tell(): int
    {
        $this->assertOpen();

        return $this->position;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($this->resource === null || ! \is_resource($this->resource)) {
            return $key !== null ? null : [];
        }

        $meta = \stream_get_meta_data($this->resource);

        return $key !== null ? ($meta[$key] ?? null) : $meta;
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    private function assertOpen(): void
    {
        if ($this->closed || $this->resource === null) {
            throw new RuntimeException('Stream is closed.');
        }
    }
}

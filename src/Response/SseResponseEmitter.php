<?php

declare(strict_types=1);

namespace Marshal\Server\Response;

use Laminas\HttpHandlerRunner\Emitter\EmitterInterface;
use Laminas\HttpHandlerRunner\Exception\EmitterException;
use Psr\Http\Message\ResponseInterface;

/**
 * Emits a {@see \Marshal\Server\Response\SseResponse} to the SAPI output.
 *
 * This is the sole class responsible for writing SSE data to the wire.
 * {@see \Marshal\Server\Response\SseResponse} is a pure data container that queues {@see \Marshal\Server\Response\SseEvent}
 * objects; this emitter drains that queue, serialises each event, and streams
 * it to the client with an immediate flush after every write.
 *
 * Non-SseResponse instances return `false` immediately, allowing an
 * {@see \Laminas\HttpHandlerRunner\Emitter\EmitterStack} to fall through
 * to the next emitter (e.g. SapiEmitter) for ordinary responses.
 */
final class SseResponseEmitter implements EmitterInterface
{
    /**
     * @param int $timeLimit
     *   PHP execution time limit in seconds. 0 = unlimited.
     *   Set a finite value per-endpoint if you need a hard cap on stream duration.
     *
     * @param int $bufferLevel
     *   Output-buffer levels to drain before streaming.
     *   -1 = drain all levels (default).
     *    0 = leave output buffering untouched (manage it yourself).
     *   >0 = drain exactly that many levels.
     */
    public function __construct(
        private readonly int $timeLimit   = 0,
        private readonly int $bufferLevel = -1,
    ) {
    }

    /**
     * Emit the SSE response.
     *
     * Returns `false` for non-{@see \Marshal\Server\Response\SseResponse} instances so that an
     * {@see \Laminas\HttpHandlerRunner\Emitter\EmitterStack} can pass the
     * response to the next emitter in the stack.
     *
     * @throws EmitterException If headers have already been sent.
     * @throws EmitterException If buffered output is present before emission.
     */
    public function emit(ResponseInterface $response): bool
    {
        if (! $response instanceof SseResponse) {
            return false;
        }

        $this->assertNoPreviousOutput();
        $this->prepareRuntime();
        $this->emitHeaders($response);
        $this->emitStatusLine($response);
        $this->drainOutputBuffers();

        // Flush SAPI send-buffer so headers reach the client before
        // the first event byte is written.
        \flush();

        $this->streamEvents($response);

        return true;
    }

    // =========================================================================
    // Event streaming
    // =========================================================================

    /**
     * Iterate the response event queue, write each serialised event to
     * php://output, and flush immediately so it reaches the client.
     *
     * Also writes the serialised payload back into the response body
     * (SseBufferStream) so that PSR-7 middleware inspecting getBody()
     * sees the full stream content.
     */
    private function streamEvents(SseResponse $response): void
    {
        $stream = $response->getBody();
 
        if (! $stream instanceof SseStream) {
            // Body was replaced with a non-SseStream — we cannot flush.
            // Fall back to plain writes without per-event flushing.
            foreach ($response->getEvents() as $event) {
                if (connection_aborted()) {
                    return;
                }
                $stream->write($event->toString());
            }
            return;
        }
 
        foreach ($response->getEvents() as $event) {
            if (connection_aborted()) {
                break;
            }
 
            $stream->write($event->toString());
            $stream->flush();
        }
 
        // Append a final comment so the client EventSource parser receives a
        // clean end-of-stream signal even if no further events were queued.
        if (! connection_aborted()) {
            $stream->write(": stream-close\n\n");
            $stream->flush();
        }
 
        $stream->close();

    }

    // =========================================================================
    // Runtime preparation
    // =========================================================================

    /**
     * Extend the execution time limit and disable output compression/buffering
     * settings that would prevent events from reaching the client immediately.
     *
     * Note: ini_set() only affects settings not locked at the php.ini level.
     * If zlib.output_compression is On in php.ini you may need to disable it
     * via Apache/Nginx config or a .htaccess directive instead.
     */
    private function prepareRuntime(): void
    {
        \set_time_limit($this->timeLimit);

        if (\ini_get('zlib.output_compression')) {
            \ini_set('zlib.output_compression', '0');
        }

        if (\ini_get('output_buffering')) {
            \ini_set('output_buffering', '0');
        }
    }

    /**
     * Drain PHP output-buffer levels so no buffered output precedes the stream.
     *
     * $bufferLevel === -1  → drain all levels
     * $bufferLevel ===  0  → no-op (caller manages buffering)
     * $bufferLevel  >   0  → drain exactly that many levels
     */
    private function drainOutputBuffers(): void
    {
        if ($this->bufferLevel === 0) {
            return;
        }

        $max        = $this->bufferLevel === -1 ? PHP_INT_MAX : $this->bufferLevel;
        $iterations = 0;

        while (\ob_get_level() > 0 && $iterations < $max) {
            \ob_end_flush();
            $iterations++;
        }
    }

    // =========================================================================
    // Output guards
    // =========================================================================

    /**
     * Assert that no headers or body output have been sent yet.
     *
     * Mirrors the guard in {@see \Laminas\HttpHandlerRunner\Emitter\SapiEmitterTrait}.
     *
     * @throws EmitterException
     */
    private function assertNoPreviousOutput(): void
    {
        $filename = null;
        $line     = null;

        if ($this->headersSent($filename, $line)) {
            \assert(\is_string($filename) && \is_int($line));
            throw EmitterException::forHeadersSent($filename, $line);
        }

        if (\ob_get_level() > 0 && \ob_get_length() > 0) {
            throw EmitterException::forOutputSent();
        }
    }

    // =========================================================================
    // Header emission
    // =========================================================================

    /**
     * Send the HTTP status line.
     *
     * Called AFTER emitHeaders() — PHP uses the last status-bearing header()
     * call as the final code, so this must come last to win.
     */
    private function emitStatusLine(ResponseInterface $response): void
    {
        $reasonPhrase = $response->getReasonPhrase();
        $statusCode   = $response->getStatusCode();

        $this->sendHeader(
            \sprintf(
                'HTTP/%s %d%s',
                $response->getProtocolVersion(),
                $statusCode,
                $reasonPhrase !== '' ? ' ' . $reasonPhrase : '',
            ),
            true,
            $statusCode,
        );
    }

    /**
     * Send all response headers.
     *
     * Multi-value headers are sent as separate header() calls except for
     * Set-Cookie, which must always have replace=false. This matches the
     * behaviour of Laminas' own SapiEmitter.
     */
    private function emitHeaders(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();

        foreach ($response->getHeaders() as $name => $values) {
            \assert(\is_string($name));
            $normalized = $this->normalizeHeaderName($name);
            $first      = $normalized !== 'Set-Cookie';

            foreach ($values as $value) {
                $this->sendHeader(
                    \sprintf('%s: %s', $normalized, $value),
                    $first,
                    $statusCode,
                );
                $first = false;
            }
        }
    }

    /**
     * Normalise a header name to Title-Case (e.g. "content-type" → "Content-Type").
     */
    private function normalizeHeaderName(string $name): string
    {
        return \ucwords($name, '-');
    }

    /**
     */
    private function sendHeader(string $headerLine, bool $replace, int $statusCode): void
    {
        \header($headerLine, $replace, $statusCode);
    }

    /**
     */
    private function headersSent(?string &$filename, ?int &$line): bool
    {
        return \headers_sent($filename, $line);
    }
}

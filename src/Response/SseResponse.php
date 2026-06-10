<?php

declare(strict_types=1);

namespace Marshal\Server\Response;

use Laminas\Diactoros\Response;
use Psr\Http\Message\StreamInterface;

/**
 * HTTP response for Server-Sent Events (SSE).
 *
 * This class is a pure data container. It:
 *  - Sets the required SSE headers (Content-Type, Cache-Control, X-Accel-Buffering).
 *  - Maintains an ordered queue of {@see SseEvent} objects via {@see self::withEvent()}.
 *  - Exposes that queue to {@see \Marshal\Server\Response\SseResponseEmitter}, which is the
 *    sole class responsible for writing events to the wire.
 */
class SseResponse extends Response
{
    // -------------------------------------------------------------------------
    // SSE-required headers
    // -------------------------------------------------------------------------
    private const SSE_HEADERS = [
        'Content-Type'      => ['text/event-stream'],
        'Cache-Control'     => ['no-cache'],
        'X-Accel-Buffering' => ['no'],  // Disable Nginx proxy buffering
    ];

    // -------------------------------------------------------------------------
    // PSR-7 state
    // -------------------------------------------------------------------------
    private string $protocol;

    private StreamInterface $body;

    // -------------------------------------------------------------------------
    // SSE event queue
    // -------------------------------------------------------------------------

    /** @var SseEvent[] */
    private array $events = [];

    /**
     * Lazy iterable used in generator mode. When non-null this takes
     * precedence over $events. Typed as iterable<SseEvent>.
     *
     * @var iterable<SseEvent>|null
     */
    private ?iterable $eventSource = null;


    public function __construct(
        int              $status          = 200,
        array            $headers         = [],
        ?StreamInterface $body            = null,
        string           $protocolVersion = '1.1'
    ) {
        $this->protocol = $protocolVersion;

        // A plain in-memory stream; the SseEmitter does not use it directly —
        // it reads from getEvents() instead. Kept for PSR-7 compliance.
        $this->body = $body ?? new SseStream();

        parent::__construct($this->body, $status, \array_merge(self::SSE_HEADERS, $headers));
    }

    // =========================================================================
    // SSE event queue
    // =========================================================================

    /**
     * Return a new instance with the given event appended to the queue.
     *
     * This is the only way to add events; the response itself never writes
     * to the output — that is the emitter's job.
     */
    public function withEvent(SseEvent $event): static
    {
        $clone = clone $this;
        $clone->events[] = $event;

        return $clone;
    }
 
    /**
     * Return a new instance whose events come from a lazy iterable.
     *
     * Pass a generator function or any iterable<SseEvent>. The emitter will
     * iterate it lazily, flushing each yielded event to the client as it
     * arrives. The generator is responsible for blocking between yields
     * (e.g. via sleep(), a blocking queue read, or a database LISTEN loop).
     *
     * When set, this takes precedence over any events queued with withEvent().
     *
     * @param iterable<SseEvent> $source
     */
    public function withEventSource(iterable $source): static
    {
        $clone = clone $this;
        $clone->eventSource = $source;
 
        return $clone;
    }


    /**
     * Return the active event iterable for this response.
     *
     * The emitter calls this once and iterates the result. When a generator
     * source is set it is returned directly; otherwise the upfront array is
     * returned. Either way the emitter loop is identical.
     *
     * @return iterable<SseEvent>
     */
    public function getEvents(): iterable
    {
        return $this->eventSource ?? $this->events;
    }
}

<?php

declare(strict_types=1);

namespace Marshal\Server\Response;

use starfederation\datastar\Consts;
use starfederation\datastar\enums\ElementPatchMode;
use starfederation\datastar\enums\EventType;
use starfederation\datastar\enums\NamespaceType;
use starfederation\datastar\events\EventInterface;
use starfederation\datastar\events\ExecuteScript;
use starfederation\datastar\events\PatchElements;
use starfederation\datastar\events\PatchSignals;
use starfederation\datastar\events\RemoveElements;

/**
 * An immutable value object wrapping a Datastar {@see EventInterface} for use
 * in an {@see SseResponse} event queue.
 *
 * Rather than re-implementing SSE wire formatting, this class delegates to the
 * official `starfederation/datastar` event classes, which already produce
 * correct `event: datastar-*` output via their {@see EventInterface::getOutput()}
 * method. The named constructors mirror the four operations Datastar defines,
 * plus a bare comment constructor for keep-alive pings.
 *
 * Named constructors:
 *
 *   SseEvent::patchElements('<div id="out">Hello</div>')
 *   SseEvent::patchElements('<div id="out">Hello</div>', selector: '#out', mode: ElementPatchMode::Inner)
 *
 *   SseEvent::patchSignals(['count' => 1])
 *   SseEvent::patchSignals('{"count":1}', onlyIfMissing: true)
 *
 *   SseEvent::removeElements('#old-row')
 *   SseEvent::removeElements('#old-row', useViewTransition: true)
 *
 *   SseEvent::executeScript('console.log("hi")')
 *   SseEvent::executeScript('console.log("hi")', autoRemove: false, attributes: ['type' => 'module'])
 *
 *   SseEvent::comment('keep-alive')   // SSE comment; invisible to EventSource
 *
 * Each named constructor also accepts $eventId and $retryDuration, which map
 * to the SSE `id:` and `retry:` fields respectively.
 *
 * toString() / __toString() returns the complete SSE wire representation,
 * including the trailing double-newline.
 */
final class SseEvent
{
    /** Holds the Datastar event when this is a proper event (not a comment). */
    private ?EventInterface $inner;

    /** Holds the comment text when $isComment is true. */
    private string $commentText;

    private bool $isComment;

    // -------------------------------------------------------------------------
    // Private constructor — use named constructors below
    // -------------------------------------------------------------------------

    private function __construct(EventInterface|null $inner, string $commentText = '', bool $isComment = false)
    {
        $this->inner       = $inner;
        $this->commentText = $commentText;
        $this->isComment   = $isComment;
    }

    // =========================================================================
    // Named constructors
    // =========================================================================

    /**
     * Patch one or more HTML elements into the DOM.
     *
     * @param string            $elements           One or more complete HTML elements.
     * @param string            $selector           CSS selector for the target element.
     * @param ElementPatchMode  $mode               How to patch the element into the DOM.
     * @param NamespaceType     $namespace          XML namespace (html / svg / mathml).
     * @param bool              $useViewTransition  Use the View Transition API.
     * @param string            $viewTransitionSelector  CSS selector for the view transition target.
     * @param string|null       $eventId            SSE `id:` field.
     * @param int|null          $retryDuration      SSE `retry:` field in milliseconds.
     */
    public static function patchElements(
        string           $elements,
        string           $selector            = '',
        ElementPatchMode $mode                = Consts::DEFAULT_ELEMENT_PATCH_MODE,
        NamespaceType    $namespace           = Consts::DEFAULT_NAMESPACE,
        bool             $useViewTransition   = Consts::DEFAULT_ELEMENTS_USE_VIEW_TRANSITIONS,
        string           $viewTransitionSelector = '',
        ?string          $eventId             = null,
        ?int             $retryDuration       = null,
    ): self {
        $options = array_filter([
            'selector'               => $selector,
            'mode'                   => $mode,
            'namespace'              => $namespace,
            'useViewTransition'      => $useViewTransition,
            'viewTransitionSelector' => $viewTransitionSelector,
            'eventId'                => $eventId,
            'retryDuration'          => $retryDuration,
        ], fn($v) => $v !== null && $v !== '' && $v !== false);

        return new self(new PatchElements($elements, $options));
    }

    /**
     * Patch signals into the Datastar signal store using RFC 7386 JSON Merge Patch.
     *
     * Setting a key to null removes it from the store.
     *
     * @param array|string  $signals        Signal data as an array (auto-encoded) or a raw JSON string.
     * @param bool          $onlyIfMissing  Only patch signals that do not already exist in the store.
     * @param string|null   $eventId        SSE `id:` field.
     * @param int|null      $retryDuration  SSE `retry:` field in milliseconds.
     */
    public static function patchSignals(
        array|string $signals,
        bool         $onlyIfMissing = Consts::DEFAULT_PATCH_SIGNALS_ONLY_IF_MISSING,
        ?string      $eventId       = null,
        ?int         $retryDuration = null,
    ): self {
        $options = array_filter([
            'onlyIfMissing' => $onlyIfMissing ?: null,
            'eventId'       => $eventId,
            'retryDuration' => $retryDuration,
        ]);

        return new self(new PatchSignals($signals, $options));
    }

    /**
     * Remove an element from the DOM by CSS selector.
     *
     * @param string        $selector               CSS selector identifying the element to remove.
     * @param bool          $useViewTransition      Use the View Transition API.
     * @param string        $viewTransitionSelector CSS selector for the view transition target.
     * @param string|null   $eventId                SSE `id:` field.
     * @param int|null      $retryDuration          SSE `retry:` field in milliseconds.
     */
    public static function removeElements(
        string  $selector,
        bool    $useViewTransition      = Consts::DEFAULT_ELEMENTS_USE_VIEW_TRANSITIONS,
        string  $viewTransitionSelector = '',
        ?string $eventId                = null,
        ?int    $retryDuration          = null,
    ): self {
        $options = array_filter([
            'useViewTransition'      => $useViewTransition ?: null,
            'viewTransitionSelector' => $viewTransitionSelector,
            'eventId'                => $eventId,
            'retryDuration'          => $retryDuration,
        ]);

        return new self(new RemoveElements($selector, $options));
    }

    /**
     * Execute JavaScript in the browser by appending a <script> tag to <body>.
     *
     * @param string        $script         JavaScript to execute.
     * @param bool          $autoRemove     Remove the <script> tag after execution (default true).
     * @param array         $attributes     Additional HTML attributes for the <script> tag.
     * @param string|null   $eventId        SSE `id:` field.
     * @param int|null      $retryDuration  SSE `retry:` field in milliseconds.
     */
    public static function executeScript(
        string  $script,
        bool    $autoRemove    = true,
        array   $attributes    = [],
        ?string $eventId       = null,
        ?int    $retryDuration = null,
    ): self {
        $options = array_filter([
            'autoRemove'    => $autoRemove,
            'attributes'    => $attributes ?: null,
            'eventId'       => $eventId,
            'retryDuration' => $retryDuration,
        ]);

        return new self(new ExecuteScript($script, $options));
    }

    /**
     * Create an SSE comment line.
     *
     * Comments (`: text`) are invisible to the browser's EventSource API but
     * travel over the wire. Use them as keep-alive pings or as a stream-close
     * signal so proxies don't time out the connection.
     *
     * @param string $text Comment body (the part after ": ").
     */
    public static function comment(string $text = ''): self
    {
        return new self(inner: null, commentText: $text, isComment: true);
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    /**
     * The underlying Datastar event, or null for comment instances.
     */
    public function getInner(): ?EventInterface
    {
        return $this->inner;
    }

    public function isComment(): bool
    {
        return $this->isComment;
    }

    /**
     * The Datastar {@see EventType} for this event, or null for comment instances.
     */
    public function getEventType(): ?EventType
    {
        return $this->inner?->getEventType();
    }

    // =========================================================================
    // Serialisation
    // =========================================================================

    /**
     * Return the complete SSE wire representation.
     *
     * For Datastar events this delegates to {@see EventInterface::getOutput()},
     * which produces the `event: datastar-*` / `data: …` lines with the
     * correct trailing double-newline.
     *
     * For comments it returns `: <text>\n\n`.
     */
    public function toString(): string
    {
        if ($this->isComment) {
            return ': ' . $this->commentText . "\n\n";
        }

        return $this->inner->getOutput();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}

<?php

declare(strict_types=1);

namespace WyriHaximus\React\Stream\Json;

use Evenement\EventEmitter;
use React\Promise\Deferred;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
use Rx\Observable;
use Rx\ObservableInterface;
use SplQueue;

use function array_keys;
use function is_array;
use function is_string;
use function React\Promise\resolve;
use function Safe\json_encode;
use function trim;

use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_PRESERVE_ZERO_FRACTION;

final class JsonStream extends EventEmitter implements ReadableStreamInterface
{
    public const OBJECT_BEGINNING     = '{';
    public const OBJECT_ENDING        = '}';
    public const ARRAY_BEGINNING      = '[';
    public const ARRAY_ENDING         = ']';
    public const DEFAULT_ENCODE_FLAGS = JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_PRESERVE_ZERO_FRACTION;

    /** @var SplQueue<array{id: int, key: string|null, value: mixed}> */
    private SplQueue $queue;
    private int|null $currentId = null;
    private bool $closing       = false;
    private bool $first         = true;
    private bool $typeDetected  = false;
    private int $i              = 0;
    private string $beginning   = self::OBJECT_BEGINNING;
    private string $ending      = self::OBJECT_ENDING;
    private bool $readable      = true;
    private bool $paused        = false;
    private string $buffer      = '';

    /** @phpstan-ignore-next-line */
    public function __construct(private int $encodeFlags = self::DEFAULT_ENCODE_FLAGS)
    {
        /** @psalm-suppress MixedPropertyTypeCoercion */
        $this->queue = new SplQueue();
    }

    public static function createArray(): JsonStream
    {
        $self               = new self();
        $self->typeDetected = true;
        $self->beginning    = self::ARRAY_BEGINNING;
        $self->ending       = self::ARRAY_ENDING;

        return $self;
    }

    public static function createObject(): JsonStream
    {
        $self               = new self();
        $self->typeDetected = true;

        return $self;
    }

    public function write(string $key, mixed $value): void
    {
        if ($this->closing) {
            return;
        }

        $id = $this->i++;

        /** @psalm-suppress MixedAssignment */
        $value = $this->wrapValue($value);

        $this->queue->enqueue([
            'id' => $id,
            'key' => $key,
            'value' => $value,
        ]);

        $this->nextItem();
    }

    public function writeValue(mixed $value): void
    {
        if ($this->closing) {
            return;
        }

        $id = $this->i++;

        /** @psalm-suppress MixedAssignment */
        $value = $this->wrapValue($value);

        $this->queue->enqueue([
            'id' => $id,
            'key' => null,
            'value' => $value,
        ]);

        $this->nextItem();
    }

    /**
     * This method can't be changed to accepting variadic because it needs to detect if it needs to be written out
     * as an array or object in JSON
     *
     * @param array<array-key, mixed> $values
     */
    public function writeArray(array $values): void
    {
        if ($this->closing) {
            return;
        }

        $this->objectOrArray($values);

        /** @psalm-suppress MixedAssignment */
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $this->write($key, $value);
                continue;
            }

            $this->writeValue($value);
        }
    }

    public function writeObservable(ObservableInterface $values): void
    {
        if ($this->closing) {
            return;
        }

        $this->objectOrArray([]);

        /** @psalm-suppress MissingClosureParamType */
        $values->subscribe(
            function ($value): void {
                $this->writeValue($value);
            },
        );
    }

    /** @inheritDoc */
    public function isReadable()
    {
        return $this->readable;
    }

    public function pause(): void
    {
        $this->paused = true;
    }

    public function resume(): void
    {
        $this->paused = false;
        $this->emitData($this->buffer);
        $this->buffer = '';

        if ($this->queue->count() !== 0 || ! $this->closing) {
            return;
        }

        $this->emit('end');
        $this->readable = false;
        $this->emit('close');
    }

    /**
     * @param array<mixed> $options
     *
     * @inheritDoc
     */
    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        return Util::pipe($this, $dest, $options);
    }

    /**
     * @param array<mixed>|null $values
     *
     * @phpstan-ignore-next-line
     */
    public function end(array|null $values = null): void
    {
        if ($this->closing) {
            return;
        }

        if (is_array($values)) {
            $this->writeArray($values);
        }

        $this->close();
    }

    public function close(): void
    {
        if ($this->closing === true) {
            return;
        }

        $this->closing = true;
        $this->nextItem();
    }

    /** @param array<mixed> $values */
    private function objectOrArray(array $values): void
    {
        if (! $this->first) {
            return;
        }

        if ($this->typeDetected) {
            return;
        }

        foreach (array_keys($values) as $key) {
            if (is_string($key)) {
                return;
            }
        }

        $this->beginning = self::ARRAY_BEGINNING;
        $this->ending    = self::ARRAY_ENDING;
    }

    private function nextItem(): void
    {
        if ($this->currentId !== null) {
            return;
        }

        if ($this->first) {
            $this->typeDetected = true;
            $this->emitData($this->beginning);
        }

        if ($this->queue->count() === 0 && $this->closing) {
            $this->emitData($this->ending);
            if ($this->buffer === '') {
                $this->emit('end');
                $this->readable = false;
                $this->emit('close');
            }

            return;
        }

        if ($this->queue->count() === 0) {
            return;
        }

        if (! $this->first) {
            $this->emitData(',');
        }

        $this->first = false;

        $item            = $this->queue->dequeue();
        $this->currentId = $item['id'];

        if ($item['key'] !== null) {
            $this->emitData($this->encode($item['key']) . ':');
        }

        /**
         * @phpstan-ignore-next-line
         * @psalm-suppress UndefinedInterfaceMethod
         */
        $this->formatValue($item['value'])->done(function (): void {
            $this->currentId = null;
            $this->nextItem();
        });
    }

    private function wrapValue(mixed $value): mixed
    {
        if ($value instanceof PromiseInterface) {
            return $value->then(fn (mixed $result): mixed => $this->wrapValue($result));
        }

        if ($value instanceof JsonStream) {
            return new BufferingJsonStream($value);
        }

        if ($value instanceof ReadableStreamInterface) {
            return new BufferingReadableStream($value);
        }

        if (is_array($value)) {
            $json            = new self();
            $bufferingStream = new BufferingJsonStream($json);
            $json->end($value);

            return $bufferingStream;
        }

        return $value;
    }

    private function formatValue(mixed $value): PromiseInterface
    {
        if ($value instanceof PromiseInterface) {
            /** @psalm-suppress MissingClosureParamType */
            return $value->then(fn ($result) => $this->formatValue(
                $this->wrapValue($result),
            ));
        }

        if ($value instanceof Observable) {
            return $this->handleObservable($value);
        }

        if ($value instanceof BufferingJsonStream) {
            return $this->handleJsonStream($value);
        }

        if ($value instanceof BufferingStreamInterface) {
            return $this->handleStream($value);
        }

        $this->emitData($this->encode($value));

        return resolve();
    }

    private function handleObservable(Observable $value): PromiseInterface
    {
        $this->emitData('[');
        $first = true;

        return new Promise(function (callable $resolve) use ($value, &$first): void {
            /** @psalm-suppress MissingClosureParamType */
            $value->flatMap(fn ($value) => Observable::fromPromise(resolve($this->wrapValue($value))))->subscribe(
                function ($item) use (&$first): void {
                    if ($first === false) {
                        $this->emitData(',');
                    }

                    $first = false;

                    $this->formatValue($item);
                },
                null,
                function () use ($resolve): void {
                    $this->emitData(']');
                    $resolve();
                },
            );
        });
    }

    private function handleJsonStream(BufferingStreamInterface $bufferingStream): PromiseInterface
    {
        $isDone = $bufferingStream->isDone();
        $stream = $bufferingStream->takeOverStream();
        $buffer = $bufferingStream->takeOverBuffer();
        $this->emitData($buffer);
        if ($isDone) {
            return resolve();
        }

        /** @psalm-suppress MissingClosureParamType */
        $stream->on('data', function (string $data): void {
            $this->emitData($data);
        });
        $deferred = new Deferred();
        $stream->once('close', static function () use ($deferred): void {
            $deferred->resolve();
        });

        return $deferred->promise();
    }

    private function handleStream(BufferingStreamInterface $bufferingStream): PromiseInterface
    {
        $isDone = $bufferingStream->isDone();
        $stream = $bufferingStream->takeOverStream();
        $this->emitData('"');
        $buffer = $bufferingStream->takeOverBuffer();
        $this->emitData($this->encode($buffer, true));
        if ($isDone) {
            $this->emitData('"');

            return resolve();
        }

        /** @psalm-suppress MissingClosureParamType */
        $stream->on('data', function ($data): void {
            $this->emitData($this->encode($data, true));
        });
        $deferred = new Deferred();
        $stream->once('close', function () use ($deferred): void {
            $this->emitData('"');
            $deferred->resolve();
        });

        return $deferred->promise();
    }

    private function emitData(string $data): void
    {
        if ($this->paused) {
            $this->buffer .= $data;

            return;
        }

        $this->emit('data', [$data]);
    }

    private function encode(mixed $value, bool $stripWrappingQuotes = false): string
    {
        $json = json_encode(
            $value,
            $this->encodeFlags,
        );

        if ($stripWrappingQuotes === false) {
            return $json;
        }

        return trim($json, '"');
    }
}

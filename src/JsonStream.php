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
use RuntimeException;
use Rx\Observable;
use Rx\ObservableInterface;
use SplQueue;

use function array_keys;
use function gettype;
use function is_array;
use function is_string;
use function json_encode;
use function json_last_error;
use function json_last_error_msg;
use function React\Promise\resolve;
use function trim;

use const JSON_ERROR_NONE;
use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_PRESERVE_ZERO_FRACTION;

/** @api */
final class JsonStream extends EventEmitter implements ReadableStreamInterface
{
    public const string OBJECT_BEGINNING  = '{';
    public const string OBJECT_ENDING     = '}';
    public const string ARRAY_BEGINNING   = '[';
    public const string ARRAY_ENDING      = ']';
    public const int DEFAULT_ENCODE_FLAGS = JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_PRESERVE_ZERO_FRACTION;

    /** @var SplQueue<array{id: int, key: string|null, value: mixed}> */
    private readonly SplQueue $queue;
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

    /** @phpstan-ignore ergebnis.noConstructorParameterWithDefaultValue */
    public function __construct(private readonly int $encodeFlags = self::DEFAULT_ENCODE_FLAGS)
    {
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

        $values->subscribe(
            function (mixed $value): void {
                $this->writeValue($value);
            },
        );
    }

    /**
     * @inheritDoc
     * @phpstan-ignore shipmonk.missingNativeReturnTypehint,typeCoverage.returnTypeCoverage
     */
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
     * @phpstan-ignore shipmonk.missingNativeReturnTypehint,typeCoverage.returnTypeCoverage
     */
    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        return Util::pipe($this, $dest, $options);
    }

    /**
     * @param array<mixed>|null $values
     *
     * @phpstan-ignore ergebnis.noParameterWithNullDefaultValue,ergebnis.noParameterWithNullableTypeDeclaration
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
        if ($this->closing) {
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

        $this->formatValue($item['value'])->then(function (): void {
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

    /** @return PromiseInterface<bool> */
    private function formatValue(mixed $value): PromiseInterface
    {
        if ($value instanceof PromiseInterface) {
            return $value->then(fn (mixed $result): PromiseInterface => $this->formatValue(
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

        return resolve(true);
    }

    /** @return PromiseInterface<bool> */
    private function handleObservable(Observable $value): PromiseInterface
    {
        $this->emitData('[');
        $first = true;

        /** @phpstan-ignore return.type */
        return new Promise(function (callable $resolve) use ($value, &$first): void {
            $value->flatMap(fn (mixed $value): Observable => Observable::fromPromise(resolve($this->wrapValue($value))))->subscribe(
                function (mixed $item) use (&$first): void {
                    if ($first === false) {
                        $this->emitData(',');
                    }

                    $first = false;

                    $this->formatValue($item);
                },
                null,
                function () use ($resolve): void {
                    $this->emitData(']');
                    $resolve(true);
                },
            );
        });
    }

    /** @return PromiseInterface<bool> */
    private function handleJsonStream(BufferingStreamInterface $bufferingStream): PromiseInterface
    {
        $isDone = $bufferingStream->isDone();
        $stream = $bufferingStream->takeOverStream();
        $buffer = $bufferingStream->takeOverBuffer();
        $this->emitData($buffer);
        if ($isDone) {
            return resolve(true);
        }

        $stream->on('data', function (string $data): void {
            $this->emitData($data);
        });
        /** @var Deferred<bool> $deferred */
        $deferred = new Deferred();
        $stream->once('close', static function () use ($deferred): void {
            $deferred->resolve(true);
        });

        return $deferred->promise();
    }

    /** @return PromiseInterface<bool> */
    private function handleStream(BufferingStreamInterface $bufferingStream): PromiseInterface
    {
        $isDone = $bufferingStream->isDone();
        $stream = $bufferingStream->takeOverStream();
        $this->emitData('"');
        $buffer = $bufferingStream->takeOverBuffer();
        $this->emitData($this->encode($buffer, true));
        if ($isDone) {
            $this->emitData('"');

            return resolve(true);
        }

        $stream->on('data', function (mixed $data): void {
            $this->emitData($this->encode($data, true));
        });
        /** @var Deferred<bool> $deferred */
        $deferred = new Deferred();
        $stream->once('close', function () use ($deferred): void {
            $this->emitData('"');
            $deferred->resolve(true);
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

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(json_last_error_msg());
        }

        if (! is_string($json)) {
            throw new RuntimeException('Expected a JSON string, got: ' . gettype($json));
        }

        if ($stripWrappingQuotes === false) {
            return $json;
        }

        return trim($json, '"');
    }
}

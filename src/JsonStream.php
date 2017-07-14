<?php declare(strict_types=1);

namespace WyriHaximus\React\Stream\Json;

use Evenement\EventEmitter;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
use SplQueue;
use function React\Promise\resolve;

final class JsonStream extends EventEmitter implements ReadableStreamInterface
{
    const OBJECT_BEGINNING = '{';
    const OBJECT_ENDING = '}';
    const ARRAY_BEGINNING = '[';
    const ARRAY_ENDING = ']';

    /**
     * @var SplQueue
     */
    private $queue;

    /**
     * @var string|null
     */
    private $currentId;

    /**
     * @var bool
     */
    private $closing = false;

    /**
     * @var bool
     */
    private $first = true;

    /**
     * @var int
     */
    private $i = 0;

    /**
     * @var string
     */
    private $beginning = self::OBJECT_BEGINNING;

    /**
     * @var string
     */
    private $ending = self::OBJECT_ENDING;

    /**
     * @var bool
     */
    private $readable = true;

    public function __construct()
    {
        $this->queue = new SplQueue();
    }

    public function write(string $key, $value)
    {
        $id = $this->i++;

        $value = $this->wrapValue($value);

        $this->queue->enqueue([
            'id' => $id,
            'key' => $key,
            'value' => $value,
        ]);

        $this->nextItem();
    }

    public function writeValue($value)
    {
        $id = $this->i++;

        $value = $this->wrapValue($value);

        $this->queue->enqueue([
            'id' => $id,
            'key' => null,
            'value' => $value,
        ]);

        $this->nextItem();
    }

    public function writeArray(array $values)
    {
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $this->write($key, $value);
                continue;
            }

            $this->writeValue($value);
        }
    }

    public function isReadable()
    {
        return $this->readable;
    }

    /**
     * @deprecated Not going to be implemented
     */
    public function pause()
    {
        // No-op
    }

    /**
     * @deprecated Not going to be implemented
     */
    public function resume()
    {
        // No-op
    }

    public function pipe(WritableStreamInterface $dest, array $options = [])
    {
        return Util::pipe($this, $dest, $options);
    }

    public function end(array $values = null)
    {
        if (is_array($values)) {
            $this->objectOrArray($values);
            $this->writeArray($values);
        }

        $this->close();
    }

    public function close()
    {
        $this->closing = true;
        $this->nextItem();
    }

    private function objectOrArray(array $values)
    {
        if (!$this->first) {
            return;
        }

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                return;
            }
        }

        $this->beginning = self::ARRAY_BEGINNING;
        $this->ending = self::ARRAY_ENDING;
    }

    private function nextItem()
    {
        if ($this->currentId !== null) {
            return;
        }

        if ($this->first) {
            $this->emitData($this->beginning);
        }

        if ($this->queue->count() === 0 && $this->closing) {
            $this->emitData($this->ending);
            $this->emit('end');
            $this->readable = false;
            $this->emit('close');

            return;
        }

        if ($this->queue->count() === 0) {
            return;
        }

        if (!$this->first) {
            $this->emitData(',');
        }
        $this->first = false;

        $item = $this->queue->dequeue();
        $this->currentId = $item['id'];

        if ($item['key'] !== null) {
            $this->emitData('"' . $item['key'] . '":');
        }
        $this->formatValue($item['value'])->done(function () {
            $this->currentId = null;
            $this->nextItem();
        });
    }

    private function wrapValue($value)
    {
        if ($value instanceof JsonStream) {
            return new BufferingJsonStream($value);
        }

        if ($value instanceof ReadableStreamInterface) {
            return new BufferingReadableStream($value);
        }

        if (is_array($value)) {
            $json = new self();
            $bufferingStream = new BufferingJsonStream($json);
            $json->end($value);

            return $bufferingStream;
        }

        return $value;
    }

    private function formatValue($value): PromiseInterface
    {
        if ($value instanceof PromiseInterface) {
            return $value->then(function ($result) {
                return $this->formatValue(
                    $this->wrapValue($result)
                );
            });
        }

        if ($value instanceof BufferingStreamInterface) {
            return $this->handleStream($value);
        }

        $this->emitData(json_encode($value));

        return resolve();
    }

    private function handleStream(BufferingStreamInterface $bufferingStream): PromiseInterface
    {
        $isDone = $bufferingStream->isDone();
        $stream = $bufferingStream->takeOverStream();
        if (!($bufferingStream instanceof BufferingJsonStream)) {
            $this->emitData('"');
        }
        $this->emitData($bufferingStream->takeOverBuffer());
        if ($isDone) {
            if (!($bufferingStream instanceof BufferingJsonStream)) {
                $this->emitData('"');
            }

            return resolve();
        }

        $stream->on('data', function ($data) {
            $this->emitData($data);
        });
        $deferred = new Deferred();
        $stream->once('close', function () use ($deferred, $bufferingStream) {
            if (!($bufferingStream instanceof BufferingJsonStream)) {
                $this->emitData('"');
            }
            $deferred->resolve();
        });

        return $deferred->promise();
    }

    private function emitData(string $data)
    {
        $this->emit('data', [$data]);
    }
}

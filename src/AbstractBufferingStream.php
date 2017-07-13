<?php declare(strict_types=1);

namespace WyriHaximus\React\Stream\JSON;

use React\Stream\ReadableStreamInterface;

abstract class AbstractBufferingStream implements BufferingStreamInterface
{
    /**
     * @var ReadableStreamInterface
     */
    private $stream;

    /**
     * @var string
     */
    private $buffer = '';

    /**
     * @var bool
     */
    private $isDone = false;

    /**
     * @param ReadableStreamInterface $stream
     */
    protected function setUp(ReadableStreamInterface $stream)
    {
        $this->stream = $stream;
        $this->stream->on('data', [$this, 'onData']);
        $this->stream->on('close', [$this, 'onClose']);
    }

    /**
     * @internal
     * @param mixed $data
     */
    public function onData($data)
    {
        $this->buffer .= $data;
    }

    /**
     * @internal
     */
    public function onClose()
    {
        $this->isDone = true;
    }

    public function takeOverStream(): ReadableStreamInterface
    {
        $this->stream->removeListener('data', [$this, 'onData']);
        $this->stream->removeListener('close', [$this, 'onClose']);
        $this->isDone = true;

        return $this->stream;
    }

    public function takeOverBuffer(): string
    {
        $buffer = $this->buffer;
        $this->buffer = '';

        return $buffer;
    }

    public function isDone(): bool
    {
        return $this->isDone;
    }
}

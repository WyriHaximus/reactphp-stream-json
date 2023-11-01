<?php

declare(strict_types=1);

namespace WyriHaximus\React\Stream\Json;

use React\Stream\ReadableStreamInterface;

abstract class AbstractBufferingStream implements BufferingStreamInterface
{
    private ReadableStreamInterface $stream;
    private string $buffer = '';
    private bool $isDone   = false;

    final protected function setUp(ReadableStreamInterface $stream): void
    {
        $this->stream = $stream;
        $this->stream->on('data', [$this, 'onData']);
        $this->stream->on('close', [$this, 'onClose']);
    }

    /** @internal */
    final public function onData(string $data): void
    {
        $this->buffer .= $data;
    }

    /** @internal */
    final public function onClose(): void
    {
        $this->isDone = true;
    }

    final public function takeOverStream(): ReadableStreamInterface
    {
        $this->stream->removeListener('data', [$this, 'onData']);
        $this->stream->removeListener('close', [$this, 'onClose']);
        $this->isDone = true;

        return $this->stream;
    }

    final public function takeOverBuffer(): string
    {
        $buffer       = $this->buffer;
        $this->buffer = '';

        return $buffer;
    }

    final public function isDone(): bool
    {
        return $this->isDone;
    }
}

<?php

declare(strict_types=1);

namespace WyriHaximus\React\Stream\Json;

use Closure;
use React\Stream\ReadableStreamInterface;

abstract class AbstractBufferingStream implements BufferingStreamInterface
{
    private ReadableStreamInterface $stream;
    private string $buffer = '';
    private bool $isDone   = false;
    private Closure $onDataListener;
    private Closure $onCloseListener;

    final protected function setUp(ReadableStreamInterface $stream): void
    {
        $this->stream          = $stream;
        $this->onDataListener  = $this->onData(...);
        $this->onCloseListener = $this->onClose(...);
        $this->stream->on('data', $this->onDataListener);
        $this->stream->on('close', $this->onCloseListener);
    }

    final public function onData(string $data): void
    {
        $this->buffer .= $data;
    }

    final public function onClose(): void
    {
        $this->isDone = true;
    }

    final public function takeOverStream(): ReadableStreamInterface
    {
        $this->stream->removeListener('data', $this->onDataListener);
        $this->stream->removeListener('close', $this->onCloseListener);
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

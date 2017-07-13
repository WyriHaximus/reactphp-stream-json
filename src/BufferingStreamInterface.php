<?php declare(strict_types=1);

namespace WyriHaximus\React\Stream\JSON;

use React\Stream\ReadableStreamInterface;

interface BufferingStreamInterface
{
    /**
     * Callee takes over handling the stream. The implementing
     * class will remove all listeners.
     *
     * @return ReadableStreamInterface
     */
    public function takeOverStream(): ReadableStreamInterface;

    /**
     * Callee takes over the buffers contents. The implementing
     * class will clear the buffer.
     *
     * @return string
     */
    public function takeOverBuffer(): string;

    /**
     * Returns done when the stream has been taken over or when
     * the underlying stream has closed.
     *
     * @return bool
     */
    public function isDone(): bool;
}

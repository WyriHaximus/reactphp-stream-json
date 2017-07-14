<?php declare(strict_types=1);

namespace WyriHaximus\React\Stream\Json;

use React\Stream\ReadableStreamInterface;

final class BufferingReadableStream extends AbstractBufferingStream
{
    /**
     * BufferingStream constructor.
     * @param ReadableStreamInterface $stream
     */
    public function __construct(ReadableStreamInterface $stream)
    {
        $this->setUp($stream);
    }
}

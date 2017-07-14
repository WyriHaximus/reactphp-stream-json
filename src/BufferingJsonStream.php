<?php declare(strict_types=1);

namespace WyriHaximus\React\Stream\Json;

use React\Stream\ReadableStreamInterface;

final class BufferingJsonStream extends AbstractBufferingStream
{
    /**
     * @param ReadableStreamInterface $stream
     */
    public function __construct(JsonStream $stream)
    {
        $this->setUp($stream);
    }
}

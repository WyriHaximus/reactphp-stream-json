<?php declare(strict_types=1);

namespace WyriHaximus\React\Stream\Json;

use React\Stream\ReadableStreamInterface;

final class BufferingWritableJsonStream extends AbstractBufferingStream
{
    /**
     * @param ReadableStreamInterface $stream
     */
    public function __construct(WritableJsonStream $stream)
    {
        $this->setUp($stream);
    }
}

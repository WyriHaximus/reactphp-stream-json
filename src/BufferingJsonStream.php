<?php

declare(strict_types=1);

namespace WyriHaximus\React\Stream\Json;

final class BufferingJsonStream extends AbstractBufferingStream
{
    public function __construct(JsonStream $stream)
    {
        $this->setUp($stream);
    }
}

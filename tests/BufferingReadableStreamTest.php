<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Stream\Json;

use React\Stream\ThroughStream;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Stream\Json\BufferingReadableStream;

final class BufferingReadableStreamTest extends AsyncTestCase
{
    public function testBuffering(): void
    {
        $stream          = new ThroughStream();
        $bufferingStream = new BufferingReadableStream($stream);

        self::assertFalse($bufferingStream->isDone());

        $stream->write('a');
        self::assertFalse($bufferingStream->isDone());

        $stream->write('b');
        self::assertFalse($bufferingStream->isDone());

        self::assertSame('ab', $bufferingStream->takeOverBuffer());
        self::assertFalse($bufferingStream->isDone());

        self::assertSame('', $bufferingStream->takeOverBuffer());
        self::assertFalse($bufferingStream->isDone());

        $stream->write('cdef');
        self::assertSame($stream, $bufferingStream->takeOverStream());
        self::assertTrue($bufferingStream->isDone());
    }

    public function testBufferingStreamClose(): void
    {
        $stream          = new ThroughStream();
        $bufferingStream = new BufferingReadableStream($stream);

        self::assertFalse($bufferingStream->isDone());

        $stream->write('a');
        self::assertFalse($bufferingStream->isDone());

        $stream->end('b');
        self::assertTrue($bufferingStream->isDone());
    }
}

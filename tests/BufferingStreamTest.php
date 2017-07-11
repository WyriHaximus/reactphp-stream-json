<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Stream\JSON;

use PHPUnit\Framework\TestCase;
use React\Stream\ThroughStream;
use WyriHaximus\React\Stream\JSON\BufferingStream;

final class BufferingStreamTest extends TestCase
{
    public function testBuffering()
    {
        $stream = new ThroughStream();
        $bufferingStream = new BufferingStream($stream);

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

    public function testBufferingStreamClose()
    {
        $stream = new ThroughStream();
        $bufferingStream = new BufferingStream($stream);

        self::assertFalse($bufferingStream->isDone());

        $stream->write('a');
        self::assertFalse($bufferingStream->isDone());

        $stream->end('b');
        self::assertTrue($bufferingStream->isDone());
    }
}

<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Stream\Json;

use PHPUnit\Framework\Attributes\Test;
use React\EventLoop\Loop;
use Rx\Observable;
use Rx\Scheduler\ImmediateScheduler;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Stream\Json\JsonStreamFactory;

use function React\Async\await;
use function React\Promise\Stream\buffer;

final class JsonStreamFactoryTest extends AsyncTestCase
{
    #[Test]
    public function createFromArray(): void
    {
        $array = [
            'cuvee',
            'buffalo',
        ];

        $stream = JsonStreamFactory::createFromArray($array);
        Loop::futureTick(static function () use ($stream): void {
            $stream->resume();
        });

        $json = await(buffer($stream));
        self::assertSame('["cuvee","buffalo"]', $json);
    }

    #[Test]
    public function createFromObservavle(): void
    {
        $array = [
            'cuvee',
            'buffalo',
        ];

        $stream = JsonStreamFactory::createFromObservable(Observable::fromArray($array, new ImmediateScheduler()));
        Loop::futureTick(static function () use ($stream): void {
            $stream->resume();
        });

        $json = await(buffer($stream));
        self::assertSame('["cuvee","buffalo"]', $json);
    }
}

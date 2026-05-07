<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Stream\Json;

use React\EventLoop\Loop;
use Rx\Observable;
use Rx\ObservableFactoryWrapper;
use Rx\Scheduler\ImmediateScheduler;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Stream\Json\JsonStreamFactory;

use function React\Async\await;
use function React\Promise\Stream\buffer;

final class JsonStreamFactoryTest extends AsyncTestCase
{
    /** @test */
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

    /** @test */
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

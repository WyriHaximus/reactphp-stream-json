<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Stream\Json;

use React\EventLoop\Loop;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Stream\Json\JsonStreamFactory;

use function ApiClients\Tools\Rx\observableFromArray;
use function React\Promise\Stream\buffer;

/**
 * @internal
 */
final class JsonStreamFactoryTest extends AsyncTestCase
{
    /**
     * @test
     */
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

        $json = $this->await(buffer($stream));
        self::assertSame('["cuvee","buffalo"]', $json);
    }

    /**
     * @test
     */
    public function createFromObservavle(): void
    {
        $array = [
            'cuvee',
            'buffalo',
        ];

        $stream = JsonStreamFactory::createFromObservable(observableFromArray($array));
        Loop::futureTick(static function () use ($stream): void {
            $stream->resume();
        });

        $json = $this->await(buffer($stream));
        self::assertSame('["cuvee","buffalo"]', $json);
    }
}

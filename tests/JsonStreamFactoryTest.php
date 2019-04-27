<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Stream\Json;

use function ApiClients\Tools\Rx\observableFromArray;
use React\EventLoop\StreamSelectLoop;
use function React\Promise\Stream\buffer;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Stream\Json\JsonStreamFactory;

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
        $loop = new StreamSelectLoop();
        $array = [
            'cuvee',
            'buffalo',
        ];

        $stream = JsonStreamFactory::createFromArray($array);
        $loop->futureTick(function () use ($stream): void {
            $stream->resume();
        });

        $json = $this->await(buffer($stream), $loop);
        self::assertSame('["cuvee","buffalo"]', $json);
    }

    /**
     * @test
     */
    public function createFromObservavle(): void
    {
        $loop = new StreamSelectLoop();
        $array = [
            'cuvee',
            'buffalo',
        ];

        $stream = JsonStreamFactory::createFromObservable(observableFromArray($array));
        $loop->futureTick(function () use ($stream): void {
            $stream->resume();
        });

        $json = $this->await(buffer($stream), $loop);
        self::assertSame('["cuvee","buffalo"]', $json);
    }
}

<?php declare(strict_types=1);

namespace WyriHaximus\React\Tests\Stream\Json;

use PHPUnit\Framework\TestCase;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Stream\ThroughStream;
use WyriHaximus\React\Stream\Json\JsonStream;
use function Clue\React\Block\await;
use function React\Promise\all;
use function React\Promise\resolve;
use function React\Promise\Stream\buffer;

final class JsonStreamTest extends TestCase
{
    public function provideJSONs()
    {
        yield [function () {
            $input = [
                'promise' => resolve('w00t'),
            ];

            return [$input, '{"promise":"w00t"}'];
        }];

        yield [function () {
            $input = [
                'promise' => all([resolve('w00t'),resolve('w00t'),resolve('1337'),resolve('train')]),
            ];

            return [$input, '{"promise":["w00t","w00t","1337","train"]}'];
        }];

        yield [function () {
            $input = [
                'a',
                'a',
            ];

            return [$input, '["a","a"]'];
        }];

        yield [function () {
            $input = [
            'a' => 'b',
            'c' => 'd',
            ];

            return [$input, '{"a":"b","c":"d"}'];
        }];

        yield [function () {
            $input = [
            'a' => resolve('b'),
            'c' => resolve('d'),
            ];

            return [$input, '{"a":"b","c":"d"}'];
        }];

        yield [function () {
            $input = [
                'a' => resolve(['b']),
                'c' => resolve(['d']),
            ];

            return [$input, '{"a":["b"],"c":["d"]}'];
        }];

        yield [function (LoopInterface $loop) {
            $stream = new ThroughStream();

            $input = [
                'river' => $stream,
            ];

            $loop->addTimer(0.05, function () use ($stream) {
                $stream->end('song');
            });

            return [$input, '{"river":"song"}'];
        }];

        yield [function (LoopInterface $loop) {
            $streamA = new ThroughStream();
            $streamB = new ThroughStream();

            $input = [
                'river' => $streamA,
                'melody' => $streamB,
            ];

            $loop->addTimer(0.1, function () use ($streamA) {
                $streamA->end('song');
            });

            $loop->addTimer(0.05, function () use ($streamB) {
                $streamB->end('by the pond');
            });

            return [$input, '{"river":"song","melody":"by the pond"}'];
        }];

        yield [function (LoopInterface $loop) {
            $streamA = new ThroughStream();
            $streamB = new ThroughStream();
            $deferred = new Deferred();

            $input = [
                'river' => $streamA,
                'melody' => $streamB,
                'from' => $deferred->promise(),
            ];

            $loop->addTimer(0.1, function () use ($streamA) {
                $streamA->end('song');
            });

            $loop->addTimer(0.05, function () use ($streamB) {
                $streamB->end('by the pond');
            });

            $loop->addTimer(0.01, function () use ($deferred) {
                $deferred->resolve('the vortex');
            });

            return [$input, '{"river":"song","melody":"by the pond","from":"the vortex"}'];
        }];

        yield [function (LoopInterface $loop) {
            $streamA = new ThroughStream();
            $streamB = new ThroughStream();
            $streamCenturion = new ThroughStream();
            $deferred = new Deferred();
            $jsonStream = new JsonStream();

            $input = [
                'river' => $streamA,
                'melody' => $streamB,
                'from' => $deferred->promise(),
                'vortex' => $jsonStream,
            ];

            $loop->addTimer(0.1, function () use ($streamA) {
                $streamA->end('song');
            });

            $loop->addTimer(0.05, function () use ($streamB) {
                $streamB->end('by the pond');
            });

            $loop->addTimer(0.01, function () use ($deferred) {
                $deferred->resolve('the vortex');
            });

            $loop->addTimer(0.05, function () use ($jsonStream, $streamCenturion) {
                $jsonStream->end([
                    'ponds' => resolve([
                        'f' => resolve(resolve(resolve('the girl who waited'))),
                        'm' => resolve($streamCenturion),
                    ]),
                ]);
            });

            $loop->addTimer(0.1, function () use ($streamCenturion) {
                $streamCenturion->end('the last centurion');
            });

            return [$input, '{"river":"song","melody":"by the pond","from":"the vortex","vortex":{"ponds":{"f":"the girl who waited","m":"the last centurion"}}}'];
        }];
    }

    /**
     * @dataProvider provideJSONs
     */
    public function testStream(callable $args)
    {
        $loop = Factory::create();
        list($input, $output) = $args($loop);

        $throughStream = new ThroughStream();

        $stream = new JsonStream();

        $stream->pipe($throughStream);

        $loop->addTimer(0.01, function () use ($stream, $input) {
            $stream->end($input);
        });

        $buffer = await(buffer($throughStream), $loop, 6);

        self::assertSame($output, $buffer);
        $json = json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, json_last_error());
        self::assertInternalType('array', $json);
        self::assertSame(json_decode($output, true), $json);
    }

    public function testObjectOrArrayObject()
    {
        $loop = Factory::create();

        $stream = new JsonStream();

        $loop->addTimer(0.01, function () use ($stream) {
            $stream->writeValue(true);
            $stream->end([false]);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('{true,false}', $buffer);
        json_decode($buffer, true);
        self::assertSame(JSON_ERROR_SYNTAX, json_last_error());
    }

    public function testObjectOrArrayArray()
    {
        $loop = Factory::create();

        $stream = new JsonStream();

        $loop->addTimer(0.01, function () use ($stream) {
            $stream->end([true, false]);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('[true,false]', $buffer);
        json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, json_last_error());
    }
}

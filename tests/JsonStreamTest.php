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

/**
 * @internal
 */
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

            $loop->addTimer(0.05, function () use ($stream): void {
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

            $loop->addTimer(0.1, function () use ($streamA): void {
                $streamA->end('song');
            });

            $loop->addTimer(0.05, function () use ($streamB): void {
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

            $loop->addTimer(0.1, function () use ($streamA): void {
                $streamA->end('song');
            });

            $loop->addTimer(0.05, function () use ($streamB): void {
                $streamB->end('by the pond');
            });

            $loop->addTimer(0.01, function () use ($deferred): void {
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

            $loop->addTimer(0.1, function () use ($streamA): void {
                $streamA->end('song');
            });

            $loop->addTimer(0.05, function () use ($streamB): void {
                $streamB->end('by the pond');
            });

            $loop->addTimer(0.01, function () use ($deferred): void {
                $deferred->resolve('the vortex');
            });

            $loop->addTimer(0.05, function () use ($jsonStream, $streamCenturion): void {
                $jsonStream->end([
                    'ponds' => resolve([
                        'f' => resolve(resolve(resolve('the girl who waited'))),
                        'm' => resolve($streamCenturion),
                    ]),
                ]);
            });

            $loop->addTimer(0.1, function () use ($streamCenturion): void {
                $streamCenturion->end('the last centurion');
            });

            return [$input, '{"river":"song","melody":"by the pond","from":"the vortex","vortex":{"ponds":{"f":"the girl who waited","m":"the last centurion"}}}'];
        }];

        yield [function (LoopInterface $loop) {
            $stream = new ThroughStream();

            $input = [
                'a' => resolve([
                    'b' => resolve([
                        'c' => resolve([
                            'd' => resolve(resolve(resolve(resolve(resolve($stream))))),
                        ]),
                    ]),
                ]),
            ];

            $loop->addTimer(0.1, function () use ($stream): void {
                $stream->end('e');
            });

            return [$input, '{"a":{"b":{"c":{"d":"e"}}}}'];
        }];

        yield [function (LoopInterface $loop) {
            $stream = new ThroughStream();

            $input = [
                'a' => resolve([
                    'b' => [
                        'c' => resolve([
                            'd' => resolve(resolve(resolve(resolve(resolve($stream))))),
                        ]),
                    ],
                ]),
            ];

            $loop->addTimer(0.1, function () use ($stream): void {
                $stream->end('e');
            });

            return [$input, '{"a":{"b":{"c":{"d":"e"}}}}'];
        }];

        yield [function () {
            $input = \range(1, 10);

            return [$input, '[1,2,3,4,5,6,7,8,9,10]'];
        }];

        yield [function () {
            $input = [
                resolve(1),
                resolve(2),
                resolve(3),
                resolve(4),
                resolve(5),
                resolve(6),
                resolve(7),
                resolve(8),
                resolve(9),
                resolve(10),
            ];

            return [$input, '[1,2,3,4,5,6,7,8,9,10]'];
        }];

        yield [function () {
            $input = [
                [
                    'foo' => 'bar',
                ],
                [
                    'bar' => 'foo',
                ],
            ];

            return [$input, '[{"foo":"bar"},{"bar":"foo"}]'];
        }];

        yield [function (LoopInterface $loop) {
            $stream = new ThroughStream();

            $input = [
                [
                    'foo' => resolve('bar'),
                ],
                resolve([
                    'bar' => $stream,
                ]),
            ];

            $loop->addTimer(0.1, function () use ($stream): void {
                $stream->end('foo');
            });

            return [$input, '[{"foo":"bar"},{"bar":"foo"}]'];
        }];

        yield [function (LoopInterface $loop) {
            $stream = new ThroughStream();

            $input = [
                '😱' => $stream,
            ];

            $loop->addTimer(0.05, function () use ($stream): void {
                $stream->end('😱');
            });

            return [$input, '{"\ud83d\ude31":"\ud83d\ude31"}'];
        }];

        yield [function (LoopInterface $loop) {
            $stream = new ThroughStream();

            $input = [
                '😱' => $stream,
            ];

            $loop->addTimer(0.05, function () use ($stream): void {
                $stream->end('<\'&"&\'>');
            });

            return [$input, '{"\ud83d\ude31":"\u003C\u0027\u0026\u0022\u0026\u0027\u003E"}'];
        }];
    }

    /**
     * @dataProvider provideJSONs
     */
    public function testStream(callable $args): void
    {
        $loop = Factory::create();
        list($input, $output) = $args($loop);

        $throughStream = new ThroughStream();

        $stream = new JsonStream();

        $stream->pipe($throughStream);

        $loop->addTimer(0.01, function () use ($stream, $input): void {
            $stream->end($input);
        });

        $buffer = await(buffer($throughStream), $loop, 6);

        self::assertSame($output, $buffer);
        $json = \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, \json_last_error());
        self::assertInternalType('array', $json);
        self::assertSame(\json_decode($output, true), $json);
    }

    public function testEncodeFlags(): void
    {
        $loop = Factory::create();

        $stream = new JsonStream(0);

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->end(['<\'&"&\'>']);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('["<\'&\"&\'>"]', $buffer);
        $json = \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, \json_last_error());
        self::assertSame(['<\'&"&\'>'], $json);
    }

    public function testObjectOrArrayObject(): void
    {
        $loop = Factory::create();

        $stream = new JsonStream();

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->writeValue(true);
            $stream->end([false]);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('{true,false}', $buffer);
        \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_SYNTAX, \json_last_error());
    }

    public function testObjectOrArrayArray(): void
    {
        $loop = Factory::create();

        $stream = new JsonStream();

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->end([true, false]);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('[true,false]', $buffer);
        \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, \json_last_error());
    }

    public function testForceArray(): void
    {
        $loop = Factory::create();

        $stream = JsonStream::createArray();

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->end([true, false]);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('[true,false]', $buffer);
        \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, \json_last_error());
    }

    public function testForceArrayWhileWeWriteAnObject(): void
    {
        $loop = Factory::create();

        $stream = JsonStream::createArray();

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->end(['a' => true, 'b' => false]);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('["a":true,"b":false]', $buffer);
        \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_SYNTAX, \json_last_error());
    }

    public function testForceObject(): void
    {
        $loop = Factory::create();

        $stream = JsonStream::createObject();

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->end(['a' => true, 'b' => false]);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('{"a":true,"b":false}', $buffer);
        \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, \json_last_error());
    }

    public function testForceObjectWhileWeWriteAnArray(): void
    {
        $loop = Factory::create();

        $stream = JsonStream::createObject();

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->end([true, false]);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('{true,false}', $buffer);
        \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_SYNTAX, \json_last_error());
    }

    public function testDoubleKeys(): void
    {
        $loop = Factory::create();

        $stream = JsonStream::createObject();

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->write('a', true);
            $stream->write('a', false);
            $stream->end();
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('{"a":true,"a":false}', $buffer);
        $json = \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, \json_last_error());
        self::assertSame(['a' => false], $json);
    }

    public function testNoMoreWriteAfterEnd(): void
    {
        $loop = Factory::create();

        $stream = JsonStream::createObject();

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->write('a', true);
            $stream->end();
            $stream->write('b', false);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('{"a":true}', $buffer);
        $json = \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, \json_last_error());
        self::assertSame(['a' => true], $json);
    }

    public function testNoMoreWriteValueAfterEnd(): void
    {
        $loop = Factory::create();

        $stream = JsonStream::createObject();

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->write('a', true);
            $stream->end();
            $stream->writeValue(false);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('{"a":true}', $buffer);
        $json = \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, \json_last_error());
        self::assertSame(['a' => true], $json);
    }

    public function testNoMoreWriteArrayAfterEnd(): void
    {
        $loop = Factory::create();

        $stream = JsonStream::createObject();

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->write('a', true);
            $stream->end();
            $stream->writeArray([1,2,3,4,5,6,7,8,9,10]);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('{"a":true}', $buffer);
        $json = \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, \json_last_error());
        self::assertSame(['a' => true], $json);
    }

    public function testNoMoreEndAfterEnd(): void
    {
        $loop = Factory::create();

        $stream = JsonStream::createObject();

        $loop->addTimer(0.01, function () use ($stream): void {
            $stream->write('a', true);
            $stream->end();
            $stream->end([1,2,3,4,5,6,7,8,9,10]);
        });

        $buffer = await(buffer($stream), $loop, 6);

        self::assertSame('{"a":true}', $buffer);
        $json = \json_decode($buffer, true);
        self::assertSame(JSON_ERROR_NONE, \json_last_error());
        self::assertSame(['a' => true], $json);
    }

    public function testPauseResume(): void
    {
        $stream = JsonStream::createObject();

        $shouldntBeCalledCount = 0;
        $shouldntBeCalled = function () use (&$shouldntBeCalledCount): void {
            $shouldntBeCalledCount++;
        };
        $shouldBeCalledCount = 0;
        $shouldBeCalled = function () use (&$shouldBeCalledCount): void {
            $shouldBeCalledCount++;
        };

        self::assertSame(0, $shouldntBeCalledCount);
        self::assertSame(0, $shouldBeCalledCount);

        $stream->on('data', $shouldntBeCalled);
        $stream->pause();

        $stream->write('key', 'value');

        self::assertSame(0, $shouldntBeCalledCount);
        self::assertSame(0, $shouldBeCalledCount);

        $stream->removeListener('data', $shouldntBeCalled);
        $stream->on('data', $shouldBeCalled);

        self::assertSame(0, $shouldntBeCalledCount);
        self::assertSame(0, $shouldBeCalledCount);

        $stream->resume();

        self::assertSame(0, $shouldntBeCalledCount);
        self::assertSame(1, $shouldBeCalledCount);

        $stream->write('key', 'value');

        self::assertSame(0, $shouldntBeCalledCount);
        self::assertSame(4, $shouldBeCalledCount);

        $stream->end();

        self::assertSame(0, $shouldntBeCalledCount);
        self::assertSame(5, $shouldBeCalledCount);
    }
}

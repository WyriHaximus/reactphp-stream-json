<?php

declare(strict_types=1);

namespace WyriHaximus\React\Tests\Stream\Json;

use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Stream\ThroughStream;
use Rx\Observable;
use Rx\ObservableInterface;
use Rx\Subject\Subject;
use WyriHaximus\AsyncTestUtilities\AsyncTestCase;
use WyriHaximus\React\Stream\Json\JsonStream;

use function json_decode;
use function json_last_error;
use function range;
use function React\Promise\all;
use function React\Promise\resolve;
use function React\Promise\Stream\buffer;
use function WyriHaximus\React\timedPromise;

use const JSON_ERROR_NONE;
use const JSON_ERROR_SYNTAX;

/**
 * @internal
 */
final class JsonStreamTest extends AsyncTestCase
{
    /**
     * @return iterable<array<callable>>
     */
    public function provideJSONs(): iterable
    {
        yield [
            static function (): array {
                $input = [
                    'promise' => resolve('w00t'),
                ];

                return [$input, '{"promise":"w00t"}'];
            },
        ];

        yield [
            static function (): array {
                $input = [
                    'promise' => all([resolve('w00t'), resolve('w00t'), resolve('1337'), resolve('train')]),
                ];

                return [$input, '{"promise":["w00t","w00t","1337","train"]}'];
            },
        ];

        yield [
            static function (): array {
                $input = [
                    'a',
                    'a',
                ];

                return [$input, '["a","a"]'];
            },
        ];

        yield [
            static function (): array {
                $input = [
                    'a' => 'b',
                    'c' => 'd',
                ];

                return [$input, '{"a":"b","c":"d"}'];
            },
        ];

        yield [
            static function (): array {
                $input = [
                    'a' => resolve('b'),
                    'c' => resolve('d'),
                ];

                return [$input, '{"a":"b","c":"d"}'];
            },
        ];

        yield [
            static function (): array {
                $input = [
                    'a' => resolve(['b']),
                    'c' => resolve(['d']),
                ];

                return [$input, '{"a":["b"],"c":["d"]}'];
            },
        ];

        yield [
            static function (): array {
                $stream = new ThroughStream();

                $input = ['river' => $stream];

                Loop::addTimer(0.05, static function () use ($stream): void {
                    $stream->end('song');
                });

                    return [$input, '{"river":"song"}'];
            },
        ];

        yield [
            static function (): array {
                $streamA = new ThroughStream();
                $streamB = new ThroughStream();

                $input = [
                    'river' => $streamA,
                    'melody' => $streamB,
                ];

                Loop::addTimer(0.1, static function () use ($streamA): void {
                    $streamA->end('song');
                });

                    Loop::addTimer(0.05, static function () use ($streamB): void {
                        $streamB->end('by the pond');
                    });

                    return [$input, '{"river":"song","melody":"by the pond"}'];
            },
        ];

        yield [
            static function (): array {
                $streamA  = new ThroughStream();
                $streamB  = new ThroughStream();
                $deferred = new Deferred();

                $input = [
                    'river' => $streamA,
                    'melody' => $streamB,
                    'from' => $deferred->promise(),
                ];

                Loop::addTimer(0.1, static function () use ($streamA): void {
                    $streamA->end('song');
                });

                    Loop::addTimer(0.05, static function () use ($streamB): void {
                        $streamB->end('by the pond');
                    });

                    Loop::addTimer(0.01, static function () use ($deferred): void {
                        $deferred->resolve('the vortex');
                    });

                    return [$input, '{"river":"song","melody":"by the pond","from":"the vortex"}'];
            },
        ];

        yield [
            static function (): array {
                $streamA         = new ThroughStream();
                $streamB         = new ThroughStream();
                $streamCenturion = new ThroughStream();
                $deferred        = new Deferred();
                $jsonStream      = new JsonStream();

                $input = [
                    'river' => $streamA,
                    'melody' => $streamB,
                    'from' => $deferred->promise(),
                    'vortex' => $jsonStream,
                ];

                Loop::addTimer(0.1, static function () use ($streamA): void {
                    $streamA->end('song');
                });

                    Loop::addTimer(0.05, static function () use ($streamB): void {
                        $streamB->end('by the pond');
                    });

                    Loop::addTimer(0.01, static function () use ($deferred): void {
                        $deferred->resolve('the vortex');
                    });

                    Loop::addTimer(0.05, static function () use ($jsonStream, $streamCenturion): void {
                        $jsonStream->end([
                            'ponds' => resolve([
                                'f' => resolve(resolve(resolve('the girl who waited'))),
                                'm' => resolve($streamCenturion),
                            ]),
                        ]);
                    });

                    Loop::addTimer(0.1, static function () use ($streamCenturion): void {
                        $streamCenturion->end('the last centurion');
                    });

                    return [$input, '{"river":"song","melody":"by the pond","from":"the vortex","vortex":{"ponds":{"f":"the girl who waited","m":"the last centurion"}}}'];
            },
        ];

        yield [
            static function (): array {
                $streamA         = new ThroughStream();
                $streamB         = new ThroughStream();
                $streamCenturion = new ThroughStream();
                $deferred        = new Deferred();
                $jsonStream      = new JsonStream();

                $input = [
                    'river' => $streamA,
                    'melody' => $streamB,
                    'from' => $deferred->promise(),
                    'vortex' => $jsonStream,
                    'timestream' => Observable::fromArray(['don\'t', 'blink']),
                ];

                Loop::addTimer(0.1, static function () use ($streamA): void {
                    $streamA->end('song');
                });

                    Loop::addTimer(0.05, static function () use ($streamB): void {
                        $streamB->end('by the pond');
                    });

                    Loop::addTimer(0.01, static function () use ($deferred): void {
                        $deferred->resolve('the vortex');
                    });

                    Loop::addTimer(0.05, static function () use ($jsonStream, $streamCenturion): void {
                        $jsonStream->end([
                            'ponds' => resolve([
                                'f' => resolve(resolve(resolve('the girl who waited'))),
                                'm' => resolve($streamCenturion),
                            ]),
                        ]);
                    });

                    Loop::addTimer(0.1, static function () use ($streamCenturion): void {
                        $streamCenturion->end('the last centurion');
                    });

                    return [$input, '{"river":"song","melody":"by the pond","from":"the vortex","vortex":{"ponds":{"f":"the girl who waited","m":"the last centurion"}},"timestream":["don\u0027t","blink"]}'];
            },
        ];

        yield [
            static function (): array {
                $streamA         = new ThroughStream();
                $streamB         = new ThroughStream();
                $streamCenturion = new ThroughStream();
                $deferred        = new Deferred();
                $jsonStream      = new JsonStream();
                $subject         = new Subject();

                $input = [
                    'timestream' => $subject,
                    'river' => $streamA,
                    'melody' => $streamB,
                    'from' => $deferred->promise(),
                    'vortex' => $jsonStream,
                ];

                Loop::addTimer(0.1, static function () use ($streamA): void {
                    $streamA->end('song');
                });

                    Loop::addTimer(0.05, static function () use ($streamB): void {
                        $streamB->end('by the pond');
                    });

                    Loop::addTimer(0.01, static function () use ($deferred): void {
                        $deferred->resolve('the vortex');
                    });

                    Loop::addTimer(0.05, static function () use ($jsonStream, $streamCenturion): void {
                        $jsonStream->end([
                            'ponds' => resolve([
                                'f' => resolve(resolve(resolve('the girl who waited'))),
                                'm' => resolve($streamCenturion),
                            ]),
                        ]);
                    });

                    Loop::addTimer(0.1, static function () use ($streamCenturion): void {
                        $streamCenturion->end('the last centurion');
                    });

                    Loop::addTimer(0.2, static function () use ($subject): void {
                        $subject->onNext('don\'t');
                    });

                    Loop::addTimer(0.7, static function () use ($subject): void {
                        $subject->onNext('blink');
                    });

                    Loop::addTimer(0.9, static function () use ($subject): void {
                        $subject->onCompleted();
                    });

                    return [$input, '{"timestream":["don\u0027t","blink"],"river":"song","melody":"by the pond","from":"the vortex","vortex":{"ponds":{"f":"the girl who waited","m":"the last centurion"}}}'];
            },
        ];

        yield [
            static function (): array {
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

                Loop::addTimer(0.1, static function () use ($stream): void {
                    $stream->end('e');
                });

                    return [$input, '{"a":{"b":{"c":{"d":"e"}}}}'];
            },
        ];

        yield [
            static function (): array {
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

                Loop::addTimer(0.1, static function () use ($stream): void {
                    $stream->end('e');
                });

                    return [$input, '{"a":{"b":{"c":{"d":"e"}}}}'];
            },
        ];

        yield [
            static function (): array {
                $input = range(1, 10);

                return [$input, '[1,2,3,4,5,6,7,8,9,10]'];
            },
        ];

        yield [
            static function (): array {
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
            },
        ];

        yield [
            static function (): array {
                $input = [
                    ['foo' => 'bar'],
                    ['bar' => 'foo'],
                ];

                return [$input, '[{"foo":"bar"},{"bar":"foo"}]'];
            },
        ];

        yield [
            static function (): array {
                $stream = new ThroughStream();

                $input = [
                    [
                        'foo' => resolve('bar'),
                    ],
                    resolve(['bar' => $stream]),
                ];

                Loop::addTimer(0.1, static function () use ($stream): void {
                    $stream->end('foo');
                });

                    return [$input, '[{"foo":"bar"},{"bar":"foo"}]'];
            },
        ];

        yield [
            static function (): array {
                $stream = new ThroughStream();

                $input = ['😱' => $stream];

                Loop::addTimer(0.05, static function () use ($stream): void {
                    $stream->end('😱');
                });

                    return [$input, '{"\ud83d\ude31":"\ud83d\ude31"}'];
            },
        ];

        yield [
            static function (): array {
                $stream = new ThroughStream();

                $input = ['😱' => $stream];

                Loop::addTimer(0.05, static function () use ($stream): void {
                    $stream->end('<\'&"&\'>');
                });

                    return [$input, '{"\ud83d\ude31":"\u003C\u0027\u0026\u0022\u0026\u0027\u003E"}'];
            },
        ];

        yield [
            static function (): array {
                $input = [
                    '😱' => Observable::fromArray(['foo', 'bar']),
                ];

                return [$input, '{"\ud83d\ude31":["foo","bar"]}'];
            },
        ];

        yield [
            static function (): array {
                $input = Observable::fromArray(['foo', 'bar']);

                return [$input, '["foo","bar"]'];
            },
        ];

        yield [
            static function (): array {
                $input = Observable::fromArray([resolve('foo'), timedPromise(0.3, 'bar')]);

                return [$input, '["foo","bar"]'];
            },
        ];

        yield [
            static function (): array {
                $streamA         = new ThroughStream();
                $streamB         = new ThroughStream();
                $streamCenturion = new ThroughStream();
                $deferred        = new Deferred();
                $jsonStream      = new JsonStream();
                $subject         = new Subject();

                $input = Observable::fromArray([
                    resolve('foo'),
                    timedPromise(0.3, 'bar'),
                    [
                        'timestream' => $subject,
                        'river' => $streamA,
                        'melody' => $streamB,
                        'from' => $deferred->promise(),
                        'vortex' => $jsonStream,
                    ],
                ]);

                    Loop::addTimer(0.1, static function () use ($streamA): void {
                        $streamA->end('song');
                    });

                    Loop::addTimer(0.05, static function () use ($streamB): void {
                        $streamB->end('by the pond');
                    });

                    Loop::addTimer(0.01, static function () use ($deferred): void {
                        $deferred->resolve('the vortex');
                    });

                    Loop::addTimer(0.05, static function () use ($jsonStream, $streamCenturion): void {
                        $jsonStream->end([
                            'ponds' => resolve([
                                'f' => resolve(resolve(resolve('the girl who waited'))),
                                'm' => resolve($streamCenturion),
                            ]),
                        ]);
                    });

                    Loop::addTimer(0.1, static function () use ($streamCenturion): void {
                        $streamCenturion->end('the last centurion');
                    });

                    Loop::addTimer(0.2, static function () use ($subject): void {
                        $subject->onNext('don\'t');
                    });

                    Loop::addTimer(0.7, static function () use ($subject): void {
                        $subject->onNext('blink');
                    });

                    Loop::addTimer(0.9, static function () use ($subject): void {
                        $subject->onCompleted();
                    });

                    return [$input, '["foo","bar",{"timestream":["don\u0027t","blink"],"river":"song","melody":"by the pond","from":"the vortex","vortex":{"ponds":{"f":"the girl who waited","m":"the last centurion"}}}]'];
            },
        ];

        yield [
            static function (): array {
                $input = [
                    'data' => Observable::fromArray([timedPromise(0.1, 'foo'), timedPromise(0.3, 'bar')]),
                ];

                return [$input, '{"data":["foo","bar"]}'];
            },
        ];
    }

    /**
     * @dataProvider provideJSONs
     */
    public function testStream(callable $args): void
    {
        [$input, $output] = $args();

        $throughStream = new ThroughStream();

        $stream = new JsonStream();

        $stream->pipe($throughStream);

        Loop::addTimer(0.01, static function () use ($stream, $input): void {
            if ($input instanceof ObservableInterface) {
                $stream->writeObservable($input);
                $stream->end();

                return;
            }

            $stream->end($input);
        });

        $buffer = $this->await(buffer($throughStream), 6);

        self::assertSame($output, $buffer);
        $json = json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_NONE, json_last_error());
        self::assertIsArray($json);
        self::assertSame(json_decode($output, true), $json); /** @phpstan-ignore-line */
    }

    public function testEncodeFlags(): void
    {
        $stream = new JsonStream(0);

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->end(['<\'&"&\'>']);
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('["<\'&\"&\'>"]', $buffer);
        $json = json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_NONE, json_last_error());
        self::assertSame(['<\'&"&\'>'], $json); /** @phpstan-ignore-line */
    }

    public function testObjectOrArrayObject(): void
    {
        $stream = new JsonStream();

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->writeValue(true);
            $stream->end([false]);
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('{true,false}', $buffer);
        json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_SYNTAX, json_last_error());
    }

    public function testObjectOrArrayArray(): void
    {
        $stream = new JsonStream();

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->end([true, false]);
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('[true,false]', $buffer);
        json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testForceArray(): void
    {
        $stream = JsonStream::createArray();

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->end([true, false]);
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('[true,false]', $buffer);
        json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testForceArrayWhileWeWriteAnObject(): void
    {
        $stream = JsonStream::createArray();

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->end(['a' => true, 'b' => false]);
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('["a":true,"b":false]', $buffer);
        json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_SYNTAX, json_last_error());
    }

    public function testForceObject(): void
    {
        $stream = JsonStream::createObject();

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->end(['a' => true, 'b' => false]);
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('{"a":true,"b":false}', $buffer);
        json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function testForceObjectWhileWeWriteAnArray(): void
    {
        $stream = JsonStream::createObject();

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->end([true, false]);
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('{true,false}', $buffer);
        json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_SYNTAX, json_last_error());
    }

    public function testDoubleKeys(): void
    {
        $stream = JsonStream::createObject();

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->write('a', true);
            $stream->write('a', false);
            $stream->end();
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('{"a":true,"a":false}', $buffer);
        $json = json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_NONE, json_last_error());
        self::assertSame(['a' => false], $json); /** @phpstan-ignore-line */
    }

    public function testNoMoreWriteAfterEnd(): void
    {
        $stream = JsonStream::createObject();

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->write('a', true);
            $stream->end();
            $stream->write('b', false);
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('{"a":true}', $buffer);
        $json = json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_NONE, json_last_error());
        self::assertSame(['a' => true], $json); /** @phpstan-ignore-line */
    }

    public function testNoMoreWriteValueAfterEnd(): void
    {
        $stream = JsonStream::createObject();

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->write('a', true);
            $stream->end();
            $stream->writeValue(false);
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('{"a":true}', $buffer);
        $json = json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_NONE, json_last_error());
        self::assertSame(['a' => true], $json); /** @phpstan-ignore-line */
    }

    public function testNoMoreWriteArrayAfterEnd(): void
    {
        $stream = JsonStream::createObject();

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->write('a', true);
            $stream->end();
            $stream->writeArray([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('{"a":true}', $buffer);
        $json = json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_NONE, json_last_error());
        self::assertSame(['a' => true], $json); /** @phpstan-ignore-line */
    }

    public function testNoMoreEndAfterEnd(): void
    {
        $stream = JsonStream::createObject();

        Loop::addTimer(0.01, static function () use ($stream): void {
            $stream->write('a', true);
            $stream->end();
            $stream->end([1, 2, 3, 4, 5, 6, 7, 8, 9, 10]);
        });

        $buffer = $this->await(buffer($stream), 6);

        self::assertSame('{"a":true}', $buffer);
        $json = json_decode($buffer, true); /** @phpstan-ignore-line */
        self::assertSame(JSON_ERROR_NONE, json_last_error());
        self::assertSame(['a' => true], $json); /** @phpstan-ignore-line */
    }

    public function testPauseResume(): void
    {
        $stream = JsonStream::createObject();

        $shouldntBeCalledCount = 0;
        $shouldntBeCalled      = static function () use (&$shouldntBeCalledCount): void {
            $shouldntBeCalledCount++;
        };
        $shouldBeCalledCount   = 0;
        $shouldBeCalled        = static function () use (&$shouldBeCalledCount): void {
            $shouldBeCalledCount++;
        };

        self::assertSame(0, $shouldntBeCalledCount);
        self::assertSame(0, $shouldBeCalledCount);

        $stream->on('data', $shouldntBeCalled);
        $stream->pause();

        $stream->write('key', 'value');

        self::assertSame(0, $shouldntBeCalledCount); /** @phpstan-ignore-line */
        self::assertSame(0, $shouldBeCalledCount); /** @phpstan-ignore-line */

        $stream->removeListener('data', $shouldntBeCalled);
        $stream->on('data', $shouldBeCalled);

        self::assertSame(0, $shouldntBeCalledCount); /** @phpstan-ignore-line */
        self::assertSame(0, $shouldBeCalledCount); /** @phpstan-ignore-line */

        $stream->resume();

        self::assertSame(0, $shouldntBeCalledCount); /** @phpstan-ignore-line */
        self::assertSame(1, $shouldBeCalledCount); /** @phpstan-ignore-line */

        $stream->write('key', 'value');

        self::assertSame(0, $shouldntBeCalledCount); /** @phpstan-ignore-line */
        self::assertSame(4, $shouldBeCalledCount);

        $stream->end();

        self::assertSame(0, $shouldntBeCalledCount); /** @phpstan-ignore-line */
        self::assertSame(5, $shouldBeCalledCount);
    }

    public function testDoubleResolveStream(): void
    {
        $jsonStream     = new JsonStream();
        $anotherStream  = new ThroughStream();
        $anotherStream1 = new ThroughStream();

        Loop::addTimer(0.001, static function () use ($jsonStream, $anotherStream, $anotherStream1): void {
            $jsonStream->end([
                'first',
                resolve($anotherStream),
                resolve($anotherStream1),
                resolve('third'),
            ]);
        });

        $i     = 0;
        $timer = Loop::addPeriodicTimer(0.1, static function () use ($anotherStream, &$i, &$timer): void {
            $i++;
            $anotherStream->write((string) $i);
            if ($i <= 10) {
                return;
            }

            $anotherStream->end();
            Loop::cancelTimer($timer);
        });

        $j      = 0;
        $timer1 = Loop::addPeriodicTimer(0.01, static function () use ($anotherStream1, &$j, &$timer1): void {
            $j++;
            $anotherStream1->write((string) ($j));
            if ($j <= 10) {
                return;
            }

            $anotherStream1->end();
            Loop::cancelTimer($timer1);
        });

        $buffer = $this->await(buffer($jsonStream), 2);

        self::assertSame('["first","1234567891011","1234567891011","third"]', $buffer);
    }
}

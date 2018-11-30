<?php declare(strict_types=1);

use React\EventLoop\Factory;
use React\Stream\ThroughStream;
use WyriHaximus\React\Stream\Json\JsonStream;
use function Clue\React\Block\await;
use function React\Promise\resolve;
use function React\Promise\Stream\buffer;

require \dirname(__DIR__) . '/vendor/autoload.php';

$loop = Factory::create();
$jsonStream = new JsonStream();

$loop->futureTick(function () use ($jsonStream): void {
    $stream = new ThroughStream();
    $anotherStream = new ThroughStream();

    $jsonStream->end([
        'key' => 'value',
        'promise' => resolve('value'),
        'stream' => $stream,
        'nested' => [
            'a' => 'b',
            'c' => 'd',
        ],
        'nested_promises' => [
            resolve('first'),
            resolve('second'),
        ],
        'nested_mixed' => [
            'first',
            resolve($anotherStream),
            resolve('third'),
        ],
    ]);

    $stream->end('stream contents');
    $anotherStream->end('second');
});

echo await(buffer($jsonStream), $loop);

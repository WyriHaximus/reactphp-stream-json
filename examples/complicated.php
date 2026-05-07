<?php

declare(strict_types=1);

use React\EventLoop\Factory;
use React\EventLoop\TimerInterface;
use React\Stream\ThroughStream;
use WyriHaximus\React\Stream\Json\JsonStream;

use function React\Promise\resolve;

require dirname(__DIR__) . '/vendor/autoload.php';

$loop       = Factory::create();
$buffer     = '';
$jsonStream = new JsonStream();
$jsonStream->on('data', static function ($data) use (&$buffer): void {
    $buffer .= $data;
    echo $data;
});

$loop->addTimer(70, static function () use ($jsonStream): void {
    $jsonStream->close();
});

$i = 0;
$j = 'a';
$loop->addPeriodicTimer(0.05, static function (TimerInterface $timer) use (&$i, &$j, $jsonStream): void {
    if ($i >= 1337) {
        $timer->cancel();
    }

    $jsonStream->write($j++, $i++);
});

$loop->addTimer(1, static function () use ($jsonStream): void {
    $jsonStream->write('promise_scalar', resolve(['foo', 'bar']));
});

for ($k = 3; $k < 50; $k += 5) {
    $streamId = $k;
    $loop->addTimer($k, static function () use ($jsonStream, $loop, $streamId): void {
        $throughStream = new ThroughStream();
        $jsonStream->write('stream_' . $streamId, $throughStream);
        $alphabet = 'a';
        $throughStream->write($alphabet);
        $loop->addPeriodicTimer(0.1, static function (TimerInterface $timer) use (&$alphabet, $throughStream): void {
            $alphabet++;
            $throughStream->write($alphabet);
            if ($alphabet !== 'z') {
                return;
            }

            $timer->cancel();
            $throughStream->end();
        });
    });
}

$loop->addTimer(5, static function () use ($jsonStream): void {
    $jsonStream->write('promise_promise', resolve([
        'promise_b' => resolve('b00t'),
        'promise_w' => resolve('w00t'),
    ]));
});

$loop->run();

var_export(json_decode($buffer, true));
var_export(json_last_error());
var_export(json_last_error_msg());

<?php

declare(strict_types=1);

namespace WyriHaximus\React\Stream\Json;

use Rx\ObservableInterface;

final class JsonStreamFactory
{
    /** @param array<mixed> $values */
    public static function createFromArray(array $values, int $encodeFlags = JsonStream::DEFAULT_ENCODE_FLAGS): JsonStream
    {
        $stream = new JsonStream($encodeFlags);
        $stream->pause();
        $stream->writeArray($values);
        $stream->end();

        return $stream;
    }

    public static function createFromObservable(ObservableInterface $values, int $encodeFlags = JsonStream::DEFAULT_ENCODE_FLAGS): JsonStream
    {
        $stream = new JsonStream($encodeFlags);
        $stream->pause();
        $stream->writeObservable($values);
        $stream->end();

        return $stream;
    }
}

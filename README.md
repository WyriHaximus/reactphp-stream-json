# Incremental JSON stream for [ReactPHP](https://github.com/reactphp/) streams

[![Linux Build Status](https://travis-ci.org/WyriHaximus/reactphp-stream-json.png)](https://travis-ci.org/WyriHaximus/reactphp-stream-json)
[![Latest Stable Version](https://poser.pugx.org/WyriHaximus/react-stream-json/v/stable.png)](https://packagist.org/packages/WyriHaximus/react-stream-json)
[![Total Downloads](https://poser.pugx.org/WyriHaximus/react-stream-json/downloads.png)](https://packagist.org/packages/WyriHaximus/react-stream-json/stats)
[![Code Coverage](https://scrutinizer-ci.com/g/WyriHaximus/reactphp-stream-json/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/WyriHaximus/reactphp-stream-json/?branch=master)
[![License](https://poser.pugx.org/WyriHaximus/react-stream-json/license.png)](https://packagist.org/packages/wyrihaximus/react-stream-json)
[![PHP 7 ready](http://php7ready.timesplinter.ch/WyriHaximus/reactphp-stream-json/badge.svg)](https://travis-ci.org/WyriHaximus/reactphp-stream-json)

### Installation ###

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```
composer require wyrihaximus/react-stream-json 
```

### Usage ###

The `JsonStream` implements the `ReadableStreamInterface` from `react/stream` and behaves like the `ThroughStream`, the moment you `write*` to it, it will emit data.

The following example has a fixed number of items in the JSON and can be written to `end` with out needed a `write*` call.

```php
$stream = new ThroughStream();
$anotherStream = new ThroughStream();

$jsonStream = new JsonStream();
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
```

Stream contents will be:
`{"key":"value","promise":"value","stream":"stream contents","nested":{"a":"b","c":"d"},"nested_promises":["first","second"],"nested_mixed":["first","second","third"]}`

### Methods ###

All the following methods try to resolve `$value`, when it encounters a promise it will wait for the promise 
to resolve, and when it encounters a stream it will forward the stream's contents to it's own listeners. 
Promises can resolve to a stream but not vise versa. Any other parameters will be run though `json_encode`, 
except for arrays, those will be searched through for promises and streams.

##### write #####

`write(string $key, $value)` accepts a key and a value as argument. Writing a new key and value pair to the stream.

##### writeValue #####

`write($value)` accepts only a value as argument. Writing the value pair to the stream.

##### writeArray #####

`writeArray(array $values)` will iterate over the items in the array and call `write` or `writeValue` depending on 
the type of the key. 

##### writeObservable #####

`writeObservable(ObservableInterface $values)` will subscribe to the observable and call `writeValue` on each item 
coming in. 

##### end #####

`end(array $values = [])` will call `writeArray` when `$values` contains something and then or otherwise
end the stream. At that point no new values are accepted and it continues to operate any outstanding promises or streams
have been resolve/completed.

### Caveats ###

The stream doesn't know if you want to write an object or an array so it assumes an object.
It does try to detect when you haven't written anything yet and call `writeArray` or `end`
with an array of items. You can force writing an array or object by calling `JsonStream::createArray`
or `JsonStream::createObject` when creating an instance of `JsonStream`. Writing object items 
to a stream set up as array or vise versa will result in malformed `JSON`. In short you MUST 
know what kind of `JSON` you will be writing.

When using [`write`](#write) the key parameter isn't checked duplicates resulting in writing it 
out again to the stream. Bear in mind that while `PHP` considers this perfectly valid `JSON`, the
[`JSON` spec](https://tools.ietf.org/html/rfc7159) doesn't specify a behavior for this. So your 
milage might vary, as described in [section 4](https://tools.ietf.org/html/rfc7159#section-4) of 
RFC7159, in `PHP`'s case it will only use the value from the last occurrence.

## Contributing ##

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License ##

Copyright 2019 [Cees-Jan Kiewiet](http://wyrihaximus.net/)

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.

<?php
namespace ryunosuke\Test\microute\http;

use ryunosuke\microute\http\FileStream;

class FileStreamTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_all()
    {
        $file = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($file, '123456789abcdef0');
        $stream = new FileStream($file);

        $this->assertEquals(16, $stream->getSize());
        $this->assertEquals('123456789abcdef0', (string) $stream);

        $stream->seek(1, SEEK_SET);
        $this->assertEquals(false, $stream->eof());
        $this->assertEquals(1, $stream->tell());
        $this->assertEquals('23', $stream->read(2));
        $this->assertEquals(3, $stream->write('xyz'));
        $stream->rewind();
        $this->assertEquals(0, $stream->tell());
        $this->assertEquals('123xy', $stream->read(5));
        $this->assertEquals('z789abcdef0', $stream->getContents());
        $this->assertEquals('', $stream->read(999));
        $this->assertEquals(true, $stream->eof());

        $this->assertEquals(16, $stream->getSize());
        $this->assertArrayHasKey('uri', $stream->getMetadata());
        $this->assertEquals($file, $stream->getMetadata('uri'));
        $this->assertEquals(true, $stream->isSeekable());
        $this->assertEquals(true, $stream->isSeekable());
        $this->assertEquals(true, $stream->isReadable());
        $this->assertEquals(true, $stream->isWritable());

        $this->assertEquals(3, $stream->write('xyz'));
        $this->assertEquals(19, $stream->getSize());
        $this->assertEquals('123xyz789abcdef0xyz', (string) $stream);
    }

    function test_error()
    {
        set_error_handler(fn() => null);

        $stream = new FileStream('notfound file');

        // pre double close
        $stream->close();
        $stream->close();

        $this->assertException('expected false', fn() => $stream->read(1));

        // post double close
        $stream->close();
        $stream->close();

        $file = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($file, '123456789abcdef0');

        $stream = new FileStream($file);

        $this->assertException('failed to feof', fn() => $stream->eof());

        $this->assertIsResource($stream->detach());
        $this->assertException('failed to fopen', fn() => $stream->read(1));

        set_error_handler(fn() => throw new \Exception());

        $stream = new FileStream('notfound file');
        $this->assertException('unknown error', fn() => $stream->read(1));
    }
}

<?php
namespace ryunosuke\Test\microute\http;

use Psr\Http\Message\StreamInterface;
use ryunosuke\microute\http\UploadedFile;

class UploadedFileTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_all()
    {
        $fn = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($fn, '123456789abcdef0');

        $symfonyFile = new \Symfony\Component\HttpFoundation\File\UploadedFile($fn, 'local-name.txt', 'text/plain', UPLOAD_ERR_OK);
        $file = UploadedFile::fromSymfonyFile($symfonyFile);

        $this->assertInstanceOf(StreamInterface::class, $file->getStream());
        $this->assertEquals($symfonyFile->getError(), $file->getError());
        $this->assertEquals($symfonyFile->getSize(), $file->getSize());
        $this->assertEquals($symfonyFile->getClientOriginalName(), $file->getClientFilename());
        $this->assertEquals($symfonyFile->getClientMimeType(), $file->getClientMediaType());

        $fn2 = tempnam(sys_get_temp_dir(), 'test');
        $file->moveTo($fn2);
        $this->assertFileDoesNotExist($fn);
        $this->assertFileExists($fn2);
    }
}

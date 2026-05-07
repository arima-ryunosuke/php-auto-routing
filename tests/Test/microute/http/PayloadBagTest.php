<?php
namespace ryunosuke\Test\microute\http;

use ryunosuke\microute\http\PayloadBag;
use ryunosuke\microute\http\Request;
use ryunosuke\microute\http\UploadedFile;

class PayloadBagTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_formdata()
    {
        $bag = new PayloadBag(new Request(
            request: ['x' => ['y' => ['z1' => 'xyz1']]],
            files  : [
                'x' => [
                    'name'     => [
                        'y' => [
                            'z2' => 'local-name.txt',
                        ],
                    ],
                    'type'     => [
                        'y' => [
                            'z2' => '',
                        ],
                    ],
                    'tmp_name' => [
                        'y' => [
                            'z2' => __FILE__,
                        ],
                    ],
                    'error'    => [
                        'y' => [
                            'z2' => UPLOAD_ERR_OK,
                        ],
                    ],
                    'size'     => [
                        'y' => [
                            'z2' => 0,
                        ],
                    ],
                ],
            ],
        ));
        $this->assertEquals([
            'x' => [
                'y' => [
                    'z1' => 'xyz1',
                    'z2' => new UploadedFile(__FILE__, 'local-name.txt'),
                ],
            ],
        ], $bag->all());
        $this->assertEquals('', $bag->getContent());
    }

    function test_json()
    {
        $bag = new PayloadBag(new Request(
            content: json_encode(['x' => ['y' => ['z' => 'xyz']]]),
            server : ['CONTENT_TYPE' => 'application/json'],
        ));
        $this->assertEquals(['x' => ['y' => ['z' => 'xyz']]], $bag->all());
        $this->assertEquals('{"x":{"y":{"z":"xyz"}}}', $bag->getContent());

        $bag = new PayloadBag(new Request(
            content: json_encode('scalar'),
            server : ['CONTENT_TYPE' => 'application/json'],
        ));
        $this->assertEquals([], $bag->all());
        $this->assertEquals('scalar', $bag->getContent());

        $this->assertException('Could not decode request body', fn() => new PayloadBag(new Request(
            content: 'invalid json',
            server : ['CONTENT_TYPE' => 'application/json'],
        )));
    }
}

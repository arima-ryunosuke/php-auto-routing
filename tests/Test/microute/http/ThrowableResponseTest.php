<?php
namespace ryunosuke\Test\microute\http;

use ryunosuke\microute\http\ThrowableResponse;
use Symfony\Component\HttpFoundation\Response;

class ThrowableResponseTest extends \ryunosuke\Test\AbstractTestCase
{
    function test_all()
    {
        $response = new Response('content', 204, ['Custom-Header' => 'true']);
        $throwable = new ThrowableResponse($response);

        $this->assertSame('content', $throwable->getContent());
        $this->assertSame(204, $throwable->getStatusCode());
        $this->assertSame('true', $throwable->headers->get('Custom-Header'));
        $this->assertSame($response, $throwable->response());
    }
}

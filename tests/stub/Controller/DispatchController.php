<?php
namespace ryunosuke\Test\stub\Controller;

use ryunosuke\microute\http\ThrowableResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DispatchController extends AbstractController
{
    public function init()
    {
        $this->request->attributes->set('init', true);
        parent::init();

        if ($this->request->attributes->get('init-response')) {
            return new JsonResponse('init-response');
        }

        if ($this->request->attributes->get('throw-response')) {
            throw new ThrowableResponse(new JsonResponse('throw-response'));
        }
    }

    public function before()
    {
        $this->request->attributes->set('before', true);
        parent::before();
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function mainAction()
    {
        return 'main';
    }

    public function after()
    {
        $this->response->setContent($this->response->getContent() . 'after');
        parent::after();
    }

    public function finish()
    {
        $this->response->setContent($this->response->getContent() . 'finish');
        parent::finish();

        if ($this->request->attributes->get('finish-response')) {
            return new JsonResponse('finish-response');
        }
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function thrown1Action()
    {
        throw new \UnexpectedValueException('catch');
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function thrown2Action()
    {
        throw new \Exception('uncatch');
    }

    public function thrown3Action()
    {
        throw new HttpException(404, '', null, ['X-Custom' => 123]);
    }

    public function catch(\Throwable $t)
    {
        if ($t instanceof \UnexpectedValueException || $t instanceof HttpException) {
            return new JsonResponse('error-response');
        }
        throw $t;
    }

    public function finally(Response $response)
    {
        $response->headers->set('X-Custom-Header', 'finally');
    }
}

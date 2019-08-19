<?php
namespace ryunosuke\Test\stub\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;

class DispatchController extends AbstractController
{
    public function init()
    {
        $this->request->attributes->set('init', true);
        parent::init();

        if ($this->request->attributes->get('init-response')) {
            return new JsonResponse('init-response');
        };
    }

    public function before()
    {
        $this->request->attributes->set('before', true);
        parent::before();
    }

    /**
     * @action get
     */
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
        };
    }

    /**
     * @context ,csv
     */
    public function contextAction()
    {
        if ($this->request->attributes->has('ctype')) {
            $this->response->headers->set('Content-Type', $this->request->attributes->get('ctype'));
        }
        return 'context';
    }

    /**
     * @action get
     */
    public function thrown1Action()
    {
        throw new \UnexpectedValueException('catch');
    }

    /**
     * @action get
     */
    public function thrown2Action()
    {
        throw new \Exception('uncatch');
    }

    public function error(\Exception $ex)
    {
        if (!$ex instanceof \UnexpectedValueException) {
            throw $ex;
        }
        parent::error($ex);
        return new JsonResponse('error-response');
    }
}

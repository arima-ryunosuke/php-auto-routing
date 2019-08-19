<?php
namespace ryunosuke\Test\stub\Controller;

use Symfony\Component\HttpFoundation\Response;

class EventController extends AbstractController
{
    /**
     * @cache 10
     */
    public function alternateAction()
    {
        return 'alternateAction';
    }

    /**
     * @event:cache 10
     */
    public function cacheAction()
    {
        return 'cached_response:' . mt_rand();
    }

    /**
     * @action get
     * @event:cache 10
     */
    public function cache1Action()
    {
        return 'cached_response1:' . mt_rand();
    }

    /**
     * @action get
     * @event:cache 10
     */
    public function cacheDirectAction()
    {
        return Response::create('cached_direct_response:' . mt_rand());
    }

    /**
     * @event:other1 1, 2, 3
     * @event:other2 4, 5, 6
     */
    public function otherAction()
    {
        return Response::create('other_event:' . mt_rand());
    }

    /**
     * @event:unknown
     */
    public function unknownAction()
    {
        return Response::create('other_event:' . mt_rand());
    }

    protected function other1Event($context, $a, $b, $c)
    {
        if ($context === 'pre') {
            if ($this->request->query->get('other1:pre') === [$a, $b, $c]) {
                return Response::create('other1:pre');
            }
        }
        if ($context === 'post') {
            if ($this->request->query->get('other1:post') === [$a, $b, $c]) {
                return Response::create('other1:post');
            }
        }
    }

    protected function other2Event($context, $a, $b, $c)
    {
        if ($context === 'pre') {
            if ($this->request->query->get('other2:pre') === [$a, $b, $c]) {
                return Response::create('other2:pre');
            }

        }
        if ($context === 'post') {

            if ($this->request->query->get('other2:post') === [$a, $b, $c]) {
                return Response::create('other2:post');
            }
        }
    }
}

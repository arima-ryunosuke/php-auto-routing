<?php
namespace ryunosuke\Test\stub\Controller;

use Symfony\Component\HttpFoundation\Response;

class EventController extends AbstractController
{
    #[\ryunosuke\microute\attribute\Cache(10 * 10)]
    public function alternateAction()
    {
        return 'alternateAction';
    }

    #[\ryunosuke\microute\attribute\Event('cache', 10)]
    public function cacheAction()
    {
        return 'cached_response:' . mt_rand();
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\Event('cache', 10)]
    public function cache1Action()
    {
        return 'cached_response1:' . mt_rand();
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\Event('cache', 10)]
    public function cacheDirectAction()
    {
        return new Response('cached_direct_response:' . mt_rand());
    }

    #[\ryunosuke\microute\attribute\WebCache(10)]
    #[\ryunosuke\microute\attribute\Context('html')]
    public function publicAction()
    {
        return new Response('publiced_response');
    }

    #[\ryunosuke\microute\attribute\Event('other1', 1, 2, 3)]
    #[\ryunosuke\microute\attribute\Event('other2', 4, 5, 6)]
    public function otherAction()
    {
        return new Response('other_event:' . mt_rand());
    }

    #[\ryunosuke\microute\attribute\Event('unknown')]
    public function unknownAction()
    {
        return new Response('other_event:' . mt_rand());
    }

    protected function other1Event($context, $a, $b, $c)
    {
        if ($context === 'pre') {
            if ($this->request->query->get('other1:pre') === "$a, $b, $c") {
                return new Response('other1:pre');
            }
        }
        if ($context === 'post') {
            if ($this->request->query->get('other1:post') === "$a, $b, $c") {
                return new Response('other1:post');
            }
        }
    }

    protected function other2Event($context, $a, $b, $c)
    {
        if ($context === 'pre') {
            if ($this->request->query->get('other2:pre') === "$a, $b, $c") {
                return new Response('other2:pre');
            }

        }
        if ($context === 'post') {
            if ($this->request->query->get('other2:post') === "$a, $b, $c") {
                return new Response('other2:post');
            }
        }
    }
}

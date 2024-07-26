<?php
namespace ryunosuke\Test\stub\Controller;

use ryunosuke\microute\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class HogeController extends AbstractController
{
    public function noaction() { }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function defaultAction()
    {
        $query = http_build_query($this->request->query->all());
        return 'default-action' . (strlen($query) ? "?" : '') . $query;
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function actionSimpleAction()
    {
        return 'simple-action';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function actionRegexAction($arg1, $arg2)
    {
        return "$arg1/$arg2";
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function nopostAction()
    {
        return $this->request->getMethod();
    }

    #[\ryunosuke\microute\attribute\Origin('http://allowed1.host', 'http://allowed2.host:1234')]
    #[\ryunosuke\microute\attribute\Origin('http://*.allowed.host')]
    public function action_originAction()
    {
        return 'origin';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\Ajaxable(400)]
    public function action_ajaxAction()
    {
        return 'ajaxed';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\Context('json')]
    public function action_contextAction()
    {
        return 'json_context';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\Context('', 'json')]
    public function action_andcontextAction()
    {
        return 'and_context';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\Context('*')]
    public function action_anycontextAction()
    {
        return 'any_context';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\Context()]
    public function action_emptycontextAction()
    {
        return 'empty_context';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function action_nullAction()
    {
        return;
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function action_rawAction()
    {
        return 'string';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function action_responseAction()
    {
        return new Response('response');
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function action_unknownAction()
    {
        return ['unknown'];
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function actionAction()
    {
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function actionIdAction(int $id)
    {
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function actionAAction(int $id)
    {
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function actionBAction()
    {
        return 'forwarded';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function actionNoneViewAction()
    {
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function parameterAction(int $arg1, string $arg2, bool $arg3, float $arg4, array $arg5, \Throwable $arg6, ?string $arg7, $arg8, $argX = "defval", $argY = null)
    {
        return new JsonResponse(func_get_args());
    }

    public function arrayAction(array $array) { }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\Argument('get', 'cookie')]
    public function argumentAction(string $arg, string $cval)
    {
        return "$arg/$cval";
    }

    #[\ryunosuke\microute\attribute\Method('post')]
    public function argAction($arg)
    {
        return 'ok';
    }

    public function nullAction($arg = null)
    {
        return json_encode($arg);
    }

    public function queryableNullAction(?int $param1 = null, int $param2 = null)
    {
        return 'queryableNull:' . var_export($param1, true) . ', ' . var_export($param2, true);
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\Context('json')]
    public function queryContextAction($arg)
    {
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\BasicAuth('This page is required BASIC auth')]
    public function basicAction()
    {
        return 'basic';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\DigestAuth('This page is required DIGEST auth')]
    public function digestAction()
    {
        return 'digest';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\BasicAuth('This page is required "REALM" auth')]
    public function realmAction()
    {
        return 'realm';
    }

    #[\ryunosuke\microute\attribute\RateLimit(2, 2, 'attributes:id')]
    #[\ryunosuke\microute\attribute\RateLimit(1, 1, 'ip')]
    public function ratelimitAction()
    {
        return 'OK';
    }

    #[\ryunosuke\microute\attribute\RateLimit(5, 1, ['ip', 'post:id'])]
    public function loginAction()
    {
        return 'OK';
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    #[\ryunosuke\microute\attribute\Context('*')]
    public function contextAction()
    {
    }

    #[\ryunosuke\microute\attribute\Method('get')]
    public function action_throwAction()
    {
        throw new \UnexpectedValueException('ex');
    }

    public function headerAction()
    {
        $this->response->setCookie($this->cookie([
            'name'  => 'hoge',
            'value' => 'HOGE',
        ]));
        $this->response->setCors([
            'origin'  => '*',
            'methods' => 'GET',
        ]);
        return $this->json('cors');
    }

    public function catch(\Throwable $t)
    {
        if (!$t instanceof \UnexpectedValueException) {
            throw $t;
        }
        return new Response('error');
    }

    protected function subrequest(Controller $controller)
    {
        if ($this->action === 'parameter') {
            $this->request->attributes->set('arg5', [5]);
            $this->request->attributes->set('arg6', new \Exception('', 6));
            $this->request->query->set('arg7', 7);
            $this->request->query->set('arg8', 8);
        }
        if ($this->action === 'nopost') {
            $this->request->setMethod('POST');
        }
    }
}

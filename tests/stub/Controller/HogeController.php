<?php
namespace ryunosuke\Test\stub\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class HogeController extends AbstractController
{
    public function noaction() { }

    /**
     * @action get
     */
    public function defaultAction() { return 'default-action'; }

    /**
     * @action get
     */
    public function actionSimpleAction() { return 'simple-action'; }

    /**
     * @action get
     */
    public function actionRegexAction($arg1, $arg2) { return "$arg1/$arg2"; }

    /**
     * @action get
     */
    public function nopostAction() { }

    /**
     * @origin http://allowed1.host, http://allowed2.host:1234
     * @origin http://*.allowed.host
     */
    public function action_originAction() { return 'origin'; }

    /**
     * @action get
     * @ajaxable
     */
    public function action_ajaxAction() { return 'ajaxed'; }

    /**
     * @action get
     * @context json
     */
    public function action_contextAction() { return 'json_context'; }

    /**
     * @action get
     * @context ,json
     */
    public function action_andcontextAction() { return 'and_context'; }

    /**
     * @action get
     * @context *
     */
    public function action_anycontextAction() { return 'any_context'; }

    /**
     * @action get
     * @context
     */
    public function action_emptycontextAction() { return 'empty_context'; }

    /**
     * @action get
     */
    public function action_nullAction() { return; }

    /**
     * @action get
     */
    public function action_rawAction() { return 'string'; }

    /**
     * @action get
     */
    public function action_responseAction() { return new Response('response'); }

    /**
     * @action get
     */
    public function action_unknownAction() { return ['unknown']; }

    /**
     * @action get
     */
    public function actionAction() { }

    /**
     * @action get
     * @queryable false
     *
     * @param int $id
     */
    public function actionIdAction($id) { }

    /**
     * @action get
     *
     * @param int $id
     */
    public function actionAAction($id) { }

    /**
     * @action get
     */
    public function actionBAction() { return 'forwarded'; }

    /**
     * @action get
     */
    public function actionNoneViewAction() { }

    /**
     * @action get
     *
     * @param int $arg1
     * @param string $arg2
     * @param bool $arg3
     * @param float $arg4
     * @param array $arg5
     * @param \stdClass $arg6
     * @param int|string|null $arg7
     * @param $arg8
     * @param $argX
     * @param $argY
     * @return JsonResponse
     */
    public function parameterAction($arg1, $arg2, $arg3, $arg4, $arg5, $arg6, ?int $arg7, $arg8, $argX = "defval", $argY = null)
    {
        return new JsonResponse(func_get_args());
    }

    /**
     * @action get
     *
     * @param $mixed
     * @param string $string
     * @param array $array
     * @return string
     */
    public function arrayableAction($mixed, $string = null, $array = null)
    {
        return 'ok';
    }

    public function arrayAction(array $array) { }

    /**
     * @action post
     * @argument get,cookie
     *
     * @param string $arg
     * @param string $cval
     */
    public function argumentAction($arg, $cval) { }

    /**
     * @param $arg
     * @action post
     * @return string
     */
    public function argAction($arg)
    {
        return 'ok';
    }

    /**
     * @action get
     * @queryable false
     *
     * @param int $p1
     * @param int $p2
     */
    public function queryAction($p1, $p2) { }

    public function nullAction($arg = null)
    {
        return json_encode($arg);
    }

    /**
     * @action get
     * @context json
     */
    public function queryContextAction($arg) { }

    public function annotationNone() { }

    public function annotationOver() { }

    /**
     * @action get
     * @authentication basic This page is required BASIC auth
     */
    public function basicAction() { return 'basic'; }

    /**
     * @action get
     * @authentication digest This page is required DIGEST auth
     */
    public function digestAction() { return 'digest'; }

    /**
     * @action get
     * @authentication realm This page is required "REALM" auth
     */
    public function realmAction() { return 'realm'; }

    /**
     * @action get
     * @authentication hogera This page is required HOGERA auth
     */
    public function hogeraAction() { return 'hogera'; }

    /**
     * @action get
     * @context *
     */
    public function contextAction() { }

    /**
     * @aname
     */
    public function annotationZero() { }

    /**
     * @aname hoge
     */
    public function annotationOne() { }

    /**
     * @aname hoge, fuga
     */
    public function annotationTwo() { }

    /**
     * @action get
     */
    public function action_throwAction() { throw new \UnexpectedValueException('ex'); }

    public function error(\Exception $ex)
    {
        if (!$ex instanceof \UnexpectedValueException) {
            throw $ex;
        }
        return new Response('error');
    }
}

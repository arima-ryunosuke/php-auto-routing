<?php
namespace ryunosuke\Test\stub\Controller\Url;

use ryunosuke\Test\stub\Controller\AbstractController;

/**
 * @alias /relay
 * @scope (?<scoped>[0-9a-z]+)/
 */
class AllController extends AbstractController
{
    /**
     * @default-route true
     */
    public function defaultOnAction() { }

    /**
     * @default-route false
     */
    public function defaultOffAction() { }

    /**
     * @action post
     */
    public function postAction() { }

    /**
     * @param string $arg1
     * @param $arg2
     */
    public function parameterAction(string $arg1, array $arg2) { }

    /**
     * @param int $arg1
     * @param $arg2
     * @queryable false
     */
    public function queryableAction(int $arg1, array $arg2) { }

    /**
     * @redirect /mapping/redirect1 301 hoge
     * @redirect /mapping/redirect2 302 fuga
     * @redirect /mapping/redirect3 piyo
     */
    public function redirectAction() { }

    /**
     * @rewrite /mapping/rewrite1 hoge
     * @rewrite /mapping/rewrite2 fuga
     */
    public function rewriteAction() { }

    /**
     * @regex /mapping/regex1 hoge
     * @regex /mapping/regex2 fuga
     */
    public function regexAction() { }

    /**
     * @route mappingRoute hoge
     * @regex /mapping/route
     */
    public function routenameAction() { }

    /**
     * @param $id
     * @context json, xml
     */
    public function contextAction(int $id = 123) { }
}

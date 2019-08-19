<?php
namespace ryunosuke\Test\stub\Controller;

class ResolverController extends AbstractController
{
    /**
     * @action get
     *
     * @param int $id
     * @return string
     */
    public function action1Action($id) { return __FUNCTION__; }

    /**
     * @action get
     * @queryable false
     *
     * @param int $id
     * @return string
     */
    public function action2Action($id) { return __FUNCTION__; }
}

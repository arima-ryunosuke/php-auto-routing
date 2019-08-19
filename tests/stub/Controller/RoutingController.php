<?php
namespace ryunosuke\Test\stub\Controller;

/**
 * @default-route false
 * @queryable false
 */
class RoutingController extends AbstractController
{
    /**
     * @action get
     * @default-route true
     */
    public function defaultOnAction()
    {
        return 'defaultOn';
    }

    /**
     * @action get
     * @default-route false
     * @rewrite /routing/custom-on
     */
    public function defaultOffAction()
    {
        return 'defaultOff';
    }

    /**
     * @action get
     */
    public function defaultdefaultAction()
    {
        return 'defaultdefaul';
    }

    /**
     * @param int $param
     * @return string
     */
    public function queryableDefault($param)
    {
        return 'queryableDefault' . $param;
    }

    /**
     * @param int $param
     * @return string
     * @queryable true
     */
    public function queryableTrue($param)
    {
        return 'queryableTrue' . $param;
    }

    /**
     * @param int $param
     * @return string
     * @queryable false
     */
    public function queryableFalse($param)
    {
        return 'queryableFalse' . $param;
    }
}

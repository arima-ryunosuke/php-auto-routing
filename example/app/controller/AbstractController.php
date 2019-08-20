<?php
namespace ryunosuke\microute\example\controller;

abstract class AbstractController extends \ryunosuke\microute\Controller
{
    /** @var \stdClass */
    protected $view;

    public function construct()
    {
        $this->view = new \stdClass();
    }

    public function render($action_value)
    {
        $vars = (array) $this->view + $this->request->attributes->get('parameter', []);
        $vars['resolver'] = $this->service->resolver;
        $vars['controller'] = $this;
        $vars['action'] = $this->action;
        extract($vars);
        ob_start();
        include(__DIR__ . '/../view/' . $this->location() . '.phtml');
        return $this->response->setContent(ob_get_clean());
    }
}

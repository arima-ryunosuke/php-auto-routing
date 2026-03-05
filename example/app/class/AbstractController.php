<?php
namespace example\application;

use Symfony\Component\HttpFoundation\Response;

abstract class AbstractController extends \ryunosuke\microute\Controller
{
    /** @var \stdClass */
    protected $view;

    public function construct()
    {
        $this->view = new \stdClass();
    }

    protected function subrequest(\ryunosuke\microute\Controller $controller)
    {
        if ($this->action === 'argument') {
            $this->request->attributes->set('id', 123);
        }
    }

    public function render(mixed $action_value): Response
    {
        $vars = (array) $this->view + $this->request->attributes->get('parameter', []);
        $vars['request'] = $this->request;
        $vars['resolver'] = $this->service->resolver;
        $vars['controller'] = $this;
        $vars['action'] = $this->action;
        extract($vars);
        ob_start();
        include($this->viewFile('.phtml'));
        return $this->response->setContent(ob_get_clean());
    }
}

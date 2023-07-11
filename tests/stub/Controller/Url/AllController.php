<?php
namespace ryunosuke\Test\stub\Controller\Url;

use ryunosuke\Test\stub\Controller\AbstractController;

#[\ryunosuke\microute\attribute\Alias('/relay')]
#[\ryunosuke\microute\attribute\Scope('(?<scoped>[0-9a-z]+)/')]
class AllController extends AbstractController
{
    #[\ryunosuke\microute\attribute\DefaultRoute(true)]
    public function defaultOnAction()
    {
    }

    #[\ryunosuke\microute\attribute\DefaultRoute(false)]
    public function defaultOffAction()
    {
    }

    #[\ryunosuke\microute\attribute\Method('post')]
    public function postAction()
    {
    }

    public function parameterAction(string $arg1, array $arg2) { }

    #[\ryunosuke\microute\attribute\Queryable(false)]
    public function queryableAction(int $arg1, array $arg2)
    {
    }

    #[\ryunosuke\microute\attribute\Redirect('/mapping/redirect1', 301)]
    #[\ryunosuke\microute\attribute\Redirect('/mapping/redirect2', 302)]
    #[\ryunosuke\microute\attribute\Redirect('/mapping/redirect3')]
    public function redirectAction()
    {
    }

    #[\ryunosuke\microute\attribute\Rewrite('/mapping/rewrite1')]
    #[\ryunosuke\microute\attribute\Rewrite('/mapping/rewrite2')]
    public function rewriteAction()
    {
    }

    #[\ryunosuke\microute\attribute\Regex('/mapping/regex1')]
    #[\ryunosuke\microute\attribute\Regex('/mapping/regex2')]
    public function regexAction()
    {
    }

    #[\ryunosuke\microute\attribute\Route('mappingRoute')]
    #[\ryunosuke\microute\attribute\Regex('/mapping/route')]
    public function routenameAction()
    {
    }

    #[\ryunosuke\microute\attribute\Context('json', 'xml')]
    public function contextAction(int $id = 123)
    {
    }
}

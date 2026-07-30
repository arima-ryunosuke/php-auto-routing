<?php
namespace example\application\mvc\Api;

use example\application\AbstractController;
use ryunosuke\microute\attribute\Callback;
use ryunosuke\microute\attribute\Context;
use ryunosuke\microute\attribute\TrailingSlash;
use ryunosuke\microute\http\Request;

#[TrailingSlash(false)]
#[Callback('self::route')]
#[Context('', 'json')]
class Controller extends AbstractController
{
    public static function route(Request $request)
    {
        $service = $request->attributes->get('@service');
        $parameters = $request->getPathParameters($service->resolver->url(self::class), true);
        if ($parameters === null) {
            return null;
        }

        return [
            'controller' => self::class,
            'action'     => strtolower(implode('_', array_keys($parameters))),
            'parameters' => array_values($parameters),
        ];
    }

    #[TrailingSlash(true)]
    public function defaultAction()
    {
    }

    public function articlesAction(?int $article_id = null)
    {
        if ($this->request->attributes->get('context') === 'json') {
            return $this->json([
                'articles' => $article_id,
            ]);
        }
        return "articles/$article_id";
    }

    public function articles_commentsAction(?int $article_id = null, ?int $comments_id = null)
    {
        if ($this->request->attributes->get('context') === 'json') {
            return $this->json([
                'articles' => $article_id,
                'comments' => $comments_id,
            ]);
        }
        return "articles/$article_id/comments/$comments_id";
    }

    public function articles_comments_filesAction(?int $article_id = null, ?int $comments_id = null, ?int $file_id = null)
    {
        if ($this->request->attributes->get('context') === 'json') {
            return $this->json([
                'articles' => $article_id,
                'comments' => $comments_id,
                'files'    => $file_id,
            ]);
        }
        return "articles/$article_id/comments/$comments_id/files/$file_id";
    }
}

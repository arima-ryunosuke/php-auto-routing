<?php
namespace ryunosuke\microute\http;

use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * container for post+files or parsed-body caused content-type.
 */
class PayloadBag extends ParameterBag
{
    protected mixed $content;

    public function __construct(private \Symfony\Component\HttpFoundation\Request $request)
    {
        $this->content = $this;

        $parameters = [];

        $ctype = $request->headers->get('CONTENT_TYPE', '');
        if (str_starts_with($ctype, 'application/json')) {
            // for symfony<=5
            try {
                // $parameters = $request->getPayload();
                $payload = json_decode($request->getContent(), true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);
                if (is_array($payload)) {
                    $parameters = $payload;
                }
                else {
                    $this->content = $payload;
                }
            }
            catch (\JsonException $e) {
                throw new BadRequestException('Could not decode request body.', $e->getCode(), $e);
            }
        }
        else {
            $parameters = array_replace_recursive($request->request->all(), $request->files->all());
        }

        parent::__construct($parameters);
    }

    public function getContent(): mixed
    {
        if ($this->content === $this) {
            return $this->request->getContent();
        }
        return $this->content;
    }
}

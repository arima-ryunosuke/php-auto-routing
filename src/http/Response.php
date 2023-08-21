<?php
namespace ryunosuke\microute\http;

use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpFoundation\Cookie;

class Response extends HttpFoundation\Response
{
    public function setHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->headers->set($name, $value);
        }

        return $this;
    }

    public function setCookie(Cookie $cookie)
    {
        $this->headers->setCookie($cookie);

        return $this;
    }

    public function setCookies(array $values, int $expire = 0)
    {
        foreach ($values as $name => $value) {
            $this->headers->setCookie(new Cookie($name, $value, $expire));
        }

        return $this;
    }

    public function setDisposition(string $filename, $disposition = 'attachment')
    {
        $this->headers->set('Content-Disposition', $this->headers->makeDisposition($disposition, $filename));

        return $this;
    }

    public function setCors(array $cors)
    {
        if (array_key_exists('origin', $cors) && $cors['origin']) {
            $this->headers->set('Access-Control-Allow-Origin', $cors['origin']);
        }
        if (array_key_exists('methods', $cors) && $cors['methods']) {
            $this->headers->set('Access-Control-Allow-Methods', implode(', ', (array) $cors['methods']));
        }
        if (array_key_exists('headers', $cors) && $cors['headers']) {
            $this->headers->set('Access-Control-Allow-Headers', implode(', ', (array) $cors['headers']));
        }
        if (array_key_exists('credentials', $cors) && $cors['credentials']) {
            $this->headers->set('Access-Control-Allow-Credentials', 'true');
        }
        if (array_key_exists('max-age', $cors) && $cors['max-age']) {
            $this->headers->set('Access-Control-Allow-Max-Age', $cors['max-age']);
        }

        return $this;
    }
}

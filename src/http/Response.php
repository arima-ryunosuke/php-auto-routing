<?php
namespace ryunosuke\microute\http;

use Symfony\Component\HttpFoundation;
use Symfony\Component\HttpFoundation\Cookie;

class Response extends HttpFoundation\Response
{
    public function setHeaders(array $headers): static
    {
        foreach ($headers as $name => $value) {
            $this->headers->set($name, $value);
        }

        return $this;
    }

    public function setCookie(Cookie $cookie): static
    {
        $this->headers->setCookie($cookie);

        return $this;
    }

    public function setCookies(array $values, int $expire = 0): static
    {
        foreach ($values as $name => $value) {
            $this->headers->setCookie(new Cookie($name, $value, $expire));
        }

        return $this;
    }

    public function setDisposition(string $filename, string $disposition = 'attachment'): static
    {
        $this->headers->set('Content-Disposition', $this->headers->makeDisposition($disposition, $filename));

        return $this;
    }

    public function setRefresh(int $seconds = 0, ?string $url = null): static
    {
        $header = $seconds . ($url === null ? '' : "; url=$url");
        $this->headers->set('Refresh', $header);

        return $this;
    }

    public function setCors(array $cors): static
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

    public function setAcceptClientHints(string|array $hints, int $lifetime = 0, bool $withVary = true): static
    {
        // @see https://developer.mozilla.org/ja/docs/Web/HTTP/Headers/Accept-CH
        if ($hints === '*') {
            $hints = [
                'Content-DPR'                => false,
                'DPR'                        => false,
                'Device-Memory'              => false,
                'Viewport-Width'             => false,
                'Width'                      => false,
                'Sec-CH-UA'                  => false,
                'Sec-CH-UA-Arch'             => false,
                'Sec-CH-UA-Full-Version'     => false,
                'Sec-CH-UA-Mobile'           => false,
                'Sec-CH-UA-Model'            => false,
                'Sec-CH-UA-Platform'         => false,
                'Sec-CH-UA-Platform-Version' => false,
            ];
        }
        if (is_string($hints)) {
            $hints = [$hints => false];
        }

        foreach ($hints as $hint => $entropy) {
            $this->headers->set('Accept-CH', $hint, false);

            if ($entropy) {
                $this->headers->set('Critical-CH', $hint, false);
            }

            if ($withVary) {
                $this->headers->set('Vary', $hint, false);
            }
        }

        if ($lifetime) {
            $this->headers->set('Accept-CH-Lifetime', $lifetime * 1000);
        }

        return $this;
    }

    public function setAlternativeCookieHints(int $expire = 60 * 60 * 24, string $cookiName = 'client_hints'): static
    {
        if ($expire) {
            $this->setExpires(new \DateTime("+$expire seconds"));
            $this->setCache([
                'max_age'       => $expire,
                'last_modified' => new \DateTime(),
            ]);
        }

        $this->headers->set('content-type', 'text/javascript');
        $this->setContent(<<<JS
        (function () {
            const oscpu = (navigator.oscpu ?? "").toLowerCase();
            const uaversion = navigator.userAgent.match(/(firefox)\/((\\d+)\\.\\d+)/i);
            const winversion = oscpu.match(/(ce|nt)\\s*(\\d+\\.\\d+)/);
            const macversion = oscpu.match(/mac\\s*(version)?\\s*(\\d+\\.\\d+)/);
            document.cookie = "$cookiName=" + encodeURIComponent(JSON.stringify({
                "Content-DPR": window.devicePixelRatio ,
                "DPR": window.devicePixelRatio ,
                "Device-Memory": navigator.deviceMemory,
                "Viewport-Width": window.innerWidth,
                "Width": window.outerWidth,
                "Sec-CH-UA": [...uaversion ? [`"\${uaversion[1]}";v="\${uaversion[3]}"`] : [], `"Not(A:Brand";v="0"`].join(','),
                "Sec-CH-UA-Arch": `"\${oscpu.includes("windows") ? "x86" : ""}"`,
                "Sec-CH-UA-Full-Version": `"\${uaversion ? uaversion[2] : ""}"`,
                "Sec-CH-UA-Mobile": !!('ontouchstart' in window || navigator.maxTouchPoints),
                "Sec-CH-UA-Model": `""`,
                "Sec-CH-UA-Platform": `"\${oscpu.includes("windows") ? "Windows" : oscpu.includes("mac") ? "MacOS" : navigator.platform}"`,
                "Sec-CH-UA-Platform-Version": `"\${winversion ? winversion[2] : macversion ? macversion[2] : ""}"`,
            })) + ";path=/;samesite=lax";
        })();
        JS,);

        return $this;
    }
}

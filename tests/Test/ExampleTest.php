<?php
namespace ryunosuke\Test;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\HttpKernelBrowser;

/**
 * Example が最低限動作する担保と HttpKernelBrowser を利用したサンプルテスト
 */
class ExampleTest extends AbstractTestCase
{
    function setUp(): void
    {
        ob_start();
        $this->service = require __DIR__ . '/../../example/public/index.php';
        $this->service->cacher->clear();
        ob_end_clean();
    }

    protected function tearDown(): void
    {
        @unlink($this->service->maintenanceFile);
    }

    function test_root()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('DefaultController', $crawler->html());
        $this->assertEquals('microute Example', $crawler->filter('title')->text());
    }

    function test_alias()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/alias');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('DefaultController', $crawler->html());
    }

    function test_urls()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/urls');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('/urls', $crawler->html());
    }

    function test_background()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/background');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('<a href="background.txt">', $crawler->html());
    }

    function test_denyIp()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/deny-ip');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('denyIp', $crawler->html());
    }

    function test_argument()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/argument?id=1&name=hoge');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('/argument?id=1&amp;name=hoge', $crawler->html());
    }

    function test_forward()
    {
        $client = new HttpKernelBrowser($this->service);

        $crawler = $client->request('GET', '/forward');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString("'id' => 123", $crawler->text());
        $this->assertStringContainsString("'name' => 'hoge1'", $crawler->text());

        $crawler = $client->request('GET', '/forward-this');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString("'id' => 123", $crawler->text());
        $this->assertStringContainsString("'name' => 'hoge2'", $crawler->text());
    }

    function test_upload()
    {
        $client = new HttpKernelBrowser($this->service);
        $client->request('GET', '/upload');

        $crawler = $client->submitForm('submit', [
            'file' => __FILE__,
        ]);

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString(UploadedFile::class, $crawler->html());
    }

    function test_original_hoge()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/hoge');

        $this->assertEquals(302, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Redirecting to', $crawler->html());
        $this->assertStringContainsString('/original', $client->followRedirect()->html());
    }

    function test_original_fuga()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/fuga');

        $this->assertEquals(303, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Redirecting to', $crawler->html());
        $this->assertStringContainsString('/original', $client->followRedirect()->html());
    }

    function test_original_piyo()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/piyo');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('/piyo', $crawler->html());
    }

    function test_regex()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/regex/123-hoge');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('/regex/123-hoge', $crawler->html());
    }

    function test_ratelimit()
    {
        $client = new HttpKernelBrowser($this->service);

        for ($i = 0; $i < 5; $i++) {
            $client->request('GET', '/ratelimit');
        }
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $client->request('GET', '/ratelimit');
        $this->assertEquals(429, $client->getResponse()->getStatusCode());

        for ($i = 0; $i < 10; $i++) {
            $client->request('GET', '/ratelimit?id=123');
        }
        $this->assertEquals(200, $client->getResponse()->getStatusCode());

        $client->request('GET', '/ratelimit?id=123');
        $this->assertEquals(429, $client->getResponse()->getStatusCode());
    }

    function test_context()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/context.json?id=1');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('/context.json?id=1', $crawler->html());
    }

    function test_push()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/push');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('SSE', $crawler->html());
    }

    function test_resolver()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/13/resolver');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertEquals('/context.json?id=123', $crawler->filter('code')->eq(1)->text());
    }

    function test_throw_runtime()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/throw-runtime');

        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('throwRuntimeAction', $crawler->html());
    }

    function test_throw_domain()
    {
        $client = new HttpKernelBrowser($this->service);
        ob_start();
        $crawler = $client->request('GET', '/throw-domain');
        $this->assertStringContainsString('throwDomainAction', ob_get_clean());

        $this->assertEquals(500, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Stack trace', $crawler->html());
    }

    function test_404()
    {
        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/not-found');

        $this->assertEquals(404, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('Stack trace', $crawler->html());
    }

    function test_503()
    {
        file_put_contents($this->service->maintenanceFile, 'maintenance');

        $client = new HttpKernelBrowser($this->service);
        $crawler = $client->request('GET', '/');

        $this->assertEquals(503, $client->getResponse()->getStatusCode());
        $this->assertStringContainsString('maintenance', $crawler->html());
    }
}

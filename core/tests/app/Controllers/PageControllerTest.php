<?php

declare(strict_types=1);

namespace app\Pages {

    use app\Controllers\PageController;
    use app\Models\Page;

    class PageControllerFixturePage
    {
        public static bool $apiCalled = false;

        public function __construct(Page $page)
        {
            $page->subtitle = 'Fixture';
        }

        public function api(PageController $controller): void
        {
            self::$apiCalled = true;
        }
    }
}

namespace tests\Controllers {

    use app\Controllers\BreadcrumbController;
    use app\Controllers\PageController;
    use app\Controllers\SessionController;
    use app\Enums\AlertType;
    use app\Enums\ErrorCode;
    use app\Pages\PageControllerFixturePage;
    use PHPUnit\Framework\TestCase;
    use ReflectionClass;
    use ReflectionMethod;

    /**
     * Only the constructor branches that return before render() are exercised directly
     * (the global $ROUTES short-circuit, and API dispatch) - render() unconditionally calls
     * part('top') first, which pulls in the whole real view include chain, so it's out of
     * scope for a unit test. redirect()-family assertions rely on http_response_code(), which
     * works reliably under the CLI SAPI; header content itself is only checked when
     * xdebug_get_headers() is available (CLI doesn't track headers without it).
     */
    class PageControllerTest extends TestCase
    {
        public function testErrorRedirectsToTheCodeSpecificErrorPage(): void
        {
            PageController::error(ErrorCode::NOT_FOUND);

            $this->assertSame(302, http_response_code());
            $this->assertTrue($this->headersContain('/error/404'));
        }

        private function headersContain(string $needle): bool
        {
            if (!function_exists('xdebug_get_headers')) {
                $this->markTestSkipped('xdebug_get_headers() not available');
            }

            foreach (xdebug_get_headers() as $header) if (str_contains($header, $needle)) return true;
            return false;
        }

        public function testErrorIncludesAnEncodedRedirectParamWhenGiven(): void
        {
            PageController::error(ErrorCode::FORBIDDEN, '/some/page?x=1');

            $this->assertTrue($this->headersContain('redirect=' . urlencode('/some/page?x=1')));
        }

        public function testRedirectSendsAnImmediate302ByDefault(): void
        {
            PageController::redirect('/somewhere');

            $this->assertSame(302, http_response_code());
            $this->assertTrue($this->headersContain('Location: /somewhere'));
        }

        public function testRedirectWithARefreshDelayDoesNotSetA302(): void
        {
            PageController::redirect('/somewhere', 5);

            $this->assertNotSame(302, http_response_code());
            $this->assertTrue($this->headersContain('refresh: 5;'));
        }

        public function testRedirectWithAlertQueuesTheAlertAndRedirects(): void
        {
            PageController::redirectWithAlert('/somewhere', 'hi', AlertType::SUCCESS);

            $this->assertSame('hi', SessionController::get('alert')['message']);
            $this->assertSame(302, http_response_code());
        }

        public function testPrevReturnsTheSecondToLastHistoryEntry(): void
        {
            SessionController::set('history', ['/a', '/b', '/c']);

            $this->assertSame('/b', PageController::prev());
        }

        public function testPrevFallsBackToRedirectWhenHistoryIsTooShort(): void
        {
            SessionController::set('history', ['/a']);

            $this->assertSame('/' . REDIRECT, PageController::prev());
        }

        public function testBackTrimsHistoryAndRedirectsToTheNewLastEntry(): void
        {
            SessionController::set('history', ['/a', '/b', '/c']);

            PageController::back();

            $this->assertSame(['/a', '/b'], SessionController::get('history'));
            $this->assertTrue($this->headersContain('Location: /b'));
        }

        public function testPartEchoesAVisibleMessageInDevModeWhenNotFound(): void
        {
            $page = $this->instanceWithoutConstructor();
            $method = new ReflectionMethod(PageController::class, 'part');
            $name = 'does-not-exist-' . uniqid();

            ob_start();
            $method->invoke($page, $name);
            $output = ob_get_clean();

            // Exact match, not substring - the non-DEV branch's comment output also contains "not found".
            $this->assertSame("Part \"$name\" not found", $output);
        }

        private function instanceWithoutConstructor(): PageController
        {
            return (new ReflectionClass(PageController::class))->newInstanceWithoutConstructor();
        }

        public function testComponentRendersAnExistingComponentFile(): void
        {
            BreadcrumbController::set([['label' => 'Home', 'url' => null]]);
            $page = $this->instanceWithoutConstructor();

            ob_start();
            $page->component('breadcrumbs');
            $output = ob_get_clean();

            $this->assertStringContainsString('Home', $output);
            $this->assertStringContainsString('breadcrumbs', $output);
        }

        public function testComponentEchoesAVisibleMessageWhenNotFound(): void
        {
            $page = $this->instanceWithoutConstructor();
            $name = 'does-not-exist-' . uniqid();

            ob_start();
            $page->component($name);
            $output = ob_get_clean();

            // Exact match, not substring - the non-DEV branch's comment output also contains "not found".
            $this->assertSame("Component \"$name\" not found", $output);
        }

        public function testRoutesShortCircuitBeforeAnyPageResolution(): void
        {
            global $ROUTES;
            $called = false;
            $ROUTES = ['custom-route-' . uniqid() => static function () use (&$called) {
                $called = true;
            }];
            $routeName = array_key_first($ROUTES);

            $_SERVER['REQUEST_URI'] = "/$routeName";
            $_SERVER['REQUEST_METHOD'] = 'GET';

            new PageController();

            $this->assertTrue($called);
        }

        public function testApiDispatchCallsTheMatchingPageObjectsApiMethod(): void
        {
            $_SERVER['REQUEST_URI'] = '/api/page-controller-fixture';
            $_SERVER['REQUEST_METHOD'] = 'GET';

            new PageController();

            $this->assertTrue(PageControllerFixturePage::$apiCalled);
        }

        public function testApiDispatchWithNoMatchingPageRedirectsToNotFound(): void
        {
            $_SERVER['REQUEST_URI'] = '/api/no-such-fixture-' . uniqid();
            $_SERVER['REQUEST_METHOD'] = 'GET';

            new PageController();

            $this->assertSame(302, http_response_code());
            $this->assertTrue($this->headersContain('/error/404'));
        }

        protected function setUp(): void
        {
            $_SESSION = [];
            $_GET = [];
            $_POST = [];
            http_response_code(200);
            PageControllerFixturePage::$apiCalled = false;
        }

        protected function tearDown(): void
        {
            global $ROUTES;
            $ROUTES = null;
        }
    }
}

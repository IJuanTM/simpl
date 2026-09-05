<?php

declare(strict_types=1);

namespace app\Pages {

    use app\Controllers\PageController;
    use app\Models\Page;

    final class PageControllerFixturePage
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
    use tests\Support\HeadersAssertionTrait;

    /**
     * Only the route() branches that return before render() are exercised directly:
     * the global $ROUTES short-circuit, and API dispatch. render() always calls part('top') first,
     * which pulls in the whole real view include chain, so it is out of scope for a unit test.
     * redirect()-family assertions rely on http_response_code(), which works reliably under the CLI SAPI.
     * Header content itself is only checked when xdebug_get_headers() is available (CLI doesn't track headers without it).
     */
    final class PageControllerTest extends TestCase
    {
        use HeadersAssertionTrait;

        private string $originalRequestUri;
        private ?string $originalRequestMethod;

        public function testConstructorRejectsABackslashEmbeddedSegment(): void
        {
            // Arrange
            $_SERVER['REQUEST_URI'] = "/..\\..\\secrets";
            $_SERVER['REQUEST_METHOD'] = 'GET';

            // Act
            new PageController();

            // Assert
            $this->assertSame(302, http_response_code());
            $this->assertTrue($this->headersContain('/error/404'));
        }

        public function testRoutesShortCircuitBeforeAnyPageResolution(): void
        {
            // Arrange
            global $ROUTES;
            $called = false;
            $ROUTES = ['custom-route-' . uniqid() => static function () use (&$called) {
                $called = true;
            }];
            $routeName = array_key_first($ROUTES);
            $_SERVER['REQUEST_URI'] = "/$routeName";
            $_SERVER['REQUEST_METHOD'] = 'GET';

            // Act
            new PageController();

            // Assert
            $this->assertTrue($called);
        }

        public function testApiDispatchCallsTheMatchingPageObjectsApiMethod(): void
        {
            // Arrange
            $_SERVER['REQUEST_URI'] = '/api/page-controller-fixture';
            $_SERVER['REQUEST_METHOD'] = 'GET';

            // Act
            new PageController();

            // Assert
            $this->assertTrue(PageControllerFixturePage::$apiCalled);
        }

        public function testApiDispatchWithNoMatchingPageRespondsWithJsonNotFound(): void
        {
            // Arrange
            $_SERVER['REQUEST_URI'] = '/api/no-such-fixture-' . uniqid();
            $_SERVER['REQUEST_METHOD'] = 'GET';

            // Act
            ob_start();
            new PageController();
            $body = ob_get_clean();

            // Assert
            $this->assertSame(404, http_response_code());
            $this->assertSame(['error' => ErrorCode::NOT_FOUND->message()], json_decode($body, true));
        }

        public function testErrorRedirectsToTheCodeSpecificErrorPage(): void
        {
            // Act
            PageController::error(ErrorCode::NOT_FOUND);

            // Assert
            $this->assertSame(302, http_response_code());
            $this->assertTrue($this->headersContain('/error/404'));
        }

        public function testErrorIncludesAnEncodedRedirectParamWhenGiven(): void
        {
            // Act
            PageController::error(ErrorCode::FORBIDDEN, '/some/page?x=1');

            // Assert
            $this->assertTrue($this->headersContain('redirect=' . urlencode('/some/page?x=1')));
        }

        public function testErrorRespondsWithJsonForAnApiRequest(): void
        {
            // Arrange
            $previous = $_SERVER['REQUEST_URI'];
            $_SERVER['REQUEST_URI'] = '/api/user/999';

            try {
                // Act
                ob_start();
                PageController::error(ErrorCode::NOT_FOUND);
                $body = ob_get_clean();

                // Assert
                $this->assertSame(404, http_response_code());
                $this->assertSame(['error' => ErrorCode::NOT_FOUND->message()], json_decode($body, true));
            } finally {
                // Cleanup
                $_SERVER['REQUEST_URI'] = $previous;
            }
        }

        public function testRedirectSendsAnImmediate302ByDefault(): void
        {
            // Act
            PageController::redirect('/somewhere');

            // Assert
            $this->assertSame(302, http_response_code());
            $this->assertTrue($this->headersContain('Location: /somewhere'));
        }

        public function testRedirectWithARefreshDelayDoesNotSetA302(): void
        {
            // Act
            PageController::redirect('/somewhere', 5);

            // Assert
            $this->assertNotSame(302, http_response_code());
            $this->assertTrue($this->headersContain('refresh: 5;'));
        }

        public function testPartEchoesAVisibleMessageInDevModeWhenNotFound(): void
        {
            // Arrange
            $page = $this->instanceWithoutConstructor();
            $method = new ReflectionMethod(PageController::class, 'part');
            $name = 'does-not-exist-' . uniqid();

            // Act
            ob_start();
            $method->invoke($page, $name);
            $output = ob_get_clean();

            // Assert
            // Exact match, not substring - the non-DEV branch's comment output also contains "not found".
            $this->assertSame("Part \"$name\" not found", $output);
        }

        private function instanceWithoutConstructor(): PageController
        {
            return (new ReflectionClass(PageController::class))->newInstanceWithoutConstructor();
        }

        public function testRedirectWithAlertQueuesTheAlertAndRedirects(): void
        {
            // Act
            PageController::redirectWithAlert('/somewhere', 'hi', AlertType::SUCCESS);

            // Assert
            $this->assertSame('hi', SessionController::get('alert')['message']);
            $this->assertSame(302, http_response_code());
        }

        public function testPrevReturnsTheSecondToLastHistoryEntry(): void
        {
            // Arrange
            SessionController::set('history', ['/a', '/b', '/c']);

            // Act + Assert
            $this->assertSame('/b', PageController::prev());
        }

        public function testPrevFallsBackToRedirectWhenHistoryIsTooShort(): void
        {
            // Arrange
            SessionController::set('history', ['/a']);

            // Act + Assert
            $this->assertSame('/' . REDIRECT, PageController::prev());
        }

        public function testBackTrimsHistoryAndRedirectsToTheNewLastEntry(): void
        {
            // Arrange
            SessionController::set('history', ['/a', '/b', '/c']);

            // Act
            PageController::back();

            // Assert
            $this->assertSame(['/a', '/b'], SessionController::get('history'));
            $this->assertTrue($this->headersContain('Location: /b'));
        }

        public function testComponentRendersAnExistingComponentFile(): void
        {
            // Arrange
            BreadcrumbController::set([['label' => 'Home', 'url' => null]]);
            $page = $this->instanceWithoutConstructor();

            // Act
            ob_start();
            $page->component('breadcrumbs');
            $output = ob_get_clean();

            // Assert
            $this->assertStringContainsString('Home', $output);
            $this->assertStringContainsString('breadcrumbs', $output);
        }

        public function testComponentEchoesAVisibleMessageWhenNotFound(): void
        {
            // Arrange
            $page = $this->instanceWithoutConstructor();
            $name = 'does-not-exist-' . uniqid();

            // Act
            ob_start();
            $page->component($name);
            $output = ob_get_clean();

            // Assert
            // Exact match, not substring - the non-DEV branch's comment output also contains "not found".
            $this->assertSame("Component \"$name\" not found", $output);
        }

        protected function setUp(): void
        {
            $_SESSION = [];
            $_GET = [];
            $_POST = [];
            http_response_code(200);
            PageControllerFixturePage::$apiCalled = false;
            $this->originalRequestUri = $_SERVER['REQUEST_URI'];
            $this->originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
        }

        protected function tearDown(): void
        {
            global $ROUTES;
            $ROUTES = null;
            $_SERVER['REQUEST_URI'] = $this->originalRequestUri;
            if ($this->originalRequestMethod === null) unset($_SERVER['REQUEST_METHOD']);
            else $_SERVER['REQUEST_METHOD'] = $this->originalRequestMethod;
        }
    }
}

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

namespace tests\Feature\Routing {

    use app\Controllers\PageController;
    use app\Controllers\SessionController;
    use app\Enums\ErrorCode;
    use app\Pages\PageControllerFixturePage;
    use PHPUnit\Framework\TestCase;
    use tests\Support\HeadersAssertionTrait;
    use tests\Support\OutputCaptureTrait;

    /**
     * Only the route() branches that return before render() are exercised: the global $ROUTES
     * short-circuit, API dispatch, and error()'s redirect/JSON paths. render() always calls
     * part('top') first, which pulls in the whole real view include chain, so it is out of scope
     * here. redirect()-family assertions rely on http_response_code(), which works reliably under
     * the CLI SAPI. Header content itself is only checked when xdebug_get_headers() is available
     * (CLI doesn't track headers without it).
     */
    final class DispatchTest extends TestCase
    {
        use HeadersAssertionTrait;
        use OutputCaptureTrait;

        private string $originalRequestUri;
        private ?string $originalRequestMethod;
        private mixed $originalRoutes;

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

        public function testApiDispatchDoesNotRecordNavigationHistory(): void
        {
            // Arrange
            $_SERVER['REQUEST_URI'] = '/api/page-controller-fixture';
            $_SERVER['REQUEST_METHOD'] = 'GET';

            // Act
            new PageController();

            // Assert
            $this->assertNull(SessionController::get('history'));
        }

        public function testApiDispatchWithNoMatchingPageRespondsWithJsonNotFound(): void
        {
            // Arrange
            $_SERVER['REQUEST_URI'] = '/api/no-such-fixture-' . uniqid();
            $_SERVER['REQUEST_METHOD'] = 'GET';

            // Act
            $body = $this->captured(static fn() => new PageController());

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
                $body = $this->captured(static fn() => PageController::error(ErrorCode::NOT_FOUND));

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

        protected function setUp(): void
        {
            global $ROUTES;

            $_SESSION = [];
            $_GET = [];
            $_POST = [];
            http_response_code(200);
            PageControllerFixturePage::$apiCalled = false;
            $this->originalRequestUri = $_SERVER['REQUEST_URI'];
            $this->originalRequestMethod = $_SERVER['REQUEST_METHOD'] ?? null;
            $this->originalRoutes = $ROUTES;
        }

        protected function tearDown(): void
        {
            global $ROUTES;

            $ROUTES = $this->originalRoutes;
            $_SERVER['REQUEST_URI'] = $this->originalRequestUri;
            if ($this->originalRequestMethod === null) unset($_SERVER['REQUEST_METHOD']);
            else $_SERVER['REQUEST_METHOD'] = $this->originalRequestMethod;
        }
    }
}

<?php

declare(strict_types=1);

namespace tests\Controllers;

use app\Controllers\MailController;
use PHPUnit\Framework\TestCase;

/**
 * Only "template not found" is covered here - the happy path reads from BASEDIR/app/Mails,
 * which only lines up with this add-on's own templates after a real install-time merge.
 */
final class MailControllerTest extends TestCase
{
    public function testTemplateReturnsFalseWhenTheFileDoesNotExist(): void
    {
        // Act + Assert
        $this->assertFalse(MailController::template('does-not-exist-' . uniqid(), []));
    }
}

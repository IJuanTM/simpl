<?php

declare(strict_types=1);

namespace app\Utils;

use RuntimeException;

/**
 * Shared by any class that lazily creates a storage directory on first write.
 */
trait EnsuresDirectory
{
    /**
     * Creates $dir if it doesn't already exist.
     *
     * @param string $dir
     *
     * @return void
     */
    private static function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }
    }
}

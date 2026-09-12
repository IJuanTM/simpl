<?php

declare(strict_types=1);

const PROFILE_IMAGE_CONFIG = [
    'max_size_mb' => 2,
    'max_dimension' => 4096,                                                    // reject wildly oversized images (pixel-flood guard)
    'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    'path' => 'img/profile/',                                                  // relative to document root
];

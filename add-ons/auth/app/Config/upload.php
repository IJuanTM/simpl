<?php

declare(strict_types=1);

// Profile image upload configuration
const PROFILE_IMAGE_MAX_SIZE = 2; // in megabytes
const PROFILE_IMAGE_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const PROFILE_IMAGE_PATH = 'img/profile/'; // relative to document root, no leading slash

<?php

return [
    'photo_disk' => env('EVENT_PHOTO_DISK', 'local'),
    // Confirm only after testing storage across deployments; a mounted persistent volume is also supported.
    'photo_storage_persistent' => env('EVENT_PHOTO_STORAGE_PERSISTENT', false),
];

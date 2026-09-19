<?php

function profilePhotoUrl(int $userId, string $prefix = '../'): ?string
{
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
        $path = __DIR__ . '/../assets/uploads/profile-photos/' . $userId . '.' . $extension;
        if (is_file($path)) {
            return $prefix . 'assets/uploads/profile-photos/' . $userId . '.' . $extension . '?v=' . filemtime($path);
        }
    }
    return null;
}

function saveProfilePhoto(array $file, int $userId): void
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return;
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('The photo could not be uploaded. Please try again.');
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) throw new RuntimeException('Profile photos must be 5 MB or smaller.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Use a valid JPEG, PNG, or WebP image.');
    }

    $directory = __DIR__ . '/../assets/uploads/profile-photos';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Profile photo storage is unavailable.');
    }
    foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
        $old = $directory . '/' . $userId . '.' . $extension;
        if (is_file($old)) unlink($old);
    }
    if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $userId . '.' . $extensions[$mime])) {
        throw new RuntimeException('Unable to save the profile photo.');
    }
}

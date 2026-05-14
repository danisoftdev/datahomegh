<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Exceptions\DriverException;
use Intervention\Image\ImageManager;
use RuntimeException;

final class UserProfileImageStorage
{
    public static function storeProfilePicture(UploadedFile $file): string
    {
        return self::storeCoverJpeg($file, 300, 300, 'profiles');
    }

    public static function storeLogo(UploadedFile $file): string
    {
        return self::storeCoverJpeg($file, 600, 200, 'profiles');
    }

    private static function storeCoverJpeg(UploadedFile $file, int $width, int $height, string $dir): string
    {
        try {
            $manager = ImageManager::gd();
        } catch (DriverException) {
            throw new RuntimeException('Image processing (GD) is not available on this server.');
        }

        $image = $manager->read($file)->cover($width, $height);

        $relative = $dir.'/'.uniqid('img_', true).'.jpg';

        Storage::disk('public')->put($relative, $image->toJpeg(85)->toString());

        return $relative;
    }
}

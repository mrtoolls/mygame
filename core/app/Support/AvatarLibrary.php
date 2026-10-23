<?php

namespace App\Support;

class AvatarLibrary
{
    private const GENDERS = ['male', 'female'];

    public static function genders(): array
    {
        return self::GENDERS;
    }

    /** Falls back to 'male' for any user without a recognized gender on file, so the picker
     *  and the registration default always have a pool to draw from. */
    public static function normalizeGender(?string $gender): string
    {
        return in_array($gender, self::GENDERS, true) ? $gender : 'male';
    }

    private static function relativeDir(string $gender): ?string
    {
        return in_array($gender, self::GENDERS, true) ? "assets/avatars/$gender" : null;
    }

    /** Plain relative path, not public_path() - this deployment serves "assets/..." URLs from
     *  wherever a bare relative path like this resolves to, same lesson as fileUploader() and
     *  the chatbot photo fix; public_path() pointed at a different, non-served location. */
    public static function filenames(string $gender): array
    {
        $dir = self::relativeDir($gender);
        if (!$dir || !is_dir($dir)) {
            return [];
        }
        $files = glob("$dir/*.png") ?: [];
        natsort($files);
        return array_values(array_map('basename', $files));
    }

    public static function randomPath(string $gender): ?string
    {
        $files = self::filenames($gender);
        if (empty($files)) {
            return null;
        }
        return self::relativeDir($gender) . '/' . $files[array_rand($files)];
    }

    public static function isValid(string $gender, string $filename): bool
    {
        return in_array(basename($filename), self::filenames($gender), true);
    }

    public static function pathFor(string $gender, string $filename): string
    {
        return self::relativeDir(self::normalizeGender($gender)) . '/' . basename($filename);
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProfilePhotoService
{
    public function replace(User $user, UploadedFile $photo): void
    {
        $newPath = $photo->store('profile-photos', 'local');
        $oldPath = $user->profile_photo_path;

        try {
            $user->forceFill(['profile_photo_path' => $newPath])->save();
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPath);
            throw $exception;
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }
    }
}

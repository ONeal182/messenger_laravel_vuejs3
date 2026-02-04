<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserService
{
    /**
     * Search users by nickname (without leading @), excluding current user if provided.
     */
    public function searchByNickname(string $rawQuery, ?int $excludeUserId = null, int $limit = 10): Collection
    {
        $term = ltrim(trim($rawQuery), '@');

        if (mb_strlen($term) < 2) {
            return collect();
        }

        $query = User::query()
            ->select(['id', 'nickname', 'name', 'email'])
            ->when($excludeUserId, fn ($q) => $q->where('id', '!=', $excludeUserId))
            ->where('nickname', 'like', '%' . $term . '%')
            ->limit($limit);

        return $query->get();
    }

    public function updateProfile(User $user, array $data): User
    {
        // Проверка уникальности ника
        if (!empty($data['nickname'])) {
            $exists = User::where('nickname', $data['nickname'])
                ->where('id', '!=', $user->id)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'nickname' => 'Этот ник уже занят',
                ]);
            }
        }

        $user->fill([
            'name'        => $data['name'] ?? $user->name,
            'last_name'   => $data['last_name'] ?? $user->last_name,
            'middle_name' => $data['middle_name'] ?? $user->middle_name,
            'nickname'    => $data['nickname'] ?? $user->nickname,
            'birth_date'  => $data['birth_date'] ?? $user->birth_date,
        ]);

        $user->save();

        return $user->fresh();
    }

    public function updateAvatar(User $user, UploadedFile $file): User
    {
        [$avatarPath, $thumbPath] = $this->processAvatar($file, $user->id);

        $user->avatar_path = $avatarPath;
        $user->avatar_thumb_path = $thumbPath;
        $user->save();

        return $user->fresh();
    }

    /**
     * Resize image to max 1080px, convert to jpg, and create 256px square thumb.
     */
    private function processAvatar(UploadedFile $file, int $userId): array
    {
        $mime = $file->getMimeType();
        $image = $this->createImageResource($file->getRealPath(), $mime);
        if (!$image) {
            throw ValidationException::withMessages(['avatar' => 'Неподдерживаемый формат изображения']);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $max = 1080;
        if ($width > $max || $height > $max) {
            $ratio = min($max / $width, $max / $height);
            $newW = (int) floor($width * $ratio);
            $newH = (int) floor($height * $ratio);
            $resized = imagecreatetruecolor($newW, $newH);
            imagealphablending($resized, true);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($image);
            $image = $resized;
            $width = $newW;
            $height = $newH;
        }

        // thumb square 256x256
        $size = 256;
        $thumb = imagecreatetruecolor($size, $size);
        imagealphablending($thumb, true);
        imagesavealpha($thumb, true);
        $srcSize = min($width, $height);
        $srcX = (int) max(0, ($width - $srcSize) / 2);
        $srcY = (int) max(0, ($height - $srcSize) / 2);
        imagecopyresampled($thumb, $image, 0, 0, $srcX, $srcY, $size, $size, $srcSize, $srcSize);

        $dir = "avatars/{$userId}";
        Storage::disk('public')->makeDirectory($dir);

        $avatarPath = "{$dir}/avatar.jpg";
        $thumbPath = "{$dir}/avatar_thumb.jpg";

        $this->saveJpeg($image, $avatarPath);
        $this->saveJpeg($thumb, $thumbPath);

        imagedestroy($image);
        imagedestroy($thumb);

        return [
            Storage::url($avatarPath),
            Storage::url($thumbPath),
        ];
    }

    private function createImageResource(string $path, ?string $mime)
    {
        try {
            return match ($mime) {
                'image/jpeg' => imagecreatefromjpeg($path),
                'image/png'  => imagecreatefrompng($path),
                'image/webp' => imagecreatefromstring(file_get_contents($path)),
                default      => imagecreatefromstring(file_get_contents($path)),
            };
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function saveJpeg($image, string $path): void
    {
        ob_start();
        imagejpeg($image, null, 90);
        $data = ob_get_clean();
        Storage::disk('public')->put($path, $data, 'public');
    }
}

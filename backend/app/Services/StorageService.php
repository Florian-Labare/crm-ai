<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class StorageService
{
    /**
     * Télécharge un fichier depuis S3 vers le disque temp local pour traitement
     *
     * @param  string  $path  Chemin relatif du fichier sur S3
     * @param  string|null  $disk  Disk source (défaut: disk par défaut)
     * @return string Chemin absolu local du fichier temporaire
     */
    public function downloadToTemp(string $path, ?string $disk = null): string
    {
        $disk = $disk ?? config('filesystems.default');
        $tempPath = Storage::disk('temp')->path($path);

        $tempDir = dirname($tempPath);
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $content = Storage::disk($disk)->get($path);
        file_put_contents($tempPath, $content);

        return $tempPath;
    }

    /**
     * Upload un fichier local vers S3
     *
     * @param  string  $localPath  Chemin absolu du fichier local
     * @param  string  $remotePath  Chemin relatif de destination sur S3
     * @param  string|null  $disk  Disk destination (défaut: disk par défaut)
     */
    public function uploadFromLocal(string $localPath, string $remotePath, ?string $disk = null): bool
    {
        $disk = $disk ?? config('filesystems.default');

        return Storage::disk($disk)->put(
            $remotePath,
            file_get_contents($localPath)
        );
    }

    /**
     * Nettoie un fichier temporaire
     *
     * @param  string  $path  Chemin relatif sur le disk temp
     */
    public function cleanupTemp(string $path): void
    {
        $tempPath = Storage::disk('temp')->path($path);
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }
    }

    /**
     * Génère une URL temporaire (presigned) pour téléchargement
     *
     * @param  string  $path  Chemin relatif du fichier
     * @param  int  $minutes  Durée de validité en minutes
     * @param  string|null  $disk  Disk source (défaut: disk par défaut)
     * @return string URL temporaire
     */
    public function getTemporaryUrl(string $path, int $minutes = 5, ?string $disk = null): string
    {
        $disk = $disk ?? config('filesystems.default');

        return Storage::disk($disk)->temporaryUrl(
            $path,
            now()->addMinutes($minutes)
        );
    }

    /**
     * Récupère l'URL publique d'un fichier
     *
     * @param  string  $path  Chemin relatif du fichier
     * @param  string|null  $disk  Disk source (défaut: disk par défaut)
     * @return string URL publique
     */
    public function getPublicUrl(string $path, ?string $disk = null): string
    {
        $disk = $disk ?? config('filesystems.default');

        return Storage::disk($disk)->url($path);
    }

    /**
     * Vérifie si un fichier existe sur S3
     *
     * @param  string  $path  Chemin relatif du fichier
     * @param  string|null  $disk  Disk (défaut: disk par défaut)
     */
    public function exists(string $path, ?string $disk = null): bool
    {
        $disk = $disk ?? config('filesystems.default');

        return Storage::disk($disk)->exists($path);
    }

    /**
     * Supprime un fichier sur S3
     *
     * @param  string  $path  Chemin relatif du fichier
     * @param  string|null  $disk  Disk (défaut: disk par défaut)
     */
    public function delete(string $path, ?string $disk = null): bool
    {
        $disk = $disk ?? config('filesystems.default');

        return Storage::disk($disk)->delete($path);
    }

    /**
     * Récupère la taille d'un fichier sur S3
     *
     * @param  string  $path  Chemin relatif du fichier
     * @param  string|null  $disk  Disk (défaut: disk par défaut)
     * @return int Taille en octets
     */
    public function size(string $path, ?string $disk = null): int
    {
        $disk = $disk ?? config('filesystems.default');

        return Storage::disk($disk)->size($path);
    }
}

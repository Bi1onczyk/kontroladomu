<?php

namespace App\Domain\Specialists\Support;

use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Przekodowuje zdjęcie zaświadczenia przez GD do nowego pliku JPEG (ADR-019).
 *
 * Dekodowanie i ponowny zapis usuwa EXIF (w tym lokalizację GPS), dopiski w metadanych i dane doklejone na końcu
 * pliku. Obraz jest najpierw zmniejszany do `image_max_side_px`, a dopiero potem obracany zgodnie z orientacją EXIF
(żeby zdjęcie z telefonu nie obróciło się na bok), dzięki czemu obrót działa na małym płótnie, nie na pełnym zdjęciu.
 * Przezroczystość PNG jest zastępowana białym tłem. Wymaga rozszerzenia `gd` (z obsługą JPEG).
 */
final class ImageReencoder
{
    /**
     * Zwraca zawartość nowego pliku JPEG zbudowaną z obrazu pod ścieżką `$path`.
     *
     * @param  string  $path  Ścieżka do pliku tymczasowego (już sprawdzonego przez {@see CertificateFileInspector}).
     * @param  string  $mime  `image/jpeg` albo `image/png`.
     *
     * @throws ValidationException Gdy obrazu nie da się odczytać albo wymaga zbyt dużo pamięci.
     */
    public function toJpeg(string $path, string $mime): string
    {
        $size = @getimagesize($path);

        if ($size === false) {
            $this->fail('file_unreadable');
        }

        [$width, $height] = $size;

        $scale = min(1.0, (int) config('specialists.image_max_side_px') / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        // Szczyt pamięci: obraz źródłowy + płótno docelowe (rozmiar po zmniejszeniu). Obrót EXIF robimy dopiero na
        // małym płótnie po zwolnieniu źródła, więc jego szczyt (2 x płótno) jest mniejszy od szczytu dekodowania.
        // GD trzyma ok. 4 B/piksel; margines 1,5x pokrywa bufory dekodera JPEG/PNG i zapis wyniku.
        // Przekroczenie to zwykły błąd walidacji, bo wyczerpania pamięci PHP nie da się przechwycić.
        $needed = (int) ceil((($width * $height) + ($targetWidth * $targetHeight)) * 4 * 1.5);

        if ($needed > $this->availableMemory()) {
            $this->fail('file_memory');
        }

        try {
            $source = $mime === 'image/png' ? @imagecreatefrompng($path) : @imagecreatefromjpeg($path);

            if ($source === false) {
                $this->fail('file_unreadable');
            }

            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($canvas === false) {
                $this->fail('file_unreadable');
            }

            imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, imagesx($source), imagesy($source));

            // Zwalniamy duży obraz źródłowy przed obrotem.
            unset($source);

            if ($mime === 'image/jpeg') {
                $canvas = $this->applyOrientation($canvas, $path);
            }

            ob_start();
            $written = imagejpeg($canvas, null, (int) config('specialists.image_jpeg_quality'));
            $contents = (string) ob_get_clean();

            if (! $written || $contents === '') {
                $this->fail('file_unreadable');
            }

            return $contents;
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable) {
            $this->fail('file_unreadable');
        }
    }

    /**
     * Obraca zdjęcie zgodnie ze znacznikiem orientacji EXIF (obroty o 90/180 stopni).
     */
    private function applyOrientation(\GdImage $image, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated === false ? $image : $rotated;
    }

    /**
     * Wolna pamięć PHP w bajtach (bardzo duża liczba, gdy `memory_limit` to -1).
     */
    private function availableMemory(): int
    {
        $limit = trim((string) ini_get('memory_limit'));

        if ($limit === '' || $limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;
        $bytes = match ($unit) {
            'g' => $value * 1024 ** 3,
            'm' => $value * 1024 ** 2,
            'k' => $value * 1024,
            default => $value,
        };

        return max(0, (int) ($bytes * 0.8) - memory_get_usage());
    }

    private function fail(string $key): never
    {
        throw ValidationException::withMessages(['file' => __('certificates.errors.'.$key)]);
    }
}

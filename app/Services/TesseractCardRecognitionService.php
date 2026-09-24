<?php

namespace App\Services;

use App\Contracts\CardRecognitionService;
use RuntimeException;
use Symfony\Component\Process\Process;

class TesseractCardRecognitionService implements CardRecognitionService
{
    public function recognize(string $imagePath): string
    {
        $binary = (string) config('services.tesseract.binary', 'tesseract');
        if (! is_file($binary)) {
            $binary = 'tesseract';
        }

        $process = new Process([$binary, $imagePath, 'stdout', '--psm', '6']);
        $process->setTimeout((float) config('services.tesseract.timeout', 15));
        $process->run();

        // if (! $process->isSuccessful()) {
        //     throw new RuntimeException('Tesseract could not read the card image.');
        // }

        if (! $process->isSuccessful()) {
            \Illuminate\Support\Facades\Log::error('Tesseract execution failed', [
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
                'image_exists' => is_file($imagePath),
                'image_readable' => is_readable($imagePath),
                'image_size_bytes' => is_file($imagePath) ? filesize($imagePath) : null,
            ]);

            throw new RuntimeException('Tesseract could not read the card image.');
        }

        return trim($process->getOutput());
    }
}

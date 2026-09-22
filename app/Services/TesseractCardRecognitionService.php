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

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Tesseract could not read the card image.');
        }

        return trim($process->getOutput());
    }
}

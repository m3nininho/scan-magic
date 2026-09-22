<?php

namespace App\Contracts;

interface CardRecognitionService
{
    public function recognize(string $imagePath): string;
}

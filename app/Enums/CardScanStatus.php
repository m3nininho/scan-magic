<?php

namespace App\Enums;

enum CardScanStatus: string
{
    case Processing = 'processing';
    case Recognized = 'recognized';
    case NeedsConfirmation = 'needs_confirmation';
    case Confirmed = 'confirmed';
    case Failed = 'failed';
}

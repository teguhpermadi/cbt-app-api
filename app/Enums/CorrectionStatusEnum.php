<?php

namespace App\Enums;

enum CorrectionStatusEnum: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}

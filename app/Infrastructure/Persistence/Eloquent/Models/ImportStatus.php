<?php

namespace App\Infrastructure\Persistence\Eloquent\Models;

enum ImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}

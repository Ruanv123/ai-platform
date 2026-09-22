<?php

namespace App\Enums;

enum FlowExecutionStatus: string
{
    case Running = 'running';
    case Waiting = 'waiting';
    case Paused = 'paused';
    case Completed = 'completed';
    case Failed = 'failed';
    case Stuck = 'stuck';
    case Abandoned = 'abandoned';
}

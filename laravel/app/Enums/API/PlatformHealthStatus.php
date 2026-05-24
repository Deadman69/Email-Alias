<?php

namespace App\Enums\API;

enum PlatformHealthStatus: string
{
    case Healthy = 'healthy';
    case Degraded = 'degraded';
}

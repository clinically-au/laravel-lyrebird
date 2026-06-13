<?php

declare(strict_types=1);

namespace Clinically\Lyrebird\Facades;

use Clinically\Lyrebird\Data\AppointmentData;
use Clinically\Lyrebird\Data\LyrebirdCredentials;
use Clinically\Lyrebird\Data\PatientContext;
use Clinically\Lyrebird\LyrebirdConnection;
use Clinically\Lyrebird\LyrebirdManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static LyrebirdConnection for(LyrebirdCredentials $credentials)
 * @method static LyrebirdConnection connection(?LyrebirdCredentials $credentials = null)
 * @method static string secureLaunchUrl(PatientContext $context)
 * @method static void pushAppointments(string $userId, array<int, AppointmentData> $appointments)
 * @method static Collection<int, array<string, mixed>> practitioners()
 *
 * @see LyrebirdManager
 */
final class Lyrebird extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LyrebirdManager::class;
    }
}

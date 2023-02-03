<?php

namespace App\Cron\Nightly;

use App\Contracts\Listener;
use App\Events\CronNightly;
use App\Models\Enums\Days;
use App\Models\Flight;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UpdateRoutesCaller extends Listener
{
    /**
     * @param CronNightly $event
     */
    public function handle(CronNightly $event): void
    {
        Log::info('Updating Routes Standby');

        $this->CallCommand();
    }

    public function CallCommand(): void
    {
        Artisan::queue('routes:fetch', [ ]);
    }
}

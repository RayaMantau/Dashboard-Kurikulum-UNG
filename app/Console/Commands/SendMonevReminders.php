<?php

namespace App\Console\Commands;

use App\Services\Curriculum\MonevReminderService;
use Illuminate\Console\Command;

class SendMonevReminders extends Command
{
    protected $signature = 'curriculum:send-monev-reminders';

    protected $description = 'Kirim pengingat Monev Minor dan Rekonstruksi kurikulum yang jatuh tempo';

    public function handle(MonevReminderService $reminders): int
    {
        $this->info($reminders->send().' notifikasi Monev dikirim.');

        return self::SUCCESS;
    }
}

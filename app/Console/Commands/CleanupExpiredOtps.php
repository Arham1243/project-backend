<?php

namespace App\Console\Commands;

use App\Modules\Administration\Models\UserLoginOtp;
use Illuminate\Console\Command;

class CleanupExpiredOtps extends Command
{
    protected $signature = 'otp:cleanup';

    protected $description = 'Delete expired admin OTP records from the database';

    public function handle(): int
    {
        $deletedCount = UserLoginOtp::query()->where('expires_at', '<', now())->delete();

        $this->info("Deleted {$deletedCount} expired OTP(s).");

        return Command::SUCCESS;
    }
}

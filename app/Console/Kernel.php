<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // My Lahab — fire scheduled-task reminders (5 min before each
        // checklist item's scheduled_time). Cheap query each minute.
        $schedule->command('checklist:send-reminders')
            ->everyMinute()
            ->withoutOverlapping(2)
            ->onOneServer();

        // My Lahab — purge checklist proof photos older than 24 h to
        // keep server storage in budget.
        $schedule->command('checklist:purge-photos')
            ->hourly()
            ->withoutOverlapping(10)
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

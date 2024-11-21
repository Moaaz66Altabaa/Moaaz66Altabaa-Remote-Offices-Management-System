<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Notifications\HostStartingReservationNotification;
use App\Notifications\UserStartingReservationNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class SendDueReservationsNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-due-reservations-notifications';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Reservation::query()
            ->where('status', Reservation::STATUS_ACTIVE)
            ->where('start_date', now())
            ->each(function ($reservation){
               Notification::send($reservation->user, new UserStartingReservationNotification($reservation));
               Notification::send($reservation->office->user, new HostStartingReservationNotification($reservation));
            });
    }
}

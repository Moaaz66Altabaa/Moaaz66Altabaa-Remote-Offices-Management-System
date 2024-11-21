<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReservationPolicy
{
    use HandlesAuthorization;

    public function cancel(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id == $user->id;
    }
}

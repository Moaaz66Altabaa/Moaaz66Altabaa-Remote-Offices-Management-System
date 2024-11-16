<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserReservationsController extends Controller
{
    public function index(): JsonResource
    {
        abort_unless(auth()->user()->tokenCan('reservations.show'), 403);

        request()->validate(['status' => 'in:1,2']);

        $reservations = Reservation::query()
            ->whereUserId(auth()->id())
            ->when(request('office_id'), fn($builder) => $builder->where('office_id', request('office_if')))
            ->when(request('from_date') && request('to_date'),
                fn($builder) => $builder->whereBetween('start_date', [request('from_date'), request('to_date')])
                    ->orWhereBetween('end_date', [request('from_date'), request('to_date')])
            )
            ->with(['office', 'office.featuredImage'])
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }
}

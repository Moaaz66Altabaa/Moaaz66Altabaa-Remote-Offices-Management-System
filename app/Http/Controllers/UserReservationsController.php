<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class UserReservationsController extends Controller
{
    public function index(): JsonResource
    {
        abort_unless(auth()->user()->tokenCan('reservations.show'), 403);

        request()->validate([
            'status' => [Rule::in([Reservation::STATUS_ACTIVE, Reservation::STATUS_CANCELLED])],
            'office_id' => ['integer'],
            'from_date' => ['date', 'required_with:to_date'],
            'to_date' => ['date', 'required_with:from_date', 'after:from_date']
        ]);

        $reservations = Reservation::query()
            ->whereUserId(auth()->id())
            ->when(request('office_id'), fn($builder) => $builder->where('office_id', request('office_id')))
            ->when(request('status'), fn($builder) => $builder->where('status', request('status')))
            ->when(request('from_date') && request('to_date'),
                fn($builder) => $builder->where(function ($builder){
                    return $builder->whereBetween('start_date', [request('from_date'), request('to_date')])
                   ->orWhereBetween('end_date', [request('from_date'), request('to_date')]);

                }))
            ->with(['office', 'office.featuredImage'])
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }
}

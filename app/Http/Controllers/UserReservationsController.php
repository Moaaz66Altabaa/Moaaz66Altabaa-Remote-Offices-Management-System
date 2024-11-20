<?php

namespace App\Http\Controllers;

use App\Http\Resources\ReservationResource;
use App\Models\Office;
use App\Models\Reservation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
                fn($builder) => $builder->betweenDates(request('from_date'), request('to_date')))
            ->with(['office', 'office.featuredImage'])
            ->paginate(20);

        return ReservationResource::collection($reservations);
    }

    public function create(): JsonResource
    {
        abort_unless(auth()->user()->tokenCan('reservations.create'), 403);

        request()->validate([
            'office_id' => ['integer'],
            'start_date' => ['required', 'date', 'date:Y-m-d', 'after:'. now()->toDateString()],
            'end_date' => ['required', 'date', 'after:start_date', 'date:Y-m-d'],
        ]);

        $office = Office::findOr(request('office_id'), function (){
            throw ValidationException::withMessages(['office_id' => 'Invalid Office Id']);
        });

        throw_if($office->user_id == auth()->id(),
            ValidationException::withMessages(['office_id' => 'you cannot make a reservation on your office'])
        );

        $reservation = Cache::lock('reservations_office_'. $office->id, 10)->block(3, function () use ($office) {
            throw_if($office->reservations()->activeBetween(request('start_date'), request('end_date'))->exists(),
                ValidationException::withMessages(['office_id' => 'you cannot make a reservation during this time'])
            );

            $numberOfDays = Carbon::parse(request('start_date'))->startOfDay()->diffInDays(
                Carbon::parse(request('end_date'))->startOfDay()
            );

            throw_if($numberOfDays < 3,
                ValidationException::withMessages(['office_id' => 'you cannot make a reservation for less than 3 days'])
            );

            $price = $numberOfDays * $office->price_per_day;

            if ($numberOfDays >= 28 && $office->monthly_discount){
                $price = $price - ($price * $office->monthly_discount / 100);
            }

            return auth()->user()->reservations()->create([
                'office_id' => $office->id,
                'start_date' => request('start_date'),
                'end_date' => request('end_date'),
                'price' => $price,
                'status' => Reservation::STATUS_ACTIVE // although this field has a default value I specified it here to have it on the resource instance when returning
            ]);

        });

        return ReservationResource::make(
            $reservation->load('office')
        );
    }

}

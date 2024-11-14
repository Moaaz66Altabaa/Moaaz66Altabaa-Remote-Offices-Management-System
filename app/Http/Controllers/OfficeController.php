<?php

namespace App\Http\Controllers;

use App\Http\Resources\OfficeResource;
use App\Models\Office;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Validators\OfficeValidator;
use App\Notifications\OfficePendingApprovalNotification;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class OfficeController extends Controller
{
    public function index(): JsonResource
    {
        $offices = Office::query()
            ->when(request('user_id') && auth()->user() && request('user_id') == auth()->id(),
                fn($builder) => $builder,
                fn($builder) => $builder->where('approval_status', Office::APPROVAL_APPROVED)->where('hidden', false)
            )
            ->when(request('user_id'), fn ($builder) => $builder->whereUserId(request('user_id')))
            ->when(request('visitor_id'), fn ($builder) => $builder->whereRelation('reservations', 'user_id', '=', request('visitor_id')))
            ->when(
                request('lng') && request('lat'),
                fn ($builder) => $builder->nearestTo(request('lat'), request('lng')),
                fn ($builder) => $builder->orderBy('id', 'ASC')
            )
            ->with(['images', 'tags', 'user'])
            ->withCount(['reservations' => fn ($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->paginate(20);


        return OfficeResource::collection(
            $offices
        );
    }


    public function show(Office $office): JsonResource
    {
        $office->loadCount(['reservations' => fn($builder) => $builder->where('status', Reservation::STATUS_ACTIVE)])
            ->load(['tags', 'images', 'user']);

        return OfficeResource::make($office);
    }


    public function create(): JsonResource
    {
        // Check if the User is Authorized
        abort_unless(auth()->user()->tokenCan('office.create'),
            Response::HTTP_FORBIDDEN
        );

        $validated = (new OfficeValidator())->validate($office = new Office(), request()->all());

        $validated['approval_status'] = Office::APPROVAL_PENDING;
        $validated['user_id'] = auth()->id();

        // Using Transaction to Make Sure that if One of the Queries Failed Nothing will be Stored in the Database (both success or nothing)
        $office = DB::transaction(function () use ($office, $validated){
            $office->fill(Arr::except($validated, ['tags']))
                ->save();

            if (isset($validated['tags'])){
                $office->tags()->attach($validated['tags']);
            }

            return $office;
        });

        Notification::send(User::where('is_admin', true)->get(), new OfficePendingApprovalNotification($office));

        return OfficeResource::make($office);
    }

    public function update(Office $office): JsonResource
    {
        // Check if the User is Authorized
        abort_unless(auth()->user()->tokenCan('office.update'),
            Response::HTTP_FORBIDDEN
        );

        Gate::authorize('update', $office);

        $validated = (new OfficeValidator())->validate($office, request()->all());

        $validated['approval_status'] = Office::APPROVAL_PENDING;

        $office->fill(Arr::except($validated, ['tags']));

        if ($requiresReview = $office->isDirty(['lat', 'lng', 'price_per_day'])){
            $office->approval_status = Office::APPROVAL_PENDING;
        }

        DB::transaction(function () use ($validated, $office){
            $office->save();

            if (isset($validated['tags'])){
                $office->tags()->sync($validated['tags']);
            }
        });

        if ($requiresReview){
            Notification::send(User::where('is_admin', true)->get(), new OfficePendingApprovalNotification($office));
        }

        return OfficeResource::make($office);
    }

    public function delete(Office $office): void
    {
        abort_unless(auth()->user()->tokenCan('office.delete'),
            Response::HTTP_FORBIDDEN
        );

        Gate::authorize('delete', $office);

        throw_if(
            $office->reservations()->where('status', Reservation::STATUS_ACTIVE)->exists(),
            ValidationException::withMessages(['office' => 'this office cannot be deleted!'])
        );

        $office->delete();
    }

}

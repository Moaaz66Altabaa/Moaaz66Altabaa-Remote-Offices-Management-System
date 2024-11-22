<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ImageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
//            'path' => Storage::url($this->path) ,
            $this->merge(
                Arr::except(parent::toArray($request), [
                    'created_at', 'updated_at'
                ])
            )
        ];
    }
}

<?php

namespace App\Modules\Administration\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'street_address_1' => $this->street_address_1,
            'street_address_2' => $this->street_address_2,
            'street_address_3' => $this->street_address_3,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'country' => $this->country,
            'logo' => $this->logo,
            'logo_url' => $this->logo,
            'timezone' => $this->timezone,
            'date_format' => $this->date_format,
        ];
    }
}

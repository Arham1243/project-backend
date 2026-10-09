<?php

namespace App\Modules\Core\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use BelongsToCompany;
    use HasUuid;

    protected $table = 'students';

    protected $guarded = ['id', 'uuid', 'created_at', 'updated_at'];


    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public function dependencies(): array
    {
        return [];
    }

    protected static function booted()
    {
        static::deleting(function ($customer) {
            $dependencies = $customer->dependencies();

            if (in_array(true, $dependencies, true)) {
                throw ValidationException::withMessages([
                    'student' => 'This student is in use and cannot be deleted.',
                ]);
            }
        });
    }
}

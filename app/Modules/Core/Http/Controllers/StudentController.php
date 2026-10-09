<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Administration\Http\Controllers\AdminOrionController;
use App\Modules\Core\Http\Requests\StudentRequest;
use App\Modules\Core\Http\Resources\StudentResource;
use App\Modules\Core\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Orion\Concerns\DisableAuthorization;
use Orion\Http\Requests\Request as OrionRequest;

class StudentController extends AdminOrionController
{
    use DisableAuthorization;

    protected $model = Student::class;

    protected $request = StudentRequest::class;

    protected $resource = StudentResource::class;

    public function searchableBy(): array
    {
        return ['student_id', 'name'];
    }

    public function sortableBy(): array
    {
        return ['student_id', 'name', 'status', 'created_at'];
    }

    public function filterableBy(): array
    {
        return ['student_id', 'name', 'status'];
    }

    protected function beforeSave(OrionRequest $request, Model $entity): void
    {
        if (! $entity instanceof Student) {
            return;
        }

        $user = $request->user();
        if ($user && $user->company_id && empty($entity->company_id)) {
            $entity->company_id = $user->company_id;
        }

        if (! $entity->exists && $entity->status === null) {
            $entity->status = true;
        }
    }

    public function changeStatus(Student $student)
    {
        $student->update(['status' => ! $student->status]);

        return new StudentResource($student->fresh());
    }

    public function deleteStudent(Student $student)
    {
        $student->delete();

        return response()->json(['message' => 'Student deleted successfully.']);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\Curriculum\StoreCurriculumRequest;
use App\Http\Requests\Api\V1\Curriculum\UpdateCurriculumRequest;
use App\Http\Resources\CurriculumResource;
use App\Models\Curriculum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CurriculumController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $phase = $request->string('phase')->trim();
        $level = $request->string('level')->trim();
        $grade = $request->integer('grade');
        $isActive = $request->boolean('is_active');

        $query = Curriculum::query();

        if ($isActive) {
            $query->active();
        }

        if ($phase) {
            $query->byPhase($phase);
        }

        if ($level) {
            $query->byLevel($level);
        }

        if ($grade) {
            $query->byGrade($grade);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $curricula = $query->latest()->paginate($perPage);

        return $this->success(
            CurriculumResource::collection($curricula)->response()->getData(true),
            'Curricula retrieved successfully'
        );
    }

    public function store(StoreCurriculumRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['code'])) {
            $data['code'] = $this->generateCode($data['level'], $data['phase']);
        }

        if (! isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        $curriculum = Curriculum::create($data);

        return $this->created(
            new CurriculumResource($curriculum),
            'Curriculum created successfully'
        );
    }

    public function show(string $id): JsonResponse
    {
        $curriculum = Curriculum::find($id);

        if (! $curriculum) {
            return $this->notFound('Curriculum not found');
        }

        return $this->success(
            new CurriculumResource($curriculum),
            'Curriculum retrieved successfully'
        );
    }

    public function update(UpdateCurriculumRequest $request, string $id): JsonResponse
    {
        $curriculum = Curriculum::find($id);

        if (! $curriculum) {
            return $this->notFound('Curriculum not found');
        }

        $curriculum->update($request->validated());

        return $this->success(
            new CurriculumResource($curriculum),
            'Curriculum updated successfully'
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $curriculum = Curriculum::find($id);

        if (! $curriculum) {
            return $this->notFound('Curriculum not found');
        }

        $curriculum->delete();

        return $this->success(
            message: 'Curriculum deleted successfully'
        );
    }

    public function subjects(string $id): JsonResponse
    {
        $curriculum = Curriculum::find($id);

        if (! $curriculum) {
            return $this->notFound('Curriculum not found');
        }

        return $this->success(
            $curriculum->getSubjects(),
            'Subjects retrieved successfully'
        );
    }

    public function getPhases(): JsonResponse
    {
        $phases = Curriculum::distinct('phase')->get()->flatten()->filter()->values();

        return $this->success($phases, 'Phases retrieved successfully');
    }

    public function getLevels(): JsonResponse
    {
        $levels = Curriculum::distinct('level')->get()->flatten()->filter()->values();

        return $this->success($levels, 'Levels retrieved successfully');
    }

    public function getGrades(): JsonResponse
    {
        $curricula = Curriculum::all();
        $grades = [];

        foreach ($curricula as $curriculum) {
            $gradeRange = $curriculum->grade_range;
            if ($gradeRange && isset($gradeRange['min'], $gradeRange['max'])) {
                for ($i = $gradeRange['min']; $i <= $gradeRange['max']; $i++) {
                    $grades[$i] = true;
                }
            }
        }

        ksort($grades);

        return $this->success(array_keys($grades), 'Grades retrieved successfully');
    }

    public function subjectOutcomes(string $id, string $subjectCode): JsonResponse
    {
        $curriculum = Curriculum::find($id);

        if (! $curriculum) {
            return $this->notFound('Curriculum not found');
        }

        $outcomes = $curriculum->getLearningOutcomesBySubject($subjectCode);

        return $this->success($outcomes, 'Learning outcomes retrieved successfully');
    }

    public function subjectObjectives(string $id, string $subjectCode): JsonResponse
    {
        $curriculum = Curriculum::find($id);

        if (! $curriculum) {
            return $this->notFound('Curriculum not found');
        }

        $objectives = $curriculum->getLearningObjectivesBySubject($subjectCode);

        return $this->success($objectives, 'Learning objectives retrieved successfully');
    }

    private function generateCode(string $level, string $phase): string
    {
        $levelCode = match ($level) {
            'SD' => 'SD',
            'SMP' => 'SMP',
            'SMA' => 'SMA',
            'SMK' => 'SMK',
            default => 'UNK',
        };

        $phaseNumber = preg_replace('/[^0-9]/', '', $phase);
        $phaseCode = $phaseNumber ? "F{$phaseNumber}" : 'FX';

        return mb_strtoupper("KM-{$levelCode}-{$phaseCode}-".date('Y'));
    }
}

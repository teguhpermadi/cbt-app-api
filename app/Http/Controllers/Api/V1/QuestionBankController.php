<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Api\V1\QuestionBank\StoreQuestionBankRequest;
use App\Http\Requests\Api\V1\QuestionBank\UpdateQuestionBankRequest;
use App\Http\Resources\QuestionBankResource;
use App\Models\QuestionBank;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use ZipArchive;

final class QuestionBankController extends ApiController
{
    /**
     * Display a listing of question banks with pagination, search, and sorting.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);
        $search = $request->string('search')->trim();
        $sortBy = $request->string('sort_by', 'created_at');
        $order = $request->string('order', 'desc');

        $questionBanks = QuestionBank::query()
            ->forUser()
            ->with(['user', 'subject'])
            ->withCount('questions')
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('academic_year_id'), function ($query) use ($request) {
                $query->whereHas('subject', function ($q) use ($request) {
                    $q->where('academic_year_id', $request->academic_year_id);
                });
            })
            ->orderBy($sortBy, $order)
            ->paginate($perPage);

        return $this->success(
            QuestionBankResource::collection($questionBanks)->response()->getData(true),
            'Question banks retrieved successfully'
        );
    }

    /**
     * Store a newly created question bank in storage.
     */
    public function store(StoreQuestionBankRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['user_id'] = Auth::id();

        $questionBank = QuestionBank::create($data);

        return $this->created(
            new QuestionBankResource($questionBank->load(['user', 'subject'])),
            'Question bank created successfully'
        );
    }

    /**
     * Display the specified question bank.
     */
    public function show(string $id): JsonResponse
    {
        $questionBank = QuestionBank::query()
            ->with(['user', 'subject', 'questions' => function ($query) {
                $query->with(['tags', 'options']);
                $query->orderBy('order', 'asc');
            }])
            ->withCount('questions')
            ->find($id);

        if (! $questionBank) {
            return $this->notFound('Question bank not found');
        }

        return $this->success(
            new QuestionBankResource($questionBank),
            'Question bank retrieved successfully'
        );
    }

    /**
     * Update the specified question bank in storage.
     */
    public function update(UpdateQuestionBankRequest $request, string $id): JsonResponse
    {
        $questionBank = QuestionBank::query()->find($id);

        if (! $questionBank) {
            return $this->notFound('Question bank not found');
        }

        $questionBank->update($request->validated());

        return $this->success(
            new QuestionBankResource($questionBank->load(['user', 'subject'])),
            'Question bank updated successfully'
        );
    }

    /**
     * Remove the specified question bank from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $questionBank = QuestionBank::query()->find($id);

        if (! $questionBank) {
            return $this->notFound('Question bank not found');
        }

        $questionBank->delete();

        return $this->success(
            message: 'Question bank deleted successfully'
        );
    }

    /**
     * Display a listing of soft-deleted question banks.
     */
    public function trashed(Request $request): JsonResponse
    {
        $perPage = $request->integer('per_page', 15);

        $questionBanks = QuestionBank::onlyTrashed()
            ->with(['user', 'subject'])
            ->withCount('questions')
            ->latest()
            ->paginate($perPage);

        return $this->success(
            QuestionBankResource::collection($questionBanks)->response()->getData(true),
            'Trashed question banks retrieved successfully'
        );
    }

    /**
     * Restore a soft-deleted question bank.
     */
    public function restore(string $id): JsonResponse
    {
        $questionBank = QuestionBank::onlyTrashed()
            ->find($id);

        if (! $questionBank) {
            return $this->notFound('Trashed question bank not found');
        }

        $questionBank->restore();

        return $this->success(
            new QuestionBankResource($questionBank->load(['user', 'subject'])),
            'Question bank restored successfully'
        );
    }

    /**
     * Permanently delete a soft-deleted question bank.
     */
    public function forceDelete(string $id): JsonResponse
    {
        $questionBank = QuestionBank::withTrashed()
            ->find($id);

        if (! $questionBank) {
            return $this->notFound('Question bank not found');
        }

        $questionBank->forceDelete();

        return $this->success(
            message: 'Question bank permanently deleted'
        );
    }

    /**
     * Import questions from Word document.
     */
    public function import(
        \App\Http\Requests\Api\V1\QuestionBank\ImportQuestionRequest $request,
        string $id,
        \App\Services\QuestionImportService $importService
    ): JsonResponse {
        $questionBank = QuestionBank::find($id);

        if (! $questionBank) {
            return $this->notFound('Question bank not found');
        }

        $file = $request->file('file');
        $path = $file->path(); // Get temporary path

        try {
            $result = $importService->parseWordDocument(
                filePath: $path,
                questionBankId: $questionBank->id,
                authorId: Auth::id()
            );

            if ($result['success']) {
                return $this->success(
                    $result,
                    "Berhasil mengimport {$result['total']} soal."
                );
            }

            return $this->error(
                'Gagal mengimport soal.',
                422,
                $result['errors']
            );
        } catch (Exception $e) {
            return $this->error(
                'Terjadi kesalahan saat memproses file.',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Export questions to Word document.
     */
    public function export(string $id, \App\Services\QuestionExportService $exportService): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $questionBank = QuestionBank::find($id);

        if (! $questionBank) {
            return $this->notFound('Question bank not found');
        }

        try {
            $filePath = $exportService->exportToWord($questionBank);

            while (ob_get_level()) {
                ob_end_clean();
            }

            return response()->download($filePath, $questionBank->name.'.docx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ])->deleteFileAfterSend(true);
        } catch (Exception $e) {
            return $this->error(
                'Terjadi kesalahan saat mengekspor file.',
                500,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Backup all assets from public storage.
     */
    public function backupAssets(): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $publicPath = storage_path('app/public');

        if (! is_dir($publicPath)) {
            return $this->error('Public storage not found', 500);
        }

        $files = [];
        $directories = glob($publicPath.'/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $dirFiles = glob($dir.'/*');
            if ($dirFiles) {
                $files = array_merge($files, $dirFiles);
            }
        }

        if (empty($files)) {
            return $this->error('No files to backup', 400);
        }

        $zipFileName = 'assets_backup_'.date('Y-m-d').'.zip';
        $zipPath = storage_path('app/'.$zipFileName);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    $relativePath = str_replace($publicPath.'\\', '', $file);
                    $relativePath = str_replace('/', '\\', $relativePath);
                    $zip->addFile($file, $relativePath);
                }
            }
            $zip->close();
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        return response()->download($zipPath, $zipFileName, [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Restore assets from uploaded zip file.
     */
    public function restoreAssets(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:zip',
        ]);

        $file = $request->file('file');
        $publicPath = storage_path('app/public');

        $zip = new ZipArchive();
        $result = $zip->open($file->path());

        if ($result !== true) {
            return $this->error('Failed to open zip file', 400);
        }

        $extractedCount = 0;
        $zip->extractTo($publicPath);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! is_dir($publicPath.'/'.$name)) {
                $extractedCount++;
            }
        }
        $zip->close();

        return $this->success(
            ['extracted_files' => $extractedCount],
            "Berhasil merestore {$extractedCount} file."
        );
    }
}

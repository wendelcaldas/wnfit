<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\StudentAccount;
use App\Services\StudentAccessService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentAccessController extends Controller
{
    private function authorizeStudent(Request $request, Aluno $student): void
    {
        abort_unless($student->organizacao_id === $request->user()->organizacoes()->firstOrFail()->id, 404);
    }

    public function suggest(Request $request, StudentAccessService $service)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        return response()->json(['username' => $service->suggest($data['name'])]);
    }

    public function show(Request $request, Aluno $student)
    {
        $this->authorizeStudent($request, $student);
        $account = StudentAccount::where('aluno_id', $student->id)->first();

        return response()->json(['access' => $account ? [
            'username' => $account->username, 'mustChangePassword' => $account->must_change_password,
            'lastLoginAt' => $account->last_login_at?->toIso8601String(),
        ] : null]);
    }

    public function issue(Request $request, Aluno $student, StudentAccessService $service)
    {
        $this->authorizeStudent($request, $student);
        $data = $request->validate(['username' => ['nullable', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9][a-z0-9._-]+$/', Rule::unique('student_accounts', 'username')], 'reset' => ['sometimes', 'boolean']]);

        return response()->json(['credentials' => $service->issue($student, $data['username'] ?? null, $request->boolean('reset'))])->header('Cache-Control', 'no-store');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Models\Cobranca;
use App\Models\Plano;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function __construct(private readonly BillingService $billing)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $this->billing->refreshOverdueCharges($organization->id);

        $query = Aluno::query()
            ->with(['assinatura.plano', 'cobrancas'])
            ->where('organizacao_id', $organization->id);

        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->toString().'%';
            $query->where(fn ($builder) => $builder
                ->where('nome', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('telefone', 'like', $term));
        }

        if ($request->filled('status') && $request->status !== 'todos') {
            $query->where('status', $request->status);
        }

        if ($request->filled('professor') && $request->professor !== 'todos') {
            $query->where('treinador', $request->professor);
        }

        if ($request->filled('plano') && $request->plano !== 'todos') {
            $query->where('plano', $request->plano);
        }

        $students = $query->latest()->get();

        $allStudents = Aluno::query()
            ->with('cobrancas')
            ->where('organizacao_id', $organization->id)
            ->get();

        $overdueIds = Cobranca::query()
            ->where('organizacao_id', $organization->id)
            ->where('status', 'atrasado')
            ->pluck('aluno_id')
            ->unique();

        $expiringSoon = $allStudents->filter(fn (Aluno $student) => $student->vencimento
            && $student->vencimento->between(today(), today()->addDays(7)));

        return response()->json([
            'summary' => [
                ['label' => 'Total de alunos', 'value' => (string) $allStudents->count(), 'caption' => 'Base cadastrada', 'badge' => '+12%', 'type' => 'students'],
                ['label' => 'Alunos ativos', 'value' => (string) $allStudents->where('status', 'ativo')->count(), 'caption' => '77% do total', 'badge' => 'Ativos', 'type' => 'active'],
                ['label' => 'Planos a vencer', 'value' => (string) $expiringSoon->count(), 'caption' => 'Nos proximos 7 dias', 'badge' => 'Atencao', 'type' => 'expiring'],
                ['label' => 'Inadimplentes', 'value' => (string) $overdueIds->count(), 'caption' => 'Com cobranca atrasada', 'badge' => 'Risco', 'type' => 'overdue'],
            ],
            'students' => $students->map(fn (Aluno $student) => $this->studentRow($student))->values(),
            'filters' => [
                'statuses' => ['todos', 'ativo', 'avaliacao', 'pausado'],
                'plans' => $allStudents->pluck('plano')->filter()->unique()->values(),
                'teachers' => $allStudents->pluck('treinador')->filter()->unique()->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        $data = $request->validate($this->validationRules());

        $student = DB::transaction(function () use ($data, $organization) {
            $plan = Plano::query()->firstOrCreate(
                ['organizacao_id' => $organization->id, 'nome' => $data['plano']],
                [
                    'valor_mensal' => $data['valor_mensal'] ?? 120,
                    'ciclo' => 'mensal',
                    'ativo' => true,
                ],
            );

            $student = Aluno::query()->create([
                ...$this->studentPayload($data),
                'organizacao_id' => $organization->id,
                'plano' => $plan->nome,
                'vencimento' => $data['data_vencimento'],
                'status' => $data['status'],
            ]);

            $this->billing->createSubscriptionForStudent(
                $student,
                $plan,
                $data['data_inicio'],
                $data['data_vencimento'],
                (bool) ($data['auto_renovacao'] ?? true),
                $data['metodo_pagamento'] ?? 'PIX',
            );

            return $student;
        });

        return response()->json(['student' => $this->studentDetailPayload($student->fresh(['assinatura.plano', 'cobrancas.eventos']))], 201);
    }

    public function show(Request $request, Aluno $student): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        abort_unless($student->organizacao_id === $organization->id, 404);

        $this->billing->refreshOverdueCharges($organization->id);

        return response()->json([
            'student' => $this->studentDetailPayload($student->load(['assinatura.plano', 'cobrancas.eventos'])),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();

        return response()->json([
            'plans' => Plano::query()
                ->where('organizacao_id', $organization->id)
                ->where('ativo', true)
                ->orderBy('valor_mensal')
                ->get(['id', 'nome', 'valor_mensal', 'ciclo']),
            'teachers' => ['Lucas Oliveira', 'Camila Alves', 'Pedro Henrique', 'Amanda Rocha'],
            'units' => ['Unidade Vila Madalena', 'Unidade Centro', 'Online'],
        ]);
    }

    public function generateCharge(Request $request, Aluno $student): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        abort_unless($student->organizacao_id === $organization->id, 404);

        $subscription = $student->assinatura()->with('plano')->firstOrFail();
        $charge = $this->billing->generateCharge($subscription, $request->date('vencimento') ?? $subscription->proximo_vencimento);

        return response()->json(['charge' => $this->chargePayload($charge->load('eventos'))]);
    }

    public function sendCharge(Request $request, Cobranca $charge): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        abort_unless($charge->organizacao_id === $organization->id, 404);

        return response()->json(['charge' => $this->chargePayload($this->billing->sendCharge($charge)->load('eventos'))]);
    }

    public function payCharge(Request $request, Cobranca $charge): JsonResponse
    {
        $organization = $request->user()->organizacoes()->firstOrFail();
        abort_unless($charge->organizacao_id === $organization->id, 404);

        return response()->json(['charge' => $this->chargePayload($this->billing->registerPayment($charge)->load('eventos'))]);
    }

    private function validationRules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'data_nascimento' => ['nullable', 'date'],
            'genero' => ['nullable', 'string', 'max:30'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'rg' => ['nullable', 'string', 'max:30'],
            'profissao' => ['nullable', 'string', 'max:255'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'estado' => ['nullable', 'string', 'max:2'],
            'cep' => ['nullable', 'string', 'max:20'],
            'peso' => ['nullable', 'numeric'],
            'altura' => ['nullable', 'integer'],
            'objetivo' => ['required', 'string', 'max:80'],
            'treinador' => ['nullable', 'string', 'max:80'],
            'unidade' => ['nullable', 'string', 'max:255'],
            'como_conheceu' => ['nullable', 'string', 'max:255'],
            'observacoes' => ['nullable', 'string'],
            'plano' => ['required', 'string', 'max:80'],
            'status' => ['required', 'string', 'max:30'],
            'data_inicio' => ['required', 'date'],
            'data_vencimento' => ['required', 'date'],
            'auto_renovacao' => ['sometimes', 'boolean'],
            'metodo_pagamento' => ['nullable', 'string', 'max:30'],
            'valor_mensal' => ['nullable', 'numeric'],
        ];
    }

    private function studentPayload(array $data): array
    {
        return collect($data)->only([
            'nome',
            'email',
            'telefone',
            'data_nascimento',
            'genero',
            'cpf',
            'rg',
            'profissao',
            'endereco',
            'numero',
            'complemento',
            'bairro',
            'cidade',
            'estado',
            'cep',
            'peso',
            'altura',
            'objetivo',
            'treinador',
            'unidade',
            'como_conheceu',
            'observacoes',
        ])->all();
    }

    private function studentRow(Aluno $student): array
    {
        $overdue = $student->cobrancas->contains('status', 'atrasado');
        $expiring = $student->vencimento && $student->vencimento->between(today(), today()->addDays(7));
        $status = $overdue ? 'Inadimplente' : ($expiring ? 'Vence em breve' : ucfirst($student->status));

        return [
            'id' => $student->id,
            'initials' => $this->initials($student->nome),
            'name' => $student->nome,
            'email' => $student->email ?: '-',
            'phone' => $student->telefone ?: '-',
            'plan' => $student->plano,
            'teacher' => $student->treinador ?: '-',
            'status' => $status,
            'statusClass' => $overdue ? 'badge-danger' : ($expiring ? 'badge-warning' : 'badge-success'),
            'dueDate' => optional($student->vencimento)->format('d/m/Y') ?: '-',
            'lastWorkout' => $student->updated_at?->diffForHumans() ?? '-',
        ];
    }

    private function studentDetailPayload(Aluno $student): array
    {
        $subscription = $student->assinatura;
        $charges = $student->cobrancas()->with('eventos')->orderByDesc('vencimento')->get();
        $paid = (float) $charges->where('status', 'pago')->sum('valor');
        $open = (float) $charges->whereIn('status', ['pendente', 'atrasado'])->sum('valor');

        return [
            'id' => $student->id,
            'name' => $student->nome,
            'initials' => $this->initials($student->nome),
            'status' => ucfirst($student->status),
            'email' => $student->email,
            'phone' => $student->telefone,
            'birthDate' => optional($student->data_nascimento)->format('d/m/Y'),
            'city' => $student->cidade,
            'state' => $student->estado,
            'plan' => $subscription?->plano?->nome ?? $student->plano,
            'teacher' => $student->treinador,
            'unit' => $student->unidade,
            'goal' => $student->objetivo,
            'observations' => $student->observacoes,
            'profile' => [
                'cpf' => $student->cpf,
                'rg' => $student->rg,
                'profession' => $student->profissao,
                'address' => trim("{$student->endereco}, {$student->numero}"),
                'neighborhood' => $student->bairro,
                'zip' => $student->cep,
                'weight' => $student->peso,
                'height' => $student->altura,
            ],
            'financial' => [
                'status' => $open > 0 ? 'Com pendencia' : 'Em dia',
                'monthlyValue' => (float) ($subscription?->plano?->valor_mensal ?? 0),
                'nextDueDate' => optional($subscription?->proximo_vencimento)->format('d/m/Y'),
                'totalPaid' => $paid,
                'openAmount' => $open,
                'subscription' => [
                    'plan' => $subscription?->plano?->nome,
                    'cycle' => $subscription?->plano?->ciclo,
                    'autoRenew' => (bool) $subscription?->auto_renovacao,
                    'paymentMethod' => $subscription?->metodo_pagamento,
                    'startDate' => optional($subscription?->inicio_em)->format('d/m/Y'),
                    'status' => $subscription?->status,
                ],
                'charges' => $charges->map(fn (Cobranca $charge) => $this->chargePayload($charge))->values(),
                'timeline' => $charges->flatMap(fn (Cobranca $charge) => $charge->eventos)->sortByDesc('ocorrido_em')->take(6)->map(fn ($event) => [
                    'type' => $event->tipo,
                    'title' => $event->descricao,
                    'date' => $event->ocorrido_em->format('d/m/Y H:i'),
                ])->values(),
            ],
        ];
    }

    private function chargePayload(Cobranca $charge): array
    {
        return [
            'id' => $charge->id,
            'competence' => $charge->competencia,
            'dueDate' => $charge->vencimento->format('d/m/Y'),
            'value' => (float) $charge->valor,
            'status' => $charge->status,
            'statusClass' => match ($charge->status) {
                'pago' => 'badge-success',
                'atrasado' => 'badge-danger',
                'cancelado' => 'badge-muted',
                default => 'badge-warning',
            },
            'paymentMethod' => $charge->forma_pagamento,
            'paidAt' => optional($charge->pago_em)->format('d/m/Y') ?: '-',
            'sentAt' => optional($charge->enviado_em)->format('d/m/Y H:i'),
        ];
    }

    private function initials(string $name): string
    {
        return collect(explode(' ', $name))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->implode('');
    }
}

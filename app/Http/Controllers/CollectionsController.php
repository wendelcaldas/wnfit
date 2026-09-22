<?php

namespace App\Http\Controllers;

use App\Models\Aluno;
use App\Services\BillingService;
use App\Services\Messaging\MessagingService;
use App\Services\RecurringBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CollectionsController extends Controller
{
    private function organization(Request $request): int
    {
        return $request->user()->organizacoes()->firstOrFail()->id;
    }

    private function authorizeStudent(Request $request, Aluno $student): void
    {
        abort_unless($student->organizacao_id === $this->organization($request), 404);
    }

    public function index(Request $request, BillingService $billing, RecurringBillingService $recurring)
    {
        $data = $request->validate(['q' => 'nullable|string|max:100', 'filter' => 'nullable|in:overdue,upcoming,all,review', 'sort' => 'nullable|in:oldest,amount,contact', 'page' => 'nullable|integer|min:1']);
        $org = $this->organization($request);
        $billing->refreshOverdueCharges($org);
        $students = Aluno::where('organizacao_id', $org)->with(['cobrancas', 'assinatura.plano'])->get();
        $rows = $students->map(function ($student) {
            $open = $student->cobrancas->whereIn('status', ['pendente', 'atrasado'])->sortBy('vencimento');
            $overdue = $open->where('status', 'atrasado');
            $upcoming = $open->filter(fn ($c) => $c->vencimento->between(today(), today()->addDays(7)->endOfDay()));

            return [
                'id' => $student->id, 'name' => $student->nome, 'plan' => $student->assinatura?->plano?->nome ?? $student->plano,
                'overdue' => (float) $overdue->sum('valor'), 'open' => (float) $open->sum('valor'), 'upcoming' => (float) $upcoming->sum('valor'),
                'oldest' => $overdue->first()?->vencimento->toDateString(),
                'lastContact' => $student->cobrancas->max('enviado_em')?->format('d/m/Y H:i'),
                'lastContactSort' => $student->cobrancas->max('enviado_em')?->timestamp ?? 0,
                'needsReview' => $student->assinatura && ! $student->assinatura->recorrencia_inicio && $student->assinatura->auto_renovacao && $student->assinatura->status === 'ativa',
                'installments' => $open->map(fn ($c) => ['id' => $c->id, 'month' => $c->competencia, 'status' => $c->status])->values(),
            ];
        });
        $summary = ['overdue' => $rows->sum('overdue'), 'students' => $rows->where('overdue', '>', 0)->count(), 'upcoming' => $rows->sum('upcoming'), 'review' => $rows->where('needsReview', true)->count()];
        $rows = $rows->filter(function ($row) use ($data) {
            $matches = match ($data['filter'] ?? 'overdue') {
                'upcoming' => $row['upcoming'] > 0, 'review' => $row['needsReview'], 'all' => true, default => $row['overdue'] > 0,
            };

            return $matches && (! filled($data['q'] ?? null) || mb_stripos($row['name'], $data['q']) !== false);
        });
        $rows = match ($data['sort'] ?? 'oldest') {
            'amount' => $rows->sortByDesc('overdue'), 'contact' => $rows->sortBy('lastContactSort'), default => $rows->sortBy(fn ($r) => $r['oldest'] ?? '9999'),
        };
        $page = max(1, min((int) ($data['page'] ?? 1), max(1, (int) ceil($rows->count() / 20))));

        return response()->json(['summary' => $summary, 'students' => $rows->values()->slice(($page - 1) * 20, 20)->values(), 'pagination' => ['page' => $page, 'lastPage' => max(1, (int) ceil($rows->count() / 20)), 'total' => $rows->count()]]);
    }

    public function show(Request $request, Aluno $student, BillingService $billing)
    {
        $this->authorizeStudent($request, $student);
        $billing->refreshOverdueCharges($student->organizacao_id);
        $charges = $student->cobrancas()->orderByDesc('vencimento')->get();

        return response()->json([
            'student' => ['id' => $student->id, 'name' => $student->nome, 'phone' => $student->telefone],
            'charges' => $charges->map(fn ($c) => ['id' => $c->id, 'competence' => $c->competencia, 'dueDate' => $c->vencimento->format('d/m/Y'), 'value' => (float) $c->valor, 'status' => $c->status, 'paidAt' => $c->pago_em?->format('d/m/Y'), 'sentAt' => $c->enviado_em?->format('d/m/Y H:i')]),
            'subscription' => $student->assinatura?->only(['id', 'status', 'recorrencia_inicio', 'pausa_em', 'encerramento_em', 'auto_renovacao']),
            'messages' => $student->mensagens()->latest('id')->limit(20)->get()->map(fn ($m) => ['id' => $m->id, 'content' => $m->conteudo, 'status' => $m->status, 'sentAt' => $m->enviado_em?->format('d/m/Y H:i'), 'createdAt' => $m->created_at->format('d/m/Y H:i')]),
        ]);
    }

    private function selected(Request $request, Aluno $student, bool $allowPaid = false)
    {
        $this->authorizeStudent($request, $student);
        $data = $request->validate(['ids' => 'required|array|min:1|max:36', 'ids.*' => 'required|integer|distinct']);
        $charges = $student->cobrancas()->where('organizacao_id', $student->organizacao_id)->whereIn('id', $data['ids'])->orderBy('id')->lockForUpdate()->get();
        abort_unless($charges->count() === count($data['ids']), 404);
        abort_if($charges->contains(fn ($c) => ! in_array($c->status, $allowPaid ? ['pendente', 'atrasado', 'pago'] : ['pendente', 'atrasado'])), 422, 'Uma das mensalidades selecionadas nao esta em aberto. Atualize a lista.');

        return $charges;
    }

    public function message(Request $request, Aluno $student, MessagingService $messaging)
    {
        return DB::transaction(function () use ($request, $student, $messaging) {
            $charges = $this->selected($request, $student);
            $message = $messaging->prepareGroupedReminder($student, $charges);

            return response()->json(['message' => ['id' => $message->id, 'content' => $message->conteudo, 'manualUrl' => $messaging->manualWhatsAppUrlForMessage($message)]]);
        });
    }

    public function pay(Request $request, Aluno $student, BillingService $billing)
    {
        $data = $request->validate(['method' => 'required|in:PIX,Dinheiro,Cartão,Transferência', 'date' => 'required|date_format:Y-m-d|before_or_equal:today']);

        return DB::transaction(function () use ($request, $student, $billing, $data) {
            $charges = $this->selected($request, $student, true);
            foreach ($charges as $charge) {
                $billing->registerPayment($charge, method: $data['method'], paymentDate: $data['date']);
            }

            return response()->json(['ok' => true]);
        });
    }

    public function recurrence(Request $request, Aluno $student, RecurringBillingService $recurring, BillingService $billing)
    {
        $this->authorizeStudent($request, $student);
        $data = $request->validate([
            'start' => 'nullable|date_format:Y-m-d|after_or_equal:2000-01-01',
            'pause' => 'nullable|date_format:Y-m-d', 'end' => 'nullable|date_format:Y-m-d',
            'confirm' => 'sometimes|boolean', 'quote' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($student, $data, $recurring, $billing) {
            $subscription = $student->assinatura()->lockForUpdate()->firstOrFail();
            abort_unless($subscription->status === 'ativa' && $subscription->auto_renovacao, 422, 'Assinatura sem recorrencia ativa.');
            $earliest = $subscription->cobrancas()->orderBy('vencimento')->first()?->vencimento;
            if ($earliest?->lt($subscription->inicio_em)) {
                $earliest = $subscription->proximo_vencimento;
            }
            $start = Carbon::parse($data['start'] ?? $subscription->recorrencia_inicio ?? $earliest ?? $subscription->proximo_vencimento);
            abort_if($start->lt($subscription->inicio_em), 422, 'O inicio nao pode anteceder a assinatura.');
            $subscription->pausa_em = $data['pause'] ?? null;
            $subscription->encerramento_em = $data['end'] ?? null;
            abort_if(($subscription->pausa_em && $subscription->pausa_em->lt($start)) || ($subscription->encerramento_em && $subscription->encerramento_em->lt($start)), 422, 'Pausa e encerramento nao podem anteceder o inicio da recorrencia.');
            $items = $recurring->missing($subscription, $start);
            $quote = hash('sha256', json_encode([$subscription->id, $start->toDateString(), $subscription->pausa_em, $subscription->encerramento_em, $items]));
            if ($data['confirm'] ?? false) {
                abort_unless(hash_equals($quote, $data['quote'] ?? ''), 409, 'A previsao mudou. Revise novamente antes de confirmar.');
                $subscription->recorrencia_inicio = $start;
                $subscription->save();
                foreach ($items as $item) {
                    $billing->generateCharge($subscription, $item['dueDate']);
                }
            }

            return response()->json(['start' => $start->toDateString(), 'items' => $items, 'quote' => $quote, 'total' => array_sum(array_column($items, 'value'))]);
        });
    }
}

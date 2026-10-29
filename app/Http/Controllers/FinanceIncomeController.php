<?php

namespace App\Http\Controllers;

use App\Models\Cobranca;
use App\Models\Receita;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinanceIncomeController extends Controller
{
    public function index(Request $request, \App\Services\FinanceIncomeService $income): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'source' => ['nullable', 'in:todos,direto,boleto,avulso,totalpass,wellhub'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $org = $request->user()->organizacoes()->firstOrFail();
        $start = Carbon::createFromFormat('!Y-m', $data['month']);
        $end = $start->copy()->endOfMonth();
        $source = $data['source'] ?? 'todos';
        $rows = $income->entries($org->id, $start, $end)
            ->filter(fn ($row) => $source === 'todos' || $row['source'] === $source)
            ->map(fn ($row) => [...$row, 'id' => $row['entryId'], 'title' => $row['student']])->values();
        $page = (int) ($data['page'] ?? 1);

        return response()->json(['entries' => $rows->forPage($page, 20)->values(),
            'totalValue' => round((float) $rows->sum('value'), 2),
            'pagination' => ['page' => $page, 'lastPage' => max(1, (int) ceil($rows->count() / 20)), 'total' => $rows->count()]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:180'],
            'value' => ['required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'method' => ['required', 'in:PIX,Dinheiro,Transferência,Cartão,Boleto'],
            'requestId' => ['required', 'uuid'],
        ]);
        $org = $request->user()->organizacoes()->firstOrFail();
        $entry = Receita::firstOrCreate(['request_id' => $data['requestId']], [
            'organizacao_id' => $org->id, 'descricao' => $data['description'], 'valor' => $data['value'],
            'competencia' => $data['date'], 'origem' => 'avulso', 'metodo' => $data['method'],
        ]);
        abort_unless($entry->organizacao_id === $org->id, 404);

        return response()->json(['month' => $entry->competencia->format('Y-m')], $entry->wasRecentlyCreated ? 201 : 200);
    }
}

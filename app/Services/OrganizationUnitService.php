<?php

namespace App\Services;

use App\Models\Organizacao;
use App\Models\Unidade;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationUnitService
{
    public function defaultFor(int $organizationId): Unidade
    {
        return DB::transaction(function () use ($organizationId) {
            Organizacao::whereKey($organizationId)->lockForUpdate()->firstOrFail();
            $unit = Unidade::where('organizacao_id', $organizationId)->where('padrao', true)->where('ativa', true)->first();
            if ($unit) return $unit;
            $unit = Unidade::where('organizacao_id', $organizationId)->where('ativa', true)->orderBy('id')->first()
                ?? Unidade::firstOrCreate(['organizacao_id' => $organizationId, 'nome' => 'Unidade principal'], ['ativa' => true]);
            Unidade::where('organizacao_id', $organizationId)->update(['padrao' => false]);
            $unit->update(['ativa' => true, 'padrao' => true]);
            return $unit;
        });
    }

    public function resolve(int $organizationId, array $data): Unidade
    {
        Organizacao::whereKey($organizationId)->lockForUpdate()->firstOrFail();
        if (! empty($data['unit_id'])) {
            $unit = Unidade::where('organizacao_id', $organizationId)->where('ativa', true)->find($data['unit_id']);
        } elseif (filled($data['unidade'] ?? null)) {
            // Accept existing clients that submit the unit name, but never create arbitrary units.
            $unit = Unidade::where('organizacao_id', $organizationId)->where('ativa', true)->where('nome', $data['unidade'])->first();
        } else {
            return $this->defaultFor($organizationId);
        }
        if (! $unit) throw ValidationException::withMessages(['unit_id' => 'Selecione uma unidade ativa do seu negócio.']);
        return $unit;
    }
}

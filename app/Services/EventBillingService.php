<?php

namespace App\Services;

use App\Models\Cobranca;
use App\Models\Event;
use App\Models\EventRegistration;
use Illuminate\Http\Request;

class EventBillingService
{
    public function create(Event $event, EventRegistration $registration): ?Cobranca
    {
        if (! $event->is_paid) {
            return null;
        }
        $charge = Cobranca::firstOrCreate(['event_registration_id' => $registration->id], [
            'organizacao_id' => $event->organizacao_id, 'aluno_id' => $registration->aluno_id, 'assinatura_id' => null, 'origem' => 'evento', 'descricao' => 'Evento: '.$event->name, 'competencia' => now()->format('m/Y'), 'vencimento' => today()->toDateString(), 'valor' => $event->price, 'status' => 'pendente', 'forma_pagamento' => 'PIX',
        ]);
        if ($charge->wasRecentlyCreated) {
            $charge->eventos()->create(['tipo' => 'cobranca_gerada', 'descricao' => 'Inscrição no evento '.$event->name, 'ocorrido_em' => now()]);
        }

        return $charge;
    }

    public function authorizePayment(Request $request, Cobranca $charge): void
    {
        if ($charge->event_registration_id) {
            EventAccess::staff($request, $charge->eventRegistration->event, true);
        }
    }

    public function cancel(Event $event): void
    {
        foreach (Cobranca::whereIn('event_registration_id', $event->registrations()->select('id'))->lockForUpdate()->get() as $charge) {
            if (in_array($charge->status, ['pendente', 'atrasado'])) {
                $charge->update(['status' => 'cancelado']);
                $charge->eventos()->create(['tipo' => 'evento_cancelado', 'descricao' => 'Cobrança cancelada junto com o evento.', 'ocorrido_em' => now()]);
            } elseif ($charge->status === 'pago' && ! $charge->refund_review_required) {
                $charge->update(['refund_review_required' => true]);
                $charge->eventos()->create(['tipo' => 'reembolso_a_tratar', 'descricao' => 'Evento cancelado. Conferir devolução com o participante; recebimento preservado.', 'ocorrido_em' => now()]);
            }
        }
    }

    public function receive(Request $request, Event $event, EventRegistration $registration)
    {
        EventAccess::staff($request, $event, true);
        abort_unless($registration->event_id === $event->id, 404);
        $data = $request->validate(['method' => 'required|in:PIX,Crédito,Débito,Dinheiro,Transferência,Boleto', 'paid_at' => 'required|date|before_or_equal:today']);
        $charge = $registration->charge()->firstOrFail();

        return app(BillingService::class)->registerPayment($charge, method: $data['method'], paymentDate: $data['paid_at']);
    }
}

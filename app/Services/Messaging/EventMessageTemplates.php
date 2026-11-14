<?php

namespace App\Services\Messaging;

class EventMessageTemplates
{
    public static function workout(string $name, string $workout): string
    {
        return 'Olá, '.$name.'! Seu treino '.$workout.' está disponível. Acesse a área do aluno para ver a ficha.';
    }

    public static function appointment(string $name, string $title, string $date, string $time): string
    {
        return 'Olá, '.$name.'! Seu agendamento para '.$title.' está confirmado para '.$date.' às '.$time.'.';
    }

    public static function billing(string $type, string $name, string $amount, string $dueDate): string
    {
        $ending = match ($type) {
            'vencimento_proximo' => 'vence em dois dias, em '.$dueDate.'.',
            'vencimento_hoje' => 'vence hoje.',
            'cobranca_atrasada' => 'está em aberto. Se você já pagou, pode desconsiderar este aviso.',
            default => throw new \InvalidArgumentException('Tipo financeiro inválido.'),
        };

        return 'Olá, '.$name.'! Sua mensalidade de '.$amount.' '.$ending;
    }

    public static function samples(?string $welcome = null): array
    {
        $date = now()->timezone('America/Bahia')->addDays(2)->format('d/m/Y');

        return [
            'boas_vindas' => strtr($welcome ?: MessagingService::defaultWelcomeMessageBody(), [
                '{aluno}' => 'Wendel', '{studio}' => 'WNFit', '{plano}' => 'Mensal',
                '{valor}' => 'R$ 150,00', '{vencimento}' => $date, '{link_pagamento}' => '(exemplo sem cobrança)',
            ]),
            'treino_atualizado' => self::workout('Wendel', 'Força e mobilidade'),
            'vencimento_proximo' => self::billing('vencimento_proximo', 'Wendel', 'R$ 150,00', $date),
            'vencimento_hoje' => self::billing('vencimento_hoje', 'Wendel', 'R$ 150,00', $date),
            'cobranca_atrasada' => self::billing('cobranca_atrasada', 'Wendel', 'R$ 150,00', $date),
            'agendamento_confirmado' => self::appointment('Wendel', 'Pilates', $date, '08:00'),
        ];
    }
}

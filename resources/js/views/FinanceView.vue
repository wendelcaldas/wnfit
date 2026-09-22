<template>
    <AppShell eyebrow="Gestão do estúdio" title="Financeiro" description="Acompanhe o faturamento e os pagamentos dos alunos por mês.">
        <FinancialResults />
    </AppShell>
</template>

<script setup>
import AppShell from '../components/AppShell.vue';
import FinancialResults from './FinancialResults.vue';
</script>

<template>
    <AppShell visual-refresh eyebrow="Financeiro" title="Financeiro" description="Seu estúdio em números. Acompanhe entradas e mensalidades.">
        <FinancialResults />
    </AppShell>
</template>

<script setup>
import AppShell from '../components/AppShell.vue';
import FinancialResults from './FinancialResults.vue';
</script>

<style src="../../css/finance.css"></style>

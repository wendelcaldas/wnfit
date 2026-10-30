# Revisão de inputs — 30/10/2026

Máscaras ajudam na digitação; regras no servidor continuam obrigatórias. O sistema
usa uma implementação compartilhada, sem dependências adicionais, com testes de
colagem, exclusão de separadores, cursor, valores incompletos e sincronização com Vue.

| Campo | Interface | Servidor |
| --- | --- | --- |
| Telefone | DDD e número, máscara dinâmica de fixo/celular, suporte a +55, sem letras | Regra brasileira compartilhada; rejeita letras, DDD/formato e tamanho inválidos |
| CPF | 000.000.000-00, 11 dígitos | Valida o formato, com ou sem pontuação; não consulta titularidade nem implementa dígitos verificadores |
| CEP | 00000-000, 8 dígitos | Exige 8 dígitos, com hífen opcional |
| UF | Duas letras maiúsculas | Lista das 27 UFs |
| Nascimento | Controle de data, limitado ao dia atual | Rejeita datas futuras |
| Medidas | Dígitos e um separador decimal, até duas casas | Faixas e precisão existentes das avaliações; referências de peso/altura alinhadas a essas faixas |
| Valores financeiros | Controles numéricos com passo de centavos, mínimo e máximo existentes | Regras financeiras existentes; valor mensal limitado ao armazenamento e sem negativos |
| Quantidades e durações de treino | Controles numéricos com mínimo/máximo existentes | Limites de séries, descanso, frequência e duração existentes |
| Nomes, e-mails e endereço | Limites de tamanho correspondentes ao servidor | Regras de tamanho e e-mail existentes |
| Usuários de acesso | Padrão e limites existentes | Regras existentes de formato e unicidade |

Aplicado a equipe, cadastro inicial do ambiente, alunos, contatos de emergência,
eventos (público e equipe), cobrança manual, agendamento público e avaliações.
Os componentes renderizados com `h()` recebem explicitamente máscara e limites;
limites em um componente não devem terminar apenas no elemento `label`.

Texto livre continua livre: nomes aceitam acentos; endereço e número de imóvel
podem conter letras; carga prescrita e repetições podem incluir unidades e faixas
("20 kg", "8–12"). Senhas mantêm a política existente, sem máscara de conteúdo
ou truncamento silencioso. Não há campo de CNPJ neste fluxo para receber máscara.

Dados já gravados não são reescritos automaticamente. A regra é aplicada ao salvar.
Não há migration nova nesta alteração.

Validação automatizada: `npm run test:inputs`, `php artisan test --compact` e
`npm run build`. Conferência adicional em formulário Vue no navegador com
digitação gradual, colagem e bloqueio de envio incompleto, usando dados fictícios.

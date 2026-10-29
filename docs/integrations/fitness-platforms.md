# Entradas financeiras, TotalPass e Wellhub

Verificação das fontes oficiais: 29/10/2026.

## Implementado

A lateral usa GET /api/finance/income, restrito à organização autenticada, por data de recebimento. Exibe pagamentos de cobranças e receitas avulsas. Origens: direto, boleto, avulso, totalpass, wellhub. A classificação como boleto depende do método efetivamente registrado, não apenas da recorrência. Boletos não são emitidos nem baixados automaticamente por esta implementação.

POST /api/finance/income registra somente receita avulsa já recebida: descrição, valor positivo, data não futura e método. requestId UUID torna o reenvio da mesma operação idempotente. Não há envio de mensagens ou cobrança ao aluno. O indicador Recebido inclui essas receitas; Previsto e Em aberto continuam relacionados às cobranças.

A tabela receitas foi preparada com origem e external_id. Uma futura importação de repasses deve usar a unicidade por organização, origem e identificador externo, além de transação. Não duplicar um repasse como receita e pagamento de cobrança simultaneamente.

Ícones: Lucide (Barcode, HandCoins, ScanLine, TicketCheck, Wallet). São símbolos funcionais, não logotipos oficiais. A plataforma é identificada também por texto e não apenas cor. Os repasses fictícios existentes continuam identificados como Demo.

## TotalPass: requisitos confirmados

- Portal de desenvolvimento com Access Control API e Booking API.
- O onboarding descreve partner_api_key e place_api_key, unidade de testes, testes simulados e envio de logo e guia de configuração do sistema antes do lançamento.
- A documentação de autenticação consultada informa uma chave do parceiro fornecida pelo suporte e uma chave da unidade obtida pelo estabelecimento; o endpoint retorna JWT renovável a cada 24 horas. Confirmar as credenciais e autenticação específicas da Access Control API durante a habilitação; não presumir que os endpoints de booking ou partner apps tenham contrato idêntico.
- No controle de acesso, Validate Use apenas verifica elegibilidade. Token Use valida e consome o token/check-in. Não tratar uma verificação de elegibilidade como visita confirmada.
- Não foi confirmado nas páginas consultadas um endpoint de liquidação financeira. Definir com a TotalPass como obter o extrato de repasses e a tabela contratual de remuneração antes de automatizar valores recebidos.

Fontes:
- https://dev.totalpass.com/docs/getting-started
- https://dev.totalpass.com/reference/overview
- https://dev.totalpass.com/reference/post_partner-auth-1

## Wellhub: requisitos confirmados

- O portal de desenvolvedores distingue integrações de Fitness Partners/CMS (incluindo Access Control API e Booking API) das APIs de RH/eligibilidade de empresas. Utilizar a integração de parceiros fitness.
- O parceiro solicita integração pelo Partner Portal, na área Integrations, selecionando unidade e sistema. A habilitação da WNFit e o acesso técnico precisam ser alinhados com a Wellhub.
- O aluno inicia o check-in no aplicativo e o parceiro o valida. A documentação de parceiros consultada informa validação em até 20 minutos para o fluxo de controle de acesso.
- Ainda é necessário obter o contrato técnico autorizado: autenticação, identificador da unidade, eventos/webhooks disponíveis, validação, cancelamento, ambiente de homologação e regras financeiras. Não há fluxo de vinculação ativo no aplicativo.

Fontes:
- https://developers.gympass.com/
- https://wellhub.com/en-us/partner-academy/cms-integration/
- https://wellhub.com/en-us/partner-academy/how-check-ins-work/
- https://wellhub.com/en-us/partners/integrate-with-wellhub/

## Próxima implementação após habilitação

1. Conexão por organização, provedor e unidade, com credenciais criptografadas no servidor, sem retorno de segredos ao navegador. Chave da WNFit separada da chave de cada unidade.
2. Adaptadores específicos para cada provedor com teste de conexão, timeout, renovação de token, filas, tratamento de limites e erros. Usar somente endpoints contratados e documentados; não receber URLs arbitrárias do usuário.
3. Registro de check-ins separado das receitas: external_id único por provedor/unidade, aluno vinculado quando identificado, data da visita, estado solicitado/validado/cancelado/expirado e estado financeiro ainda não conciliado/conciliado. Validar assinatura/autenticação de callbacks conforme o contrato, com deduplicação e processamento fora da requisição.
4. Visita validada conta presença e pode gerar estimativa a receber. Não deve incrementar Recebido. A lateral atual é de dinheiro recebido; eventos de presença ficam na consulta de check-ins. Após conciliação financeira, mostrar o repasse com a origem e vínculo aos check-ins, sem contabilizar o mesmo dinheiro duas vezes.
5. Remuneração conforme contrato, limites e extrato real, não multiplicação fixa e indiscriminada por check-in. Repasses agregados devem ser exibidos como repasse da plataforma, sem inventar aluno ou data individual de pagamento.
6. Homologar eventos duplicados, repetição de requisições, cancelamentos, unidades de organizações diferentes, valores ausentes, falhas temporárias e divergência entre estimativa e liquidação. Só então habilitar vinculação em produção.

Pendências externas: credenciamento/habilitação da WNFit, credenciais de homologação, unidades do estúdio, contratos técnicos e forma de conciliação de repasses. Nenhuma conta externa foi criada ou contatada neste trabalho.

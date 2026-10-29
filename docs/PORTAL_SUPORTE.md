# Central de ajuda WNFit

Portal público de tutoriais, sem login e sem consultas ao banco no fluxo de conteúdo. A versão inicial contém 20 guias escritos, organizados em sete coleções. Não há vídeos fictícios, canal de atendimento inventado nem integração com os módulos ainda indisponíveis.

## Avaliação local

- Início: `/ajuda`
- Coleção: `/ajuda/modulos/agenda`
- Artigo: `/ajuda/artigos/links-aula-experimental`
- Busca: `/ajuda?q=avaliação`

O conteúdo é renderizado no servidor e funciona sem JavaScript. O CSS é um ponto de entrada próprio do Vite; a central não carrega a aplicação Vue, o sino ou a conexão WebSocket.

## Conteúdo

Edite `resources/content/help-center.json`. Cada artigo tem slug, coleção, título, resumo, tempo estimado, data de revisão e seções com parágrafos ou passos. Os campos `onboarding` e `popular` selecionam os guias da home; a interface os apresenta como trilha inicial e atalhos úteis, sem alegar métricas de acesso.

Antes de publicar um novo guia, confira os nomes das ações e o comportamento no sistema. Evite prometer integração, automação ou permissão que ainda não existem. Os testes verificam referências, renderização de todos os artigos, busca, escape de conteúdo e isolamento do domínio.

### Referências e capturas de tela

No texto dos parágrafos e passos, use `[[slug-do-artigo|texto do link]]` para referenciar outro tutorial. A renderização só transforma referências a artigos existentes; rótulos e textos continuam escapados, sem permitir HTML arbitrário. O teste de conteúdo identifica referências quebradas.

Uma seção pode conter `figures`, com `src`, `alt`, `caption`, `width` e `height`. As imagens ficam em `public/images/support/`, abrem em tamanho maior ao clicar e são carregadas sob demanda. As legendas identificam os dados de demonstração.

O script `tests/browser/capture-help-screenshots.cjs` captura a interface local com Playwright e respostas de API fictícias. Ele não consulta cadastros de clientes nem submete formulários. Os destaques são inseridos somente no navegador de captura. Após mudanças na interface, execute-o com Laravel/Vite locais ativos para atualizar as imagens e suas dimensões. Não substitua capturas por telas inventadas.

Aulas em vídeo dependem de gravações reais. Quando os vídeos existirem, incluir reprodução acessível, título e conteúdo textual equivalente nos artigos correspondentes.

## Domínio e publicação

Domínio previsto: `suporte.wnfit.com.br`. A configuração `SUPPORT_HOST` tem esse valor por padrão; `SUPPORT_APPLICATION_URL` define os links para o sistema, com padrão `https://wnfit.com.br`.

Após aprovar esta versão, é possível usar o mesmo serviço web existente:

1. Publicar o código e o novo build CSS.
2. Adicionar `suporte.wnfit.com.br` aos domínios do serviço web no Railway, porta 8080.
3. Criar o registro DNS solicitado pelo Railway e aguardar o certificado HTTPS.
4. Conferir início, busca, coleção e artigo pelo domínio público.

As rotas do subdomínio são registradas antes das rotas do sistema. Endereços que não pertencem à central retornam 404 nesse domínio. O sistema principal continua com suas rotas atuais; o prefixo `/ajuda` também fica disponível nele. Não há migration ou alteração dos dados dos clientes.

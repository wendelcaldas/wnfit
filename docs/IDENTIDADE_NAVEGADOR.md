# Identidade do WNFit no navegador

O documento principal declara título WNFit, favicon SVG/PNG, ícone Apple e manifesto do atalho. Os arquivos ficam em `public/icons`, `public/favicon.ico` e `public/site.webmanifest` e precisam ser incluídos na publicação junto com a aplicação.

No ambiente de produção, configure `APP_NAME=WNFit` e atualize os caches após o deploy:

```sh
php artisan config:cache
php artisan view:cache
```

O título da aba é definido explicitamente como WNFit para não herdar Laravel de uma configuração antiga. O APP_NAME também identifica a aplicação em outros serviços, como remetentes de e-mail.

Os ícones podem ser reconstruídos com `php scripts/build-brand-icons.php` (PHP GD). O manifesto abre o portal do aluno e fornece identidade para o atalho; não implementa funcionamento offline.

Se a aba continuar com o ícone anterior após o deploy, recarregue sem cache ou abra uma nova aba. Atalhos já instalados podem precisar ser recriados.

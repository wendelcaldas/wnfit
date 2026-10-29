<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HelpCenterTest extends TestCase
{
    public function test_public_home_and_collections_work_without_login_or_database_queries(): void
    {
        DB::enableQueryLog();
        $this->get('/ajuda')->assertOk()->assertSee('Como podemos')->assertSee('20 guias práticos')->assertSee('/ajuda/artigos/cadastrar-aluno');
        $this->get('/ajuda/modulos/agenda')->assertOk()->assertSee('Como criar um link de aula experimental');
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_dedicated_host_uses_its_own_urls_and_does_not_expose_the_app_routes(): void
    {
        $this->get('https://suporte.wnfit.com.br/')->assertOk()->assertSee('Central de ajuda')->assertSee('href="/modulos/agenda"', false)->assertDontSee('href="/ajuda/', false);
        $this->get('https://suporte.wnfit.com.br/artigos/configurar-horarios')->assertOk()->assertSee('Como definir o horário de funcionamento');
        $this->get('https://suporte.wnfit.com.br/api/notifications')->assertNotFound();
        $this->get('https://suporte.wnfit.com.br/artigos/inexistente')->assertNotFound();
    }

    public function test_all_published_articles_render_and_content_references_exist(): void
    {
        $content = json_decode(file_get_contents(resource_path('content/help-center.json')), true);
        $slugs = array_column($content['articles'], 'slug');
        $this->assertCount(count(array_unique($slugs)), $slugs);
        foreach ($content['articles'] as $article) {
            $this->assertContains($article['collection'], array_column($content['collections'], 'slug'));
            $this->get('/ajuda/artigos/'.$article['slug'])->assertOk()->assertSee($article['title']);
        }
        foreach ([...$content['onboarding'], ...$content['popular']] as $slug) {
            $this->assertContains($slug, $slugs);
        }
        $this->get('/ajuda/modulos/inexistente')->assertNotFound();
    }

    public function test_search_handles_accents_empty_results_and_untrusted_input(): void
    {
        $this->get('/ajuda?q=avaliacao')->assertOk()->assertSee('Como gerenciar seus três links de reserva');
        $this->get('/ajuda?q=xyzxyzxyz')->assertOk()->assertSee('Ainda não encontramos esse assunto.');
        $this->get('/ajuda?q='.urlencode('<script>alert(1)</script>'))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->getJson('/ajuda?q[]=agenda')->assertUnprocessable();
    }

    public function test_guide_references_resolve_and_illustrations_are_real_local_assets(): void
    {
        $content = json_decode(file_get_contents(resource_path('content/help-center.json')), true);
        $slugs = array_column($content['articles'], 'slug');
        foreach ($content['articles'] as $article) {
            preg_match_all('/\[\[([a-z0-9-]+)\|/', json_encode($article, JSON_UNESCAPED_UNICODE), $links);
            foreach ($links[1] as $target) {
                $this->assertContains($target, $slugs, 'Broken article reference in '.$article['slug']);
            }
            foreach ($article['sections'] as $section) {
                foreach ($section['figures'] ?? [] as $figure) {
                    $this->assertStringStartsWith('images/support/', $figure['src']);
                    $this->assertFileExists(public_path($figure['src']));
                    $this->assertNotEmpty($figure['alt']);
                    [$width, $height] = getimagesize(public_path($figure['src']));
                    $this->assertSame($width, $figure['width']);
                    $this->assertSame($height, $figure['height']);
                }
            }
        }
        $this->get('/ajuda/artigos/organizar-seu-negocio')->assertOk()->assertSee('href="/ajuda/artigos/configurar-planos"', false)->assertSee('configurar-planos.jpg')->assertDontSee('[[', false);
        $this->get('https://suporte.wnfit.com.br/artigos/cadastrar-aluno')->assertOk()->assertSee('href="/artigos/configurar-planos"', false)->assertSee('cadastro-matricula.jpg')->assertSee('dados de demonstração');
    }
}

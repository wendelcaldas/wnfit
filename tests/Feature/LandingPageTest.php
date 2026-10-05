<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingPageTest extends TestCase
{
    public function test_site_serves_public_content_and_registration_links_without_javascript(): void
    {
        $this->get('/site')
            ->assertOk()
            ->assertSee('Gestão que aproxima. Treino que move.')
            ->assertSee('Criar minha conta')
            ->assertSee('href="/cadastro"', false)
            ->assertSee('href="/aluno/entrar"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('href="'.route('site').'"', false)
            ->assertDontSee('id="app"', false);
    }

    public function test_login_and_student_entry_still_serve_the_application(): void
    {
        $this->get('/')->assertStatus(302)->assertRedirect('/entrar');
        $this->get('/entrar')->assertOk()->assertSee('id="app"', false);
        $this->get('/aluno/entrar')->assertOk()->assertSee('id="app"', false);
    }
}

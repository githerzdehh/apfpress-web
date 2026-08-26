<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_and_catalog_are_server_rendered_with_seo_content(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertOk()
            ->assertSee('Ideas that question')
            ->assertSee('application/ld+json', false)
            ->assertSee('"@context":"https://schema.org"', false)
            ->assertDontSee('__contextArgs', false)
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/books')->assertOk()->assertSee('The APF Press catalogue');
        $this->get('/books/resistance-and-empowerment-racialized-women-of-the-diaspora')
            ->assertOk()->assertSee('Resistance and Empowerment');
    }

    public function test_draft_legal_pages_are_not_publicly_exposed(): void
    {
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);

        $this->get('/policies/privacy')->assertNotFound();
    }

    public function test_local_csp_allows_vite_without_weakening_production(): void
    {
        $this->withoutVite();
        config([
            'app.env' => 'local',
            'apf.vite_dev_origin' => 'http://localhost:5174',
        ]);

        $localPolicy = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringContainsString('style-src', $localPolicy);
        $this->assertStringContainsString('http://localhost:5174', $localPolicy);
        $this->assertStringContainsString('ws://localhost:5174', $localPolicy);

        config(['app.env' => 'production']);

        $productionPolicy = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('localhost:5174', $productionPolicy);
        $this->assertStringContainsString("frame-ancestors 'self'", $productionPolicy);
    }
}

<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocalizationTest extends TestCase
{
    public function test_spanish_locale_and_validation_messages_are_available(): void
    {
        $this->assertSame('es', config('app.locale'));
        $this->assertSame('es', config('app.fallback_locale'));
        $this->assertSame('es_ES', config('app.faker_locale'));
        $this->assertSame('El campo correo es obligatorio.', __('validation.required', ['attribute' => 'correo']));
    }

    public function test_sample_fixture_uses_visible_spanish_synthetic_content(): void
    {
        $fixture = json_decode(
            file_get_contents(storage_path('app/fixtures/boe-sample.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $this->assertSame('synthetic fixture for local development', $fixture['source']);
        $this->assertStringContainsString('Ejemplo sintético', $fixture['documents'][0]['title']);
        $this->assertStringContainsString('Este aviso sintético', $fixture['documents'][0]['text']);
    }
}

<?php

namespace Tests\Unit;

use App\Services\Boe\HtmlContentExtractor;
use PHPUnit\Framework\TestCase;

final class HtmlContentExtractorTest extends TestCase
{
    public function test_it_prefers_configured_content_selector(): void
    {
        $mainText = str_repeat('Texto genérico que no debería ganar. ', 12);
        $documentText = str_repeat('Artículo 1. Contenido normativo principal con obligaciones sectoriales. ', 12);
        $html = <<<HTML
            <html>
                <body>
                    <main>{$mainText}</main>
                    <div id="textoxslt">{$documentText}</div>
                </body>
            </html>
        HTML;

        $text = (new HtmlContentExtractor)->extract($html, ['#textoxslt']);

        $this->assertStringContainsString('Contenido normativo principal', $text);
        $this->assertStringNotContainsString('Texto genérico', $text);
    }

    public function test_it_removes_non_content_regions(): void
    {
        $documentText = str_repeat('Artículo 1. Esta norma contiene medidas antifraude y obligaciones de seguimiento para la administración pública. ', 8);
        $html = <<<HTML
            <html>
                <body>
                    <header>Cabecera de navegación</header>
                    <main>{$documentText}</main>
                    <footer>Contacto Aviso legal Empleo en la sede</footer>
                </body>
            </html>
        HTML;

        $text = (new HtmlContentExtractor)->extract($html);

        $this->assertStringContainsString('medidas antifraude', $text);
        $this->assertStringNotContainsString('Cabecera de navegación', $text);
        $this->assertStringNotContainsString('Empleo en la sede', $text);
    }
}

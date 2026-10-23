<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Evidencia de T-19 · Artículo I de la constitución:
 * app/Domain no importa Laravel, ni otras capas del proyecto, ni librerías de terceros.
 */
class DomainIsPureTest extends TestCase
{
    private const FORBIDDEN = [
        'Illuminate\\',
        'App\\Application\\',
        'App\\Adapters\\',
        'App\\Http\\',
        'App\\Models\\',
        'Firebase\\',
    ];

    public function test_el_dominio_no_depende_de_nada(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../../app/Domain'));
        $checked = 0;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            foreach (self::FORBIDDEN as $namespace) {
                $this->assertStringNotContainsString(
                    $namespace,
                    $code,
                    $file->getFilename().' depende de '.$namespace.' y viola el artículo I',
                );
            }
            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'No se encontraron archivos de dominio');
    }
}

<?php

namespace Tests\Feature;

use App\Services\AI\Language\LanguageDetector;
use Tests\TestCase;

class LanguageDetectionTest extends TestCase
{
    public function test_language_detector_identifies_all_9_languages()
    {
        $detector = app(LanguageDetector::class);

        $testCases = [
            'Hello, I would like to buy some server equipment.' => 'en',
            'Bonjour, je voudrais acheter un produit s\'il vous plaît.' => 'fr',
            'Muito obrigado pelo seu produto e pelo preço excelente.' => 'pt',
            'مرحبا، أود شراء خادم جديد للحاسوب الخاص بي.' => 'ar',
            'Habari za asubuhi, ningependa kununua bidhaa hii leo.' => 'sw',
            'Muraho, nifuza kugura igicuruzwa cyanyu.' => 'rw',
            'Muchas gracias, hola, me gustaría comprar el procesador.' => 'es',
            'Guten Tag, ich möchte dieses Produkt kaufen.' => 'de',
            'Buongiorno, vorrei comprare questo server per la mia azienda.' => 'it',
        ];

        foreach ($testCases as $text => $expectedLang) {
            $result = $detector->detect($text);
            $this->assertEquals($expectedLang, $result['code'], "Failed for text: {$text}");
            $this->assertGreaterThan(0.5, $result['confidence']);
        }
    }
}

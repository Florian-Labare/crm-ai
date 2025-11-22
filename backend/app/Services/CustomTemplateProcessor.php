<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;

class CustomTemplateProcessor extends TemplateProcessor
{
    /**
     * Change les délimiteurs de macro pour supporter {{variable}} au lieu de ${variable}
     */
    public function __construct($documentTemplate)
    {
        parent::__construct($documentTemplate);

        // Changer les délimiteurs de macro via reflection car ce sont des propriétés statiques protected
        $reflection = new \ReflectionClass(TemplateProcessor::class);

        $openingCharsProperty = $reflection->getProperty('macroOpeningChars');
        $openingCharsProperty->setAccessible(true);
        $openingCharsProperty->setValue(null, '{{');

        $closingCharsProperty = $reflection->getProperty('macroClosingChars');
        $closingCharsProperty->setAccessible(true);
        $closingCharsProperty->setValue(null, '}}');
    }
}

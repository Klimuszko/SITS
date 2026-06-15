<?php

namespace App\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanityzacja HTML treści bazy wiedzy (§22, §30).
 * Oparta o ezyang/htmlpurifier – niezależna od wersji frameworka.
 */
class HtmlSanitizer
{
    protected HTMLPurifier $purifier;

    public function __construct()
    {
        $cachePath = config('sanitizer.cache_path', storage_path('app/purifier'));

        if (! is_dir($cachePath)) {
            @mkdir($cachePath, 0775, true);
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', config('sanitizer.allowed_html'));
        $config->set('CSS.AllowedProperties', config('sanitizer.allowed_css'));
        $config->set('Attr.AllowedClasses', config('sanitizer.allowed_classes'));
        $config->set('URI.AllowedSchemes', collect(config('sanitizer.allowed_schemes', ['http', 'https', 'mailto']))
            ->mapWithKeys(fn ($scheme) => [$scheme => true])->all());
        $config->set('HTML.TargetBlank', true);
        $config->set('AutoFormat.AutoParagraph', false);
        $config->set('AutoFormat.RemoveEmpty', true);
        $config->set('Cache.SerializerPath', $cachePath);

        $this->purifier = new HTMLPurifier($config);
    }

    public function clean(?string $html): string
    {
        return $this->purifier->purify((string) $html);
    }
}

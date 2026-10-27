<?php

namespace App\Support;

/**
 * Keeps competition copy safe to render while preserving the small set of formatting
 * supported by the admin editor. Also preserves line breaks in older plain-text records.
 */
class CompetitionRichText
{
    private static ?\HTMLPurifier $purifier = null;

    public static function sanitize(?string $content): string
    {
        $content = trim((string) $content);
        if ($content === '') {
            return '';
        }

        // Existing competitions were stored as plain text. Escape that text before adding
        // breaks, so historic rules retain their line layout and cannot become markup.
        if (!preg_match('/<\/?[a-z][^>]*>/i', $content)) {
            $content = nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false);
        }

        if (!self::$purifier) {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,strong,b,em,i,u,s,ul,ol,li,a[href|title|target],h2,h3,h4,blockquote,div[style],span[style],font[color|size|face]');
            $config->set('CSS.AllowedProperties', [
                'background-color', 'color', 'font-family', 'font-size', 'font-style',
                'font-weight', 'text-align', 'text-decoration',
            ]);
            $config->set('Attr.AllowedFrameTargets', ['_blank']);
            self::$purifier = new \HTMLPurifier($config);
        }

        return self::$purifier->purify($content);
    }
}

<?php
declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class View
{
    /**
     * Render a content-only template inside a full-page layout.
     *
     * Template/layout names and HTML slots are server-controlled. Content templates
     * must omit document tags and the main landmark, and escape their own data.
     * Layouts escape display values; headAssets and scripts are trusted HTML (for
     * example, rendered asset partials), never raw request or database values.
     * Template data is deliberately separate from layout context. Optional ui data
     * comes from AppUiContextService after middleware; display values are plain text.
     *
     * @param array{
     *     layout?: string,
     *     documentTitle?: string,
     *     pageTitle?: string,
     *     bodyClass?: string,
     *     headAssets?: string,
     *     ui?: array,
     *     scripts?: string
     * } $pageContext
     */
    public static function page(string $template, array $data = [], array $pageContext = []): string
    {
        $content = self::render($template, $data);

        return self::render('layouts/' . ($pageContext['layout'] ?? 'app'), [
            'content' => $content,
            'ui' => $pageContext['ui'] ?? null,
            'documentTitle' => $pageContext['documentTitle'] ?? 'ปพ.5',
            'pageTitle' => $pageContext['pageTitle'] ?? '',
            'bodyClass' => $pageContext['bodyClass'] ?? '',
            'headAssets' => $pageContext['headAssets'] ?? '',
            'scripts' => $pageContext['scripts'] ?? '',
        ]);
    }

    /** Context-free safe full-page errors. Never accepts request paths or exception messages. */
    public static function error(int $status): string
    {
        $title = match ($status) {
            403 => 'ไม่มีสิทธิ์เข้าใช้งาน',
            404 => 'ไม่พบหน้า',
            default => throw new RuntimeException('Unsupported error page'),
        };
        return self::page('errors/' . $status, [], [
            'layout' => 'error', 'documentTitle' => $title . ' — ปพ.5',
            'pageTitle' => $title, 'bodyClass' => 'pp5-error',
        ]);
    }

    public static function render(string $template, array $data = []): string
    {
        $path = dirname(__DIR__, 2) . '/views/' . $template . '.php';

        if (!is_file($path)) {
            throw new RuntimeException("View not found: {$template}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $path;

            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}

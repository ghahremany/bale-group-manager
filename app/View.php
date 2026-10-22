<?php
declare(strict_types=1);

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layout'): void
    {
        $file = BGM_ROOT . '/views/' . $view . '.php';
        if (!is_file($file)) {
            http_response_code(500);
            echo 'view not found: ' . h($view);
            return;
        }
        $data['view'] = $view;
        extract($data, EXTR_SKIP);
        if ($layout === null) {
            require $file;
            return;
        }
        $layoutFile = BGM_ROOT . '/views/' . $layout . '.php';
        ob_start();
        require $file;
        $content = ob_get_clean();
        require $layoutFile;
    }

    public static function partial(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require BGM_ROOT . '/views/' . $view . '.php';
    }
}

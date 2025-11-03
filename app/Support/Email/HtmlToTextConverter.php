<?php

namespace App\Support\Email;

use Soundasleep\Html2Text;

final class HtmlToTextConverter
{
    public function convert(string $html): string
    {
        return Html2Text::convert($html, [
            'ignore_errors' => true,
            'drop_links' => true
        ]);
    }
}

<?php

namespace Tests\Unit;

use CharlesStOlive\MsGraphFilament\Support\DraftBody;
use PHPUnit\Framework\TestCase;

/**
 * Découpage du corps d'un brouillon Outlook (package filament-msgraph-email) : le message
 * rédigé d'un côté, l'historique de la conversation cité de l'autre.
 */
class DraftBodyTest extends TestCase
{
    public function test_outlook_web_reply(): void
    {
        $html = <<<'HTML'
            <html><head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"><style type="text/css" style="display:none;"> P {margin-top:0;margin-bottom:0;} </style>
            </head>
            <body dir="ltr">
            <div class="elementToProof" style="font-family: Aptos, sans-serif;">Bonjour Paul,</div>
            <div id="Signature"><p>Charles</p></div>
            <div id="appendonsend"></div>
            <hr style="display:inline-block;width:98%" tabindex="-1">
            <div id="divRplyFwdMsg" dir="ltr"><font face="Calibri, sans-serif" style="font-size:11pt" color="#000000"><b>De :</b> Paul &lt;paul@example.com&gt;<br><b>Envoyé :</b> lundi 22 septembre 2025 10:12</font>
            <div>&nbsp;</div></div>
            <div><p>Hi Charles</p></div>
            </body>
            </html>
            HTML;

        $body = DraftBody::fromHtml($html);

        $this->assertStringEndsWith('<body dir="ltr">', $body->before);
        $this->assertStringContainsString('Bonjour Paul', $body->message);
        $this->assertStringContainsString('<div id="Signature">', $body->message, 'La signature fait partie du message.');
        $this->assertStringNotContainsString('Hi Charles', $body->message);
        $this->assertStringStartsWith('<div id="appendonsend">', $body->after);
        $this->assertSame($html, $body->withMessage($body->message));
    }

    public function test_outlook_desktop_reply(): void
    {
        $html = "<html xmlns:o=\"urn:schemas-microsoft-com:office:office\"><head><style>p.MsoNormal{margin:0cm;}</style></head>"
            ."<body lang=FR link=\"#0563C1\"><div class=WordSection1><p class=MsoNormal>Bonjour Paul,<o:p></o:p></p>"
            ."<p class=MsoNormal><o:p>&nbsp;</o:p></p><div>"
            ."<div style='border:none;border-top:solid #E1E1E1 1.0pt;padding:3.0pt 0cm 0cm 0cm'><p class=MsoNormal><b>De&nbsp;:</b> Paul</p></div>"
            ."</div><p class=MsoNormal>Hi Charles</p></div></body></html>";

        $body = DraftBody::fromHtml($html);

        $this->assertStringContainsString('Bonjour Paul', $body->message);
        $this->assertStringNotContainsString('Hi Charles', $body->message);
        $this->assertStringStartsWith("<div style='border:none;border-top", $body->after);
        $this->assertSame($html, $body->withMessage($body->message));
    }

    public function test_new_message_without_history(): void
    {
        $html = "<html><head></head><body>\n<p>De : notre côté, tout est prêt.</p>\n<p>From: Charles</p>\n</body></html>";

        $body = DraftBody::fromHtml($html);

        $this->assertSame("\n<p>De : notre côté, tout est prêt.</p>\n<p>From: Charles</p>\n", $body->message, 'Le texte « De : » ou « From: » ne coupe pas le message.');
        $this->assertSame('</body></html>', $body->after);
        $this->assertSame('<html><head></head><body><p>Hello</p></body></html>', DraftBody::fromHtml('<html><head></head><body><p>Bonjour</p></body></html>')->withMessage('<p>Hello</p>'));
    }

    public function test_plain_text_reply(): void
    {
        $body = DraftBody::fromHtml("Bonjour\n\n-----Original Message-----\nFrom: Paul");

        $this->assertSame('', $body->before);
        $this->assertSame("Bonjour\n\n", $body->message);
        $this->assertSame("-----Original Message-----\nFrom: Paul", $body->after);
    }

    public function test_a_reply_with_nothing_written_is_empty(): void
    {
        $this->assertTrue(DraftBody::fromHtml('<html><body><div><br></div><div>&nbsp;</div><div id="appendonsend"></div><div id="divRplyFwdMsg">De : Paul</div></body></html>')->messageIsEmpty());
        $this->assertFalse(DraftBody::fromHtml('<html><body><p>Ok</p></body></html>')->messageIsEmpty());
    }
}

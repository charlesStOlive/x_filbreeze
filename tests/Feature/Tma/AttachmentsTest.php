<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\CreateTask;
use CharlesStOlive\FilamentTma\Filament\Resources\Tasks\Pages\ViewTask;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Models\Project;
use CharlesStOlive\FilamentTma\Models\ProjectTask;
use CharlesStOlive\FilamentTma\Support\Attachments;
use CharlesStOlive\FilamentTma\Sync\Mirror;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\TaskWorkflow;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/**
 * Les pièces jointes : PDF, documents Office (Word, Excel, PowerPoint) et OpenDocument, Markdown, images, texte. Le
 * type se contrôle sur le contenu, l'extension doit aller avec, un document Office ne contient ni macro ni contenu
 * actif ; tout se télécharge sans être interprété.
 */
class AttachmentsTest extends TmaTestCase
{
    private const OOXML = [
        'docx' => ['word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'],
        'xlsx' => ['xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml'],
        'pptx' => ['ppt/presentation.xml', 'application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml'],
    ];

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/tma-attachments-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    /**
     * Un document Office minimal de la sorte voulue ; `$mainType` le fait passer pour une autre (un classeur à macros),
     * `$extra` y ajoute des parties (une macro, un contrôle ActiveX, une relation externe…).
     *
     * @param  array<string, string>  $extra
     */
    private function office(string $name, ?string $mainType = null, array $extra = []): string
    {
        $kind = pathinfo($name, PATHINFO_EXTENSION);
        [$part, $type] = self::OOXML[$kind];
        $path = "{$this->dir}/{$name}";
        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/'.$part.'" ContentType="'.($mainType ?? $type).'"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="'.$part.'"/></Relationships>');
        $zip->addFromString($part, '<?xml version="1.0" encoding="UTF-8"?><root/>');

        foreach ($extra as $entry => $content) {
            $zip->addFromString($entry, $content);
        }

        $zip->close();

        return $path;
    }

    private function file(string $name, string $content): string
    {
        file_put_contents($path = "{$this->dir}/{$name}", $content);

        return $path;
    }

    private function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
    }

    private function upload(string $path): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(basename($path), (string) file_get_contents($path));
    }

    public function test_office_documents_pdf_and_markdown_pass_and_active_or_disguised_files_do_not(): void
    {
        $accepted = [
            $this->office('budget.xlsx') => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $this->office('lancement.pptx') => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            $this->office('cahier.docx') => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $this->file('devis.pdf', $this->pdf()) => 'application/pdf',
            $this->file('notes.md', "# Compte rendu\n\n- point 1\n- **gras**\n") => Attachments::MARKDOWN,
            // Une balise suffit pour que le contenu se détecte en HTML : c'est toujours du Markdown.
            $this->file('liens.md', "# Liens\n\n<a href=\"https://exemple.test\">la maquette</a>\n<table><tr><td>a</td></tr></table>\n") => Attachments::MARKDOWN,
            $this->file('export.csv', "nom;montant\nAda;12\n") => null,
            // Une photo JPEG nommée .png : une image reste une image.
            $this->file('photo.png', UploadedFile::fake()->image('photo.jpg', 4, 4)->getContent()) => 'image/jpeg',
        ];

        foreach ($accepted as $path => $type) {
            $this->assertNull(Attachments::problem($path, basename($path)), basename($path));

            if ($type !== null) {
                $this->assertSame($type, Attachments::typeOf((string) mime_content_type($path), basename($path)), basename($path));
            }
        }

        $refused = [
            // Des macros : un classeur à macros renommé, une macro glissée dans un .pptx, une présentation « à macros ».
            $this->office('macro.xlsx', 'application/vnd.ms-excel.sheet.macroEnabled.main+xml', ['xl/vbaProject.bin' => str_repeat("\xD0\xCF\x11\xE0", 64)]) => 'macros',
            $this->office('vba.pptx', null, ['ppt/vbaProject.bin' => 'vba']) => 'macros',
            $this->office('macros.pptx', 'application/vnd.ms-powerpoint.presentation.macroEnabled.main+xml') => 'macros',
            // Du contenu actif : un contrôle ActiveX, un objet OLE incorporé, un modèle chargé à distance.
            $this->office('activex.xlsx', null, ['xl/activeX/activeX1.xml' => '<ax/>']) => 'macros',
            $this->office('ole.docx', null, ['word/embeddings/oleObject1.bin' => 'ole']) => 'macros',
            $this->office('modele.docx', null, ['word/_rels/settings.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate" Target="https://exemple.test/modele.dotm" TargetMode="External"/></Relationships>']) => 'macros',
            // Un document qui n'est pas ce que dit son extension.
            $this->office('classeur.pptx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml') => 'extension',
            // Des types qui ne sont pas dans la liste, ou déguisés.
            $this->file('faux.pdf', "Ceci n'est pas un PDF\n") => 'type',
            $this->file('page.html', '<html><body><script>alert(1)</script></body></html>') => 'type',
            $this->file('outil.exe', "MZ\x90\x00\x03".str_repeat("\x00", 200)) => 'type',
            $this->file('image.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>') => 'type',
            $this->file('script.md', "\x7FELF".str_repeat("\x00", 64)) => 'text',
        ];

        foreach ($refused as $path => $why) {
            $problem = Attachments::problem($path, basename($path));
            $this->assertNotNull($problem, basename($path));
            $this->assertStringContainsString(match ($why) {
                'macros' => 'macros ou du contenu actif',
                'extension' => 'n’est pas ce que dit son extension',
                'text' => 'n’est pas du texte',
                default => 'n’est pas accepté',
            }, $problem, basename($path));
        }

        // Une archive qui se décompresse en beaucoup plus gros.
        config(['filament-tma.attachments.max_unpacked_mb' => 1]);
        $bomb = $this->office('gonfle.xlsx', null, ['xl/media/zeros.bin' => str_repeat("\x00", 2 * 1024 * 1024)]);
        $this->assertStringContainsString('trop gros une fois décompressé', (string) Attachments::problem($bomb, 'gonfle.xlsx'));
    }

    public function test_the_composer_takes_markdown_and_office_files_and_serves_them_as_downloads(): void
    {
        Storage::fake('local');
        $this->asMaster();
        $ticket = $this->serviceDeskTicket();
        $this->actingAs($agent = $this->agent());

        $markdown = $this->file('Compte rendu.md', "# Compte rendu\n\n<a href=\"https://exemple.test\">la maquette</a>\n");
        Livewire::test(\CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket::class, ['record' => $ticket->getKey()])
            ->fillForm(['body' => '<p>Le compte rendu et le budget.</p>', 'attachments' => [$this->upload($markdown), $this->upload($this->office('budget.xlsx')), $this->upload($this->office('lancement.pptx'))]], 'composer')
            ->callAction(TestAction::make('composerReply_request_answer')->schemaComponent('composerActions', schema: 'composer'))
            ->assertHasNoFormErrors([], 'composer');

        $media = Entry::query()->sole()->getMedia('attachments')->keyBy('file_name');
        $this->assertSame(['compte-rendu.md', 'budget.xlsx', 'lancement.pptx'], $media->keys()->all());
        $this->assertSame(Attachments::MARKDOWN, $media['compte-rendu.md']->mime_type);
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $media['budget.xlsx']->mime_type);

        // Téléchargé, jamais affiché, même demandé « en ligne » : son texte n'est pas interprété.
        $response = $this->get(route('filament-tma.media', [$media['compte-rendu.md'], 'inline' => 1]))->assertOk()->assertDownload('compte-rendu.md');
        $this->assertStringStartsWith(Attachments::MARKDOWN, (string) $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', (string) $response->headers->get('Content-Security-Policy'));
    }

    public function test_a_macro_document_is_refused_by_the_forms_and_from_the_other_application(): void
    {
        Storage::fake('local');
        $this->asMaster();
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'url' => 'https://desapp.test', 'token' => 'jeton-desapp']);
        Http::fake(['desapp.test/api/tma/v1/users*' => Http::response(['data' => [['id' => '7', 'name' => 'Ada Lovelace', 'email' => 'ada@client.test', 'service_desk' => true]]]), 'desapp.test/*' => Http::response(['missing_media' => []])]);
        $project = Project::create(['title' => 'Portail parrain', 'target_instance_uid' => self::CLIENT_UID]);
        $this->actingAs($agent = User::factory()->withPermissions('panels.admin.access', 'tma.task.*')->create());
        $macro = $this->office('macro.xlsx', 'application/vnd.ms-excel.sheet.macroEnabled.main+xml', ['xl/vbaProject.bin' => 'vba']);

        // Le formulaire d'une tâche (medialibrary) : refusé, avec la raison ; le classeur sans macro passe.
        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Budget', 'project_id' => $project->getKey(), 'primary_recipients' => ['c:7'], 'attachments' => [$this->upload($macro)]])
            ->call('create')
            ->assertHasFormErrors(['attachments']);
        $this->assertSame(0, ProjectTask::query()->count());

        Livewire::test(CreateTask::class)
            ->fillForm(['title' => 'Budget', 'project_id' => $project->getKey(), 'primary_recipients' => ['c:7'], 'attachments' => [$this->upload($this->office('Budget 2027.xlsx'))]])
            ->call('create')
            ->assertHasNoFormErrors();
        $task = ProjectTask::query()->sole();
        $this->assertSame(['budget-2027.xlsx'], $task->getMedia('attachments')->pluck('file_name')->all());

        // La réponse sous un message : refusée aussi.
        $entry = app(Timeline::class)->add($task, EntryType::Comment, '<p>Le budget.</p>', Context::ui($agent));
        Livewire::test(ViewTask::class, ['record' => $task->getKey()])
            ->fillForm(['body' => '<p>Avec le classeur.</p>', 'attachments' => [$this->upload($macro)]], 'composer')
            ->callAction(TestAction::make('composerMessage')->schemaComponent('composerActions', schema: 'composer'))
            ->assertHasFormErrors(['attachments'], 'composer');
        $this->assertSame(1, $task->entries()->count());

        // Ce qu'envoie l'autre application passe par les mêmes contrôles.
        $this->expectException(ValidationException::class);
        Mirror::files($entry, [['uuid' => 'f1', 'name' => 'macro', 'file_name' => 'macro.xlsx', 'content' => base64_encode((string) file_get_contents($macro))]]);
    }
}

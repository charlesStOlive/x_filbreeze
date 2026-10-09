<?php

namespace Tests\Feature\Tma;

use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;

/** L'aide du module dans la base de connaissances : ses pages publiées, et le « ? » de ses écrans. */
class KnowledgeBaseTest extends TmaTestCase
{
    public function test_the_help_pages_of_the_module_are_published_and_readable(): void
    {
        foreach (['comprendre', 'demarrer', 'tickets', 'chronologie', 'temps', 'taches', 'forfaits', 'notifications', 'synchronisation', 'droits', 'commandes', 'audit-nettoyage', 'glossaire', 'demarrer/identifiant', 'demarrer/adresse', 'tickets/pause'] as $page) {
            $this->assertFileExists(base_path("docs/knowledge-base/fr/tma/{$page}.md"), "Page d'aide non publiée : {$page}");
        }

        $this->actingAs($this->agent('panels.knowledge-base.access'));

        $this->get('/aide/tma/demarrer')->assertOk()->assertSee('Configuration de départ')->assertSee('TMA_INSTANCE_UID');
    }

    public function test_each_screen_points_to_existing_help_pages(): void
    {
        foreach ([
            \CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource::class,
            \CharlesStOlive\FilamentTma\Filament\Resources\Tasks\TaskResource::class,
            \CharlesStOlive\FilamentTma\Filament\Resources\Instances\InstanceResource::class,
            \CharlesStOlive\FilamentTma\Filament\Resources\Allowances\AllowanceResource::class,
            \CharlesStOlive\FilamentTma\Filament\Resources\Audits\AuditResource::class,
        ] as $resource) {
            foreach ((array) $resource::getDocumentation() as $id) {
                $this->assertNotNull(KnowledgeBase::documentable($id, 'knowledge-base'), "{$resource} : page d'aide introuvable « {$id} »");
            }
        }

        $this->asClient();
        $this->actingAs($this->agent('panels.knowledge-base.access'));
        // Le menu « ? » est posé par le gabarit du panel : on regarde la page entière.
        $this->get(\CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Les tickets')
            ->assertSee('Comprendre la TMA');
    }

    public function test_every_question_mark_of_the_module_points_to_an_existing_page(): void
    {
        $source = collect(\Illuminate\Support\Facades\File::allFiles(base_path('vendor/charlesstolive/filament-tma/src')))
            ->map(fn ($file): string => $file->getContents())
            ->implode("\n");

        // Les « ? » : `Help::action('tma.…', __('…'))`, `['tma.…', __('…')]` ; les menus : getDocumentation().
        preg_match_all("/'(tma\\.[a-z.-]+)',\\s*__\\(/", $source, $hints);
        preg_match_all('/getDocumentation\\(\\): array\\|string\\s*\\{\\s*return \\[([^\\]]*)\\]/', $source, $menus);
        preg_match_all("/'(tma\\.[a-z.-]+)'/", implode(',', $menus[1]), $menuPages);
        $pages = array_unique([...$hints[1], ...$menuPages[1]]);

        $this->assertGreaterThan(20, count($pages));

        foreach ($pages as $page) {
            $this->assertNotNull(KnowledgeBase::documentable($page, 'knowledge-base'), "Page d'aide introuvable : {$page}");
        }
    }

    public function test_the_fields_have_their_question_marks_and_a_missing_page_hides_it(): void
    {
        $this->asMaster();
        $this->actingAs($this->agent('tma.instance.*', 'panels.knowledge-base.access'));

        \Livewire\Livewire::test(\CharlesStOlive\FilamentTma\Filament\Resources\Instances\Pages\CreateInstance::class)
            ->assertSee('À quoi sert l’identifiant ?')
            ->assertSee('Quelle adresse ?')
            ->assertSee('Les comptes et leurs jetons');

        $this->assertTrue(\CharlesStOlive\FilamentTma\Support\Help::action('tma.page-qui-n-existe-pas', 'Rien')->isHidden());
        $this->assertInstanceOf(\CharlesStOlive\FilamentUi\Actions\DocumentationAction::class, \CharlesStOlive\FilamentTma\Support\Help::action('tma.demarrer.adresse', 'Quelle adresse ?'));
    }
}

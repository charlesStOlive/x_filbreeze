<?php

namespace Tests\Feature\Tma;

use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;

/** L'aide du module dans la base de connaissances : ses pages publiées, et le « ? » de ses écrans. */
class KnowledgeBaseTest extends TmaTestCase
{
    public function test_the_help_pages_of_the_module_are_published_and_readable(): void
    {
        foreach (['comprendre', 'demarrer', 'tickets', 'chronologie', 'temps', 'taches', 'forfaits', 'notifications', 'synchronisation', 'droits', 'commandes', 'audit-nettoyage', 'glossaire'] as $page) {
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
}

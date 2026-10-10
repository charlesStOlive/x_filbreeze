<?php

namespace Tests\Feature\Tma;

use CharlesStOlive\FilamentTma\Enums\HandlingMode;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Workflow\Context;
use Livewire\Livewire;

/**
 * Les mots du module (`Support\Terms`) : « Support » partout ; le nom court de chaque client (« EDM ») forme
 * « Équipe EDM », « Confier à l'équipe EDM », « Publier chez EDM ».
 */
class VocabularyTest extends TmaTestCase
{
    public function test_a_client_names_its_local_team_with_its_short_name(): void
    {
        $this->asClient();
        config(['filament-tma.instance.short_name' => 'EDM']);
        $requester = $this->agent();
        $ticket = $this->draft($requester);

        $this->assertSame('Confier à l’équipe EDM', $this->workflow()->move('submit')->labelFor($ticket));
        $this->assertSame('Équipe EDM', HandlingMode::Local->getLabel());
        // Un brouillon n'est encore confié à personne.
        $this->assertNull($ticket->handledBy());
        $this->assertSame('Demandeur', $ticket->waitingOn());

        $this->workflow()->run($ticket, 'submit', [], Context::ui($requester));
        $ticket->refresh();

        $this->assertSame('Nouveau', $ticket->state->getLabel());
        $this->assertSame('Équipe EDM', $ticket->handledBy());
        $this->assertSame('Équipe EDM', $ticket->waitingOn());

        $this->actingAs($requester);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSee('En attente de')
            ->assertSee('Traité par')
            ->assertSee('Équipe EDM')
            ->assertSee('Confier à l’équipe EDM');

        // Sans nom court, des mots neutres.
        config(['filament-tma.instance.short_name' => null]);
        $this->assertSame('Équipe locale', $ticket->handledBy());
        $this->assertSame('Confier à l’équipe locale', $this->workflow()->move('submit')->label());
    }

    public function test_the_support_names_each_client_with_its_own_short_name(): void
    {
        $this->asMaster();
        $instance = Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'short_name' => 'EDM', 'url' => 'https://desapp.test']);
        $draft = $this->serviceDeskTicket('draft');

        $this->assertSame('Publier chez EDM', $this->workflow()->move('publish')->labelFor($draft));
        $this->assertSame('Support', $this->serviceDeskTicket('in_progress', 'DES-0002')->waitingOn());
        $this->assertSame('Demandeur', $this->serviceDeskTicket('awaiting_requester', 'DES-0003')->waitingOn());
        $this->assertSame('Résolu', $this->serviceDeskTicket('pending_validation', 'DES-0004')->state->getLabel());

        // Le nom court changé dans la fiche vaut tout de suite.
        $instance->update(['short_name' => 'ENF']);
        $this->assertSame('Publier chez ENF', $this->workflow()->move('publish')->labelFor($draft));

        // Sans nom court : le client, sans plus.
        $instance->update(['short_name' => null]);
        $this->assertSame('Publier chez le client', $this->workflow()->move('publish')->labelFor($draft));
    }

    public function test_the_timeline_says_where_a_person_comes_from(): void
    {
        $this->asMaster();
        Instance::create(['uid' => self::CLIENT_UID, 'name' => 'Desapp', 'short_name' => 'EDM', 'url' => 'https://desapp.test']);

        $this->assertSame('EDM', Actor::fromInstance(self::CLIENT_UID, '7', 'Ada Lovelace')->team());
        $this->assertNull(Actor::fromInstance(self::MASTER_UID, '1', 'Charles')->team());

        // Vue de l'application cliente, une personne du maître est du Support.
        $this->asClient();
        $this->assertSame('Support', Actor::fromInstance(self::MASTER_UID, '1', 'Charles')->team());
        $this->assertNull(Actor::fromInstance(self::CLIENT_UID, '7', 'Ada Lovelace')->team());
    }
}

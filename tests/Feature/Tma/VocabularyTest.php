<?php

namespace Tests\Feature\Tma;

use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Instance;
use CharlesStOlive\FilamentTma\Support\Terms;
use Livewire\Livewire;

/**
 * Les mots du module (`Support\Terms`) : « Support » partout ; le nom court de chaque client (« EDM ») forme
 * « Publier chez EDM » et « Correspondants EDM ».
 */
class VocabularyTest extends TmaTestCase
{
    public function test_a_client_speaks_of_the_support_and_its_correspondents(): void
    {
        $this->asClient();
        config(['filament-tma.instance.short_name' => 'EDM']);
        $requester = $this->correspondent();
        $ticket = $this->draft($requester);

        // Un brouillon attend son auteur ; transmis, le Support.
        $this->assertSame('Demandeur', $ticket->waitingOn());
        $ticket->forceFill(['state' => 'submitted'])->save();
        $this->assertSame('Nouveau', $ticket->state->getLabel());
        $this->assertSame('Support', $ticket->waitingOn());
        $this->assertSame('Retour au Support', $this->workflow()->move('reopen')->labelFor($ticket));
        $this->assertSame('Correspondants du Support', Terms::correspondents());

        $this->actingAs($requester);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSee(['En attente de', 'Support'])
            ->assertDontSee(['Traité par', 'Équipe EDM']);
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
        $this->assertSame('Retour au Support', $this->workflow()->move('reopen')->labelFor($draft));

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

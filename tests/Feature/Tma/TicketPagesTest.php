<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\CreateTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\EditTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ListTickets;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\RelationManagers\TimeEntriesRelationManager;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** Les écrans des tickets : liste, création, fiche et chronologie, boutons selon les droits, fichiers protégés. */
class TicketPagesTest extends TmaTestCase
{
    public function test_the_list_needs_its_permission(): void
    {
        $this->asClient();
        $agent = $this->agent();
        $ticket = $this->draft($agent);

        $this->actingAs($agent);
        Livewire::test(ListTickets::class)->assertOk()->assertCanSeeTableRecords([$ticket]);

        $this->actingAs(User::factory()->withPermissions('panels.admin.access')->create());
        $this->get(TicketResource::getUrl('index'))->assertForbidden();
    }

    public function test_a_client_lists_its_own_requests_first_and_who_must_answer(): void
    {
        $this->asClient();
        $me = $this->agent();
        $colleague = $this->agent();
        $mine = $this->draft($me);
        $theirs = $this->draft($colleague, ['title' => 'Autre demande']);
        $this->workflow()->run($mine, 'submit', [], Context::ui($me));
        $this->workflow()->run($theirs, 'submit', [], Context::ui($colleague));
        $this->workflow()->run($mine, 'take-charge', [], Context::ui($colleague));
        $this->workflow()->run($mine, 'request-answer', ['question' => '<p>Laquelle ?</p>'], Context::ui($colleague));

        // D'office, ses propres demandes ; le filtre se retire pour voir celles des autres.
        $this->actingAs($me);
        Livewire::test(ListTickets::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertTableColumnStateSet('waiting_on', 'Vous', $mine)
            ->filterTable('mine', false)
            ->assertCanSeeTableRecords([$mine, $theirs])
            ->assertTableColumnStateSet('waiting_on', 'Équipe locale', $theirs);

        // Pour le collègue, la demande attend son auteur, par son nom.
        $this->actingAs($colleague);
        Livewire::test(ListTickets::class)
            ->filterTable('mine', false)
            ->assertTableColumnStateSet('waiting_on', $me->name, $mine);

        // Chez le Support, pas de filtre d'office.
        $this->asMaster();
        $support = $this->agent();
        $this->actingAs($support);
        $received = $this->serviceDeskTicket('in_progress', 'DES-0099');
        Livewire::test(ListTickets::class)->assertCanSeeTableRecords([$received]);
    }

    public function test_a_new_request_is_a_draft_of_the_connected_user(): void
    {
        $this->asClient();
        $agent = $this->agent();
        $this->actingAs($agent);

        Livewire::test(CreateTicket::class)
            ->fillForm(['title' => 'Ajouter un champ « téléphone »', 'priority' => 'high', 'description' => '<p>Sur la fiche parrain.</p>'])
            ->call('create')
            ->assertHasNoFormErrors();

        $ticket = Ticket::query()->sole();
        $this->assertSame('draft', $ticket->state::getMorphClass());
        $this->assertSame('high', $ticket->priority->value);
        $this->assertTrue($ticket->requester->isUser($agent));
    }

    public function test_the_page_shows_the_moves_allowed_now(): void
    {
        $this->asClient();
        $agent = $this->agent();
        $ticket = $this->draft($agent);
        $this->actingAs($agent);

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible('move_submit')
            ->assertActionHidden('move_take_charge')
            // Les réponses (« Confirmer et clore »…) ne sont pas des boutons d'en-tête : elles sont dans la zone de réponse.
            ->assertActionDoesNotExist('move_validate')
            ->callAction('move_submit', [])
            ->assertHasNoActionErrors()
            ->assertActionHidden('move_submit')
            ->assertActionVisible('move_take_charge')
            ->callAction('move_take_charge');

        $this->assertInstanceOf(InProgress::class, $ticket->refresh()->state);

        // Sans le droit du mouvement, pas de bouton ; sans droit d'écrire, pas de zone de réponse.
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view')->create());
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionHidden('move_pause')
            ->assertDontSee('Votre message…');
    }

    public function test_a_move_with_a_form_validates_its_fields(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket('in_progress');
        $this->actingAs($agent);

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            // « Refuser » ne vaut qu'avant la prise en charge.
            ->assertActionHidden('move_refuse')
            ->callAction('move_cancel', ['reason' => ''])
            ->assertHasActionErrors(['reason' => 'required']);

        // « Demander des précisions » se fait dans la zone de réponse ; sans question, rien ne bouge.
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->callAction(self::composer('composerReply_request_answer'))
            ->assertNotified('Le message est vide.');
        $this->assertSame('in_progress', $ticket->refresh()->state::getMorphClass());

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->fillForm(['body' => '<p>Quel parrain exactement ?</p>'], 'composer')
            ->callAction(self::composer('composerReply_request_answer'));

        $this->assertSame('awaiting_requester', $ticket->refresh()->state::getMorphClass());
        $this->assertStringContainsString('Quel parrain', Entry::query()->sole()->body);
    }

    /** Le bouton n'est pas proposé : une action masquée d'un schéma n'est même pas trouvée. */
    private function assertNoComposerAction(Testable $page, string $name): void
    {
        try {
            $page->assertActionHidden(self::composer($name));
        } catch (ActionNotResolvableException) {
            $this->addToAssertionCount(1);
        }
    }

    /** Une action de la zone de réponse, sous la chronologie. */
    private static function composer(string $name): TestAction
    {
        return TestAction::make($name)->schemaComponent('composerActions', schema: 'composer');
    }

    public function test_message_note_and_share_from_the_reply_area(): void
    {
        Storage::fake('local');
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $this->actingAs($agent);

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->fillForm(['body' => '<p>Je reproduis le problème.</p>', 'attachments' => [UploadedFile::fake()->image('capture.png', 40, 40)]], 'composer')
            ->callAction(self::composer('composerMessage'))
            ->assertNotified('Envoyé')
            ->fillForm(['kind' => 'note', 'body' => '<p>Penser au cache.</p>'], 'composer')
            ->tap(fn ($page) => $this->assertNoComposerAction($page, 'composerMessage'))
            ->callAction(self::composer('composerNote'));

        $comment = Entry::query()->where('type', 'comment')->sole();
        $note = Entry::query()->where('type', 'note')->sole();
        $this->assertSame('capture.png', $comment->getFirstMedia('attachments')->file_name);
        $this->assertNull($note->shared_at);

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSee('Je reproduis le problème.')
            ->assertSee('Partager avec l’autre équipe')
            ->callAction(TestAction::make('shareNote')->arguments(['entry' => $note->getKey()]));

        $this->assertNotNull($note->refresh()->shared_at);
    }

    public function test_only_the_party_awaited_may_answer(): void
    {
        Storage::fake('local');
        $this->asClient();
        $requester = $this->agent();
        $team = $this->agent();
        $ticket = $this->draft($requester);
        $this->workflow()->run($ticket, 'submit', [], Context::ui($requester));

        // Ticket nouveau : l'équipe répond en le prenant en charge au passage ; le demandeur n'a qu'un message.
        $this->actingAs($requester);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible(self::composer('composerMessage'))
            ->tap(fn ($page) => $this->assertNoComposerAction($page, 'composerReply_answer'));

        $this->actingAs($team);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible(self::composer('composerMessage'))
            ->assertActionVisible(self::composer('composerReply_submit_for_validation'))
            ->fillForm(['body' => '<p>Sur quelle fiche ?</p>'], 'composer')
            ->callAction(self::composer('composerReply_request_answer'));

        $ticket->refresh();
        $this->assertSame('awaiting_requester', $ticket->state::getMorphClass());
        $this->assertTrue($ticket->assignee->isUser($team));

        // Le demandeur est attendu : il répond (pas de simple message à côté) ; l'équipe ne peut plus qu'écrire.
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible(self::composer('composerMessage'))
            ->tap(fn ($page) => $this->assertNoComposerAction($page, 'composerReply_request_answer'));

        $this->actingAs($requester);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSee('attend votre réponse')
            ->tap(fn ($page) => $this->assertNoComposerAction($page, 'composerMessage'))
            ->fillForm(['body' => '<p>Celle du parrain 42.</p>', 'attachments' => [UploadedFile::fake()->image('fiche.png', 40, 40)]], 'composer')
            ->callAction(self::composer('composerReply_answer'));

        $ticket->refresh();
        $this->assertSame('in_progress', $ticket->state::getMorphClass());
        $answer = $ticket->entries()->where('meta->answer', true)->sole();
        $this->assertNotNull($answer->parent_id);
        $this->assertSame('fiche.png', $answer->getFirstMedia('attachments')->file_name);

        // Résolu : le demandeur confirme (son mot part en message), ou rouvre.
        $this->workflow()->run($ticket, 'submit-for-validation', [], Context::ui($team));
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible(self::composer('composerReply_reopen'))
            ->fillForm(['body' => '<p>Parfait, merci.</p>'], 'composer')
            ->callAction(self::composer('composerReply_validate'));

        $this->assertSame('closed', $ticket->refresh()->state::getMorphClass());
        $this->assertSame(1, $ticket->entries()->where('body', '<p>Parfait, merci.</p>')->count());
    }

    public function test_the_follow_up_and_the_time_are_in_the_side_pane(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket();
        $this->actingAs($this->agent('tma.ticket.time'));

        // Le volet de droite est ouvert d'office : le suivi, puis le temps passé (maître).
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSet('sidePane', 'infos')
            ->assertSee('En attente de')
            ->assertSee('Chronologie')
            ->assertSeeLivewire(TimeEntriesRelationManager::class);

        // Dans une application cliente, pas de temps à saisir : le volet n'a que le suivi.
        $this->asClient();
        $agent = $this->agent();
        $this->actingAs($agent);
        Livewire::test(ViewTicket::class, ['record' => $this->draft($agent)->getKey()])
            ->assertSet('sidePane', 'infos')
            ->assertSee('En attente de')
            ->assertDontSeeLivewire(TimeEntriesRelationManager::class);
    }

    public function test_internal_notes_and_their_sharing_can_be_turned_off(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $this->actingAs($agent);
        $timeline = app(Timeline::class);

        // Sans partage : la note reste à son équipe.
        config(['filament-tma.notes.sharing' => false]);
        $note = $timeline->add($ticket, EntryType::Note, '<p>Penser au cache.</p>', Context::ui($agent));
        $this->assertFalse($timeline->canShare($note, Context::ui($agent)));

        // Sans notes internes : il n'y a plus que des messages.
        config(['filament-tma.notes.enabled' => false]);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->set('composerData.kind', 'note')
            ->assertActionVisible(self::composer('composerMessage'))
            ->tap(fn ($page) => $this->assertNoComposerAction($page, 'composerNote'))
            ->assertDontSee('Penser au cache.');
        $this->assertFalse($timeline->canAdd($ticket, EntryType::Note, Context::ui($agent)));
    }

    public function test_editing_follows_the_hand(): void
    {
        $this->asMaster();
        $this->actingAs($this->agent());

        $mine = $this->serviceDeskTicket('in_progress');
        Livewire::test(EditTicket::class, ['record' => $mine->getKey()])
            ->fillForm(['title' => 'Export des parrains (CSV)'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('Export des parrains (CSV)', $mine->refresh()->title);

        $theirs = $this->serviceDeskTicket('awaiting_requester', 'DES-0002');
        $this->get(TicketResource::getUrl('edit', ['record' => $theirs]))->assertForbidden();
    }

    public function test_files_are_served_only_to_who_may_see_them(): void
    {
        Storage::fake('local');
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();

        $note = app(Timeline::class)->add($ticket, EntryType::Note, '<p>Journal du serveur.</p>', Context::ui($agent));
        $media = $note->addMedia(UploadedFile::fake()->createWithContent('journal.txt', "Erreur 500 à 10h02\n"))->toMediaCollection('attachments');

        $this->actingAs($agent)->get(route('filament-tma.media', $media))->assertOk()->assertDownload('journal.txt');

        // Voir le ticket ne suffit pas pour une note interne.
        $viewer = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view')->create();
        $this->actingAs($viewer)->get(route('filament-tma.media', $media))->assertForbidden();

        $this->post('/logout');
        auth()->logout();
        $this->get(route('filament-tma.media', $media))->assertRedirect();
    }

    public function test_a_forbidden_file_type_is_refused(): void
    {
        Storage::fake('local');
        $this->asMaster();
        $this->actingAs($this->agent());

        Livewire::test(ViewTicket::class, ['record' => $this->serviceDeskTicket()->getKey()])
            ->fillForm(['body' => '<p>Voici le script.</p>', 'attachments' => [UploadedFile::fake()->create('outil.php', 1, 'application/x-php')]], 'composer')
            ->callAction(self::composer('composerMessage'))
            ->assertHasFormErrors(['attachments'], 'composer');

        $this->assertSame(0, Entry::query()->count());
    }
}

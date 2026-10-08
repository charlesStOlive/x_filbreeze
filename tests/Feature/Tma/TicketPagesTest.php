<?php

namespace Tests\Feature\Tma;

use App\Models\User;
use CharlesStOlive\FilamentTma\Enums\EntryType;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\CreateTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\EditTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ListTickets;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\Pages\ViewTicket;
use CharlesStOlive\FilamentTma\Filament\Resources\Tickets\TicketResource;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\States\Ticket\InProgress;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertActionHidden('move_validate')
            ->callAction('move_submit', [])
            ->assertHasNoActionErrors()
            ->assertActionHidden('move_submit')
            ->assertActionVisible('move_take_charge')
            ->callAction('move_take_charge');

        $this->assertInstanceOf(InProgress::class, $ticket->refresh()->state);

        // Sans le droit du mouvement, pas de bouton.
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view')->create());
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionHidden('move_request_answer')
            ->assertActionHidden('comment');
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

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->callAction('move_request_answer', ['question' => '<p>Quel parrain exactement ?</p>'])
            ->assertHasNoActionErrors();

        $this->assertSame('awaiting_requester', $ticket->refresh()->state::getMorphClass());
        $this->assertStringContainsString('Quel parrain', Entry::query()->sole()->body);
    }

    public function test_comment_reply_and_share_from_the_page(): void
    {
        Storage::fake('local');
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $this->actingAs($agent);

        $page = Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->callAction('comment', [
                'body' => '<p>Je reproduis le problème.</p>',
                'attachments' => [UploadedFile::fake()->image('capture.png', 40, 40)],
            ])
            ->assertHasNoActionErrors()
            ->callAction('note', ['body' => '<p>Penser au cache.</p>'])
            ->assertHasNoActionErrors();

        $comment = Entry::query()->where('type', 'comment')->sole();
        $note = Entry::query()->where('type', 'note')->sole();
        $this->assertCount(1, $comment->getMedia('attachments'));
        $this->assertSame('capture.png', $comment->getFirstMedia('attachments')->file_name);

        $page->callAction(TestAction::make('reply')->arguments(['entry' => $comment->getKey()]), ['body' => '<p>Merci.</p>'])
            ->assertHasNoActionErrors()
            ->callAction(TestAction::make('shareNote')->arguments(['entry' => $note->getKey()]))
            ->assertSee('Je reproduis le problème.')
            ->assertSee('Merci.');

        $this->assertSame($comment->getKey(), Entry::query()->where('body', '<p>Merci.</p>')->sole()->parent_id);
        $this->assertNotNull($note->refresh()->shared_at);
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
            ->callAction('comment', [
                'body' => '<p>Voici le script.</p>',
                'attachments' => [UploadedFile::fake()->create('outil.php', 1, 'application/x-php')],
            ])
            ->assertHasActionErrors(['attachments']);

        $this->assertSame(0, Entry::query()->count());
    }
}

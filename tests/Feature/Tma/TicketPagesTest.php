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
use CharlesStOlive\FilamentTma\Models\Actor;
use CharlesStOlive\FilamentTma\Models\Agent;
use CharlesStOlive\FilamentTma\Models\Entry;
use CharlesStOlive\FilamentTma\Models\Ticket;
use CharlesStOlive\FilamentTma\Sync\Payload;
use CharlesStOlive\FilamentTma\Workflow\Context;
use CharlesStOlive\FilamentTma\Workflow\Timeline;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

    public function test_images_open_in_the_viewer_without_being_downloaded(): void
    {
        $this->asMaster();
        Storage::fake('local');
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        Storage::disk('local')->put('tma-uploads/capture.png', UploadedFile::fake()->image('capture.png', 20, 20)->getContent());
        Storage::disk('local')->put('tma-uploads/journal.txt', 'erreur 500');
        // Un message écrit par un passage (ici, sans passer par lui).
        $entry = app(Timeline::class)->write($ticket, EntryType::Comment, '<p>Voici l’écran.</p>', Context::ui($agent), uploads: [
            ['path' => 'tma-uploads/capture.png', 'name' => 'capture.png'],
            ['path' => 'tma-uploads/journal.txt', 'name' => 'journal.txt'],
        ]);
        [$image, $text] = [$entry->getMedia(Entry::ATTACHMENTS_COLLECTION)->firstWhere('file_name', 'capture.png'), $entry->getMedia(Entry::ATTACHMENTS_COLLECTION)->firstWhere('file_name', 'journal.txt')];

        // La fiche porte la visionneuse ; la vignette de l'image l'ouvre, le document se télécharge.
        $this->actingAs($agent);
        $this->get(TicketResource::getUrl('view', ['record' => $ticket]))
            ->assertOk()
            ->assertSee('fi-tma-lightbox', false)
            ->assertSee('tma-lightbox', false)
            ->assertSee('href="'.route('filament-tma.media', [$image, 'inline' => 1]).'"', false)
            ->assertSee('href="'.route('filament-tma.media', $text).'"', false);

        // L'image s'affiche (inline) ; un document se télécharge, même demandé inline.
        $this->assertStringStartsWith('inline', (string) $this->get(route('filament-tma.media', [$image, 'inline' => 1]))->assertOk()->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('attachment', (string) $this->get(route('filament-tma.media', $image))->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('attachment', (string) $this->get(route('filament-tma.media', [$text, 'inline' => 1]))->headers->get('Content-Disposition'));
    }

    public function test_a_client_lists_its_own_requests_first_and_who_must_answer(): void
    {
        $this->asClient();
        $me = $this->agent();
        $colleague = $this->agent();
        $mine = $this->draft($me);
        $mine->forceFill(['state' => 'awaiting_requester'])->save();
        $theirs = $this->draft($colleague, ['title' => 'Autre demande']);
        $theirs->forceFill(['state' => 'in_progress'])->save();

        // D'office, ses propres demandes ; le filtre se retire pour voir celles des autres.
        $this->actingAs($me);
        Livewire::test(ListTickets::class)
            ->assertSet('activeTab', 'mine')
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertTableColumnStateSet('waiting_on', 'Vous', $mine)
            ->filterTable('mine', false)
            ->assertCanSeeTableRecords([$mine, $theirs])
            ->assertTableColumnStateSet('waiting_on', 'Support', $theirs);

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

    public function test_the_list_leaves_out_paused_closed_and_cancelled_tickets(): void
    {
        $this->asMaster();
        $this->actingAs($this->agent());
        $open = collect(['submitted', 'in_progress', 'awaiting_requester', 'pending_validation'])
            ->map(fn (string $state, int $i): Ticket => $this->serviceDeskTicket($state, 'DES-00'.($i + 10)));
        $done = collect(['paused', 'closed', 'cancelled'])
            ->map(fn (string $state, int $i): Ticket => $this->serviceDeskTicket($state, 'DES-00'.($i + 20)));

        // On les revoit en les cochant dans « État ».
        Livewire::test(ListTickets::class)
            ->assertCanSeeTableRecords($open)
            ->assertCanNotSeeTableRecords($done)
            ->filterTable('state', ['closed'])
            ->assertCanSeeTableRecords([$done[1]])
            ->assertCanNotSeeTableRecords([...$open, $done[0], $done[2]]);
    }

    public function test_in_a_client_a_support_member_sees_every_request(): void
    {
        $this->asClient();
        $requester = $this->agent();
        $theirs = $this->draft($requester);
        $theirs->forceFill(['state' => 'submitted'])->save();

        // Un membre du Support n'y demande rien : pas de « Mes demandes » d'office.
        $charles = $this->agent();
        Agent::create(['user_id' => $charles->getKey(), 'email' => $charles->email, 'name' => 'Charles', 'master_uid' => self::MASTER_UID, 'master_user_id' => '1', 'token' => 'jeton-agent-charles', 'linked_at' => now()]);
        $this->actingAs($charles);
        Livewire::test(ListTickets::class)
            ->assertSet('activeTab', 'active')
            ->assertCanSeeTableRecords([$theirs]);

        // Ce qui l'attend : les demandes du Support qu'on lui a confiées (il y est l'acteur du maître), ou que
        // personne n'a prises.
        $assigned = $this->serviceDeskTicket('in_progress', 'DES-0031');
        $assigned->update(['assignee_actor_id' => Actor::fromInstance(self::MASTER_UID, '1', 'Charles')->getKey()]);
        $toTake = $this->serviceDeskTicket('submitted', 'DES-0032');
        $elsewhere = $this->serviceDeskTicket('in_progress', 'DES-0033');
        $elsewhere->update(['assignee_actor_id' => Actor::fromInstance(self::MASTER_UID, '2', 'Bob')->getKey()]);
        Livewire::test(ListTickets::class)
            ->set('activeTab', 'awaiting')
            ->assertCanSeeTableRecords([$assigned, $toTake])
            ->assertCanNotSeeTableRecords([$elsewhere]);
    }

    public function test_the_shortcuts_fill_the_filters_of_the_list(): void
    {
        $this->asMaster();
        $me = $this->agent();
        $mine = $this->serviceDeskTicket('in_progress', 'DES-0040');
        $mine->update(['assignee_actor_id' => Actor::forUser($me)->getKey()]);
        $toTake = $this->serviceDeskTicket('submitted', 'DES-0041');
        $theirs = $this->serviceDeskTicket('in_progress', 'DES-0042');
        $theirs->update(['assignee_actor_id' => Actor::forUser($this->agent())->getKey()]);
        $atTheClient = $this->serviceDeskTicket('awaiting_requester', 'DES-0043');
        $paused = $this->serviceDeskTicket('paused', 'DES-0044');
        $paused->update(['assignee_actor_id' => Actor::forUser($me)->getKey()]);
        $closed = $this->serviceDeskTicket('closed', 'DES-0045');

        $this->actingAs($me);
        Livewire::test(ListTickets::class)
            ->assertSee(['En attente de moi', 'Actifs', 'Tous'])
            ->tap(fn (Testable $page) => $this->assertSame(['awaiting', 'active', 'all'], array_keys($page->instance()->getTabs())))
            ->assertSet('activeTab', 'active')
            ->assertCanSeeTableRecords([$mine, $toTake, $theirs, $atTheClient])
            ->assertCanNotSeeTableRecords([$paused, $closed])
            // Le raccourci remplit les filtres de la liste, et vide les autres.
            ->set('activeTab', 'awaiting')
            ->assertSet('tableFilters.awaiting.isActive', true)
            ->assertSet('tableFilters.state.values', fn (?array $values): bool => blank($values))
            ->assertCanSeeTableRecords([$mine, $toTake])
            ->assertCanNotSeeTableRecords([$theirs, $atTheClient, $paused, $closed])
            // Des filtres changés à la main : plus de raccourci allumé.
            ->filterTable('state', ['paused'])
            ->assertSet('activeTab', null)
            ->assertCanNotSeeTableRecords([$mine, $toTake, $paused])
            ->set('activeTab', 'all')
            ->assertSet('tableFilters.awaiting.isActive', fn (mixed $on): bool => ! $on)
            ->assertCanSeeTableRecords([$mine, $toTake, $theirs, $atTheClient, $paused, $closed])
            // Les filtres d'un raccourci, choisis un à un, l'allument.
            ->filterTable('awaiting')
            ->assertSet('activeTab', 'awaiting');

        // Une adresse qui ne donne qu'un raccourci ouvre la liste avec ses filtres.
        Livewire::withQueryParams(['tab' => 'awaiting'])->test(ListTickets::class)
            ->assertSet('activeTab', 'awaiting')
            ->assertCanSeeTableRecords([$mine, $toTake])
            ->assertCanNotSeeTableRecords([$theirs]);
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
        // Dans l'application cliente, un brouillon se transmet au Support, ou se retire.
        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton']);
        $requester = $this->correspondent();
        $this->actingAs($requester);
        Livewire::test(ViewTicket::class, ['record' => $this->draft($requester)->getKey()])
            ->assertActionVisible('move_transmit')
            ->assertActionVisible('move_cancel')
            // Les réponses (« Confirmer et clore »…) ne sont pas des boutons d'en-tête : elles sont dans la zone de réponse.
            ->assertActionDoesNotExist('move_validate');

        // Dans le maître, pas de « prise en charge » : le Support répond tout de suite, refuse, ou met en pause.
        $this->asMaster();
        $ticket = $this->serviceDeskTicket('submitted', 'DES-0010');
        $this->actingAs($this->supportMember());
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionDoesNotExist('move_take_charge')
            ->assertActionHidden('move_transmit')
            ->assertActionHidden('move_force_close')
            ->assertActionVisible('move_refuse')
            ->assertActionVisible(self::composer('composerReply_request_answer'))
            ->callAction('move_pause', ['until' => now()->addDay()->toDateTimeString()]);

        $this->assertSame('paused', $ticket->refresh()->state::getMorphClass());

        // Sans être membre du Support, pas de bouton, et rien à écrire.
        $this->actingAs(User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view')->create());
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionHidden('move_resume')
            ->assertDontSee('Votre message…');
    }

    public function test_a_move_with_a_form_validates_its_fields(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket('in_progress');
        $this->actingAs($agent);

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            // « Refuser » ne vaut qu'avant la prise en charge ; le Support ne retire pas une demande.
            ->assertActionHidden('move_refuse')
            ->assertActionHidden('move_cancel')
            ->callAction('move_force_close', ['reason' => ''])
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

    public function test_a_message_from_the_reply_area_and_its_notes_in_their_window(): void
    {
        Storage::fake('local');
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $this->actingAs($agent);

        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->tap(fn ($page) => $this->assertNoComposerAction($page, 'composerMessage'))
            ->fillForm(['body' => '<p>Je reproduis le problème.</p>', 'attachments' => [UploadedFile::fake()->image('capture.png', 40, 40)]], 'composer')
            ->callAction(self::composer('composerReply_request_answer'))
            ->assertNotified('Envoyé');

        $comment = Entry::query()->messages()->sole();
        $this->assertSame('capture.png', $comment->getFirstMedia('attachments')->file_name);

        // L'historique : la demande, puis le message ; le nouveau message est dessous, à part.
        $page = Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSeeInOrder(['Historique', 'Demande', 'Je reproduis le problème.', 'Nouveau message'])
            ->callAction(TestAction::make('createNote')->arguments(['entry' => $comment->getKey()]), ['body' => '<p>Penser au cache.</p>'])
            ->assertNotified('Note n° 1 ajoutée')
            ->assertSee('n° 1');
        $note = Entry::query()->notes()->sole();
        $this->assertTrue($note->parent->is($comment));

        // La grande fenêtre : le message, ses fichiers et ses notes ; supprimer la note y revient.
        $page->mountAction(TestAction::make('notes')->arguments(['entry' => $comment->getKey(), 'note' => $note->getKey()]))
            ->assertMountedActionModalSee(['Je reproduis le problème.', 'capture.png', 'Penser au cache.'])
            ->callAction(TestAction::make('deleteNote')->arguments(['note' => $note->getKey()]))
            ->assertNotified('Note n° 1 supprimée')
            ->assertActionMounted(TestAction::make('notes'))
            ->assertMountedActionModalSee('Pas encore de note.');
        $this->assertSoftDeleted($note);

        // L'onglet « Notes » du volet les montre toutes ; les notes sur le ticket entier aussi.
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->callAction(TestAction::make('createNote'), ['body' => '<p>Voir avec le client en mars.</p>'])
            ->assertNotified('Note n° 2 ajoutée')
            ->assertSee(['Sur le ticket', 'Voir avec le client en mars.'])
            ->mountAction(TestAction::make('notes'))
            ->assertMountedActionModalSee(['Notes sur le ticket', 'Voir avec le client en mars.']);
    }

    public function test_a_note_kept_for_oneself_is_not_shown_to_the_others(): void
    {
        $this->asMaster();
        $author = $this->agent();
        $ticket = $this->serviceDeskTicket();

        $this->actingAs($author);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->callAction(TestAction::make('createNote'), ['body' => '<p>Le client a sûrement oublié son cache.</p>', 'private' => true])
            ->assertNotified('Note n° 1 ajoutée')
            ->assertSee(['Le client a sûrement oublié son cache.', 'Visible de vous seul']);
        $note = Entry::query()->notes()->sole();
        $this->assertTrue($note->private);

        // Pour un collègue, elle n'existe pas : ni dans l'historique, ni dans l'onglet, ni en fenêtre.
        $this->actingAs($this->agent());
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertDontSee('Le client a sûrement oublié son cache.')
            ->assertSee('Aucune note pour l’instant.')
            ->mountAction(TestAction::make('notes'))
            ->assertMountedActionModalDontSee('Le client a sûrement oublié son cache.')
            ->assertActionHidden(TestAction::make('archiveNote')->arguments(['note' => $note->getKey()]));

        // Son auteur la montre à tous.
        $this->actingAs($author);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->callAction(TestAction::make('editNote')->arguments(['note' => $note->getKey()]), ['body' => '<p>Le client a oublié son cache.</p>', 'private' => false])
            ->assertNotified('Note n° 1 modifiée');
        $this->assertFalse($note->refresh()->private);
    }

    /** Le maître, tel qu'il répond à un passage demandé par l'application cliente : le nouvel état, et le message. */
    private function fakeMasterMoves(Ticket $ticket): void
    {
        Http::fake(['master.test/api/tma/v1/tickets/*/moves' => function (Request $request) use ($ticket) {
            $to = ['answer' => 'in_progress', 'reopen' => 'in_progress', 'validate' => 'closed'][$request['move']];
            $snapshot = Payload::snapshot($ticket->refresh());
            $snapshot['ticket'] = [...$snapshot['ticket'], 'state' => $to, 'version' => $ticket->sync_version + 1];
            $snapshot['transitions'][] = ['ulid' => $request['transition_ulid'], 'move' => $request['move'], 'from_state' => $ticket->state::getMorphClass(),
                'to_state' => $to, 'actor' => $request['actor'], 'origin_instance_uid' => self::CLIENT_UID, 'origin' => 'ui'];
            $text = $request['data']['answer'] ?? $request['data']['message'] ?? null;

            if (filled($text)) {
                $snapshot['entries'][] = ['ulid' => strtolower((string) Str::ulid()), 'type' => 'comment', 'body' => $text, 'meta' => ['transition' => $request['transition_ulid']],
                    'author' => $request['actor'], 'origin_instance_uid' => self::CLIENT_UID, 'origin' => 'ui', 'created_at' => now()->toIso8601String()];
            }

            return Http::response($snapshot);
        }]);
    }

    public function test_only_the_party_awaited_may_answer(): void
    {
        Storage::fake('local');

        // Dans le maître : sur un ticket nouveau, le Support répond tout de suite, toujours par un passage.
        $this->asMaster();
        $support = $this->supportMember();
        $ticket = $this->serviceDeskTicket('submitted');
        $this->actingAs($support);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->tap(fn ($page) => $this->assertNoComposerAction($page, 'composerMessage'))
            ->assertSee('Pour un point d’étape, écrivez une note.')
            ->assertActionVisible(self::composer('composerReply_submit_for_validation'))
            ->tap(fn ($page) => $this->assertNoComposerAction($page, 'composerReply_answer'))
            ->fillForm(['body' => '<p>Sur quelle fiche ?</p>'], 'composer')
            ->callAction(self::composer('composerReply_request_answer'));

        $ticket->refresh();
        $this->assertSame('awaiting_requester', $ticket->state::getMorphClass());
        $this->assertTrue($ticket->assignee->isUser($support));

        // Le demandeur est attendu : le Support n'écrit plus de message, il le dit et renvoie aux notes.
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSee('Ce n’est pas votre tour')
            ->tap(fn ($page) => $this->assertNoComposerAction($page, 'composerMessage'))
            ->tap(fn ($page) => $this->assertNoComposerAction($page, 'composerReply_request_answer'));

        // Dans l'application cliente : le demandeur répond (pas de simple message à côté) ; le maître fait le passage.
        $this->asClient();
        config(['filament-tma.master.url' => 'https://master.test', 'filament-tma.master.token' => 'jeton']);
        $requester = $this->correspondent();
        $mine = $this->draft($requester);
        $mine->forceFill(['state' => 'awaiting_requester', 'sync_status' => 'synced'])->save();
        $this->fakeMasterMoves($mine);

        $this->actingAs($requester);
        Livewire::test(ViewTicket::class, ['record' => $mine->getKey()])
            ->assertSee('attend votre réponse')
            ->tap(fn ($page) => $this->assertNoComposerAction($page, 'composerMessage'))
            ->fillForm(['body' => '<p>Celle du parrain 42.</p>', 'attachments' => [UploadedFile::fake()->image('fiche.png', 40, 40)]], 'composer')
            ->callAction(self::composer('composerReply_answer'));

        $this->assertSame('in_progress', $mine->refresh()->state::getMorphClass());
        Http::assertSent(fn (Request $request): bool => $request['move'] === 'answer' && $request['data']['answer'] === '<p>Celle du parrain 42.</p>'
            && $request['data']['files'][0]['file_name'] === 'fiche.png');

        // Résolu : le demandeur confirme, avec un mot qui accompagne le passage, ou renvoie la demande.
        $mine->forceFill(['state' => 'pending_validation'])->save();
        Livewire::test(ViewTicket::class, ['record' => $mine->getKey()])
            ->assertActionVisible(self::composer('composerReply_reopen'))
            ->fillForm(['body' => '<p>Parfait, merci.</p>'], 'composer')
            ->callAction(self::composer('composerReply_validate'));

        $this->assertSame('closed', $mine->refresh()->state::getMorphClass());
        Http::assertSent(fn (Request $request): bool => $request['move'] === 'validate' && $request['data']['message'] === '<p>Parfait, merci.</p>');
        $this->assertSame(
            $mine->transitions()->where('move', 'validate')->sole()->ulid,
            $mine->entries()->where('body', '<p>Parfait, merci.</p>')->sole()->meta['transition'],
        );
    }

    public function test_a_move_shows_in_the_block_of_the_message_written_with_it(): void
    {
        $this->asMaster();
        $support = $this->agent();
        $ticket = $this->serviceDeskTicket('submitted');
        $ticket->transitions()->create(['ulid' => strtolower((string) Str::ulid()), 'move' => 'transmit', 'from_state' => 'draft', 'to_state' => 'submitted',
            'actor_id' => $ticket->requester_actor_id, 'origin_instance_uid' => self::CLIENT_UID, 'origin' => 'ui']);
        $this->workflow()->run($ticket, 'request-answer', ['question' => '<p>Sur quel navigateur ?</p>'], Context::ui($support));
        $this->travel(1)->minute();
        $this->workflow()->run($ticket->refresh(), 'answer', ['answer' => '<p>Firefox.</p>'], $this->fromClient());
        $this->travel(1)->minute();
        $this->workflow()->run($ticket->refresh(), 'pause', ['until' => now()->addDay()->toDateTimeString()], Context::ui($support));
        $this->travel(1)->minute();
        app(Timeline::class)->add($ticket, EntryType::Comment, '<p>Toujours d’actualité ?</p>', $this->fromClient(), meta: ['reminder' => true]);
        $this->travel(1)->minute();
        $this->workflow()->run($ticket->refresh(), 'force-close', ['reason' => 'Doublon de la demande d’hier.'], Context::ui($support));

        // Dans des blocs : la demande avec « Transmettre au Support », la question avec « Demander des précisions », la
        // clôture forcée avec son motif ; à part, sur une ligne, la pause sans motif ; la relance écrite après elle reste à elle.
        $this->actingAs($support);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSeeInOrder(['Demande', 'Transmettre au Support', 'Pas de description.',
                'Demander des précisions', 'Sur quel navigateur ?', 'Firefox.',
                'Mettre en pause', 'Toujours d’actualité ?',
                'Forcer la clôture', 'Doublon de la demande d’hier.'])
            ->tap(function (Testable $page): void {
                $html = $page->html();
                $this->assertSame(1, substr_count($html, 'class="fi-tma-timeline-event"'));
                $this->assertSame(1, substr_count($html, 'fi-tma-entry--move'));
            });
    }

    public function test_the_follow_up_and_the_time_are_in_the_side_pane(): void
    {
        $this->asMaster();
        $ticket = $this->serviceDeskTicket();
        $this->actingAs($this->agent('tma.ticket.time'));

        // Le volet de droite est ouvert d'office : le suivi, puis le temps passé (maître) ; les notes dans un second
        // onglet.
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertSet('sidePane', 'infos')
            ->assertSee('En attente de')
            ->assertSee(['Historique', 'Notes', 'Aucune note pour l’instant.'])
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

    public function test_notes_can_be_turned_off(): void
    {
        $this->asMaster();
        $agent = $this->agent();
        $ticket = $this->serviceDeskTicket();
        $this->actingAs($agent);
        $timeline = app(Timeline::class);
        $timeline->add($ticket, EntryType::Note, '<p>Penser au cache.</p>', Context::ui($agent));

        // Sans notes : il n'y a plus que les messages des passages.
        config(['filament-tma.notes.enabled' => false]);
        Livewire::test(ViewTicket::class, ['record' => $ticket->getKey()])
            ->assertActionVisible(self::composer('composerReply_request_answer'))
            ->assertActionHidden('createNote')
            ->assertActionHidden('notes')
            ->assertDontSee('Penser au cache.');
        $this->assertFalse($timeline->canAdd($ticket, EntryType::Note, Context::ui($agent)));
    }

    public function test_the_support_retouches_title_and_priority_on_its_turn(): void
    {
        $this->asMaster();
        $this->actingAs($this->supportMember());

        // La description reste à l'auteur de la demande.
        $mine = $this->serviceDeskTicket('in_progress');
        Livewire::test(EditTicket::class, ['record' => $mine->getKey()])
            ->assertFormFieldIsDisabled('description')
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

        // Une note est lue de tous : voir le ticket suffit. Supprimée, ses fichiers ne s'ouvrent plus.
        $viewer = User::factory()->withPermissions('panels.admin.access', 'tma.ticket.viewany', 'tma.ticket.view')->create();
        $this->actingAs($viewer)->get(route('filament-tma.media', $media))->assertOk();
        app(Timeline::class)->delete($note, Context::ui($agent));
        $this->actingAs($agent)->get(route('filament-tma.media', $media))->assertNotFound();

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
            ->callAction(self::composer('composerReply_request_answer'))
            ->assertHasFormErrors(['attachments'], 'composer');

        $this->assertSame(0, Entry::query()->count());
    }
}

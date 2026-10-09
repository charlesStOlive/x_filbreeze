{{--
    Surcharge de la vue du composant « Modals » de la base de connaissances (guava/filament-knowledge-base), pour que les
    liens « #modal-… » ouvrent leur page. Seule différence avec l'original : la fenêtre est rendue hors du `@if($documentable)`.

    Le bug du plugin (constaté en 3.3.2 avec Filament 5.7.6 et Livewire 4.4.1 comme 4.4.6) :
    1. La racine porte `x-ignore x-load` : Filament charge `modalsComponent()` en différé, puis retire `x-ignore` et
       initialise le bloc.
    2. Un lien `#modal-…` appelle `showDocumentation()`. Le HTML renvoyé porte toujours `x-ignore` sur la racine : la mise à
       jour de Livewire (morph) le remet, et Alpine considère de nouveau tout le bloc comme ignoré.
    3. Dans la vue d'origine, la fenêtre n'existe que lorsqu'une page est choisie : elle arrive avec cette mise à jour, sous
       un bloc ignoré. Alpine ne l'initialise jamais, « open-modal » n'a personne pour l'entendre : rien ne s'ouvre.
    Ici, la fenêtre existe dès le chargement et Alpine l'initialise avec le reste ; la mise à jour ne change que son texte.

    Le HelpAction (« ? » et menu d'aide des ressources) n'utilise pas ce composant : il n'est touché que par les liens
    `#modal-…` entre pages d'aide. Conséquence de cette surcharge : la fenêtre ne disparaît plus avec sa page, d'où
    App\Livewire\KnowledgeBaseModals. Les deux se retirent ensemble quand le plugin corrigera sa vue (vérifier qu'un lien
    `#modal-…` ouvre sa page avec la vue d'origine) ; (repris de desappmap, où `docs/voyage-aide-suivi.md` décrit comment le vérifier).
--}}
@php
    use Guava\FilamentKnowledgeBase\Facades\KnowledgeBase;

    $plugin = KnowledgeBase::plugin();
    $companion = KnowledgeBase::companion();

    $hasSlideOverPreviews = $companion->hasSlideOverPreviews();
    $hasModalTitleBreadcrumbs = $companion->hasModalTitleBreadcrumbs();
    $hasOpenDocumentationButton = ! $companion->shouldDisableOpenDocumentationButton();
    $target = $companion->shouldOpenKnowledgeBasePanelInNewTab() ? '_blank' : '_self';
    $articleClass = $plugin->getArticleClass();
@endphp

<div
    x-ignore
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('modals-component', 'guava/filament-knowledge-base') }}"
    x-data="modalsComponent()"
>
    <x-filament::modal id="kb-custom-modal"
                       :close-by-clicking-away="true"
                       :close-button="true"
                       width="2xl"
                       :slide-over="$hasSlideOverPreviews"
                       class="[&_.fi-modal-content]:px-12 [&_.fi-modal-content]:gap-y-0"
                       :footer-actions-alignment="\Filament\Support\Enums\Alignment::End"
                       :sticky-footer="true"
                       :sticky-header="true">

        <x-slot name="heading">
            @if($documentable)
                @if($hasModalTitleBreadcrumbs && !empty($documentable->getBreadcrumbs()))
                    {{ KnowledgeBase::breadcrumbs($documentable) }}
                @else
                    {{ $documentable->getTitle() }}
                @endif
            @endif
        </x-slot>

        @if($documentable)
            <x-filament-knowledge-base::content @class([
                "gu-kb-article-modal",
                $articleClass => ! empty($articleClass),
            ])>
                {!! $documentable->getSimpleHtml() !!}
            </x-filament-knowledge-base::content>
        @endif

        <x-slot name="footerActions">
            @if($documentable && $hasOpenDocumentationButton)
                <x-filament::button tag="a"
                                    :href="$documentable->getUrl()"
                                    :target="$target">
                    {{ __('filament-knowledge-base::translations.open-documentation') }}
                </x-filament::button>
            @endif
            <x-filament::button color="gray"
                                x-on:click.prevent="$dispatch('close-modal', { id: 'kb-custom-modal' })">

                {{ __('filament-knowledge-base::translations.close') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>
</div>

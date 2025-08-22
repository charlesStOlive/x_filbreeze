// Custom Nestable Extensions
// Extensions personnalisées pour le plugin jQuery Nestable
import $ from 'jquery';

(function ($) {

    // Extension pour gérer les actions personnalisées
    $.fn.nestableActions = function (options) {
        var settings = $.extend({
            expandAll: '[data-action="expand-all"]',
            collapseAll: '[data-action="collapse-all"]',
            save: '[data-action="save"]'
        }, options);

        return this.each(function () {
            var $this = $(this);
            var nestable = $this.data('nestable');

            if (!nestable) {
                console.warn('Nestable plugin not initialized');
                return;
            }

            // Expand All
            $(settings.expandAll).on('click', function (e) {
                e.preventDefault();
                nestable.expandAll();
            });

            // Collapse All
            $(settings.collapseAll).on('click', function (e) {
                e.preventDefault();
                nestable.collapseAll();
            });

            // Save
            $(settings.save).on('click', function (e) {
                e.preventDefault();
                var serialized = nestable.serialize();

                // Déclencher un événement personnalisé
                $this.trigger('nestable:save', [serialized]);

                // Si Livewire est disponible, appeler la méthode
                if (window.Livewire && window.Livewire.find) {
                    var livewireComponent = window.Livewire.find($this.closest('[wire\\:id]').attr('wire:id'));
                    if (livewireComponent && livewireComponent.call) {
                        livewireComponent.call('updateTreeOrder', serialized);
                    }
                }
            });
        });
    };

    // Extension pour personnaliser l'apparence
    $.fn.nestableCustom = function (options) {
        var settings = $.extend({
            handleSelector: '.dd-handle',
            itemSelector: '.dd-item',
            actionSelector: '.dd-item-actions'
        }, options);

        return this.each(function () {
            var $this = $(this);

            // Masquer les actions pendant le drag
            $this.on('change', function () {
                $(settings.actionSelector).css('opacity', '1');
            });

            // Gérer les événements de hover pour les handles
            $this.on('mouseenter', settings.handleSelector, function () {
                $(this).closest(settings.itemSelector).addClass('dd-hover');
            });

            $this.on('mouseleave', settings.handleSelector, function () {
                $(this).closest(settings.itemSelector).removeClass('dd-hover');
            });
        });
    };

})(jQuery);

// Export pour les modules ES
export default true;

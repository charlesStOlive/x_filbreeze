<?php

namespace App\Livewire\Tables;

use Livewire\Component;

use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;

use App\Models\MsgUserDraft;
use App\Models\MsgEmailDraft;
use App\Services\MsGraph\ServiceFormBuilder;
use App\Services\EmailsProcessorRegisterServices;

class MailServiceCell extends Component implements HasForms
{
    use InteractsWithForms;

    public $record; // Le record Model passé directement
    public string $serviceType; // 'email-draft'
    public string $openMode = 'view'; // 'view' | 'edit' | 'results'
    public string $modalWidth = 'xl'; // Taille du modal
    public string $buttonSize = 'w-24 h-24'; // Taille des boutons
    public bool $showMessage = false; // Afficher le message de retour

    public ?string $currentServiceKey = null;
    public array $servicesData = [];
    public array $data = [];

    public function mount($record, string $serviceType, string $openMode = 'view', string $modalWidth = 'xl', string $buttonSize = 'w-24 h-24', bool $showMessage = false): void
    {
        $this->record = $record;
        $this->serviceType = $serviceType;
        $this->openMode = in_array($openMode, ['view', 'edit', 'results'], true) ? $openMode : 'view';
        $this->modalWidth = $modalWidth;
        $this->buttonSize = $buttonSize;
        $this->showMessage = $showMessage;

        $this->rebuildServicesData();
    }

    /**
     * Récupère le record - maintenant directement disponible comme propriété
     */
    protected function getRecord()
    {
        return $this->record;
    }

    /** v4 : Schema */
    public function form(Schema $form): Schema
    {
        $record = $this->getRecord();

        $schema = [];
        if ($this->currentServiceKey && $this->openMode === 'edit') {
            $schema = ServiceFormBuilder::buildSingleServiceForm($this->serviceType, $record, $this->currentServiceKey);
        }

        return $form->schema($schema)->statePath('data');
    }

    /** Schema pour "view" (config) ou "results" */
    public function serviceInfoSchema(Schema $schema): Schema
    {
        $record = $this->getRecord();
        $components = [];

        if ($this->currentServiceKey) {
            $components = match ($this->openMode) {
                'results' => ServiceFormBuilder::buildSingleServiceResults($this->serviceType, $record, $this->currentServiceKey),
                default => ServiceFormBuilder::buildSingleServiceInfo($this->serviceType, $record, $this->currentServiceKey),
            };
        }

        return $schema->components($components);
    }

    public function openService(string $serviceKey): void
    {
        $this->currentServiceKey = $serviceKey;

        if ($this->openMode === 'edit') {
            $record = $this->getRecord();
            $existing = $record->services_options[$serviceKey] ?? [];

            // Fusionner avec les valeurs par défaut du service
            $services = EmailsProcessorRegisterServices::getAll($this->serviceType);
            $serviceClass = $services[$serviceKey]['class'] ?? null;
            $defaults = [];

            if ($serviceClass && method_exists($serviceClass, 'getDefaults')) {
                $defaults = $serviceClass::getDefaults();
            }

            // Les valeurs existantes prennent la priorité sur les défauts
            $formData = array_merge($defaults, $existing);

            $this->form->fill([
                $serviceKey => $formData,
            ]);
        }

        $this->dispatch('open-modal', id: $this->modalId());
    }

    public function save(): void
    {
        if ($this->openMode !== 'edit' || !$this->currentServiceKey) {
            return;
        }

        $record = $this->getRecord();

        $incoming = $this->data[$this->currentServiceKey] ?? [];
        $options = $record->services_options ?? [];

        $options[$this->currentServiceKey] = array_merge($options[$this->currentServiceKey] ?? [], $incoming);

        $record->services_options = $options;
        $record->save();

        $this->rebuildServicesData();

        $this->dispatch('close-modal', id: $this->modalId());
    }

    public function getHeadingProperty(): string
    {
        $label = 'Service';
        if ($this->currentServiceKey) {
            $all = EmailsProcessorRegisterServices::getAll($this->serviceType);
            $label = $all[$this->currentServiceKey]['label'] ?? $label;
        }

        $suffix = match ($this->openMode) {
            'edit' => ' — Éditer',
            'view' => ' — Voir',
            'results' => ' — Résultats',
            default => '',
        };

        return $label . $suffix;
    }

    public function modalId(): string
    {
        return 'ms-cell-modal-' . $this->record->getKey() . '-' . $this->getId();
    }

    protected function rebuildServicesData(): void
    {
        $record = $this->getRecord();
        $services = EmailsProcessorRegisterServices::getAll($this->serviceType);

        $data = [];
        foreach ($services as $serviceKey => $service) {
            $serviceClass = $service['class'];

            // En mode 'results', on utilise services_results, sinon services_options
            if ($this->openMode === 'results') {
                $serviceResults = $record->services_results[$serviceKey] ?? null;
                if (!$serviceResults) {
                    continue; // Ne pas afficher les services sans résultats
                }
                $mode = $serviceResults['mode'] ?? 'inactif';
            } else {
                $mode = $record->getServiceOption($serviceKey, 'mode', 'inactif');
            }

            $options = [];
            if (method_exists($serviceClass, 'getDefaults')) {
                $defaults = $serviceClass::getDefaults();
                foreach ($defaults as $optionKey => $defaultValue) {
                    if ($optionKey === 'mode') {
                        continue;
                    }

                    if ($this->openMode === 'results') {
                        // Pour les résultats, on lit depuis services_results
                        $value = $serviceResults[$optionKey] ?? $defaultValue;
                    } else {
                        // Pour les options, on lit depuis services_options
                        $value = $record->getServiceOption($serviceKey, $optionKey, $defaultValue);
                    }

                    $options[$this->formatOptionLabel($optionKey)] = $this->formatOptionValue($value);
                }
            }

            // Déterminer les informations de style selon le mode
            $styleInfo = $this->getServiceStyleInfo($mode, $serviceKey, $record);

            // Récupérer le message de retour si disponible
            $message = '';
            if ($this->openMode === 'results') {
                $message = $serviceResults['message'] ?? '';
            } else {
                // En mode normal, on peut récupérer le dernier message depuis services_results
                $lastResults = $record->services_results[$serviceKey] ?? null;
                $message = $lastResults['message'] ?? '';
            }

            $data[] = [
                'key' => $serviceKey,
                'label' => $service['label'],
                'mode' => $mode,
                'mode_label' => ucfirst($mode),
                'icon' => method_exists($serviceClass, 'getIcon') ? $serviceClass::getIcon() : 'heroicon-o-cog',
                'options' => $options,
                'record_id' => $this->record->getKey(),
                'message' => $message, // Message de retour du service
                // Nouvelles propriétés pour le design
                'border_color' => $styleInfo['border_color'],
                'background_color' => $styleInfo['background_color'],
                'icon_background_color' => $styleInfo['icon_background_color'],
                'top_left_icon' => $styleInfo['top_left_icon'],
                'bottom_right_icon' => $styleInfo['bottom_right_icon'],
                'status' => $styleInfo['status'] ?? null,
            ];
        }

        $this->servicesData = $data;
    }

    protected function formatOptionLabel(string $optionKey): string
    {
        return ucwords(str_replace('_', ' ', $optionKey));
    }

    protected function formatOptionValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Oui' : 'Non';
        }
        if (is_array($value)) {
            return implode(', ', $value);
        }
        return (string) $value;
    }

    /**
     * Détermine les informations de style (couleurs, icônes) selon le mode et le contexte
     */
    protected function getServiceStyleInfo(string $mode, string $serviceKey, $record): array
    {
        // Couleurs de contour selon le mode
        $borderColors = [
            'actif' => 'border-success-500 dark:border-success-400',
            'test' => 'border-info-500 dark:border-info-400',
            'inactif' => 'border-gray-400 dark:border-gray-500',
        ];

        // Icône en haut à gauche selon le openMode
        $topLeftIcon = match ($this->openMode) {
            'results' => 'heroicon-o-eye',
            default => 'heroicon-o-pencil', // edit ou view
        };

        // Icône en bas à droite selon le mode
        $bottomRightIcons = [
            'actif' => 'heroicon-o-check',
            'test' => 'heroicon-o-beaker', // icône chimie
            'inactif' => 'heroicon-o-x-mark',
        ];

        // Couleurs de fond pour les icônes (même couleur que le contour)
        $iconBackgroundColors = [
            'actif' => 'bg-success-500 dark:bg-success-600',
            'test' => 'bg-info-500 dark:bg-info-600',
            'inactif' => 'bg-gray-400 dark:bg-gray-500',
        ];

        $style = [
            'border_color' => $borderColors[$mode] ?? $borderColors['inactif'],
            'background_color' => 'bg-white dark:bg-gray-800', // Fond par défaut
            'icon_background_color' => $iconBackgroundColors[$mode] ?? $iconBackgroundColors['inactif'],
            'top_left_icon' => $topLeftIcon,
            'bottom_right_icon' => $bottomRightIcons[$mode] ?? $bottomRightIcons['inactif'],
        ];

        // En mode résultats, on détermine le fond selon le statut
        if ($this->openMode === 'results') {
            $serviceResults = $record->services_results[$serviceKey] ?? null;
            $status = $serviceResults['status'] ?? 'unknown';

            // Le statut est maintenant directement utilisable
            $finalStatus = $this->determineFinalStatus($status);
            $style['status'] = $finalStatus;

            // Couleur de fond selon le statut en mode résultats
            $style['background_color'] = match ($finalStatus) {
                'success' => 'bg-success-500 dark:bg-success-600',
                'blocked' => 'bg-gray-500 dark:bg-gray-600', // Fond gris pour blocked
                'error' => 'bg-danger-500 dark:bg-danger-600',
                'test' => 'bg-info-500 dark:bg-info-600',
                default => 'bg-gray-500 dark:bg-gray-600',
            };
        }

        return $style;
    }

    /**
     * Détermine le statut final basé sur les résultats (simplifié)
     */
    protected function determineFinalStatus(?string $status): string
    {
        return match ($status) {
            'success' => 'success',
            'blocked' => 'blocked',
            'error' => 'error',
            'processing' => 'processing',
            default => 'unknown',
        };
    }

    public function render()
    {
        return view('livewire.tables.mail-service-cell');
    }
}

<?php

namespace App\Services\MsGraph;

use RuntimeException;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\ViewComponent;
use App\Services\EmailsProcessorRegisterServices;

/**
 * Générateur de formulaires et infolists pour les services d'email processing.
 * 
 * Responsabilités :
 * - buildResultsSchema() : Génère l'infolist des résultats pour les relations (onglets multi-services)
 * - buildSingleServiceForm() : Génère le formulaire d'édition d'un service unique (Livewire)
 * - buildSingleServiceInfo() : Génère l'infolist d'affichage d'un service unique (Livewire)
 * - buildSingleServiceResults() : Génère l'infolist des résultats d'un service unique
 */
class ServiceFormBuilder
{
    // =========================
    // Public API
    // =========================

    /**
     * Génère l'infolist des résultats pour tous les services actifs (onglets).
     * Utilisé dans les RelationManagers pour afficher les résultats des traitements.
     */
    public static function buildResultsSchema(string $serviceType, $record = null): array
    {
        $services = static::getServices($serviceType);
        $tabs = [];

        foreach ($services as $serviceKey => $service) {
            $serviceClass = $service['class'];
            $mode = static::mode($record, $serviceKey) ?? 'inactif';

            // masquer les services inactifs
            if ($mode === 'inactif') {
                continue;
            }

            $hasResults = static::getResult($record, $serviceKey, 'success') !== null;

            $components = array_merge(
                static::getBaseResultsInfoListComponents($record, $serviceKey, $service, $serviceClass),
                method_exists($serviceClass, 'getResultsInfoList') ? $serviceClass::getResultsInfoList() : []
            );

            $components = static::fillComponents($components, $record, $serviceKey, $serviceType, forInfoList: true, fillResults: true);

            // badge résultat
            [$badge, $badgeColor] = static::resultBadgeAndColor(
                status: static::getResult($record, $serviceKey, 'status'),
                success: static::getResult($record, $serviceKey, 'success'),
                mode: $mode,
                hasResults: $hasResults
            );

            $tabs[] = static::makeTab(
                label: $service['label'],
                icon: static::serviceIcon($serviceClass),
                badge: $badge,
                badgeColor: $badgeColor,
                schema: $components
            );
        }

        return [Tabs::make('results_tabs')->tabs($tabs)];
    }

    /**
     * Génère le formulaire d'édition d'un service unique.
     * Utilisé par MailServiceCell en mode 'edit'.
     */
    public static function buildSingleServiceForm(string $serviceType, $record, string $serviceKey): array
    {
        $serviceClass = static::getServiceClass($serviceType, $serviceKey);
        if (!$serviceClass || !method_exists($serviceClass, 'getForm')) {
            throw new RuntimeException("Le service '{$serviceKey}' n'expose pas getForm().");
        }

        $fields = array_merge(
            static::getBaseFormFields(),
            $serviceClass::getForm()
        );

        $fields = static::fillComponents($fields, $record, $serviceKey, $serviceType, forInfoList: false);

        return [
            Group::make()
                ->schema($fields)
                ->statePath($serviceKey),
        ];
    }

    /**
     * Génère l'infolist d'affichage d'un service unique.
     * Utilisé par MailServiceCell en mode 'view'.
     */
    public static function buildSingleServiceInfo(string $serviceType, $record, string $serviceKey): array
    {
        $serviceClass = static::getServiceClass($serviceType, $serviceKey);

        if (!$serviceClass || !method_exists($serviceClass, 'getInfoList')) {
            // Si pas d'info list spécifique, on retombe sur la base
            $components = static::getBaseInfoListComponents(
                $record,
                $serviceKey,
                EmailsProcessorRegisterServices::getAll($serviceType)[$serviceKey] ?? [],
                $serviceClass ?? ''
            );
        } else {
            $components = array_merge(
                static::getBaseInfoListComponents(
                    $record,
                    $serviceKey,
                    EmailsProcessorRegisterServices::getAll($serviceType)[$serviceKey] ?? [],
                    $serviceClass
                ),
                $serviceClass::getInfoList()
            );
        }

        $components = static::fillComponents($components, $record, $serviceKey, $serviceType, forInfoList: true);

        return [
            Group::make()
                ->schema($components),
        ];
    }

    /**
     * Génère l'infolist des résultats d'un service unique.
     * Utile pour ouvrir les résultats d'un service dans un modal.
     */
    public static function buildSingleServiceResults(string $serviceType, $record, string $serviceKey): array
    {
        $serviceClass = static::getServiceClass($serviceType, $serviceKey);

        $components = array_merge(
            static::getBaseResultsInfoListComponents(
                $record,
                $serviceKey,
                EmailsProcessorRegisterServices::getAll($serviceType)[$serviceKey] ?? [],
                $serviceClass ?? ''
            ),
            method_exists($serviceClass, 'getResultsInfoList') ? $serviceClass::getResultsInfoList() : []
        );

        // Ici on remplit avec les *résultats* (pas options)
        $components = static::fillComponents($components, $record, $serviceKey, $serviceType, forInfoList: true, fillResults: true);

        // Log pour debug
        \Log::info("ServiceFormBuilder::buildSingleServiceResults - Final components", [
            'serviceKey' => $serviceKey,
            'totalComponentsCount' => count($components),
            'servicesResults' => $record->services_results[$serviceKey] ?? null,
        ]);

        return [
            Group::make()
                ->schema($components),
        ];
    }

    // =========================
    // Composants de base
    // =========================

    private static function getBaseFormFields(): array
    {
        return [
            \Filament\Forms\Components\Select::make('mode')
                ->label('Mode de fonctionnement')
                ->options([
                    'inactif' => 'Inactif',
                    'actif'   => 'Actif',
                    'test'    => 'Test',
                ])
                ->reactive()
                ->helperText('Choisissez le mode de fonctionnement du service'),
        ];
    }

    private static function getBaseInfoListComponents($record, string $serviceKey, array $service, string $serviceClass): array
    {
        $components = [];

        $components[] = TextEntry::make('service_info')
            ->label('Service')
            ->formatStateUsing(fn() => $service['label'])
            ->icon(static::serviceIcon($serviceClass));

        if ($record) {
            $mode = static::mode($record, $serviceKey) ?? 'inactif';
            $components[] = TextEntry::make('mode')
                ->label('Mode de fonctionnement')
                ->formatStateUsing(fn() => ucfirst($mode))
                ->badge()
                ->color(static::modeBadgeColor($mode));
        }

        return $components;
    }

    private static function getBaseResultsInfoListComponents($record, string $serviceKey, array $service, string $serviceClass): array
    {
        if (!$record) return [];

        $status = static::getResult($record, $serviceKey, 'status', 'unknown');
        $mode   = static::mode($record, $serviceKey) ?? 'inactif';

        $components = [];

        // Statut principal
        $components[] = TextEntry::make('status')
            ->label('Statut')
            ->formatStateUsing(fn() => static::statusLabel($status))
            ->badge()
            ->color(static::statusColor($status))
            ->icon(static::statusIcon($status));

        // Code détecté
        if ($code = static::getResult($record, $serviceKey, 'code')) {
            $components[] = TextEntry::make('code')
                ->label('Code détecté')
                ->formatStateUsing(fn() => "## {$code} ##")
                ->badge()
                ->color('primary')
                ->icon('heroicon-o-hashtag');
        }

        // Message unifié (remplace blocked_message et error_message)
        if ($message = static::getResult($record, $serviceKey, 'message')) {
            $color = match ($status) {
                'blocked' => 'warning',
                'error' => 'danger',
                'success' => 'success',
                default => 'info',
            };

            $icon = match ($status) {
                'blocked' => 'heroicon-o-pause-circle',
                'error' => 'heroicon-o-exclamation-triangle',
                'success' => 'heroicon-o-check-circle',
                default => 'heroicon-o-information-circle',
            };

            $components[] = TextEntry::make('message')
                ->label('Message')
                ->formatStateUsing(fn() => $message)
                ->color($color)
                ->icon($icon);
        }

        $errors = static::getResult($record, $serviceKey, 'errors');
        if (!empty($errors) && is_array($errors)) {
            $components[] = TextEntry::make('errors')
                ->label('Erreurs détaillées')
                ->formatStateUsing(fn() => implode(', ', $errors))
                ->color('danger')
                ->icon('heroicon-o-x-circle');
        }

        return $components;
    }

    // =========================
    // Helpers
    // =========================

    private static function makeTab(string $label, string $icon, string $badge, string $badgeColor, array $schema): Tab
    {
        return Tab::make($label)
            ->icon($icon)
            ->badge($badge)
            ->badgeColor($badgeColor)
            ->schema($schema);
    }

    private static function fillComponents(array $components, $record, string $serviceKey, string $serviceType, bool $forInfoList, bool $fillResults = false): array
    {
        return array_map(function ($component) use ($record, $serviceKey, $serviceType, $forInfoList, $fillResults) {
            if (!method_exists($component, 'getName')) {
                return $component;
            }

            $name = $component->getName();

            // 1) Valeur actuelle (results ou options)
            $currentValue = null;
            if ($record) {
                $currentValue = $fillResults
                    ? static::getResult($record, $serviceKey, $name)
                    : static::getOption($record, $serviceKey, $name);
            }

            // 2) Sinon fallback sur defaults du service (options uniquement)
            if ($currentValue === null && !$fillResults) {
                $serviceClass = static::getServiceClass($serviceType, $serviceKey);
                if ($serviceClass && method_exists($serviceClass, 'getDefaults')) {
                    $defaults = $serviceClass::getDefaults();
                    $currentValue = $defaults[$name] ?? null;
                }
            }

            // 3) Affectation : default() pour formulaires, state() pour Infolists
            if ($currentValue !== null) {
                if ($forInfoList && method_exists($component, 'state')) {
                    $component->state($currentValue);
                } elseif (!$forInfoList && method_exists($component, 'default')) {
                    $component->default($currentValue);
                }
            }

            return $component;
        }, $components);
    }

    // =========================
    // Accesseurs et mappings
    // =========================

    private static function getServices(string $serviceType): array
    {
        return EmailsProcessorRegisterServices::getAll($serviceType);
    }

    private static function getServiceClass(string $serviceType, string $serviceKey): ?string
    {
        $services = static::getServices($serviceType);
        return $services[$serviceKey]['class'] ?? null;
    }

    private static function serviceIcon(string $serviceClass): string
    {
        return method_exists($serviceClass, 'getIcon') ? $serviceClass::getIcon() : 'heroicon-o-cog';
    }

    private static function mode($record, string $serviceKey): ?string
    {
        return $record ? $record->getServiceOption($serviceKey, 'mode', 'inactif') : null;
    }

    private static function modeBadgeColor(?string $mode): string
    {
        return match ($mode) {
            'actif' => 'success',
            'test'  => 'info',
            default => 'gray',
        };
    }

    private static function statusLabel(?string $status): string
    {
        return match ($status) {
            'success' => 'Succès',
            'blocked' => 'Bloqué',
            'error'   => 'Erreur',
            default   => 'Inconnu',
        };
    }

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            'success' => 'success',
            'blocked' => 'warning',
            'error'   => 'danger',
            default   => 'gray',
        };
    }

    private static function statusIcon(?string $status): string
    {
        return match ($status) {
            'success' => 'heroicon-o-check-circle',
            'blocked' => 'heroicon-o-pause-circle',
            'error'   => 'heroicon-o-x-circle',
            default   => 'heroicon-o-question-mark-circle',
        };
    }

    private static function resultBadgeAndColor(?string $status, ?bool $success, string $mode, bool $hasResults): array
    {
        if (!$hasResults) {
            return ['En attente', 'warning'];
        }

        return match ($status) {
            'success' => [$mode === 'test' ? 'Succès (Test)' : 'Succès', $mode === 'test' ? 'info' : 'success'],
            'blocked' => [$mode === 'test' ? 'Bloqué (Test)' : 'Bloqué', 'warning'],
            'error'   => [$mode === 'test' ? 'Erreur (Test)' : 'Erreur', 'danger'],
            default   => [
                // Fallback legacy sur boolean "success"
                $mode === 'test' ? ($success ? 'Succès (Test)' : 'Erreur (Test)') : ($success ? 'Succès' : 'Erreur'),
                $success ? ($mode === 'test' ? 'info' : 'success') : 'danger'
            ]
        };
    }



    private static function getOption($record, string $serviceKey, string $optionKey, $default = null)
    {
        if (! $record) {
            return $default;
        }

        // Récupère toutes les options du service :
        $options = $record->services_options[$serviceKey] ?? [];

        // Supporte les clés simples et imbriquées : "a.b.c"
        return data_get($options, $optionKey, $default);
    }

    private static function getResult($record, string $serviceKey, string $resultKey, $default = null)
    {
        if (! $record) {
            return $default;
        }

        // Récupère toutes les résultats du service :
        $results = $record->services_results[$serviceKey] ?? [];

        // Accès direct aux données (plus de sous-niveau facts)
        return data_get($results, $resultKey, $default);
    }
}

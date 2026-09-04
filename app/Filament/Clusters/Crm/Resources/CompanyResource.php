<?php

namespace App\Filament\Clusters\Crm\Resources;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use App\Enums\Country;
use Filament\Forms\Components\ColorPicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Clusters\Crm\Resources\CompanyResource\Pages\ListCompanies;
use App\Filament\Clusters\Crm\Resources\CompanyResource\Pages\CreateCompany;
use App\Filament\Clusters\Crm\Resources\CompanyResource\Pages\EditCompany;
use Filament\Forms;
use Filament\Tables;
use App\Models\Sector;
use App\Models\Company;
use App\Enums\CompanyType;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use App\Filament\Clusters\Crm;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use App\Filament\Utils\ImageUtils;
use Filament\Forms\Components\FileUpload;
use App\Filament\Components\Tables\DateColumn;
use App\Filament\Components\Tables\DateTimeColumn;
use App\Filament\Components\Forms\CloudinaryFileUpload;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use App\Filament\Clusters\Crm\Resources\CompanyResource\Pages;
use App\Filament\Clusters\Crm\Resources\CompanyResource\RelationManagers\ContactsRelationManager;
use App\Filament\Clusters\Crm\Resources\CompanyResource\RelationManagers\ProductsRelationManager;
use App\Filament\Clusters\Crm\Resources\CompanyResource\RelationManagers\InvoicesRelationManager;
use App\Filament\Clusters\Crm\Resources\CompanyResource\RelationManagers\QuotesRelationManager;
use Filament\Actions\DeleteAction;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-s-building-office';

    protected static ?string $cluster = Crm::class;

    public $colorsForColorPicker = [];

    public static function getLabel(): string
    {
        return 'Clients';
    }


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Flex::make([
                    Section::make([
                        Fieldset::make('Informations générales')
                            ->schema([
                                Toggle::make('is_ex')->columnSpanFull(),
                                TextInput::make('title')->label('Nom entreprise')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (callable $set, $state) {
                                        $set('slug', Str::slug($state));
                                    })
                                    ->maxLength(255),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255),
                                Select::make('type')
                                    ->label('Type de structure')
                                    ->options(
                                        collect(CompanyType::cases())
                                            ->groupBy(fn($case) => $case->category())
                                            ->map(fn($group) => $group->mapWithKeys(fn($case) => [$case->name => $case->label()]))
                                            ->toArray()
                                    )
                                    ->searchable()
                                    ->required(),
                                Select::make('sector_id')
                                    ->relationship(name: 'sector', titleAttribute: 'title')->options(Sector::selectArrayNested()),
                                TextInput::make('nb_collab')
                                    ->numeric()
                                    ->default(10),
                                TextInput::make('site_url')
                                    ->maxLength(255),
                                TextInput::make('email')
                                    ->email()
                                    ->maxLength(255),
                                TextInput::make('siret')
                                    ->maxLength(255),
                                TextInput::make('vat_number')
                                    ->label('TVA intracom')
                                    ->maxLength(255),
                                TextInput::make('tax_identification_number')
                                    ->label('Identifiant fiscal')
                                    ->helperText('Pour la France : SIREN ou SIRET. Utilisé par Qonto pour la facturation.')
                                    ->maxLength(255),
                                TextInput::make('e_invoicing_address')
                                    ->label('Adresse e-invoicing')
                                    ->helperText('Adresse Annuaire pour la facture électronique française si différente du SIREN/SIRET.')
                                    ->maxLength(255),
                                TextInput::make('qonto_client_id')
                                    ->label('ID client Qonto')
                                    ->helperText('Renseigné automatiquement après création ou rapprochement Qonto.')
                                    ->maxLength(255),
                                Toggle::make('qonto_e_invoicing_reachable')
                                    ->label('Joignable e-invoicing Qonto')
                                    ->disabled(),
                            ])
                            ->columns([
                                'sm' => 1, // Mobile: 1 colonne
                                'md' => 2, // Écran normal: 4 colonnes
                            ]),
                        Section::make('Localisation')
                            ->headerActions([
                                Action::make('extractAddress')
                                    ->label('Extraire adresse')
                                    ->icon('heroicon-o-map-pin')
                                    ->schema([
                                        Textarea::make('raw_address')
                                            ->label('Adresse complète')
                                            ->rows(5)
                                            ->required(),
                                    ])
                                    ->modalSubmitActionLabel('Extraire')
                                    ->action(function (array $data, callable $set): void {
                                        $parsed = self::parsePostalAddress((string) ($data['raw_address'] ?? ''));

                                        foreach ($parsed as $field => $value) {
                                            if (filled($value)) {
                                                $set($field, $value);
                                            }
                                        }

                                        Notification::make()
                                            ->title('Adresse extraite')
                                            ->body($parsed['cp'] ? 'Code postal et ville détectés.' : 'Adresse reprise, code postal non détecté.')
                                            ->success()
                                            ->send();
                                    }),
                            ])
                            ->schema([
                                Textarea::make('address')
                                    ->label('Adresse')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                TextInput::make('cp')
                                    ->label('Code postal')
                                    ->maxLength(20),
                                TextInput::make('city')
                                    ->label('Ville')
                                    ->maxLength(255),
                                Select::make('country')
                                    ->label('Pays')
                                    ->options(Country::options())
                                    ->default(Country::France->value),
                                TextInput::make('tel')
                                    ->label('Téléphone')
                                    ->tel()
                                    ->maxLength(255),
                                TextInput::make('longitude')
                                    ->numeric(),
                                TextInput::make('latitude')
                                    ->numeric(),
                                TextInput::make('distance')
                                    ->numeric(),
                            ])
                            ->columns([
                                'sm' => 1,
                                'md' => 4,
                            ]),
                        Fieldset::make('Paramètres et autres')
                            ->schema([

                                // Forms\Components\TextInput::make('others')
                                //     ->maxLength(255),
                                Textarea::make('memo')
                                    ->columnSpanFull(),
                            ])
                            ->columns([
                                'sm' => 1,
                                'md' => 4,
                            ]),
                    ])->compact(),
                    Section::make('Style')
                        ->schema([
                            SpatieMediaLibraryFileUpload::make('logo')
                                ->collection('logo')
                                ->label('Logo'),
                            CloudinaryFileUpload::make('logo_cloudinary')
                                ->label('Logo Cloudinary')
                                ->relation('logo_cloudinary')
                                ->dehydrated(false), // on ne stocke pas dans la colonne du modèle
                            ColorPicker::make('primary_color')
                                ->label('Couleur primaire')
                                ->suffixAction(ImageUtils::getPalettesFromImage('logo', 'primary_color')),
                            ColorPicker::make('secondary_color')
                                ->label('Couleur secondaire')
                                ->suffixAction(ImageUtils::getPalettesFromImage('logo', 'secondary_color')),
                        ])
                        ->grow(false)
                        ->compact()
                        ->columns(1),
                ])->from('md')->columnSpanFull()
            ]);
    }

    protected static function parsePostalAddress(string $rawAddress): array
    {
        $lines = collect(preg_split('/\R+/', trim($rawAddress)) ?: [])
            ->map(fn (string $line): string => trim(preg_replace('/\s+/', ' ', $line)))
            ->filter()
            ->values();

        $country = Country::France->value;

        if ($lines->isNotEmpty()) {
            $lastLine = $lines->last();
            $countryCode = self::countryCodeFromLine($lastLine);

            if ($countryCode) {
                $country = $countryCode;
                $lines = $lines->slice(0, -1)->values();
            }
        }

        $addressLines = $lines->all();
        $zipCode = null;
        $city = null;

        foreach ($lines as $index => $line) {
            if (! preg_match('/\b(?:[A-Z]{1,3}[-\s])?(\d{4,5})\b\s*(.*)$/u', $line, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $zipCode = $matches[1][0];
            $city = trim($matches[2][0] ?? '');
            $lineAddress = trim(substr($line, 0, $matches[0][1]));
            $addressLines = array_slice($lines->all(), 0, $index);

            if ($lineAddress !== '') {
                $addressLines[] = $lineAddress;
            }

            if ($city === '' && $lines->has($index + 1)) {
                $city = $lines->get($index + 1);
            }

            break;
        }

        $city = self::cleanParsedCity((string) $city);

        return [
            'address' => trim(implode(PHP_EOL, array_filter($addressLines))),
            'cp' => $zipCode,
            'city' => $city !== '' ? $city : null,
            'country' => $country,
        ];
    }

    protected static function countryCodeFromLine(string $line): ?string
    {
        $normalized = Str::of($line)->ascii()->upper()->trim()->toString();

        if (in_array($normalized, ['FR', 'FRA', 'FRANCE'], true)) {
            return Country::France->value;
        }

        foreach (Country::cases() as $country) {
            if (in_array($normalized, [Str::of($country->label())->ascii()->upper()->toString(), $country->value], true)) {
                return $country->value;
            }
        }

        return null;
    }

    protected static function cleanParsedCity(string $city): string
    {
        return trim(preg_replace('/\b(FR|FRA|FRANCE)\b/iu', '', $city));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')
                    ->searchable()
                    ->description(fn($record): string => \Str::limit($record->slug, 35))
                    ->searchable(['slug', 'title']),
                IconColumn::make('qonto_export_status')
                    ->label('Qonto')
                    ->icon(fn (Company $record): string => $record->qonto_export_status_icon)
                    ->color(fn (Company $record): string => $record->qonto_export_status_color)
                    ->tooltip(fn (Company $record): string => $record->qonto_export_status_description),
                TextColumn::make('sector.title')
                    ->sortable()->searchable(),
                TextColumn::make('qonto_client_id')
                    ->label('ID Qonto')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('qonto_e_invoicing_reachable')
                    ->label('E-invoicing')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('vat_number')
                    ->label('TVA')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contacts_count')
                    ->label('NB contacts')
                    ->counts('contacts')
                    ->sortable(),
                IconColumn::make('is_ex')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('distance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('others')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                DateColumn::make('deleted_at')
                    ->toggleable(isToggledHiddenByDefault: true),
                DateColumn::make('created_at')
                    ->toggleable(isToggledHiddenByDefault: true),
                DateColumn::make('updated_at')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('title', 'asc')
            ->filters([
                TernaryFilter::make('is_ex')->label('Exemple')->default(false),
                SelectFilter::make('sector')
                    ->label('Secteur')
                    ->relationship('sector', 'title'), // Assuming 'company' is a valid relationship
                TernaryFilter::make('qonto_e_invoicing_reachable')
                    ->label('Joignable e-invoicing Qonto'),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton(),
                DeleteAction::make()
                    ->iconButton(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProductsRelationManager::class,
            ContactsRelationManager::class,
            InvoicesRelationManager::class,
            QuotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}

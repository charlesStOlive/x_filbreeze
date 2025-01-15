<?php

namespace App\Filament\Clusters\Crm\Pages;

use App\Filament\Clusters\Crm;
use Filament\Pages\Page;

class FormInfoList extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.clusters.crm.pages.form-info-list';

    protected static ?string $cluster = Crm::class;
}

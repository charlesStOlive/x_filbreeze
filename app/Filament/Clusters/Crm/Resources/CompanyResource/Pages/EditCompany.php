<?php

namespace App\Filament\Clusters\Crm\Resources\CompanyResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ActionGroup;
use Filament\Forms;
use Filament\Actions;
use App\Dto\MsGraph\EmailMessageDTO;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Services\MsGraph\MsGraphEmailService;
use App\Filament\Clusters\Crm\Resources\CompanyResource;
use App\Services\Pdf\Filament\Actions\GeneratePdfDownload;
use App\Services\MsGraph\EmailDraft\Filament\Actions\GenerateMsGraphEmailDraft;

class EditCompany extends EditRecord
{
    protected static string $resource = CompanyResource::class;



    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ActionGroup::make([
                GenerateMsGraphEmailDraft::make('generateEmailDraft'),
                GeneratePdfDownload::make('downloadPdf')
            ])->label('Produire')
                ->icon('fas-file-export')
                ->button()
                ->color('gray'),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }
}

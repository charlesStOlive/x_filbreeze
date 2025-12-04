<?php

namespace App\Filament\Components\Forms;

use Filament\Actions\Action;
use Filament\Schemas\Components\Utilities\Set;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Log;
use Filament\Forms\Components\FileUpload;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class CloudinaryFileUpload extends FileUpload
{
    protected string $view = 'filament.forms.components.cloudinary-file-upload';

    protected ?int $previewWidth = 200;
    protected string|int|null $previewHeight = null;
    protected string $relationName = 'logo_cloudinary';
    protected ?string $uploadPreset = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerActions([
            Action::make('delete')
                ->label('S')
                ->icon('heroicon-s-trash')
                ->color('danger')
                ->visible(fn($livewire) => filled($this->getPublicIdFromRecord()))
                ->action(function (Set $set) {
                    $this->deleteImageFromCloudinary();
                    $this->state([]); // ✅ Filament détecte la maj du champ
                    $set($this->getStatePath(), []); // ✅ sécurisation côté form parent
                }),
        ]);

        $this->afterStateUpdated(function ($state, $component, $livewire) {
            $record = $livewire->getRecord();

            if (! $record || !$state instanceof TemporaryUploadedFile) {
                // Log::info('ABANDON-------------------------');
                return;
            }

            $upload = Cloudinary::uploadApi()->upload($state->getRealPath(), [
                'folder' => 'companies/logos',
            ]);
            $relation = $this->getRelationName();

            $record->{$relation}()?->delete();

            $record->{$relation}()->create([
                'file_name' => $state->getClientOriginalName(),
                'url' => $upload['secure_url'],
                'public_id' => $upload['public_id'],
            ]);
        });
    }

    public function previewWidth(?int $width): static
    {
        $this->previewWidth = $width;
        return $this;
    }

    public function getPreviewWidth(): ?int
    {
        return $this->previewWidth;
    }

    public function previewHeight(string|int|null $height): static
    {
        $this->previewHeight = $height;
        return $this;
    }

    public function getPreviewHeight(): string|int|null
    {
        return $this->previewHeight;
    }

    public function relation(string $relation): static
    {
        $this->relationName = $relation;
        return $this;
    }

    public function getRelationName(): string
    {
        return $this->relationName;
    }

    public function uploadPreset(?string $preset): static
    {
        $this->uploadPreset = $preset;
        return $this;
    }

    public function getUploadPreset(): ?string
    {
        return $this->uploadPreset;
    }

    public function getUploadedFiles(): ?array
    {
        $record = $this->getLivewire()?->getRecord();

        if (! $record || ! method_exists($record, $this->getRelationName())) {
            return [];
        }

        $resource = $record->{$this->getRelationName()};

        if (!$resource || !$resource->exists()) {
            return [];
        }

        return [$resource->url];
    }

    public function getPublicIdFromRecord(): ?string
    {
        $record = $this->getLivewire()?->getRecord();

        if (! $record || ! method_exists($record, $this->getRelationName())) {
            return null;
        }

        return $record->{$this->getRelationName()}?->public_id;
    }

    public function deleteImageFromCloudinary(): void
    {
        // Log::info('State Path: ' . $this->getStatePath());
        // Log::info('delete image');
        $record = $this->getLivewire()?->getRecord();

        if (! $record || ! method_exists($record, $this->getRelationName())) {
            return;
        }

        $relation = $this->getRelationName();
        $image = $record->{$relation};

        if (!$image) {
            return;
        }

        Cloudinary::uploadApi()->destroy($image->public_id);
        $image->delete();
        $record->refresh(); // ✅ recharge depuis la DB
        $this->state([]);   // ✅ reset du Field state
        // Log::info('State Path on end !!!!: ' . $this->getStatePath());
    }
}

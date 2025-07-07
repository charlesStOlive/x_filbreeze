<?php 

namespace App\Services\Imports;

use App\Traits\SendsNotifications;
use App\Contracts\HasImportForm;

abstract class BaseFilImporter implements HasImportForm
{
    use SendsNotifications;

    public array $errors = [];
    public int $created = 0;
    public int $updated = 0;
    protected array $options = [];

    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    public function finalize(): void
    {
        if (empty($this->errors)) {
            $this->notifySuccess(
                'Import terminé',
                "✅ {$this->created} créés, {$this->updated} mis à jour"
            );
        } else {
            $message = "❌ {$this->created} créés, {$this->updated} mis à jour, " . count($this->errors) . " erreurs.";
            $this->notifyError('Import partiellement échoué', $message);
        }
    }
}

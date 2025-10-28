<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Invoice;
use CharlesStOlive\FilamentStateFusionEnhanced\Services\StateParserService;
use App\Services\Formatters\MermaidFormatter;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "🚀 Test de la nouvelle API fluide StateParserService\n";
echo "=" . str_repeat("=", 55) . "\n\n";

try {
    // Récupérer une facture
    $invoice = Invoice::first();

    if (!$invoice) {
        echo "❌ Aucune facture trouvée dans la base de données.\n";
        exit(1);
    }

    echo "📋 Facture testée: #{$invoice->id}\n";
    echo "État actuel: " . ($invoice->state ? $invoice->state->getValue() : 'Non défini') . "\n\n";

    // Test 1: Nouvelle API fluide avec constructor
    echo "🔹 1. NOUVELLE API FLUIDE - Constructor + format()\n";
    echo "-" . str_repeat("-", 50) . "\n";

    $parser = new StateParserService($invoice);
    $mermaidResult = $parser->format(MermaidFormatter::class);

    echo "Mermaid généré (" . strlen($mermaidResult) . " caractères):\n";
    echo substr($mermaidResult, 0, 200) . "...\n\n";

    // Test 2: API fluide - toArray()
    echo "🔹 2. API FLUIDE - toArray()\n";
    echo "-" . str_repeat("-", 50) . "\n";

    $arrayResult = $parser->toArray();
    echo "Array data:\n";
    echo "- Model: " . $arrayResult['model']['name'] . "\n";
    echo "- States: " . count($arrayResult['states']) . " états\n";
    echo "- Transitions: " . count($arrayResult['transitions']) . " transitions\n\n";

    // Test 3: API fluide - toJson()
    echo "🔹 3. API FLUIDE - toJson()\n";
    echo "-" . str_repeat("-", 50) . "\n";

    $jsonResult = $parser->toJson(['pretty' => true]);
    echo "JSON généré (" . strlen($jsonResult) . " caractères):\n";
    echo substr($jsonResult, 0, 300) . "...\n\n";

    // Test 4: toJson avec différents formats
    echo "🔹 4. FORMATS JSON DIFFÉRENTS\n";
    echo "-" . str_repeat("-", 50) . "\n";

    $formats = ['standard', 'compact', 'api', 'detailed'];

    foreach ($formats as $format) {
        $jsonFormatted = $parser->toJson(['format' => $format]);
        echo "Format {$format}: " . strlen($jsonFormatted) . " caractères\n";
    }
    echo "\n";

    // Test 5: Constructor avec string class
    echo "🔹 5. CONSTRUCTOR AVEC CLASS STRING\n";
    echo "-" . str_repeat("-", 50) . "\n";

    $parser2 = new StateParserService(Invoice::class);
    $arrayResult2 = $parser2->toArray();
    echo "Parser avec class string: " . $arrayResult2['model']['name'] . "\n";
    echo "States trouvés: " . count($arrayResult2['states']) . "\n\n";

    // Test 6: Chaînage fluide avec options
    echo "🔹 6. CHAÎNAGE AVEC OPTIONS\n";
    echo "-" . str_repeat("-", 50) . "\n";

    $compactJson = (new StateParserService($invoice))->toJson([
        'format' => 'compact',
        'pretty' => true
    ]);

    echo "JSON compact (" . strlen($compactJson) . " caractères):\n";
    echo $compactJson . "\n\n";

    // Sauvegarder les résultats
    file_put_contents('test_output_fluent_mermaid.md', $mermaidResult);
    file_put_contents('test_output_fluent.json', $parser->toJson(['pretty' => true]));
    file_put_contents('test_output_fluent_compact.json', $compactJson);

    echo "📁 Fichiers de sortie créés:\n";
    echo "  - test_output_fluent_mermaid.md\n";
    echo "  - test_output_fluent.json\n";
    echo "  - test_output_fluent_compact.json\n";

    echo "\n✅ Tests de l'API fluide terminés avec succès!\n";
} catch (Exception $e) {
    echo "❌ Erreur lors des tests: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

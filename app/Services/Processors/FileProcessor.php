<?php

namespace App\Services\Processors;

use Imagick;
use Smalot\PdfParser\Parser;
use thiagoalessio\TesseractOCR\TesseractOCR;
use App\Exceptions\Processor\FileInvalidFormatException;
use App\Exceptions\Processor\FileEmptyException;

class FileProcessor
{
    /**
     * Traite un fichier (PDF ou image) et retourne son contenu.
     *
     * @param string $filePath
     * @return array
     * @throws FileEmptyException
     * @throws UnsupportedFileTypeException
     */
    public function processFile(string $filePath): array
    {
        // Vérifier si le fichier existe
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException('Le fichier est introuvable : ' . $filePath);
        }

        // Identifier le type MIME du fichier
        $fileMime = mime_content_type($filePath);

        // Traiter selon le type de fichier
        if ($fileMime === 'application/pdf') {
            return $this->processPdf($filePath);
        } elseif (str_starts_with($fileMime, 'image/')) {
            return $this->processImage($filePath);
        }

        // Lever une exception si le type de fichier n'est pas pris en charge
        throw new FileInvalidFormatException("Le type de fichier '$fileMime' n'est pas pris en charge.");
    }

    /**
     * Traite un fichier PDF et retourne son contenu.
     *
     * @param string $pdfPath
     * @return array
     * @throws FileEmptyException
     */
    private function processPdf(string $pdfPath): array
    {
        // Essayer d'extraire le texte avec smalot/pdfparser
        $parser = new Parser();
        $pdf = $parser->parseFile($pdfPath);
        $text = $pdf->getText();

        // Vérifier si le contenu du texte est suffisant
        if (strlen(trim($text)) > 50) {
            return [
                'type' => 'text',
                'content' => $text
            ];
        }

        // Traiter le PDF comme un document basé sur des images
        $result = $this->processImagePdf($pdfPath);

        // Vérifier si le contenu est vide
        if (strlen(trim($result['content'])) === 0) {
            throw new FileEmptyException('Le contenu extrait du PDF est vide.');
        }

        return $result;
    }

    /**
     * Traite une image via OCR et retourne son contenu.
     *
     * @param string $imagePath
     * @return array
     */
    private function processImage(string $imagePath): array
    {
        $text = (new TesseractOCR($imagePath))
            ->lang('eng') // Modifier la langue si nécessaire (ex. 'fra' pour le français)
            ->run();

        if (strlen(trim($text)) === 0) {
            throw new FileEmptyException('Le contenu extrait de l\'image est vide.');
        }

        return [
            'type' => 'image',
            'content' => $text
        ];
    }

    /**
     * Traite un PDF contenant des images via OCR.
     *
     * @param string $pdfPath
     * @return array
     */
    private function processImagePdf(string $pdfPath): array
    {
        $imagick = new Imagick();
        try {
            // Configuration de l'Imagick
            $imagick->setResolution(300, 300);
            $imagick->readImage($pdfPath);
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompression(Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality(100);

            $fullText = '';

            // Traiter chaque page du PDF
            foreach ($imagick as $pageIndex => $page) {
                $tempImagePath = sys_get_temp_dir() . '/temp_page_' . $pageIndex . '.jpg';
                $page->writeImage($tempImagePath);

                // Exécuter Tesseract OCR sur l'image
                $text = (new TesseractOCR($tempImagePath))
                    ->lang('eng') // Modifier la langue si nécessaire
                    ->run();

                $fullText .= $text . "\n";

                // Supprimer le fichier temporaire
                if (file_exists($tempImagePath)) {
                    unlink($tempImagePath);
                }
            }

            return [
                'type' => 'image_pdf',
                'content' => $fullText
            ];
        } catch (\Exception $e) {
            throw new \RuntimeException('Erreur lors du traitement du PDF en mode image : ' . $e->getMessage());
        } finally {
            $imagick->clear();
            $imagick->destroy();
        }
    }
}

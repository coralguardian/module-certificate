<?php

namespace D4rk0snet\Certificate\Service;

use D4rk0snet\Adoption\Enums\AdoptedProduct;
use D4rk0snet\Certificate\Model\CertificateModel;
use Hyperion\Api2pdf\Service\Wkhtmlto;
use Exception;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use ZipArchive;

class CertificateService
{
    public static function createCertificate(CertificateModel $certificateModel): string
    {
        // Generate certificate
        $loader = new FilesystemLoader(__DIR__ . "/../Template");
        $twig = new Environment($loader); // @todo : Activer le cache
        $lang = $certificateModel->getLanguage()->value;

        if ($certificateModel->getAdoptedProduct() === AdoptedProduct::CORAL) {
            $file =  "coral-$lang.twig";
            $certificateModel->setProductPicture("corals/" . $certificateModel->getProductPicture());
        } else {
            $file = "reef-$lang.twig";
            $certificateModel->setProductPicture("reefs/" . $certificateModel->getProductPicture());
        }

        $html = $twig->load($file)->render(
            [
                'data' => $certificateModel->toArray(),
                'assets_path' => home_url("/app/plugins/certificate/assets/", "http")
            ]
        );

//        @todo: gérer le cas de noms identiques
        $imageTemporaryFilename = $lang === 'fr' ?
            __DIR__ . "/../../tmp/Coral_Guardian_Certificat_" . urlencode($certificateModel->getAdopteeName()) :
            __DIR__ . "/../../tmp/Coral_Guardian_Certificate_" . urlencode($certificateModel->getAdopteeName());

        Wkhtmlto::convertToImage($html, $imageTemporaryFilename);

        return $imageTemporaryFilename;
    }

    public static function createZipFile(array $certificateFiles, string $temporaryFilePathName): ZipArchive
    {
        try {
            $zip = new ZipArchive();
            if (false === $errorCode = $zip->open($temporaryFilePathName, ZipArchive::CREATE)) {
                throw new Exception("Unable to open Zip File (error code " . $errorCode . ")");
            }
            foreach ($certificateFiles as $certificate) {
                $certificate.= ".jpg";
                if (false === $errorCode = $zip->addFile($certificate, basename($certificate))) {
                    throw new Exception("Unable to add file to zip (error code " . $errorCode . ")");
                }
            }

            if (false === $zip->close()) {
                throw new Exception("Unable to write zip to disk !");
            }

            self::cleanTemporaryFiles($certificateFiles);

            return $zip;
        } catch (Exception $exception) {
            self::cleanTemporaryFiles($certificateFiles);
            throw new $exception;
        }
    }

    private static function cleanTemporaryFiles(array $certificateFiles): void
    {
        foreach ($certificateFiles as $certificateFile) {
            unlink($certificateFile . '.jpg');
            unlink($certificateFile . '.pdf');
        }
    }
}
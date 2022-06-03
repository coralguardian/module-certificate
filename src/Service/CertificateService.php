<?php

namespace D4rk0snet\Certificate\Service;

use D4rk0snet\Adoption\Enums\AdoptedProduct;
use D4rk0snet\Certificate\Endpoint\GetCertificateEndpoint;
use D4rk0snet\Certificate\Model\CertificateModel;
use Hyperion\Api2pdf\Service\Api2PdfService;
use Hyperion\Api2pdf\Service\Wkhtmlto;
use Exception;
use Hyperion\RestAPI\APIManagement;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use WP_REST_Response;
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
            $file = "coral-$lang.twig";
            $picturePath = "corals/" . $certificateModel->getProductPicture();
        } else {
            $file = "reef-$lang.twig";
            $picturePath = "reefs/" . $certificateModel->getProductPicture();
        }

        $html = $twig->load($file)->render(
            [
                'data' => $certificateModel->toArray(),
                'backgroundImg' => base64_encode(file_get_contents(__DIR__ . "/../../assets/img/bg-global-xl.jpg")),
                'productImg' => base64_encode(file_get_contents(__DIR__ . "/../../assets/img/$picturePath")),
                'transplantImg' => base64_encode(file_get_contents(__DIR__ . "/../../assets/img/seeders/" . $certificateModel->getSeeder()->getPicture())),
                'teamImg' => base64_encode(file_get_contents(__DIR__ . "/../../assets/img/coral-guardian-team.png")),
                'logoImg' => base64_encode(file_get_contents(__DIR__ . "/../../assets/img/logo-coral-guardian.png")),
                'stampImg' => base64_encode(file_get_contents(__DIR__ . "/../../assets/img/stamp.png")),
            ]
        );

        $pdf = Api2PdfService::convertHtmlToPdf(
            $html,
            false,
            "certificate-" . urlencode($certificateModel->getAdopteeName()) . ".pdf"
        );

        $fileName = $lang === 'fr' ?
            "Coral_Guardian_Certificat_" . urlencode($certificateModel->getAdopteeName()) :
            "Coral_Guardian_Certificate_" . urlencode($certificateModel->getAdopteeName());

        $imageTemporaryFilename = $certificateModel->getSaveFolder() . "/" . $fileName;

        file_put_contents($imageTemporaryFilename . ".pdf", file_get_contents($pdf));
        Wkhtmlto::convertToImage($imageTemporaryFilename, $fileName);
        unlink($imageTemporaryFilename . '.pdf');

        return $imageTemporaryFilename;
    }

    public static function downloadCertificates(string $certificatesPath): WP_REST_Response
    {
        // Création du zip
        $temporaryFilePathName = tempnam(sys_get_temp_dir(), "") . ".zip";
        CertificateService::createZipFile($certificatesPath, $temporaryFilePathName);

        // == Téléchargement du fichier zip par le navigateur ==
        $response = APIManagement::APIClientDownloadWithURL($temporaryFilePathName, "certificats.zip");
        unlink($temporaryFilePathName);
        return $response;
    }

    public static function createZipFile(string $certificateFolder, string $temporaryFilePathName): ZipArchive
    {
        try {
            $zip = new ZipArchive();
            if (false === $errorCode = $zip->open($temporaryFilePathName, ZipArchive::CREATE)) {
                throw new Exception("Unable to open Zip File (error code " . $errorCode . ")");
            }
            $files = array_diff(scandir($certificateFolder), array('..', '.'));
            foreach ($files as $certificate) {
                if (is_dir($folder = $certificateFolder . "/" . $certificate)) {
                    $newZipName = tempnam(sys_get_temp_dir(), "") . ".zip";
                    self::createZipFile($folder, $newZipName);
                    if (false === $errorCode = $zip->addFile($newZipName, $certificate . ".zip")) {
                        throw new Exception("Unable to add file to zip (error code " . $errorCode . ")");
                    }
                } else if (false === $errorCode = $zip->addFile($certificateFolder . "/" . $certificate, $certificate)) {
                    throw new Exception("Unable to add file to zip (error code " . $errorCode . ")");
                }
            }

            if (false === $zip->close()) {
                throw new Exception("Unable to write zip to disk !");
            }

            return $zip;
        } catch (Exception $exception) {
            unlink($temporaryFilePathName);
            throw new $exception;
        }
    }

    public static function getUrl(string $uuid)
    {
        return GetCertificateEndpoint::getUrl() . "?" . GetCertificateEndpoint::ORDER_UUID_PARAM . "=" . $uuid;
    }
}
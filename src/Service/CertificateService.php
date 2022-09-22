<?php

namespace D4rk0snet\Certificate\Service;

use D4rk0snet\Adoption\Entity\AdopteeEntity;
use D4rk0snet\Adoption\Entity\AdoptionEntity;
use D4rk0snet\Adoption\Entity\GiftAdoption;
use D4rk0snet\Adoption\Enums\AdoptedProduct;
use D4rk0snet\Certificate\Endpoint\GetCertificateByGiftEndpoint;
use D4rk0snet\Certificate\Endpoint\GetCertificateEndpoint;
use D4rk0snet\Certificate\Enums\CertificateState;
use D4rk0snet\Certificate\Model\CertificateModel;
use Hyperion\Api2pdf\Service\Api2PdfService;
use Hyperion\Api2pdf\Service\Wkhtmlto;
use Exception;
use Hyperion\Doctrine\Service\DoctrineService;
use Hyperion\RestAPI\APIManagement;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use WP_REST_Response;
use ZipArchive;

class CertificateService
{
    const BASE_SAVE_FOLDER = WP_CONTENT_DIR . "/uploads/certificates/";

    public static function createCertificate(CertificateModel $certificateModel): string
    {
        // Generate certificate
        $loader = new FilesystemLoader(__DIR__ . "/../Template");
        $twig = new Environment($loader); // @todo : Activer le cache
        $lang = $certificateModel->getLanguage()->value;

        if ($certificateModel->getAdoptedProduct() === AdoptedProduct::CORAL) {
            $picturePath = "corals/" . $certificateModel->getProductPicture();
            $type = $lang === "fr" ? "Corail" : "Coral";
        } else {
            $picturePath = "reefs/" . $certificateModel->getProductPicture();
            $type = $lang === "fr" ? "Recif" : "Reef";
        }

        $file = self::getTemplateFileName($certificateModel);

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
            "Coral_Guardian_Certificat_" . $type :
            "Coral_Guardian_Certificate_" . $type;

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

    public static function getUrl(string $uuid, bool $fromGift = false)
    {
        return $fromGift ? GetCertificateByGiftEndpoint::getUrl() . "?" . GetCertificateByGiftEndpoint::GIFT_CODE_PARAM . "=" . $uuid :
            GetCertificateEndpoint::getUrl() . "?" . GetCertificateEndpoint::ORDER_UUID_PARAM . "=" . $uuid;
    }

    public static function updateState(AdopteeEntity $adoptee, CertificateState $state = null): void
    {
        $adoptee->setState($state ?: $adoptee->getState()->nextState());
        DoctrineService::getEntityManager()->flush();
    }

    public static function generateCertificate(AdopteeEntity $adoptee, AdoptionEntity $adoptionEntity, string $saveFolder): void
    {
        $certificateModel = new CertificateModel(
            adoptedProduct: $adoptionEntity->getAdoptedProduct(),
            adopteeName: $adoptee->getName(),
            seeder: $adoptee->getSeeder(),
            date: $adoptionEntity->getDate(),
            language: $adoptionEntity->getLang(),
            productPicture: $adoptee->getPicture(),
            saveFolder: $saveFolder, 
            project: $adoptionEntity->getProject()
        );
        self::createCertificate($certificateModel);
    }

    public static function createFolders(string $dir): void
    {
        if (!is_dir(self::BASE_SAVE_FOLDER)) {
            mkdir(self::BASE_SAVE_FOLDER);
        }
        if (!is_dir($dir)) {
            mkdir($dir);
        }
    }

    public static function fullGenerationProcess(AdopteeEntity $adoptee) {
        $adoption = $adoptee->getAdoption();
        $folder = self::BASE_SAVE_FOLDER . $adoption->getUuid();

        if ($adoption instanceof GiftAdoption) {
            $giftCode = $adoptee->getGiftCode();

            if (null === $giftCode) {
                self::updateState($adoptee, CertificateState::GENERATION_ERROR);
                throw new Exception("Gift adoption should have a giftCode to generate certificate.");
            }

            self::createFolders($folder);
            $folder .= "/" . $giftCode->getGiftCode();
        }

        try {
            self::updateState($adoptee, CertificateState::GENERATING);
            self::createFolders($folder);
            self::generateCertificate($adoptee, $adoption, $folder);
            self::updateState($adoptee, CertificateState::GENERATED);
        } catch (\Exception $exception) {
            self::updateState($adoptee, CertificateState::GENERATION_ERROR);
            throw $exception;
        }
    }

    private static function getTemplateFileName(CertificateModel $certificateModel)
    {
        $project = $certificateModel->getProject()->value;
        $lang = $certificateModel->getLanguage()->value;
        if ($certificateModel->getAdoptedProduct() === AdoptedProduct::CORAL) {
            return "coral-$project-$lang.twig";
        } else {
            return "reef-$project-$lang.twig";
        }
    }
}
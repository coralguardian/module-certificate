<?php

namespace D4rk0snet\Certificate\Endpoint;

use D4rk0snet\Adoption\Entity\AdopteeEntity;
use D4rk0snet\Adoption\Entity\AdoptionEntity;
use D4rk0snet\Certificate\Service\CertificateService;
use D4rk0snet\Certificate\Model\CertificateModel;
use Hyperion\Doctrine\Service\DoctrineService;
use Hyperion\RestAPI\APIEnpointAbstract;
use Hyperion\RestAPI\APIManagement;
use WP_REST_Request;
use WP_REST_Response;

class GetCertificateEndpoint extends APIEnpointAbstract
{
    public const ORDER_UUID_PARAM = 'order_uuid';

    public static function callback(WP_REST_Request $request): WP_REST_Response
    {
        $orderUUID = $request->get_param(self::ORDER_UUID_PARAM);
        if ($orderUUID === null) {
            return APIManagement::APIError('Missing order uuid', 400);
        }

        /** @var AdoptionEntity $adoption */
        $adoption = DoctrineService::getEntityManager()->getRepository(AdoptionEntity::class)->find($orderUUID);
        if ($adoption === null) {
            return APIManagement::APIError('Adoption not found', 404);
        }

        $imageFilePathCollection = [];

        /** @var AdopteeEntity $adoptee */
        foreach ($adoption->getAdoptees() as $adoptee) {
            $certificateModel = new CertificateModel(
                adoptedProduct: $adoption->getAdoptedProduct(),
                adopteeName: $adoptee->getName(),
                seeder: $adoptee->getSeeder(),
                date: $adoption->getDate(),
                language: $adoption->getLang(),
                productPicture: $adoptee->getPicture()
            );

            $imageFilePathCollection[] = CertificateService::createCertificate($certificateModel);
        }

//        die;
//        var_dump($imageFilePathCollection);die;
        // Création du zip
        $temporaryFilePathName = tempnam(sys_get_temp_dir(),"").".zip";
        CertificateService::createZipFile($imageFilePathCollection, $temporaryFilePathName);

        // == Téléchargement du fichier zip par le navigateur ==
        header('Content-Type: application/zip');
        header("Content-disposition: attachment; filename=\"Certificats.zip\"");
        readfile($temporaryFilePathName);
        unlink($temporaryFilePathName);
        exit;





        return APIManagement::APIClientDownloadWithURL($fileURL, "receipt-coralguardian-".$fiscalReceiptModel->getReceiptCode().".pdf");
    }

    public static function getEndpoint(): string
    {
        return "getCertificates";
    }

    public static function getMethods(): array
    {
        return ["GET"];
    }

    public static function getPermissions(): string
    {
        return "__return_true";
    }
}

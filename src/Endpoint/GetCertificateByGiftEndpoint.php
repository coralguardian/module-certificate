<?php

namespace D4rk0snet\Certificate\Endpoint;

use D4rk0snet\Adoption\Entity\AdopteeEntity;
use D4rk0snet\Adoption\Entity\AdoptionEntity;
use D4rk0snet\Certificate\Service\CertificateService;
use D4rk0snet\Certificate\Model\CertificateModel;
use D4rk0snet\GiftCode\Entity\GiftCodeEntity;
use Hyperion\Doctrine\Service\DoctrineService;
use Hyperion\RestAPI\APIEnpointAbstract;
use Hyperion\RestAPI\APIManagement;
use WP_REST_Request;
use WP_REST_Response;

class GetCertificateByGiftEndpoint extends APIEnpointAbstract
{
    public const GIFT_CODE_PARAM = 'gift_code';

    public static function callback(WP_REST_Request $request): WP_REST_Response
    {
        $giftCode = $request->get_param(self::GIFT_CODE_PARAM);
        if ($giftCode === null) {
            return APIManagement::APIError('Missing gift code', 400);
        }

        /** @var GiftCodeEntity $giftCode */
        $giftCode = DoctrineService::getEntityManager()->getRepository(GiftCodeEntity::class)->findOneBy(["giftCode" => $giftCode]);
        if ($giftCode === null) {
            return APIManagement::APIError('GiftCodeEntity not found', 404);
        }

        if ($giftCode->getAdoptees()->count() === 0 || !$giftCode->isUsed()) {
            return APIManagement::APIError('Adoption without adoptees', 400);
        }

        $adoption = $giftCode->getGiftAdoption();
        $imageFilePathCollection = [];

        /** @var AdopteeEntity $adoptee */
        foreach ($giftCode->getAdoptees() as $index => $adoptee) {
            try {
                $certificateModel = new CertificateModel(
                    adoptedProduct: $adoption->getAdoptedProduct(),
                    adopteeName: $adoptee->getName(),
                    seeder: $adoptee->getSeeder(),
                    date: $adoption->getDate(),
                    language: $adoption->getLang(),
                    productPicture: $adoptee->getPicture()
                );
            } catch (\Exception $exception) {
                return APIManagement::APIError($exception->getMessage(), 400);
            }

            $imageFilePathCollection[] = CertificateService::createCertificate(
                $certificateModel,
                $giftCode->getAdoptees()->count() > 1 ? $index + 1 : null
            );
        }

        // Création du zip
        $temporaryFilePathName = tempnam(sys_get_temp_dir(), "") . ".zip";
        CertificateService::createZipFile($imageFilePathCollection, $temporaryFilePathName);

        // == Téléchargement du fichier zip par le navigateur ==
        $response = APIManagement::APIClientDownloadWithURL($temporaryFilePathName, "certificats.zip");
        unlink($temporaryFilePathName);
        return $response;
    }

    public static function getEndpoint(): string
    {
        return "getCertificatesByGift";
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

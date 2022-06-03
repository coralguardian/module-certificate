<?php

namespace D4rk0snet\Certificate\Endpoint;

use D4rk0snet\Adoption\Entity\AdopteeEntity;
use D4rk0snet\Certificate\Enums\CertificateState;
use D4rk0snet\Certificate\Service\CertificateService;
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

        if (!$adoption->isPaid()) { // ce cas ne devrait pas arriver
            return APIManagement::APIError('Adoption not payed', 400);
        }

        $areAllAdopteesGenerated = true;

        /** @var AdopteeEntity $adoptee */
        foreach ($giftCode->getAdoptees() as $adoptee) {
            if ($adoptee->getState() === CertificateState::GENERATION_ERROR) {
                echo "Une erreur est survenue lors de la génération de vos certificats. Veuillez nous contacter directement.";
                die;
            }
            if ($adoptee->getState() !== CertificateState::GENERATED) {
                $areAllAdopteesGenerated = false;
                break;
            }
        }

        if (!$areAllAdopteesGenerated) {
            echo "Vos certificats sont en cours de génération, veuillez réessayer d'ici quelques minutes.";
            die;
        }

        $certificatesPath = WP_PLUGIN_DIR ."/certificate/certificates/" . $adoption->getUuid() . "/" . $giftCode->getGiftCode();

        return CertificateService::downloadCertificates($certificatesPath);
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

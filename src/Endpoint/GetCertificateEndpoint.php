<?php

namespace D4rk0snet\Certificate\Endpoint;

use D4rk0snet\Adoption\Entity\AdopteeEntity;
use D4rk0snet\Adoption\Entity\AdoptionEntity;
use D4rk0snet\Adoption\Entity\GiftAdoption;
use D4rk0snet\Certificate\Enums\CertificateState;
use D4rk0snet\Certificate\Service\CertificateService;
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

        if (!$adoption->isPaid()) { // ce cas ne devrait pas arriver
            return APIManagement::APIError('Adoption not payed', 400);
        }

        if ($adoption->getAdoptees()->count() === 0) {
            return APIManagement::APIError('Adoption without adoptees', 400);
        }

        $areAllAdopteesGenerated = true;

        /** @var AdopteeEntity $adoptee */
        foreach ($adoption->getAdoptees() as $adoptee) {
            if ($adoptee->getState() === CertificateState::NOT_GENERATED) {
                $adoptee->setState(CertificateState::TO_GENERATE);
                DoctrineService::getEntityManager()->flush();
            }
            if ($adoptee->getState() !== CertificateState::GENERATED) {
                $areAllAdopteesGenerated = false;
                break;
            }
        }

        if (!$areAllAdopteesGenerated) {
            $response = new WP_REST_Response();
            $content = "Vos certificats sont en cours de generation, veuillez reessayer d'ici quelques minutes.";
            $response->set_data($content);
            $response->set_headers([
                'Content-Type' => 'text/html',
                'Content-Length' => strlen($content)
            ]);

            return $response;
        }

        $certificatesPath = WP_PLUGIN_DIR ."/certificate/certificates/" . $adoption->getUuid();

        return CertificateService::downloadCertificates($certificatesPath);
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

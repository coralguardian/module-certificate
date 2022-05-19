<?php

namespace D4rk0snet\Certificate\Endpoint;

use D4rk0snet\Adoption\Entity\AdoptionEntity;
use D4rk0snet\Certificate\Service\FiscalReceiptService;
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

        /** @var AdoptionEntity $order */
        $order = DoctrineService::getEntityManager()->getRepository(AdoptionEntity::class)->find($orderUUID);
        if ($order === null) {
            return APIManagement::APIError('Order not found', 404);
        }

        $fiscalReceiptModel = new CertificateModel(
            articles: '45/407',
            receiptCode: 1,
            customerFullName: $order->getFirstname(). " ".$order->getLastname(),
            customerAddress: $order->getAddress(),
            customerPostalCode: "xxx",
            customerCity: $order->getCity(),
            fiscalReductionPercentage: 60,
            priceWord: "soixante",
            price: $order->getAmount(),
            date: new \DateTime(),
            orderUuid: $orderUUID,
            paymentMethod: 'Carte bancaire'
        );

        $fileURL = FiscalReceiptService::createReceipt($fiscalReceiptModel);

        return APIManagement::APIClientDownloadWithURL($fileURL, "receipt-coralguardian-".$fiscalReceiptModel->getReceiptCode().".pdf");
    }

    public static function getEndpoint(): string
    {
        return "getFiscalReceipt";
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

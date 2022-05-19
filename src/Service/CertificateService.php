<?php

namespace D4rk0snet\Certificate\Service;

use D4rk0snet\Adoption\Enums\AdoptedProduct;
use D4rk0snet\Certificate\Model\CertificateModel;
use D4rk0snet\CoralAdoption\Service\Wkhtmlto;
use Hyperion\Api2pdf\Service\Api2PdfService;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

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
            $picturePath = "corals/" . $certificateModel->getProductPicture();
        } else {
            $file = "reef-$lang.twig";
            $picturePath = "reefs/" . $certificateModel->getProductPicture();

        }
        $html = $twig->load($file)->render(
            [
                'data' => $certificateModel->toArray(),
                'productImg' => base64_encode(file_get_contents(__DIR__ . "/../Template/img/$picturePath")),
                'transplantImg' => base64_encode(file_get_contents(__DIR__ . "/../Template/img/seeders/" . $certificateModel->getSeeder()->getPicture())),
                'teamImg' => base64_encode(file_get_contents(__DIR__ . "/../Template/img/coral-guardian-team.png")),
                'logoImg' => base64_encode(file_get_contents(__DIR__."/../Template/img/logo.png")),
                'stampImg' => base64_encode(file_get_contents(__DIR__."/../Template/img/stamp.png")),
            ]
        );

        $imageTemporaryFilename = $lang === 'fr' ?
            __DIR__ . "/../tmp/Coral_Guardian_Certificat_" . $certificateModel->getAdopteeName() . ".jpg" :
            __DIR__ . "/../tmp/Coral_Guardian_Certificate_" . $certificateModel->getAdopteeName() . ".jpg";

        Wkhtmlto::convertToPDF($html, $imageTemporaryFilename);

        return $imageTemporaryFilename;

//        return Api2PdfService::convertHtmlToPdf(
//            $html,
//            false,
//            "receipt-" . $fiscalReceiptModel->getReceiptCode() . ".pdf"
//        );
    }
//
//    public function getCertificate(Adoption $adoption): string
//    {
//        $lang = $adoption->getOrder()->getLang();
//        $certificateFile = "Certificates/" . ($adoption->getProduct()->getType() === Product::CORAL_TYPE ? "coral-$lang.twig" : "reef-$lang.twig");
//
//        if (!isset($this->templateWrapper[$certificateFile])) {
//            $this->templateWrapper[$certificateFile] = $this->twigEnvironment->load($certificateFile);
//        }
//
//        $html = $this->templateWrapper[$certificateFile]->render([
//            'customerName' => $adoption->getOrder()->getCustomer()->getFullName(),
//            'productName' => $adoption->getName(),
//            'productPictureUrl' => wp_get_original_image_url($adoption->getPicture()),
//            'date' => $adoption->getOrder()->getDate()->format("d/m/Y"),
//            'transplantImgUrl' => wp_get_original_image_url($adoption->getSeeder()->getPicture()),
//            'transplantName' => ucfirst($adoption->getSeeder()->getName()),
//            'assets_path' => home_url("/app/plugins/coral-adoption/assets/", "http")
//        ]);
//
//        $imageTemporaryFilename = $lang === 'fr' ?
//            __DIR__ . "/../tmp/Coral_Guardian_Certificat_" . $adoption->getName() . ".jpg" :
//            __DIR__ . "/../tmp/Coral_Guardian_Certificate_" . $adoption->getName() . ".jpg";
//
//        $this->wkhtmltoService->convertToImage($html, $imageTemporaryFilename);
//
//        return $imageTemporaryFilename;
//    }
}
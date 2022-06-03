<?php

namespace D4rk0snet\Certificate\Command;

use D4rk0snet\Adoption\Entity\AdopteeEntity;
use D4rk0snet\Adoption\Entity\AdoptionEntity;
use D4rk0snet\Adoption\Entity\GiftAdoption;
use D4rk0snet\Certificate\Model\CertificateModel;
use D4rk0snet\Certificate\Service\CertificateService;
use D4rk0snet\GiftCode\Entity\GiftCodeEntity;
use Doctrine\Common\Collections\Collection;
use Hyperion\Doctrine\Service\DoctrineService;
use WP_CLI;

class GenerateCertificates
{
    public static function runCommand()
    {
        WP_CLI::log("==== Lancement du script de génération des certificats ====\n");

        $adoptions = DoctrineService::getEntityManager()->getRepository(AdoptionEntity::class)->findCertificatesToGenerate();

        if (count($adoptions) === 0) {
            return WP_CLI::success("Aucun certificat à générer, fin de la commande.");
        }

        WP_CLI::log("=== " . count($adoptions) . " récupérées ===");

        $baseSaveFolder = __DIR__ . "/../../certificates/";
        /** @var AdoptionEntity $adoption */
        foreach ($adoptions as $adoption) {

            WP_CLI::log("=== Démarrage génération des certificats de l'adoption " . $adoption->getUuid() . " ===");

            if ($adoption instanceof GiftAdoption) {
                WP_CLI::log("== Gift Adoption ==");

                if ($adoption->getGiftCodes()->count() === 0) {
                    WP_CLI::log("= Cette adoption n'a pas de giftCodes =");
                    continue;
                }

                /** @var GiftCodeEntity $giftCode */
                foreach ($adoption->getGiftCodes() as $giftCode) {

                    if ($giftCode->getAdoptees()->count() === 0) {
                        WP_CLI::log("= Ce giftCode n'a pas d'adoptees =");
                        continue;
                    }
                    $folder = $baseSaveFolder . $adoption->getUuid();
                    self::createFolders($folder);
                    $folder .= "/" . $giftCode->getGiftCode();
                    self::createFolders($folder);

                    if (!self::isEmptyDir($folder)) {
                        // le certificat est déjà généré
                        continue;
                    }
                    self::generateCertificates($giftCode->getAdoptees(), $adoption, $folder);
                }

            } else {
                WP_CLI::log("== Adoption ==");
                if ($adoption->getAdoptees()->count() === 0) {
                    WP_CLI::log("= Cette adoption n'a pas d'adoptees =");
                    continue;
                }

                $folder = $baseSaveFolder . $adoption->getUuid();
                self::createFolders($folder);
                self::generateCertificates($adoption->getAdoptees(), $adoption, $folder);
            }

            WP_CLI::log("=== Succès génération des certificats de l'adoption " . $adoption->getUuid() . " ===");
        }

        return WP_CLI::success("Fin de la génération des certificats");
    }

    private static function updateState(AdoptionEntity $adoptionEntity): void
    {
        $adoptionEntity->setState($adoptionEntity->getState()->nextState());
        DoctrineService::getEntityManager()->flush();
    }

    private static function generateCertificates(Collection $adoptees, AdoptionEntity $adoptionEntity, string $saveFolder): void
    {
        /** @var AdopteeEntity $adoptee */
        foreach ($adoptees as $index => $adoptee) {
            $certificateModel = new CertificateModel(
                adoptedProduct: $adoptionEntity->getAdoptedProduct(),
                adopteeName: $adoptee->getName(),
                seeder: $adoptee->getSeeder(),
                date: $adoptionEntity->getDate(),
                language: $adoptionEntity->getLang(),
                productPicture: $adoptee->getPicture(),
                saveFolder: $saveFolder
            );
            CertificateService::createCertificate($certificateModel, $index + 1);
        }
    }

    private static function createFolders(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755);
        }
    }

    private static function isEmptyDir(string $dir): bool
    {
        $res = scandir($dir);
        if ($res === false) {
            return false;
        }
        return count($res) == 2;
    }
}
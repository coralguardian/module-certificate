<?php

namespace D4rk0snet\Certificate\Command;

use D4rk0snet\Adoption\Entity\AdopteeEntity;
use D4rk0snet\Adoption\Entity\AdoptionEntity;
use D4rk0snet\Adoption\Entity\GiftAdoption;
use D4rk0snet\Certificate\Enums\CertificateState;
use D4rk0snet\Certificate\Model\CertificateModel;
use D4rk0snet\Certificate\Service\CertificateService;
use Hyperion\Doctrine\Service\DoctrineService;
use WP_CLI;

class GenerateCertificates
{
    public static function runCommand()
    {
        WP_CLI::log("==== Lancement du script de génération des certificats ====\n");

        $adoptees = DoctrineService::getEntityManager()->getRepository(AdopteeEntity::class)
            ->findBy(
                ["state" => CertificateState::TO_GENERATE],
                null,
                3
            );

        if (count($adoptees) === 0) {
            return WP_CLI::success("Aucun certificat à générer, fin de la commande.");
        }

        WP_CLI::log("=== " . count($adoptees) . " récupérées ===\n");

        $baseSaveFolder = __DIR__ . "/../../certificates/";

        /** @var AdopteeEntity $adoptee */
        foreach ($adoptees as $adoptee) {
            WP_CLI::log("=== Démarrage génération du certificat de l'adoptee " . $adoptee->getUuid() . " ===");

            $adoption = $adoptee->getAdoption();
            $folder = $baseSaveFolder . $adoption->getUuid();

            if ($adoption instanceof GiftAdoption) {
                WP_CLI::log("== Gift Adoption ==");

                $giftCode = $adoptee->getGiftCode();

                if (null === $giftCode) {
                    WP_CLI::log("Gift adoption sans giftCode");
                    self::updateState($adoptee, CertificateState::GENERATION_ERROR);
                    continue;
                }

                self::createFolders($folder);
                $folder .= "/" . $giftCode->getGiftCode();
            }

            try {
                self::updateState($adoptee);
                self::createFolders($folder);
                self::generateCertificate($adoptee, $adoption, $folder);
                self::updateState($adoptee);
            } catch (\Exception $exception) {
                self::updateState($adoptee, CertificateState::TO_GENERATE);
                WP_CLI::error($exception);
            }

            WP_CLI::log("=== Succès génération du certificat de l'adoptee " . $adoptee->getUuid() . " ===\n");
        }

        return WP_CLI::success("Fin de la génération des certificats");
    }

    private static function updateState(AdopteeEntity $adoptee, CertificateState $state = null): void
    {
        $adoptee->setState($state ?: $adoptee->getState()->nextState());
        DoctrineService::getEntityManager()->flush();
    }

    private static function generateCertificate(AdopteeEntity $adoptee, AdoptionEntity $adoptionEntity, string $saveFolder): void
    {
        $certificateModel = new CertificateModel(
            adoptedProduct: $adoptionEntity->getAdoptedProduct(),
            adopteeName: $adoptee->getName(),
            seeder: $adoptee->getSeeder(),
            date: $adoptionEntity->getDate(),
            language: $adoptionEntity->getLang(),
            productPicture: $adoptee->getPicture(),
            saveFolder: $saveFolder
        );
        CertificateService::createCertificate($certificateModel);
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
<?php

namespace D4rk0snet\Certificate\Command;

use D4rk0snet\Adoption\Entity\AdopteeEntity;
use D4rk0snet\Certificate\Enums\CertificateState;
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

        /** @var AdopteeEntity $adoptee */
        foreach ($adoptees as $adoptee) {
            WP_CLI::log("=== Démarrage génération du certificat de l'adoptee " . $adoptee->getUuid() . " ===");

            try {
                CertificateService::fullGenerationProcess($adoptee);
            } catch (\Exception $exception) {
                WP_CLI::error($exception);
            }

            WP_CLI::log("=== Succès génération du certificat de l'adoptee " . $adoptee->getUuid() . " ===\n");
        }

        return WP_CLI::success("Fin de la génération des certificats");
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
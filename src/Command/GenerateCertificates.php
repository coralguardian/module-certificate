<?php

namespace D4rk0snet\Certificate\Command;

use D4rk0snet\Adoption\Entity\AdoptionEntity;
use D4rk0snet\Certificate\Enums\CertificateState;
use Hyperion\Doctrine\Service\DoctrineService;
use WP_CLI;

class GenerateCertificates
{
    public static function runCommand()
    {
        WP_CLI::log("== Lancement du script de génération des certificats ==\n");

        $adoptions = DoctrineService::getEntityManager()->getRepository(AdoptionEntity::class)->findCertificatesToGenerate();

        if (count($adoptions) === 0) {
            return WP_CLI::success("Aucun certificat à générer, fin de la commande.");
        }



        WP_CLI::log("");

        return WP_CLI::success("Fin de l'envoi des codes cadeaux");
    }

    private static function createFolders(): void
    {

    }
}
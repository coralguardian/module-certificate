<?php

namespace D4rk0snet\Certificate;

use D4rk0snet\Certificate\Command\GenerateCertificates;
use D4rk0snet\Certificate\Endpoint\GetCertificateByGiftEndpoint;
use D4rk0snet\Certificate\Endpoint\GetCertificateEndpoint;
use WP_CLI;

class Plugin
{
    public static function launchActions()
    {
        do_action(\Hyperion\RestAPI\Plugin::ADD_API_ENDPOINT_ACTION, new GetCertificateEndpoint());
        do_action(\Hyperion\RestAPI\Plugin::ADD_API_ENDPOINT_ACTION, new GetCertificateByGiftEndpoint());
    }

    public static function addCLICommands()
    {
        WP_CLI::add_command('generate_certificates', [GenerateCertificates::class, 'runCommand']);
    }
}
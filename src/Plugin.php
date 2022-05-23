<?php

namespace D4rk0snet\Certificate;

use D4rk0snet\Certificate\Endpoint\GetCertificateEndpoint;

class Plugin
{
    public static function launchActions()
    {
        do_action(\Hyperion\RestAPI\Plugin::ADD_API_ENDPOINT_ACTION, new GetCertificateEndpoint());
    }
}
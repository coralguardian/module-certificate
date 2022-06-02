<?php

namespace D4rk0snet\Certificate\Enums;


enum CertificateState: string
{
    case NOT_GENERATED = 'not_generated';
    case TO_GENERATE = 'to_generate';
    case GENERATING = 'generating';
    CASE GENERATED = 'generated';

    public function nextState() : ?CertificateState
    {
        return match ($this) {
            CertificateState::NOT_GENERATED => CertificateState::TO_GENERATE,
            CertificateState::TO_GENERATE => CertificateState::GENERATING,
            CertificateState::GENERATING => CertificateState::GENERATED,
            CertificateState::GENERATED => null
        };
    }
}
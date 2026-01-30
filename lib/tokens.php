<?php

declare(strict_types=1);

function generate_qr_token(): string
{
    return hash('sha256', random_bytes(32));
}

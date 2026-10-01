<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Http;

/** Names of request attributes shared between middleware and controllers. */
final class RequestAttributes
{
    public const REQUEST_ID = '_request_id';
    public const CLIENT_IP = 'client_ip';
    /** Set by the PCF Router. */
    public const ROUTE_PARAMETERS = '_route_parameters';
}

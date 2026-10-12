<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Careers;

/**
 * The recruitment settings in force right now (D-028): the server's settings
 * (.env) with whatever an administrator saved in the staff area on top. Read on
 * each request, so a change in the staff area applies at once.
 */
final class RecruitmentSettingsSource
{
    public function __construct(private readonly RecruitmentSettings $server, private readonly ?CareersSettingsStore $store = null)
    {
    }

    public function current(): RecruitmentSettings
    {
        return $this->store === null ? $this->server : $this->server->withSaved($this->store->all());
    }

    /** The server's own settings, without anything saved in the staff area. */
    public function server(): RecruitmentSettings
    {
        return $this->server;
    }
}

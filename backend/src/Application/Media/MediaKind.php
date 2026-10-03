<?php

declare(strict_types=1);

namespace Paxofi\CorporateWebsite\Application\Media;

enum MediaKind: string
{
    case Image = 'image';
    case Document = 'document';
}

<?php

declare(strict_types=1);

namespace EICC\StaticForge\Features\DevServer\Services;

enum HostDecision
{
    case Run;
    case WarnLando;
    case WarnRemote;
    case Refuse;
}

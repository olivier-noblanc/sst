<?php

namespace App\Enum;

enum VisibilityMode: string
{
    case Confidential = 'confidential';
    case AgentChoice  = 'agent_choice';
    case Public       = 'public';
}

<?php

namespace Src\Workforce\Application\Plan;

/** Whose plans a request is about: those of the actor's own tenant (a company member) or of every legal entity of the actor's group (a group member). */
enum PlanSide
{
    case Company;
    case Group;
}

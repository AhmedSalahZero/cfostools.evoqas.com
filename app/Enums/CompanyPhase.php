<?php

namespace App\Enums;

enum CompanyPhase: string
{
    case PreSeed    = 'pre-seed';
    case Seed       = 'seed';
    case SeriesA    = 'series-a';
    case SeriesB    = 'series-b';
    case Growth     = 'growth';
    case Mature     = 'mature';
    case Exited     = 'exited';
    case Deadpooled = 'deadpooled';
}
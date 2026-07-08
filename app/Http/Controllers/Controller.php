<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesPortfolioCompany;

abstract class Controller
{
    use AuthorizesPortfolioCompany;
}

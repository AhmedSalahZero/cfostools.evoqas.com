<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use MathPHP\Algebra;

class TestMathController extends Controller
{
    public function gcd()
    {
        $a = 14408;
        $b = 18;
        
        $gcd = Algebra::gcd($a, $b);

        return "GCD of $a and $b is: $gcd";
    }
}

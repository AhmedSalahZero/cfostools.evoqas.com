<?php

namespace App\Exports;




use Illuminate\Contracts\View\View;
class HeadersExport  implements  WithHeadings

{
    use Exportable;

    public $company_id;
    public $heads;

    public function __construct($company_id,$heads=[]){
        $this->company_id = $company_id;
        $this->heads = $heads;
    }

 
    // Headings Names
    public function headings(): array
    {

        return $this->heads;
    }

}

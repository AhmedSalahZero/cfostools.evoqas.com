@extends('layouts.dashboard')
@php
use App\Helpers\HArr;
use App\Helpers\HMath;
use MathPHP\Statistics\Correlation ;
@endphp
@section('css')
<link href="{{url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css')}}" rel="stylesheet" type="text/css" />
<link href="{{url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="/custom/css/non-banking-services/common.css">
@endsection


@section('dash_nav')
<style>
a.back-to-study-btn{
	color:white !important;
	margin-bottom:10px !important;
}
a.back-to-study-btn:hover{
	color:white !important;
	
}
    .max-column-th-class {
        width: 30% !important;
        min-width: 30% !important;
        max-width: 30% !important;
    }

    .three-dots-parent {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }

    .b-bottom {
        border-bottom: 1px solid green !important;
    }

    .expandable-amount-input {
        max-width: 90px !important;
        min-width: 90px !important;
        width: 90px !important;
    }

    table:not(.table-condensed) thead th,
    table:not(.table-condensed) tbody td {
        padding-top: 6px !important;
        padding-bottom: 6px !important;
    }

    input {
        padding-top: 6px !important;
        padding-bottom: 6px !important;
    }

    .chartdiv_two_lines {
        width: 100%;
        height: 500px;
    }

    .chartDiv {
        max-height: 500px !important;
    }

    .margin__left {
        border-left: 2px solid #366cf3;
    }

    .sky-border {
        border-bottom: 1.5px solid #CCE2FD !important;
    }

    .kt-widget24__title {
        color: black !important;
    }

</style>

@endsection
@section('css')
<link href="{{ url('assets/vendors/custom/datatables/datatables.bundle.css') }}" rel="stylesheet" type="text/css" />
<link href="{{url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css')}}" rel="stylesheet" type="text/css" />
<link href="{{url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css')}}" rel="stylesheet" type="text/css" />
<style>
    table {
        white-space: nowrap;
    }

    /* .dataTables_wrapper{max-width: 100%;  padding-bottom: 50px !important;overflow-x: overlay;max-height: 4000px;} */

</style>
@endsection
@section('sub-header')

{{-- <x-main-form-title :id="'main-form-title'" :class="''">{{ __('General Fixed Expense Statement Report') .' - '. $hospitalitySector->getStudyName() . ' - ' . $hospitalitySector->getPropertyName() }}</x-main-form-title> --}}
{{-- <x-navigators-dropdown :navigators="$navigators"></x-navigators-dropdown> --}}


@endsection

@section('content')
<div class="kt-portlet">


</div>

<div class="tab-content  kt-margin-t-20">
    @php
    $index = 0 ;
    @endphp

    <div class="tab-pane  active " id="kt_apps_contacts_view_tab_main" role="tabpanel">















        <div class="row">



   
   <div class="col-md-12">
                <div class="kt-portlet kt-portlet--tabs">

                    <div class="kt-portlet__body pt-0">


                        <div class="tab-content  kt-margin-t-20">

                            <div class="tab-pane active" id="FullySecuredOverdraftchartkt_apps_contacts_view_tab_1" role="tabpanel">


                                <div class="row">
                                    <div class="col-md-12">
									<div class="d-flex">
                                        <h3 class="font-weight-bold text-black form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap"> {{ __('Investment Feasibility Results Comparing') }} </h3>
											<a class="font-weight-bold text-black form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap	btn btn-primary text-white back-to-study-btn" href="{{ route('admin.view.hospitality.sector',['company'=>$company->id]) }}">{{ __('Studies Table') }}</a>
									
									</div>
										
                                    </div>


                                      @include('non_banking_services.dashboard._investment',['firstCardItems'=>$firstCardItems,'secondCardItems'=>$secondCardItems,'studyNames'=>$studyNames])











                                </div>

                            </div>


                        </div>
                    </div>
                </div>


            </div>
			
            @foreach($comparingResults as $cardTitle => $comparingResult)
            <div class="col-md-12">
                <div class="kt-portlet kt-portlet--tabs">

                    <div class="kt-portlet__body pt-0">


                        <div class="tab-content  kt-margin-t-20">

                            <div class="tab-pane active" id="FullySecuredOverdraftchartkt_apps_contacts_view_tab_1" role="tabpanel">


                                <div class="row">
                                    <div class="col-md-12">
                                        <h3 class="font-weight-bold text-black form-label kt-subheader__title small-caps mr-5 text-primary text-nowrap"> {{ $cardTitle }} </h3>
                                    </div>


                                    @include('non_banking_services.dashboard._revenues',['formattedResults'=>$comparingResult])











                                </div>

                            </div>


                        </div>
                    </div>
                </div>


            </div>
            @endforeach





        </div>



        <!--end:: Widgets/Stats-->


    </div>





</div>
@endsection
@section('js')
<script src="{{ url('assets/js/demo1/pages/crud/datatables/basic/paginations.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/datatables/datatables.bundle.js') }}" type="text/javascript"></script>
<!-- Resources -->
<script src="https://cdn.amcharts.com/lib/4/core.js"></script>
<script src="https://cdn.amcharts.com/lib/4/charts.js"></script>
<script src="https://cdn.amcharts.com/lib/4/themes/animated.js"></script>








<!--begin::Page Scripts(used by this page) -->
<script src="{{url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js')}}" type="text/javascript"></script>
<script src="{{url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js')}}" type="text/javascript"></script>
<script src="{{url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js')}}" type="text/javascript"></script>
<script src="{{url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js')}}" type="text/javascript"></script>
<script src="{{url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js')}}" type="text/javascript"></script>
<script src="{{url('assets/vendors/general/jquery.repeater/src/lib.js')}}" type="text/javascript"></script>
<script src="{{url('assets/vendors/general/jquery.repeater/src/jquery.input.js')}}" type="text/javascript"></script>
<script src="{{url('assets/vendors/general/jquery.repeater/src/repeater.js')}}" type="text/javascript"></script>
<script src="{{url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js')}}" type="text/javascript"></script>

<script src="https://cdn.amcharts.com/lib/5/index.js"></script>
<script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
<script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>



<!--end::Page Scripts -->

@endsection

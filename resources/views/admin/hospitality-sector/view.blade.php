@extends('layouts.dashboard')
@php
use App\Models\HospitalitySector;
@endphp
@section('css')
<link href="{{ url('assets/vendors/general/bootstrap-datepicker/dist/css/bootstrap-datepicker3.css') }}" rel="stylesheet" type="text/css" />
<link href="{{ url('assets/vendors/general/bootstrap-select/dist/css/bootstrap-select.css') }}" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="/custom/css/financial-planning/common.css">

<style>
.form-border {
	border:1px solid #366cf3 !important;
}
    .modal-backdrop {
        z-index: 0;
    }
	.text-hover-white:hover{
		color:white !important;
	}
.text-blue{
	color:blue !important;
}
    .text-green {
        color: green;
    }

    .th-main-color {
        background-color: #0742A6 !important;
        color: white !important;
    }

    .bg-green {
        background-color: green !important;
    }

    .delete-btn-class {
        border: 1px solid red !important;
    }

    .delete-btn-class i {
        color: red !important
    }

    .delete-btn-class:hover {
        background-color: red;
    }

    .delete-btn-class:hover i {
        color: white !important
    }

    .edit-btn-class {
        border: 1px solid blue !important;
    }

    .edit-btn-class i {
        color: blue !important
    }

    .edit-btn-class:hover {
        background-color: blue;
    }

    .edit-btn-class:hover i {
        color: white !important
    }

    .copy-btn-class {
        border: 1px solid green !important;
    }

    .copy-btn-class i {
        color: green !important
    }

    .copy-btn-class:hover {
        background-color: green;
    }

    .copy-btn-class:hover i {
        color: white !important
    }

    .font-14px {
        font-size: 14px !important;
    }

    .border-green {
        border-color: green;
    }

    .bg-red {
        background-color: red;
    }

    .border-red {
        background-color: green;
    }

    .color-red {
        color: red !important;

    }

    .color-green {
        color: green !important;
    }

    .w-60-percentage {
        width: 60% !important;
    }

    .w-50-percentage {
        width: 50% !important;
    }

    .w-40-percentage {
        width: 40% !important;
    }

    .w-30-percentage {
        width: 30% !important;
    }

    .w-20-percentage {
        width: 20% !important;
    }

    .w-70-percentage {
        width: 70% !important;
    }

    .w-15-percentage {
        width: 15% !important;
    }

    .w-10-percentage {
        width: 10% !important;
    }

    .flex-tabs {
        display: flex;
        gap: 10px;
    }

    .text-green {
        color: green !important;
    }

    .text-red {
        color: red !important;
    }

    .show-class-js {
        display: block !important;
    }

    .table-condensed th {
        background-color: white !important;
    }

    input,
    select,
    .dropdown-toggle.bs-placeholder {
        border: 1px solid #CCE2FD !important;
    }

    .flex-2 {
        flex: 2 !important;
    }

    .text-main-color {
        color: #0742A6 !important
    }

    ::placeholder {
        color: lightgray !important;
        font-weight: 100;
    }

    .visibility-hidden {
        visibility: hidden !important;
    }

    .income-statement-table {}

    .btn-border-radius {
        border-radius: 10px !important;
    }

    .income-statement-table .main-level-tr td,
    .income-statement-table .main-level-tr th {
        background-color: #9FC9FB !important;
        border: 1px solid #fff;

    }

    .income-statement-table .main-level-tr td:first-of-type,
    .income-statement-table .main-level-tr td:nth-of-type(2),
    .income-statement-table .main-level-tr th:first-of-type,
    .income-statement-table .main-level-tr th:nth-of-type(2) {
        background-color: #9FC9FB !important;
    }

    .income-statement-table .sub-level-tr td,
    .income-statement-table .sub-level-tr th {
        background-color: #fff !important;
    }

    input,
    select,
    .filter-option-inner-inner {
        font-weight: 600 !important;
        color: black !important;
    }

    html body tr.all-td-white td {
        background-color: white !important;
    }

    .font-size-1-25rem {
        font-size: 1.25rem !important;
    }

    .font-size-15px {
        font-size: 15px !important
    }

    .label-clr {
        color: #646c9a !important;
    }

    .installment-section {
        background: #F2F2F2 !important;
        padding-top: 10px;
        margin-bottom: 10px !important;
    }

    .label-size {
        font-size: 1.25rem !important;
    }

    .pr-6rem {
        padding-right: 6rem;
    }

    .pointer-events-none {
        pointer-events: none;
    }

    .dtfh-floatingparent.dtfh-floatingparenthead {
        top: 59px !important;
    }

    .table-for-collection-policy tr:nth-child(odd) {
        background-color: white !important;
    }

    .percentage-weight {
        font-weight: bold;
        margin-right: 10px;
    }





    .small-caps {
        font-variant: small-caps;
    }

    .sharing-sign {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin: auto;
    }

    .active-sharing {
        background: #00ff28;
    }

    .inactive-sharing {
        background: #f00;
    }

    .w-full {
        width: 100%;
    }

    .btn.dropdown-toggle {
        height: 100%;
    }

    /* .dropdown-toggle{} */


    .bg-white-hover:hover {
        color: white !important;
    }

    .new-study-item i {
        color: #055dac !important
    }

    .new-study-item:hover i {
        color: white !important;
    }

</style>
@endsection
@section('sub-header')
{{ $title }}
@endsection
@section('content')

<div class="kt-portlet kt-portlet--tabs">
    <div class="kt-portlet__head">
        <div class="kt-portlet__head-toolbar justify-content-between flex-grow-1">
            <ul class="nav nav-tabs nav-tabs-space-lg nav-tabs-line nav-tabs-bold nav-tabs-line-3x nav-tabs-line-brand" role="tablist">
                <li class="nav-item">
                    <a class="nav-link {{ !Request('active') || Request('active') == HospitalitySector::STUDY ?'active':'' }}" data-toggle="tab" href="#{{HospitalitySector::STUDY  }}" role="tab">
                        <i class="fa fa-money-check-alt"></i> {{ __('Hospitality Sector Feasibilities & Multi-years Financial Plan Table') }}
                    </a>
                </li>

                {{-- <li class="nav-item">
                    <a class="nav-link {{  Request('active') == HospitalitySector::ANNUALLY_STUDY ?'active':'' }}" data-toggle="tab" href="#{{HospitalitySector::ANNUALLY_STUDY }}" role="tab">
                <i class="fa fa-money-check-alt"></i> {{ __('Annually Study') }}
                </a>
                </li> --}}



            </ul>
            {{-- @if(auth()->user()->can('create study info')) --}}

            <div class="flex-tabs">
             
				
				 <a data-toggle="modal" data-target="#compare-id" type="button" class="btn new-record-class new-study-item rounded btn-icon-sm align-self-center" title="{{ __('Compare') }}">
				 <i class="fa fa-layer-group exclude-icon default-icon-color"></i>
				 <span class="text-black text-hover-white">{{ __('Compare Between Two Studies') }}</span>
				 </a>
                                    <div class="modal fade" id="compare-id" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                        <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <form action="{{ route('compare.between',['company'=>$company->id]) }}" method="post" class="form-border">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title text-blue" id="exampleModalLongTitle">{{ __('Compare Between Two Studies') }}</h5>
                                                        <button type="button" class="close" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6 mb-4">
                                                                <label>{{__('First Study')}} </label>
                                                                <div class="kt-input-icon">
                                                                    <select required name="first_hospitality_id" id="first-study-id" class="form-control">
																		@foreach($models[HospitalitySector::STUDY] as $model)
																		<option value="{{ $model->getId() }}">{{ $model->getName() }}</option>
																		@endforeach
																	</select>
                                                                </div>
                                                            </div>
															
															 <div class="col-md-6 mb-4">
                                                                <label>{{__('Second Study')}} </label>
                                                                <div class="kt-input-icon">
                                                                    <select required name="second_hospitality_id" id="second-study-id" class="form-control">
																		@foreach($models[HospitalitySector::STUDY] as $model)
																		<option value="{{ $model->getId() }}">{{ $model->getName() }}</option>
																		@endforeach
																	</select>
                                                                </div>
                                                            </div>
															


                                                        </div>


                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn bg-green  text-white 
																
																
																">{{ __('Confirm') }}</button>
                                                    </div>

                                                </form>
                                            </div>
                                        </div>
                                    </div>
									
				 <a href="{{ route('create.rooms',['company'=>$company->id]) }}" class="btn new-record-class new-study-item rounded btn-icon-sm align-self-center">
                    <i class="fas fa-plus exclude-icon"></i>
                    {{ __('Rooms') }}
                </a>

                <a href="{{ route('create.foods',['company'=>$company->id]) }}" class="btn new-record-class new-study-item rounded btn-icon-sm align-self-center">
                    <i class="fas fa-plus exclude-icon"></i>
                    {{ __('Foods') }}
                </a>

                <a href="{{ route('create.casinos',['company'=>$company->id]) }}" class="btn new-record-class new-study-item rounded btn-icon-sm align-self-center">
                    <i class="fas fa-plus white-icon exclude-icon"></i>
                    {{ __('Gaming') }}
                </a>

                <a href="{{ route('create.meetings',['company'=>$company->id]) }}" class="btn new-record-class new-study-item rounded btn-icon-sm align-self-center">
                    <i class="fas fa-plus white-icon exclude-icon"></i>
                    {{ __('Meetings') }}
                </a>

                <a href="{{ route('create.others',['company'=>$company->id]) }}" class="btn new-record-class new-study-item rounded btn-icon-sm align-self-center">
                    <i class="fas fa-plus white-icon exclude-icon"></i>
                    {{ __('Others') }}
                </a>

                <a href="{{ route('admin.create.hospitality.sector',['company'=>$company->id]) }}" class="btn btn-2-bg bg-white-hover new-study-item rounded btn-icon-sm align-self-center">
                    <i class="fas fa-plus white-icon exclude-icon"></i>
                    {{ __('New') }}
                </a>


            </div>

            {{-- <div class="flex-tabs">
			
                <a href="{{ $createRoute }}" class="btn new-record-class rounded btn-icon-sm align-self-center">
            <i class="fas fa-plus white-icon"></i>
            {{ __('New Study') }}
            </a>
        </div> --}}
        {{-- @endif  --}}

    </div>
</div>
<div class="kt-portlet__body">
    <div class="tab-content  kt-margin-t-20">

        @php
        $currentType = HospitalitySector::STUDY ;
        @endphp
        <!--Begin:: Tab Content-->
        <div class="tab-pane {{  !Request('active') || Request('active') == $currentType ?'active':'' }}" id="{{ $currentType }}" role="tabpanel">
            <div class="kt-portlet kt-portlet--mobile">
                @php
                $rowIndex = 0;
                @endphp
                <x-tables.repeater-table :removeActionBtn="true" :removeRepeater="true" :initialJs="false" :repeater-with-select2="true" :canAddNewItem="false" :parentClass="'js-remove-hidden'" :hide-add-btn="true" :tableName="''" :repeaterId="''" :relationName="'food'" :isRepeater="$isRepeater=!(isset($removeRepeater) && $removeRepeater)">
                    <x-slot name="ths">
                        <x-tables.repeater-table-th class="  header-border-down first-column-th-class" :title="__('Name')"></x-tables.repeater-table-th>
                        {{-- <x-tables.repeater-table-th class=" interval-class header-border-down " :title="__('Start Date')"></x-tables.repeater-table-th> --}}
                        {{-- <x-tables.repeater-table-th class=" interval-class header-border-down " :title="__('End Date')"></x-tables.repeater-table-th> --}}
                        <x-tables.repeater-table-th class=" interval-class header-border-down " :title="__('Financial Statement')"></x-tables.repeater-table-th>
                        {{-- <x-tables.repeater-table-th class=" interval-class header-border-down " :title="__('Balance Sheet')"></x-tables.repeater-table-th> --}}
                        {{-- <x-tables.repeater-table-th class=" interval-class header-border-down " :title="__('Cash Flow')"></x-tables.repeater-table-th> --}}
                        <x-tables.repeater-table-th class=" interval-class header-border-down " :title="__('Dashboard')"></x-tables.repeater-table-th>
                        <x-tables.repeater-table-th class=" interval-class header-border-down " :title="__('Actions')"></x-tables.repeater-table-th>
                    </x-slot>
                    <x-slot name="trs">

                        @php
                        $currentLoanTotalPerYear = [];
                        @endphp

                        @foreach ($models[$currentType] as $index=>$model)

                        <tr data-repeat-formatting-decimals="0" data-repeater-style>

                            <td>
                                <div class="">

                                    <input value="{{ $model->getName() }}" disabled class="form-control text-left " type="text">
                                </div>
                            </td>
                            {{-- <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <x-repeat-right-dot-inputs :removeThreeDots="true" :removeCurrency="true" :mark="' '" :is-number="false" :removeThreeDotsClass="true" :number-format-decimals="0" :currentVal="$model->getStudyStartDateFormattedForView()" :classes="''" :is-percentage="false" :name="''" :columnIndex="0"></x-repeat-right-dot-inputs>

                                        </div>
                                    </td> --}}
                            {{-- <td>
                                        <div class="d-flex align-items-center justify-content-center">
                                            <x-repeat-right-dot-inputs :removeThreeDots="true" :removeCurrency="true" :mark="' '" :is-number="false" :removeThreeDotsClass="true" :number-format-decimals="0" :currentVal="$model->getStudyEndDateFormattedForView()" :classes="''" :is-percentage="false" :name="''" :columnIndex="0"></x-repeat-right-dot-inputs>

                                        </div>
                                    </td> --}}
                            <td>
                                <div class="d-flex align-items-center flex-column " style="gap:10px;">
                                    <div class="d-flex mr-auto justify-content-center w-full" style="gap:10px;">
                                        <a href="{{ route('admin.view.hospitality.sector.income.statement',['company'=>$company->id,'hospitality_sector_id'=>$model->id]) }}" class="btn btn-md-width btn-1-bg btn-sm btn-brand btn-pill">{{ __('Income Statement') }}</a>
                                        <a href="{{ route('admin.view.hospitality.sector.balance.sheet.report',['company'=>$company->id,'hospitality_sector_id'=>$model->id]) }}" class="btn btn-md-width btn-2-bg btn-sm btn-brand btn-pill">{{ __('Balance Sheet') }}</a>
                                    </div>
                                    <div class="d-flex mr-auto justify-content-center w-full" style="gap:10px;">
                                        <a href="{{ route('admin.view.hospitality.sector.cash.in.out.report',['company'=>$company->id,'hospitality_sector_id'=>$model->id]) }}" class="btn btn-md-width btn-3-bg btn-sm btn-brand btn-pill">{{ __('Cash Flow') }}</a>
                                        <a href="{{ route('admin.view.hospitality.sector.ratio.analysis.report',['company'=>$company->id,'hospitality_sector_id'=>$model->id]) }}" class="btn btn-md-width btn-4-bg btn-sm btn-brand btn-pill">{{ __('Ratio Analysis') }}</a>
                                    </div>
                                </div>

                            </td>

                            <td>
                                <div class="d-flex mr-auto d-flex mr-auto justify-content-center w-full" style="gap:10px;">
                                    <a href="{{ route('admin.view.hospitality.sector.study.dashboard',['company'=>$company->id,'hospitality_sector_id'=>$model->id]) }}" class="btn btn-sm-width btn-1-bg btn-sm btn-brand btn-pill">{{ __('Result') }}</a>
                                    {{-- <a href="#" class="btn btn-sm-width btn-2-bg btn-sm btn-brand btn-pill" >{{ __('Valuation') }}</a> --}}
                                </div>
                            </td>
                            <td class="kt-datatable__cell--left kt-datatable__cell " data-field="Actions" data-autohide-disabled="false">
                                <span style="overflow: visible; position: relative; width: 110px;">
                                    {{-- @if(hasAuthFor('update lc settlement internal transfer')) --}}
                                    <a type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon edit-btn-class" title="{{ __('Edit') }}" href="{{ route('admin.edit.hospitality.sector',['company'=>$company->id,'hospitalitySector'=>$model->id]) }}"><i class="fa fa-pen-alt exclude-icon default-icon-color"></i></a>
                                    <a data-toggle="modal" data-target="#copy-{{ $model->id }}" type="button" class="btn btn-secondary btn-outline-hover-brand btn-icon copy-btn-class" title="{{ __('Copy') }}"><i class="fa fa-layer-group exclude-icon default-icon-color"></i></a>
                                    <div class="modal fade" id="copy-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                        <div class="modal-dialog modal-md modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <form action="{{ route('copy.hospitality',['company'=>$company->id,'hospitalitySector'=>$model->id]) }}" method="post">
                                                    @csrf
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Copy This ?') }}</h5>
                                                        <button type="button" class="close" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="row mb-3">
                                                            <div class="col-md-12 mb-4">
                                                                <label>{{__('Name')}} </label>
                                                                <div class="kt-input-icon">
                                                                    <input value="{{ $model->getName() }}" type="text" name="name" class="form-control">
                                                                </div>
                                                            </div>





                                                        </div>


                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                                        <button type="submit" class="btn bg-green  text-white 
																submit-form-btn
																
																">{{ __('Confirm') }}</button>
                                                    </div>

                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- @endif  --}}
                                    {{-- @if(hasAuthFor('delete lc settlement internal transfer')) --}}
                                    <a data-toggle="modal" data-target="#delete-study-{{ $model->id }}" type="button" class="btn delete-btn-class btn-secondary btn-outline-hover-danger btn-icon" title="Delete" href="#"><i class="fa fa-trash-alt exclude-icon default-icon-color"></i></a>
                                    <div class="modal fade" id="delete-study-{{ $model->id }}" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered" role="document">
                                            <div class="modal-content">
                                                <form action="{{ route('admin.delete.hospitality.sector',['company'=>$company->id,'hospitalitySector'=>$model->id]) }}" method="post">
                                                    @csrf
                                                    @method('delete')
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="exampleModalLongTitle">{{ __('Do You Want To Delete This Item ?') }}</h5>
                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                            <span aria-hidden="true">&times;</span>
                                                        </button>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Close') }}</button>
                                                        <button type="submit" class="btn btn-danger">{{ __('Confirm Delete') }}</button>
                                                    </div>

                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    {{-- @endif  --}}
                                </span>
                            </td>



                        </tr>




                        @endforeach







                    </x-slot>




                </x-tables.repeater-table>


            </div>
        </div>







        <!--End:: Tab Content-->



        <!--End:: Tab Content-->
    </div>
</div>
</div>

@endsection
@section('js')
<!--begin::Page Scripts(used by this page) -->
<script src="{{ url('assets/vendors/general/bootstrap-datepicker/dist/js/bootstrap-datepicker.min.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/custom/js/vendors/bootstrap-datepicker.init.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-datepicker.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/bootstrap-select/dist/js/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/bootstrap-select.js') }}" type="text/javascript">
</script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/lib.js') }}" type="text/javascript"></script>
<script src="{{ url('assets/vendors/general/jquery.repeater/src/jquery.input.js') }}" type="text/javascript">
</script>
{{-- <script src="{{ url('assets/vendors/general/jquery.repeater/src/repeater.js') }}" type="text/javascript"></script> --}}
{{-- <script src="{{ url('assets/js/demo1/pages/crud/forms/widgets/form-repeater.js') }}" type="text/javascript"></script> --}}

<script>
    $(document).on('click', '.js-close-modal', function() {
        $(this).closest('.modal').modal('hide');
    })

</script>
<script>
    $(document).on('change', '.js-search-modal', function() {
        const searchFieldName = $(this).val();
        const popupType = $(this).attr('data-type');
        const modal = $(this).closest('.modal');
        if (searchFieldName === 'transfer_date') {
            modal.find('.data-type-span').html('[ {{ __("Transfer Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else if (searchFieldName === 'contract_end_date') {
            modal.find('.data-type-span').html('[ {{ __("Contract End Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else if (searchFieldName === 'balance_date') {
            modal.find('.data-type-span').html('[ {{ __("Balance Date") }} ]')
            $(modal).find('.search-field').val('').trigger('change').prop('disabled', true);
        } else {
            modal.find('.data-type-span').html('[ {{ __("Contract Start Date") }} ]')
            $(modal).find('.search-field').prop('disabled', false);
        }
    })
    $(function() {

        $('.js-search-modal').trigger('change')

    })
	$(document).on('change','select#first-study-id',function(){
		const firstId = $(this).val();
		$.ajax({
			url:"{{ route('get.second.hospitality.sector',['company'=>$company->id]) }}",
			data:{firstId},
			type:"get" , 
			success:function(res){
				let items = res.secondSectors ; 
				let options  = '';
				for(index in items){
					options = `<option value="${items[index].id}">${items[index].study_name}</option>`;
				}
				$('select#second-study-id').empty().append(options).trigger('change');
			}
		})
	})
	$('select#first-study-id').trigger('change')
</script>
@endsection
@push('js')
<script src="/custom/js/financial-planning/common.js"></script>

@endpush

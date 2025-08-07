@extends('layouts.dashboard')
@section('css')
<x-styles.commons></x-styles.commons>
@endsection
@section('sub-header')
<x-main-form-title :id="'main-form-title'" :class="''">{{ __('Property Acquisition Cost Input Sheet') }}</x-main-form-title>
<x-navigators-dropdown :navigators="$navigators"></x-navigators-dropdown>
<style>
    .total-tr {
        background-color: #074FA4 !important
    }

    .total-tr td {
        color: white !important;
    }

</style>
@endsection
@section('content')
<div class="row">
    <div class="col-md-12">


        <form id="form-id" class="kt-form kt-form--label-right" method="POST" enctype="multipart/form-data" action="{{$storeRoute}}">

            @csrf
            <input type="hidden" name="company_id" value="{{ getCurrentCompanyId()  }}">
            <input type="hidden" name="creator_id" value="{{ \Auth::id()  }}">
            <input type="hidden" name="hospitality_sector_id" value="{{ $hospitalitySector->id  }}">

            @php
            $modelName = lastSegmentInRequest() ;
            @endphp
            <input type="hidden" name="model_name" value="{{ $modelName }}">



            {{-- Property Acquisition Cost Section  --}}

            <div class="kt-portlet">
                <div class="kt-portlet__body">
                    <div class="row">
                        <div class="col-md-10">
                            <div class="d-flex align-items-center ">
                                <h3 class="font-weight-bold form-label kt-subheader__title small-caps mr-5" style=""> {{ __('Property Acquisition Cost') }} </h3>
                                <input class="can-not-be-removed-checkbox" type="checkbox" name="has_property_section" value="1" style="width:20px;height:20px" checked readonly>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="btn active-style show-hide-repeater" data-query=".property-repeater">{{ __('Show/Hide') }}</div>
                        </div>
                    </div>
                    <div class="row">
                        <hr style="flex:1;background-color:lightgray">
                    </div>
                    <div class="row" data-repeater-row=".property-repeater">

                        <div class="form-group row" style="flex:1;">
                            <div class="col-md-12 mt-3">
                                <div class="row mb-5">

                                    <div class="col-md-2 ">
                                        <x-form.label :class="'label'" :id="'test-id'">{{ __('Purchase Date') }} @include('star') </x-form.label>
                                        <div class="kt-input-icon">
                                            <div class="input-group date">
                                              <input type="text" name="purchase_date" data-max-date class="form-control only-month-year-picker date-input" value="{{ formatDateForInput(isset($model) && $model->getPropertyPurchaseDateAsString()? $model->getPropertyPurchaseDateAsString() : $hospitalitySector->getStudyStartDate()) }}"  />
                                                <div class="input-group-append">
                                                    <span class="input-group-text">
                                                        <i class="la la-calendar"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>


                                    <div class="col-md-3 ">
                                        <label class="form-label font-weight-bold">{{ __('Purchase Cost') }} @include('star') </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group">
                                                <input type="text" class="form-control only-greater-than-or-equal-zero-allowed reclaulate-total-purchase-cost purchase-cost" value="{{  number_format($model ? $model->getPropertyPurchaseCost() : 0,0)  }}" step="0.1">
                                                <input name="property_purchase_cost" class="" type="hidden" value="{{  $model ? $model->getPropertyPurchaseCost() :0  }}">
                                            </div>
                                        </div>
                                    </div>


                                    <div class="col-md-2 ">
                                        <label class="form-label font-weight-bold">{{ __('Contingency Rate (%)') }} </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group">
                                                <input type="text" class="form-control only-percentage-allowed reclaulate-total-purchase-cost property-contingency-rate" name="property_contingency_rate" value="{{  $model ? $model->getPropertyContingencyRate() : 0  }}" step="0.1">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-3 ">
                                        <label class="form-label font-weight-bold">{{ __('Total Purchase Cost') }} </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group">
                                                <input readonly type="text" class="form-control recalaculate-debt-amount reclculate-equity-amount recalculate-hard-debet-amount  total-purchase-cost-input-text" value="{{  number_format($model ? $model->getTotalPurchaseCost() : 0)   }}" step="0.1">
                                                <input type="hidden" class=" total-purchase-cost-input-hidden" value="{{  $model? $model->getTotalPurchaseCost() : 0  }}">
                                            </div>
                                        </div>
                                    </div>


                                    <div class="col-md-2">
                                        <label class="form-label font-weight-bold">{{ __('Select Property Payment Method') }} @include('star') </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group ">
                                                <select data-live-search="true" name="property_payment_method" required class="form-control property-payment-method-class  form-select form-select-2 form-select-solid fw-bolder">
                                                    @foreach(getPropertyPaymentMethod() as $value=>$name)
                                                    <option value="{{ $value }}" @if(isset($model) && $model->getPropertyPaymentMethod() == $value ) selected @endif> {{ $name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>












                                </div>

                                <div class="row d-none installment-section" data-property-payment-method="installment">
                                    <div class="col-md-2 mb-5 ">
                                        <label class="form-label font-weight-bold">{{ __('First Down payment %') }} </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group">
                                                <input type="text" class="form-control only-percentage-allowed reclulate-property-balance-sheet " value="{{  number_format($model ? $model->getFirstPropertyDownPaymentPercentage() : 0 ,1)   }}" step="0.1">
                                                <input type="hidden" class="reclulate-property-balance-sheet first-property-down-payment-percentage" name="first_property_down_payment_percentage" value="{{  $model ?$model->getFirstPropertyDownPaymentPercentage( ) : 0   }}" step="0.1">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-2 mb-5 ">
                                        <label class="form-label font-weight-bold">{{ __('Second Down payment %') }} </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group">
                                                <input type="text" class="form-control only-percentage-allowed reclulate-property-balance-sheet " value="{{   number_format($model ? $model->getSecondPropertyDownPaymentPercentage() : 0 ,1)   }}" step="0.1">
                                                <input type="hidden" class="second-property-down-payment-percentage" name="second_property_down_payment_percentage" value="{{  $model ? $model->getSecondPropertyDownPaymentPercentage() : 0   }}" step="0.1">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-2 mb-5 ">
                                        <label class="form-label font-weight-bold">{{ __('After Month') }} </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group">
                                                <input type="numeric" class="form-control only-greater-than-or-equal-zero-allowed" name="property_after_month" value="{{  $model ? $model->getPropertyAfterMonthDays() : 0   }}" step="1">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-2 mb-5 ">
                                        <label class="form-label font-weight-bold">{{ __('Balance Rate') }} </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group">
                                                <input readonly type="text" class="form-control property-balance-rate" value="{{  number_format($model ? $model->getPropertyBalanceRate() : 0 ,1)   }}" step="1">
                                                <input type="hidden" class="form-control property-balance-rate" name="property_balance_rate" value="{{  $model ? $model->getPropertyBalanceRate() : 0   }}" step="1">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-2 mb-5 ">
                                        <label class="form-label font-weight-bold">{{ __('Installment Count') }} </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group">
                                                <input name="property_installment_count" type="numeric" class="form-control only-greater-than-or-equal-zero-allowed" value="{{  $model ?$model->getPropertyInstallmentCount():0   }}" step="1">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-2 mb-5 ">
                                        <label class="form-label font-weight-bold">{{ __('Installment Interval') }} @include('star') </label>
                                        <div class="kt-input-icon">
                                            <div class="input-group ">
                                                <select data-live-search="true" name="installment_interval" required class="form-control  form-select form-select-2 form-select-solid fw-bolder">
                                                    @foreach(getDurationIntervalTypesForSelect() as $intervalTypeArr)
                                                    <option value="{{ $intervalTypeArr['value'] }}" @if(isset($model) && $model->getPropertyPaymentMethod() == $intervalTypeArr['value'] ) selected @endif> {{ $intervalTypeArr['title'] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                    </div>


                                </div>



                                <div class="row collection-policy-row d-none" data-property-payment-method="customize">

                                    <div class="table-responsive" data-name="per-sales-channel-collection">
                                        <table class="table table-striped table-bordered table-hover table-checkable kt_table_2 table-for-collection-policy removeGlobalStyle">

                                            <tbody class="">

                                                @php
                                                $currentTotal = [];
                                                $subItemIndex=0;

                                                @endphp



                                                <tr>

                                                    @php
                                                    $order = 1 ;
                                                    @endphp

                                                    <td class="align-middle ">

                                                        @php
                                                        $currentVal = 0 ;


                                                        @endphp
                                                        <div class="form-group three-dots-parent" style="
								
																	flex-direction: row !important;
																	width:100%;
																	gap: 40px !important;
																	
																	">



                                                            <div class="basis-100 for-only-one-checked w-100 " data-item="customize">
                                                                <div class="customize-content" style="display:flex;flex-direction:row !important;flex-wrap:wrap !important;width:100%;background-color:#F2F2F2 !important;padding-top:10px;padding-bottom:10px;">
                                                                    <div class="collection-rate d-flex flex-wrap w-100 mb-2 align-items-center ">
                                                                        <div class="d-flex align-items-center justify-content-center w-100 mb-3 ">

                                                                            <label class="label-clr label-size form-label font-weight-bold mb-0 ml-5 " style="white-space:nowrap;">{{ __('Collection Rate %') }} </label>

                                                                        </div>
                                                                        @for($i = 0 ; $i<4 ; $i++) <div class="collection-rate-item mb-3 d-flex flex-column align-items-start col">
                                                                            <label class="form-label label-text label-clr form-label font-weight-bold"> Rate {{ $i+1 }} % </label>
                                                                            <div class="d-flex">
                                                                                <div class="d-inline-flex align-items-center ">
                                                                                    <input class="form-control collection_rate_input mr-2 only-percentage-allowed" type="text" name="sub_items[collection_policy][type][value][rate][{{ $i }}]" data-identifier="{{ 0 }}" value="{{ $model->getSalesChannelRateAndDueInDays($i,'rate')??0 }}">
                                                                                </div>
                                                                                <span class="percentage-weight">%</span>
                                                                            </div>
                                                                    </div>
                                                                    @endfor
                                                                    <div class="d-flex align-items-center justify-content-center" style="margin-left:10px;margin-bottom:1rem">
                                                                        <div class="d-inline-flex align-items-center flex-column">
                                                                            <label class="label form-label ">{{ __('Total') }}</label>
                                                                            <input data-identifier="{{ 0 }}" style="width:240px;margin-left:20px" value="{{ $model->isCustomizeCollectionPolicy()?100:0 }}" class="form-control collection_rate_total_class mr-2" readonly name="sub_items[collection_rate_total][{{ $i }}]">
                                                                        </div>

                                                                        <span class="percentage-weight">%</span>
                                                                    </div>
                                                                </div>
                                                                <div class="due-in-days d-flex flex-wrap w-100">
                                                                    <div class="d-flex align-items-center justify-content-center w-100 mb-3">

                                                                        <label class="label-clr label-size form-label font-weight-bold label form-label mb-0 ml-5">{{ __('Due In Days') }}</label>
                                                                    </div>


                                                                    @for($i=0;$i<4;$i++) <div class="collection-rate-item mb-3  d-flex align-content-center col ">
                                                                        <select style="" name="sub_items[collection_policy][type][value][due_in_days][{{ $i }}]" class="form-control mr-2 ">
                                                                            @foreach(dueInDaysInAcquisition() as $dueDay=>$dueDayName)
                                                                            <option value="{{ $dueDay }}" @if($model->getSalesChannelRateAndDueInDays($i,'due_in_days') == $dueDay)
                                                                                selected
                                                                                @endif

                                                                                >{{ $dueDayName }}

                                                                            </option>
                                                                            @endforeach
                                                                        </select>
                                                                        <span class="percentage-weight visibility-hidden">%</span>
                                                                </div>
                                                                @endfor

                                                                <div class="d-flex align-items-center justify-content-center" style="margin-left:10px;margin-bottom:1rem;visibility:hidden">
                                                                    <div class="d-inline-flex align-items-center flex-column">
                                                                        <input style="width:240px;margin-left:20px" value="0" class="form-control collection_rate_total_class mr-2" readonly>
                                                                    </div>

                                                                    <span class="percentage-weight">%</span>
                                                                </div>





                                                            </div>
                                                        </div>
                                    </div>


                                </div>

                                </td>

                                @php
                                $order = $order +1 ;
                                @endphp
                                {{-- @endforeach --}}

                                </tr>
                                @php
                                $subItemIndex = $subItemIndex +1 ;
                                @endphp





                                </tbody>
                                </table>
                            </div>


                        </div>
                        <div class="row mt-4">
                            <div class="col-md-3 mb-4 ">
                                <label class="form-label font-weight-bold">{{ __('Equity Funding %') }} </label>
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input type="text" class="form-control only-percentage-allowed reclculate-equity-amount  equity-funding-percentage-text recalulate-debt-funding" value="{{  $model->getPropertyEquityFundingRate()   }}" step="0.1">
                                        <input type="hidden" name="property_equity_funding_rate" class="equity-funding-percentage-hidden" value="{{  $model->getPropertyEquityFundingRate()   }}" step="0.1">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-4 ">
                                <label class="form-label font-weight-bold">{{ __('Equity Amount') }} </label>
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input readonly type="text" class="form-control equity-amount-text" name="equity_amount" value="{{  number_format($model->getEquityAmount())   }}" step="0.1">
                                        <input type="hidden" class="form-control equity-amount-hidden" name="equity_amount" value="{{  $model->getEquityAmount()   }}">
                                    </div>
                                </div>
                            </div>


                            <div class="col-md-3 mb-4 ">
                                <label class="form-label font-weight-bold">{{ __('Debt Funding %') }} </label>
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input readonly type="text" class="form-control loan-form-trigger recalaculate-debt-amount recalculate-hard-debet-amount only-percentage-allowed debt-funding-input-text" value="{{  number_format($model->getDebtFundingPercentage(),1)   }}" step="0.1">
                                        <input type="hidden" class="debt-funding-input-text-hidden " value="{{  $model->getDebtFundingPercentage()   }}" step="0.1">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-3 mb-4 ">
                                <label class="form-label font-weight-bold">{{ __('Debt Amount') }} </label>
                                <div class="kt-input-icon">
                                    <div class="input-group">
                                        <input readonly type="text" class="form-control debt-amount-input-text" name="debt_amount" value="{{  number_format($model->getDebtAmount(),0)   }}" step="0.1">
                                        <input type="hidden" class="form-control debt-amount-input-hidden" name="debt_amount" value="{{  $model->getDebtAmount()   }}" step="0.1">
                                    </div>
                                </div>
                            </div>

                            {{-- <div class="col-md-2 mb-4 ">
                                <label class="form-label font-weight-bold" style="visibility:hidden">{{ 1 }} </label>
                            <div class="kt-input-icon">
                                <div class="input-group">
                                    <button class="btn active-style mx-auto" show-loan-type="{{ LAND_LOAN }}"> {{ __('Create Loan') }} </button>
                                </div>
                            </div>
                        </div> --}}


                    </div>


                    @include('loan-form',[
                    'currentSectionName'=>PROPERTY_LOAN
                    ])


                </div>
            </div>
    </div>
</div>






<!--end::Form-->

</div>






<!--end::Form-->

</div>


</div>

<!--end::Portlet-->



@php
$currentSectionName = PROPERTY_ACQUISITION;
@endphp

<div class="kt-portlet">
    <div class="kt-portlet__body">
        <div class="table-responsive percenatge-from-rooms-revenues-method">
            <table class="table table-striped table-bordered table-hover table-checkable kt_table_2 ">
                <thead>
                    <tr>
                        <th class="text-center">{{ __('Item Name') }}</th>
                        <th class="text-center"> {{ __('As % Property Cost') }} </th>
                        <th class="text-center"> {{ __('Item Value') }} </th>
                        <th class="text-center"> {{ __('Depreciation Duration')}} </th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $currentTotal = [];

                    @endphp

                    @for($instance=0 ; $instance<count(getPropertyAcquisitionNameForIndex()) ; $instance++) @php $propertyCostBreakDown=$hospitalitySector->getPropertyCostBreakdownForSection($modelName,$currentSectionName , $instance);
                        @endphp
                        <tr>
                            <td style="vertical-align:middle;text-transform:capitalize;text-align:left">
                                <input readonly type="text" class="form-control placeholder-light-gray exclude-text mt-2" name="name[{{ $currentSectionName }}][{{ $instance }}]" value="{{ getPropertyAcquisitionNameForIndex($instance) }}" placeholder="{{ __('Please Enter Item Name...') }}">
                            </td>






                            <td>

                                @php
                                $currentVal = $propertyCostBreakDown ? $propertyCostBreakDown->getPropertyCostPercentage() : 0;
                                @endphp
                                <div class="mt-2 d-flex justify-content-center align-items-center">
                                    <input data-has-row-total="0" data-max-row-total="0" data-has-column-total="1" data-max-column-total="100" data-is-percentage="1" data-no-digits="1" data-order="1" data-year="1" data-index="{{ $instance ??0 }}" type="text" class="only-percentage-allowed form-control property-cost-percentage-text text-center target_repeating_amounts" style="margin: initial; max-width: 50%;min-width: 80px;text-align: center" value="{{ number_format($currentVal,1) }}">
                                    <input data-column-identifier="1" type="hidden" class="property-cost-percentage-hidden text-center" name="property_cost_percentage[{{ $currentSectionName }}][{{ $instance }}]" value="{{ $currentVal }}">
                                    <span class="ml-2">
                                        <b>%</b>
                                    </span>
                                </div>

                            </td>


                            <td>

                                @php
                                $currentVal = $propertyCostBreakDown ? $propertyCostBreakDown->getItemAmount() : 0;
                                @endphp
                                <div class="mt-2">
                                    <input data-year="2" data-has-row-total="0" data-max-row-total="0" data-has-column-total="1" data-max-column-total="0" data-is-percentage="0" data-no-digits="0" data-order="2" data-index="{{ $instance ??0 }}" type="text" class="only-greater-than-or-equal-zero-allowed form-control text-center item-value-formatted target_repeating_amounts" style="margin: auto; max-width: 50%;min-width: 80px;text-align: center" value="{{ number_format($currentVal) }}">
                                    <input data-column-identifier="2" data-year="2" type="hidden" class="form-control text-center item-value-hidden " name="item_amount[{{ $currentSectionName }}][{{ $instance }}]" value="{{ $currentVal }}">
                                </div>

                            </td>


                            <td>

                                @php
                                $currentVal = $propertyCostBreakDown ? $propertyCostBreakDown->getDepreciationDuration() : 0;
                                @endphp
                                <div class="mt-2">
                                    @if($instance != 0)
                                    <select name="depreciation_duration[{{ $currentSectionName }}][{{ $instance }}]" class="form-control">
                                        @for($year = 3 ; $year<=25 ;$year++) 
										<option value="{{ $year }}" @if($currentVal==$year) selected @endif> {{ $year . ' ' . __('Years')  }} 
										</option>
                                            @endfor
											<option value="{{ 50 }}" @if($currentVal==50) selected @endif> {{ 50 . ' ' . __('Years')  }} 
										</option>
										
                                    </select>
                                    @endif
                                    {{-- <input type="text" class="only-greater-than-or-equal-zero-allowed form-control"  value="{{ $currentVal }}"> --}}
                                </div>

                            </td>



                        </tr>

                        @endfor

                        {{-- totals row  --}}

                        <tr class="total-tr">
                            <td style="vertical-align:middle;text-transform:capitalize;text-align:center">
                                <b>
                                    {{ __('Total') }}
                                </b>
                            </td>




                            <td>
                                <div class="input-group input-group-sm align-items-center justify-content-center div-for-percentage" style="margin: auto; max-width: 50%;min-width: 80px;text-align: center">
                                    <input data-column-identifier="{{ 1 }}" data-order="{{ 1 }}" data-index="{{ $instance }}" data-year="1" type="text" style="form-conrol" value="{{ 0 }}" readonly data-index="{{ $instance }}" data-year="{{ 0 }}" onchange="this.style.width = ((this.value.length + 1) * 10) + 'px';" class="form-control text-center  size">
                                    <span class="ml-2">
                                        <b>%</b>
                                    </span>
                                </div>
                            </td>

                            <td>
                                <div class="input-group input-group-sm align-items-center justify-content-center div-for-percentage" style="margin: auto; max-width: 50%;min-width: 80px;text-align: center">
                                    <input data-column-identifier="{{ 2 }}" type="text" style="form-conrol total-percenttage" value="{{ 0 }}" readonly data-order="{{ 2 }}" data-index="{{ $instance }}" data-year="{{ 2 }}" onchange="this.style.width = ((this.value.length + 1) * 10) + 'px';" class="form-control  text-center size total-percentage--field">
                                    <span class="ml-2">
                                        <b></b>
                                    </span>
                                </div>
                            </td>
                            <td>
                            </td>

                        </tr>



                        {{-- end total rows --}}





                </tbody>
            </table>
        </div>
        <div class="row mt-3">
            <div class="col-md-3 ">
                <label class="form-label font-weight-bold">{{ __('Item Name') }} </label>
                <div class="kt-input-icon">
                    <div class="input-group">
                        {{-- only-greater-than-or-equal-zero-allowed --}}
                        <input readonly type="text" class="form-control  " value="{{  __('Refurbishment & Renovation')  }}" step="0.1">
                        <input name="replacement_cost_name" class="" type="hidden" value="{{  __('Refurbishment & Renovation')  }}">
                    </div>
                </div>
            </div>

            <div class="col-md-2 ">
                <label class="form-label font-weight-bold">{{ __('Replacement Cost Rate') }} </label>
                <div class="kt-input-icon">
                    <div class="input-group">
                        {{-- only-greater-than-or-equal-zero-allowed --}}
                        <input type="text" class="form-control  only-percentage-allowed" value="{{  number_format($model->getReplacementCostRateForBuilding(),1)  }}" step="0.1">
                        <input name="replacement_cost_rate" class="" type="hidden" value="{{  $model->getReplacementCostRateForBuilding()  }}">
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label font-weight-bold">{{ __('Select Replacement Interval') }} @include('star') </label>
                <div class="kt-input-icon">
                    <div class="input-group ">
                        <select data-live-search="true" name="replacement_interval" required class="form-control property-payment-method-class  form-select form-select-2 form-select-solid fw-bolder">
                            @for($year = 1 ; $year<=5 ;$year++) <option value="{{ $year }}" @if($model->getReplacementIntervalForBuilding() ==$year) selected @endif> {{ $year . ' ' . __('Years')  }} </option>
                                @endfor
                        </select>
                    </div>
                </div>
            </div>



        </div>
		
		
		<div class="row mt-3">
            <div class="col-md-3 ">
                <label class="form-label font-weight-bold">{{ __('Item Name') }} </label>
                <div class="kt-input-icon">
                    <div class="input-group">
                        {{-- only-greater-than-or-equal-zero-allowed --}}
                        <input readonly type="text" class="form-control  " value="{{  __('Furniture, Fixture & Equipment')  }}" step="0.1">
                        <input name="ffe_replacement_cost_name" class="" type="hidden" value="{{  __('Furniture, Fixture & Equipment')  }}">
                    </div>
                </div>
            </div>

            <div class="col-md-2 ">
                <label class="form-label font-weight-bold">{{ __('Replacement Cost Rate') }} </label>
                <div class="kt-input-icon">
                    <div class="input-group">
                        {{-- only-greater-than-or-equal-zero-allowed --}}
                        <input type="text" class="form-control  only-percentage-allowed" value="{{  number_format($model->getFFEReplacementCostRate(),1)  }}" step="0.1">
                        <input name="ffe_replacement_cost_rate" class="" type="hidden" value="{{  $model->getFFEReplacementCostRate()  }}">
                    </div>
                </div>
            </div>

            <div class="col-md-2">
                <label class="form-label font-weight-bold">{{ __('Select Replacement Interval') }} @include('star') </label>
                <div class="kt-input-icon">
                    <div class="input-group ">
                        <select data-live-search="true" name="ffe_replacement_interval" required class="form-control property-payment-method-class  form-select form-select-2 form-select-solid fw-bolder">
                            @for($year = 1 ; $year<=5 ;$year++) <option value="{{ $year }}" @if($model->getFFEReplacementInterval() ==$year) selected @endif> {{ $year . ' ' . __('Years')  }} </option>
                                @endfor
                        </select>
                    </div>
                </div>
            </div>



        </div>
		
    </div>
</div>






<div class="kt-portlet">
    <div class="kt-portlet__body">
        <x-save-or-back :btn-text="__('Create')" />
    </div>
</div>



</div>









</div>
</div>
</form>

</div>
@endsection
@section('js')
<x-js.commons></x-js.commons>

<script>
    $(document).on('click', '[show-loan-type]', function(e) {
        e.preventDefault();
        const loanType = $(this).attr('show-loan-type')
        $('[data-loan="' + loanType + '"]').toggleClass('d-none')
    })

    $(document).on('keyup', '.pricing-calc-item', function() {
        let base_rate = $('#base_rate').val();
        let margin_rate = $('#margin_rate').val();
        if (isPercentageNumber(base_rate) && isPercentageNumber(margin_rate)) {
            let pricing = parseFloat(base_rate) + parseFloat(margin_rate);
            $('#pricing').val(pricing);
        } else if (isPercentageNumber(base_rate)) {
            let pricing = parseFloat(base_rate);
            $('#pricing').val(pricing);
        } else if (isPercentageNumber(margin_rate)) {
            let pricing = parseFloat(margin_rate);
            $('#pricing').val(pricing);
        } else {
            $('#pricing').val(0);
        }

    })

    $(document).on('change', '.property-payment-method-class', function() {
        $('[data-property-payment-method]').addClass('d-none');
        const paymentMethod = $(this).val();

        if (paymentMethod) {

            $('[data-property-payment-method="' + paymentMethod + '"]').removeClass('d-none');
        }

    })



    $(document).on('change', '.reclulate-property-balance-sheet', function() {
        let firstPropertyDownPaymentPercentage = number_unformat($('.first-property-down-payment-percentage').val());
        let secondPropertyDownPaymentPercentage = number_unformat($('.second-property-down-payment-percentage').val());
        let propertyBalanceRate = 100 - firstPropertyDownPaymentPercentage - secondPropertyDownPaymentPercentage;
        $('.property-balance-rate').val(propertyBalanceRate).trigger('change');
    })
    $(document).on('change', '.reculate-hard-construction-contingency-rate', function() {
        let hardConstructionCost = number_unformat($('.hard-construction-cost').val());
        hardConstructionCost = hardConstructionCost;
        let hardConstructionContingencyRate = number_unformat($('.hard-construction-contingency-rate').val());
        hardConstructionContingencyRate = hardConstructionContingencyRate / 100;
        let totalConstructionCost = hardConstructionCost * (1 + hardConstructionContingencyRate);
        $('.total-hard-construction-cost-hidden').val(totalConstructionCost);
        $('.total-hard-construction-cost-text').val(totalConstructionCost).trigger('change');

    })
    $('.reclulate-property-balance-sheet').trigger('change')
    $(document).on('change', '.reclaulate-hard-balance-rate-two', function() {
        let hardDownPayment = number_unformat($('.hard-down-payment').val());
        let hardBalanceRateOnePercentage = number_unformat($('.hard-balance-rate-one-percentage').val());
        let hardBalanceRateTwo = 100 - hardDownPayment - hardBalanceRateOnePercentage;
        $('.hard-balance-rate-two').val(number_format(hardBalanceRateTwo, 1)).trigger('change');
        $('.hard-balance-rate-two-hidden').val(hardBalanceRateTwo);
    })
    $('.reclaulate-hard-balance-rate-two').trigger('change')


    $(document).on('change', '.recalulate-debt-funding', function() {
        let equityFundingPercentage = number_unformat($('.equity-funding-percentage-hidden').val());
        let debtFunding = 100 - equityFundingPercentage;
        $('.debt-funding-input-hidden').val(debtFunding);
        $('.debt-funding-input-text').val(number_format(debtFunding, 1)).trigger('change');
    })
    $('.recalulate-debt-funding').trigger('change')



    $(document).on('change', '.reclaulate-hard-equity-amount', function() {
        let totalHardConstructionCost = number_unformat($('.total-hard-construction-cost-hidden').val());
        let hardEquityFunding = number_unformat($('.hard-equity-funding').val());
        hardEquityFunding = hardEquityFunding / 100;
        let hardEquityAmount = hardEquityFunding * totalHardConstructionCost;
        $('.equity-amount-input-text').val(number_format(hardEquityAmount)).trigger('keyup');
        $('.equity-amount-input-hidden').val(hardEquityAmount);
    })
    $('.reclaulate-hard-equity-amount').trigger('change')



    $(document).on('change', '.recalculate-hard-debt-amount', function() {
        let totalHardConstructionCost = number_unformat($('.total-hard-construction-cost-hidden').val());
        let hardDebtFunding = number_unformat($('.hard-debt-funding-input-hidden').val());
        hardDebtFunding = hardDebtFunding / 100;
        let hardDebtAmount = hardDebtFunding * totalHardConstructionCost;
        $('.hard-debt-amount-hidden').val(hardDebtAmount);
        $('.hard-debt-amount-text').val(number_format(hardDebtAmount)).trigger('keyup');
    })
    $('.recalculate-hard-debt-amount').trigger('change')



    $(document).on('change', '.reclaulate-total-purchase-cost', function() {
        let purchaseCost = number_unformat($('.purchase-cost').val());
        let propertyContingencyRate = number_unformat($('.property-contingency-rate').val());
        propertyContingencyRate = propertyContingencyRate / 100;
        let propertyTotalPurchaseCost = (1 + propertyContingencyRate) * purchaseCost;
        $('.total-purchase-cost-input-text').val(number_format(propertyTotalPurchaseCost, 0));

        $('.total-purchase-cost-input-hidden').val(propertyTotalPurchaseCost);
        $('.property-cost-percentage-text').trigger('change')
    })
    $('.reclaulate-total-purchase-cost').trigger('change')
    $(document).on('change', '.recalculate-hard-debet-amount', function() {
        let totalPurchaseCost = number_unformat($('.total-purchase-cost-input-hidden').val());
        let hardDebtFundingPercentage = number_unformat($('.hard-debt-funding-input-text-hidden').val()) / 100;
        let hardDebtAmount = totalPurchaseCost * hardDebtFundingPercentage;
        $('.hard-debt-amount-input-hidden').val(hardDebtAmount);
        $('.hard-debt-amount-input-text').val(number_format(hardDebtAmount, 0));
    })
    $('.recalculate-hard-debet-amount').trigger('change')

    $('.property-payment-method-class').trigger('change')


    $(document).on('change', '.reclculate-equity-amount', function() {
        let totalPurchaseCost = number_unformat($('.total-purchase-cost-input-hidden').val());
        let equityFundingPercentage = number_unformat($('.equity-funding-percentage-hidden').val()) / 100;
        let equityAmount = totalPurchaseCost * equityFundingPercentage;
        $('.equity-amount-text').val(number_format(equityAmount, 1)).trigger('change');
        $('.equity-amount-hidden').val(equityAmount);
    })
    $('.reclculate-equity-amount').trigger('change')


    $(document).on('change', '.recalaculate-debt-amount', function() {
        let totalPurchaseCost = number_unformat($('.total-purchase-cost-input-hidden').val());
        let debtFunding = number_unformat($('.debt-funding-input-text-hidden').val());
        debtFunding = debtFunding / 100;
        let debtAmount = debtFunding * totalPurchaseCost;
        $('.debt-amount-input-text').val(number_format(debtAmount)).trigger('keyup');
        $('.debt-amount-input-hidden').val(debtAmount);
    })
    $('.recalaculate-debt-amount').trigger('change')

    //TODO:Uncaught TypeError: studyStartDate.addMonths is not a function
    $(document).on('change', '.recalc-hard-end-date', function(e) {
        e.preventDefault()
        const studyStartDate = new Date($('.hard-start-date').val());
        const studyDuration = parseFloat($('.hard-duration').val());
        if (studyDuration) {
            // const numberOfMonths = (studyDuration * 12) - 1
            const numberOfMonths = studyDuration
            let studyEndDate = studyStartDate.addMonths(numberOfMonths)
            studyEndDate = formatDate(studyEndDate)
            $('#hard-construction-end-date').val(studyEndDate).trigger('change')
        }

    })
    $('.recalc-hard-end-date').trigger('change')


    $(document).on('click', '.save-form', function(e) {
        e.preventDefault(); {

            let form = document.getElementById('form-id');
            var formData = new FormData(form);
            //   alert(document.body.getAttribute('data-token'))
            //	formData.append('_token',document.body.getAttribute('data-token'))
            $('.save-form').prop('disabled', true);

            $.ajax({
                cache: false
                , contentType: false
                , processData: false
                , url: form.getAttribute('action')
                , data: formData
                , type: form.getAttribute('method')
                , success: function(res) {
                    $('.save-form').prop('disabled', false)

                    Swal.fire({
                        icon: 'success'
                        , title: res.message,

                    });

                    window.location.href = res.redirectTo;




                }
                , complete: function() {
                    $('#enter-name').modal('hide');
                    $('#name-for-calculator').val('');

                }
                , error: function(res) {
                    $('.save-form').prop('disabled', false);
                    $('.submit-form-btn-new').prop('disabled', false)
                    Swal.fire({
                        icon: 'error'
                        , title: res.responseJSON.message
                    , });
                }
            });
        }
    })


    $(document).on('change', '.use-rooms', function() {
        let useRooms = $("#use-rooms-1").is(':checked')
        if (useRooms) {
            $('.rooms-repeater').fadeIn(300)
            $('input[type="radio"][name*="rooms"]').val(1);

        } else {
            $('.rooms-repeater').fadeOut(300);
            $('input[type="radio"][name*="rooms"]').val(0);
        }
    });

    $('.use-rooms').trigger('change')




    $(document).on('change', '.use-foods', function() {
        let useFoods = $("#use-foods-1").is(':checked')
        if (useFoods) {
            $('.foods-repeater').fadeIn(300)
            $('input[type="radio"][name*="foods"]').val(1);

        } else {
            $('.foods-repeater').fadeOut(300);
            $('input[type="radio"][name*="foods"]').val(0);
        }
    });
    $('.use-foods').trigger('change')



    $(document).on('change', '.use-casino', function() {
        let useCasino = $("#use-casinos-1").is(':checked')

        if (useCasino) {
            $('.casino-repeater').fadeIn(300)
            $('input[type="radio"][name*="casinos"]').val(1);
        } else {
            $('.casino-repeater').fadeOut(300);
            $('input[type="radio"][name*="casinos"]').val(0);
        }
    });

    $('.use-casino').trigger('change')


    $(document).on('change', '.use-meeting', function() {
        let useCasino = $("#use-meetings-1").is(':checked')

        if (useCasino) {
            $('.meeting-repeater').fadeIn(300)
            $('input[type="radio"][name*="meetings"]').val(1);
        } else {
            $('.meeting-repeater').fadeOut(300);
            $('input[type="radio"][name*="meetings"]').val(0);
        }
    })
    $('.use-meeting').trigger('change')


    $(document).on('change', '.use-other', function() {
        let useCasino = $("#use-others-1").is(':checked')

        if (useCasino) {
            $('.other-repeater').fadeIn(300)
            $('input[type="radio"][name*="other"]').val(1);
        } else {
            $('.other-repeater').fadeOut(300);
            $('input[type="radio"][name*="other"]').val(0);
        }
    })
    $('.use-other').trigger('change')

</script>

<script>


</script>

<script>
    $(document).find('.datepicker-input').datepicker({
        dateFormat: 'mm-dd-yy'
        , autoclose: true
    })
    $(document).on('change', '.can-not-be-removed-checkbox', function() {
        $(this).prop('checked', true)
    })

    $(document).on('click', '.show-hide-repeater', function() {
        const query = this.getAttribute('data-query')
        $(query).fadeToggle(300)

    })
    $(document).on('change', '.not-allowed-duplication-in-selection-inside-repeater', function() {
        const val = $(this).val()
        const currentSelect = this
        const currentSelectedOption = $(currentSelect).find('option[value="' + val + '"]')
        const commonParent = $(this).closest('[data-repeater-list]')
        // let selectItems = []
        // $(commonParent).find('select').each(function(index,select){
        // 	selectItems.push($(select).val())
        // })
        $(commonParent).find('select').each(function(index, select) {
            if (select != currentSelect) {
                if ($(select).find('option[value="' + val + '"]:selected').length) {
                    alert('This Item has been choosen before')
                    $(currentSelect).val('').trigger('change')

                }

                //.prop('disabled',true).attr('title','This Item has been choosen before')
            } else {}
        })
    })

    $(document).on('change', '.can-be-toggle-show-repeater-btn', function() {
        let val = $(this).is(':checked')
        let repeaterQuery = $(this).attr('data-repeater-query')
        if (!val) {
            $('.show-hide-repeater[data-query="' + repeaterQuery + '"]').addClass('disabled');
            $('[data-repeater-row="' + repeaterQuery + '"]').fadeOut(300)
            $(this).val(0)
        } else {
            $('.show-hide-repeater[data-query="' + repeaterQuery + '"]').removeClass('disabled');
            $('[data-repeater-row="' + repeaterQuery + '"]').fadeIn(300)
            $(this).val(1)

        }

    })
    $('.can-be-toggle-show-repeater-btn').trigger('change')

    $(document).on('change', '.recalc-study-end-date', function(e) {
        e.preventDefault()
        const studyStartDate = new Date($('.study-start-date').val());
        const studyDuration = parseFloat($('.study-duration option:selected').attr('value'));
        if (studyDuration) {
            const numberOfMonths = (studyDuration * 12) - 1
            let studyEndDate = studyStartDate.addMonths(numberOfMonths)
            studyEndDate = formatDate(studyEndDate)
            $('#study-end-date').val(studyEndDate).trigger('change')

        }

    })
    $(document).on('change', '.recalculate-hard-debet-funding', function(e) {
        e.preventDefault()
        let hardEquityFunding = $('.hard-equity-funding-input-hidden').val();
        hardEquityFunding = number_unformat(hardEquityFunding);
        const hardDebetFunding = (100 - hardEquityFunding);
        $('.hard-debt-funding-input-hidden').val(hardDebetFunding);
        $('.hard-debt-funding-input-text').val(number_format(hardDebetFunding, 1)).trigger('change');
    })
    $('.recalculate-hard-debet-funding').trigger('change')

    $(document).on('change', '.recalate-development-start-date', function() {
        const studyStartDate = new Date($('.study-start-date').val());
        const developementStartAfter = parseFloat($('#developement-start-after').val())
        if (developementStartAfter) {
            const developmentStartDate = formatDate(studyStartDate.addMonths(developementStartAfter))
            $('#development-start-date').val(developmentStartDate)

        }
    })

    $(document).on('change', '.recalate-operation-start-date', function() {
        const studyStartDate = new Date($('.study-start-date').val());
        const propertyWillStartAfter = parseFloat($('#property-will-start-after').val())
        if (propertyWillStartAfter) {
            const developmentStartDate = formatDate(new Date($('.study-start-date').val()).addMonths(propertyWillStartAfter))
            $('#operation-start-date').val(developmentStartDate)
        }
    })

    // 
    $(document).on('change', '.exhange-rate-recalculate', function() {
        let mainFunctionalCurrency = $('.main_functional_currency option:selected').html()
        let additionalCurrency = $('.additional-currency option:selected').html()
        if (mainFunctionalCurrency) {
            $('#exhange-rate-span-id-from').html('From ' + mainFunctionalCurrency)
        }
        if (additionalCurrency) {
            $('#exhange-rate-span-id-to').html(' To ' + additionalCurrency)
        }
    })
    $('.exhange-rate-recalculate').trigger('change')

</script>



<script>
    var type = "{{$loanType}}";

</script>

<script>
    let start_date = '';

    //on change for the borrowing rate
    $(document).on('keyup', '#borrowing_rate', function() {
        loanInterest();
    });
    //on change for the margin_interest rate
    $(document).on('keyup', '#margin_interest', function() {
        loanInterest();
    });
    //calculate loaninterest
    function loanInterest() {
        var margin_interest = +$('#margin_interest').val();
        var borrowing_rate = +$('#borrowing_rate').val();
        var total = borrowing_rate + margin_interest;
        $('#loan_interest').val(total + " % ");
    }




    //installment_interval
    function installmentIntervalChange() {

        var interval = $('#installment_interval').val();
        var select = '';
        if (interval != '') {
            $('#interest_interval option:not(:first)').remove();
            var loan_amount = +$('#loan_amount').val();
            var repayment_duration = +$('#repayment_duration').val();
            var installment_amount = 0;
            if (interval == 'monthly') {
                select = '<option value="monthly">Monthly</option>\n';

                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / (repayment_duration);
                }

            } else if (interval == 'quartly') {
                select = '<option value="monthly">{{__("Monthly")}}</option>\n' +
                    '<option value="quartly">{{__("Quarterly")}}</option>\n';
                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / ((repayment_duration / 12) * 4);
                }
            } else if (interval == 'semi annually') {

                select = '<option value="monthly">{{__("Monthly")}}</option>\n' +
                    '<option value="quartly">{{__("Quarterly")}}</option>\n' +
                    '<option value="semi annually">{{__("Semi-annually")}}</option>\n';

                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / ((repayment_duration / 12) * 2);
                }
            }

            $('#interest_interval').append(select);
            $('#installment_amount').val(installment_amount.toFixed(2));
        }
    }

    function installmentIntervalOld(loan_interval) {
        var interval = $('#installment_interval').val();
        var select = '';
        if (interval != '') {
            if (interval == 'monthly') {

                select = '<option value="monthly" selected >{{__("Monthly")}}</option>\n';


            } else if (interval == 'quartly') {
                if (loan_interval == 'monthly') {
                    select = '<option value="monthly" selected>{{__("Monthly")}}</option>\n' +
                        '<option value="quartly" >{{__("Quarterly")}}</option>\n';
                } else {
                    select = '<option value="monthly">{{__("Monthly")}}</option>\n' +
                        '<option value="quartly" selected>{{__("Quarterly")}}</option>\n';
                }


            } else if (interval == 'semi annually') {
                if (loan_interval == 'monthly') {
                    select = '<option value="monthly" selected>{{__("Monthly")}}</option>\n' +
                        '<option value="quartly">{{__("Quarterly")}}</option>\n' +
                        '<option value="semi annually">{{__("Semi-annually")}}</option>\n';
                } else if (loan_interval == 'quartly') {
                    select = '<option value="monthly">{{__("Monthly")}}</option>\n' +
                        '<option value="quartly" selected>{{__("Quarterly")}}</option>\n' +
                        '<option value="semi annually">Semi-{{__("Annually")}}</option>\n';
                } else {
                    select = '<option value="monthly">{{__("Monthly")}}</option>\n' +
                        '<option value="quartly">{{__("Quarterly")}}</option>\n' +
                        '<option value="semi annually" selected>{{__("Semi-annually")}}</option>\n';
                }

            }

            $('#interest_interval').append(select);
        }
    }
    $(document).on('change', '#installment_interval', function() {
        installmentIntervalChange();

    });
    //Installment Amount

    function instalmentAmount() {
        var interval = $('#installment_interval').val();
        if (interval != '') {
            var loan_amount = +$('#loan_amount').val();
            var repayment_duration = +$('#repayment_duration').val();
            var installment_amount = 0;
            if (interval == 'monthly') {
                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / (repayment_duration);
                }

            } else if (interval == 'quartly') {
                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / ((repayment_duration / 12) * 4);
                }
            } else if (interval == 'semi annually') {
                if (loan_amount != '' && repayment_duration != '') {
                    var installment_amount = loan_amount / ((repayment_duration / 12) * 2);
                }
            }

            $('#installment_amount').val(installment_amount.toFixed(0));
        }
    }
    $(document).on('change', '#repayment_duration', function() {
        instalmentAmount();
    });
    $(document).on('change', '#loan_amount', function() {
        instalmentAmount();
    });




    $(document).on('keypress', '.number', function(e) {
        var keyCode = (e.which) ? e.which : e.keyCode;;
        /*
        8 - (backspace)
        32 - (space)
        48-57 - (0-9)Numbers
        */
        if ((keyCode != 8 || keyCode == 32) && (keyCode != 46 && keyCode > 31) && (keyCode < 48 || keyCode > 57)) {
            return false;
        }
    });

</script>


{{-- salah --}}

<script>
    $(function() {
        $(document).on('change', '.loan-form-trigger', function() {
            const value = $(this).val();
            const parent = $(this).closest('[data-repeater-row]')
            if (value > 0) {
                parent.find('[data-loan]').removeClass('d-none')
            } else {
                parent.find('[data-loan]').addClass('d-none')
            }
        })
        $('.loan-form-trigger').trigger('change')
    })

</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.3/moment.min.js" type="text/javascript">

</script>
<script>
    $(function() {
        $(document).on('change keyup', '.property-cost-percentage-text', function() {
            const parent = $(this).closest('tr')
            const propertyPercentage = number_unformat($(this).val()) / 100
            const totalPropertyPurchaseCost = $('.total-purchase-cost-input-hidden').val()
            const itemValue = totalPropertyPurchaseCost * propertyPercentage
            parent.find('.item-value-hidden').val(itemValue)
            parent.find('.item-value-formatted').val(number_format(itemValue, 1))
			var total = 0 ; 
			$('.item-value-hidden').each(function(index,input){
				total+= parseFloat($(input).val());
			})
			$('.total-percentage--field').val(number_format(total));
        })
        $('.property-cost-percentage-text').trigger('change')
        $(document).on('change keyup', '.item-value-formatted', function() {
            const parent = $(this).closest('tr')
            const itemValue = number_unformat($(this).val())
            const totalPropertyPurchaseCost = $('.total-purchase-cost-input-hidden').val()
            const propertyPercentage = totalPropertyPurchaseCost ? itemValue / totalPropertyPurchaseCost * 100 : 0;
            parent.find('.property-cost-percentage-hidden').val(propertyPercentage)
            parent.find('.property-cost-percentage-text').val(number_format(propertyPercentage, 1))
        })

    })

    $(document).on('change', '.collection_rate_input', function() {
        let identifier = $(this).attr('data-identifier')
        let total = 0;
        $('.collection_rate_input[data-identifier="' + identifier + '"]').each(function(index, input) {
            total += parseFloat(input.value)
        })
        $('.collection_rate_total_class[data-identifier="' + identifier + '"]').val(total)
    })
    $(function() {
        $('.collection_rate_input').trigger('change')
    })

</script>


@endsection

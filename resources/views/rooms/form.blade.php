@extends('layouts.dashboard')
@php
use App\Models\RoomName;
@endphp
@section('css')
<x-styles.commons></x-styles.commons>
<link rel="stylesheet" href="/custom/css/non-banking-services/common.css">
<link rel="stylesheet" href="/custom/css/non-banking-services/leasing-revenue-stream-breakdown.css">
<style>
[data-repeater-create]{
	background-color:green !important;
}
</style>
@endsection
@section('sub-header')
<x-main-form-title :id="'main-form-title'" :class="''">{{ $title }}</x-main-form-title>

{{-- <x-navigators-dropdown :navigators="$navigators"></x-navigators-dropdown> --}}

@endsection
@section('content')

<div class="row">
    <div class="col-md-12">



        <div class="kt-portlet">
            <div class="kt-portlet__body">
                <div class="row">
                    <div class="col-md-10">
                        <div class="d-flex align-items-center ">
                            <h3 class="font-weight-bold form-label kt-subheader__title small-caps mr-5" style=""> {{ $title }} </h3>
                        </div>
                    </div>
                    <div class="col-md-2 text-right">
                        <x-show-hide-btn :query="'.leasing-revenue-stream-category'"></x-show-hide-btn>
                    </div>
                </div>
                <div class="row">
                    <hr style="flex:1;background-color:lightgray">
                </div>
                <div class="row leasing-revenue-stream-category">

                    <div class="form-group row" style="flex:1;">
                        <div class="col-md-12 mt-3" data-repeater-row=".leasing-revenue-stream-category">

                            <form id="main-form-id" class="kt-form kt-form--label-right" method="POST" enctype="multipart/form-data" action="{{  isset($disabled) && $disabled ? '#' :  $storeRoute  }}">

                                <input type="hidden" name="company_id" value="{{ getCurrentCompanyId()  }}">
                                <input type="hidden" name="creator_id" value="{{ \Auth::id()  }}">

                                <div id="roomNames" class="leasing-repeater-parent">
                                    <div class="form-group2  m-form__group2 row">
                                        <div data-repeater-list="roomNames" class="col-lg-6">

                                            @include('rooms._repeater' , [
												'tableId'=>'roomNames',
												'isRepeater'=>true ,
												'canAddNewItem'=>true ,
												'model'=>$model
                                            ])



                                        </div>
                                    </div>

                                </div>
                                <div class="row">
                                    <div class="col-md-6"></div>
                                    <div class="col-md-6 text-right">
                                        <input type="submit" name="save-and-continue" class="btn active-style save-form" value="{{  __('Save & Continue') }}">
                                    </div>
                                </div>
                            </form>

                        </div>


                    </div>

                </div>
            </div>

        </div>



    </div>
    </div>
    @endsection
    @section('js')
    <x-js.commons></x-js.commons>

    <script>


    </script>

    <script>
        $(document).on('click', '.save-form', function(e) {
            e.preventDefault(); {

                const hasSalesChannel = $('#add-sales-channels-share-discount-id:checked').length

                let canSubmitForm = true;
                let errorMessage = '';
                let messageTitle = 'Oops...';



                if (!canSubmitForm) {
                    Swal.fire({
                        icon: "warning"
                        , title: messageTitle
                        , text: errorMessage
                    , })

                    return;
                }

                let formId = $(this).closest('form').attr('id')

                let form = document.getElementById(formId);
                var formData = new FormData(form);
                formData.append('submitBtnType', formId)

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

    </script>

    <script>
        $('.use-rooms:checked').trigger('change');

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

    </script>

    <script src="/custom/js/non-banking-services/common.js"></script>
    <script src="/custom/js/non-banking-services/revenue-stream-breakdown.js"></script>
    {{-- <script></script> --}}
    @endsection

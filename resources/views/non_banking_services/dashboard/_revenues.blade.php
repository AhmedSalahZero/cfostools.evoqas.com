<style>
.min-w-100{
	min-width:120px !important;
}
</style>
     <x-tables.repeater-table :table-class="'col-md-12'" :removeActionBtn="true" :removeRepeater="true" :initialJs="false" :repeater-with-select2="true" :canAddNewItem="false" :parentClass="'js-remove-hidden'" :hide-add-btn="true" :tableName="''" :repeaterId="''" :relationName="'food'" :isRepeater="$isRepeater=!(isset($removeRepeater) && $removeRepeater)">
                                <x-slot name="ths">
                                    <x-tables.repeater-table-th class="  header-border-down max-column-th-class" :title="__('Item')"></x-tables.repeater-table-th>
                                    @foreach($formattedDates as $dateAsIndex=>$dateAsString)
                                    <x-tables.repeater-table-th class=" interval-class header-border-down " :title="__('Yr-') . explode('-',$dateAsString)[2] "></x-tables.repeater-table-th>
                                    @endforeach
                                </x-slot>
                                <x-slot name="trs">

                                  
									@foreach($formattedResults as $title => $values)
                                    <tr data-repeat-formatting-decimals="0" data-repeater-style>
                                        @php
                                        // $key ='cost-of-service';
                                        // $currentModalId = $key.'-modal-id';
                                        $currentModalTitle = $title ;
                                        @endphp
                                        <td>
                                            <div class="d-flex align-items-center ">
                                                <input value="{{ $title }}" disabled class="form-control text-left " type="text">
                                                {{-- <div>
                                                    <i data-toggle="modal" data-target="#{{ $currentModalId }}" class="flaticon2-information kt-font-primary exclude-icon ml-2 cursor-pointer "></i>
                                                    @include('non_banking_services.dashboard._expense-modal',['currentModalId'=>$currentModalId,'modalTitle'=>$currentModalTitle,'modalData'=>$values])
                                                </div> --}}

                                                {{-- <button class="btn btn-sm btn-brand btn-elevate btn-pill text-white ml-3" data-toggle="modal" data-target="#id">
													</button>   --}}

                                            </div>
                                        </td>


                                        @php
                                        $columnIndex = 0 ;
                                        @endphp
                                        @foreach($formattedDates as $dateAsIndex=>$dateAsString)
                                        @php
										$isPercentage = $title == 'Variance %' ; 
                                        $currentVal = !$isPercentage ?  ($values[$dateAsIndex]??0)/1000 : $values[$dateAsIndex]??0 ;
										$prefix = $isPercentage ? ' %' : '';
                                        @endphp
                                        <td>
                                            <div class="d-flex align-items-center justify-content-center">
                                                <x-repeat-right-dot-inputs :isNumber="true" :inlinePrefix="$prefix" :formattedInputClasses="'min-w-100'" :disabled="true" :removeThreeDotsClass="true" :removeThreeDots="true" :number-format-decimals="$isPercentage ? 2 : 0" :currentVal="$currentVal" :classes="'only-greater-than-or-equal-zero-allowed total-loans-hidden js-recalculate-equity-funding-value'" :is-percentage="false" :mark="''" :name="''" :columnIndex="$columnIndex"></x-repeat-right-dot-inputs>
                                            </div>
                                        </td>
                                        @php
                                        $columnIndex++ ;
                                        @endphp

                                        @endforeach


                                    </tr>
									@endforeach 







                                  























                                </x-slot>




                            </x-tables.repeater-table>

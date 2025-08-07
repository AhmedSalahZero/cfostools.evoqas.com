<style>
    .min-w-100 {
        min-width: 120px !important;
    }
	.th-centered{
		max-width: 175px !important;
    text-wrap: auto !important;
    justify-self: center !important;
	}
</style>
<x-tables.repeater-table :table-class="'col-md-12'" :removeActionBtn="true" :removeRepeater="true" :initialJs="false" :repeater-with-select2="true" :canAddNewItem="false" :parentClass="'js-remove-hidden'" :hide-add-btn="true" :tableName="''" :repeaterId="''" :relationName="'food'" :isRepeater="$isRepeater=!(isset($removeRepeater) && $removeRepeater)">
    <x-slot name="ths">
        <x-tables.repeater-table-th class="  header-border-down max-column-th-class" :title="__('Item')"></x-tables.repeater-table-th>
        @foreach($studyNames as $studyName)
        <x-tables.repeater-table-th class=" interval-class header-border-down " :title="$studyName"></x-tables.repeater-table-th>
        @endforeach
    </x-slot>
    <x-slot name="trs">


        @foreach($firstCardItems as $index => $firstCardItem)
        <tr data-repeat-formatting-decimals="0" data-repeater-style>
            @php
            // $key ='cost-of-service';
            // $currentModalId = $key.'-modal-id';
            // $currentModalTitle = $title ;
            @endphp
            <td>
                <div class="d-flex align-items-center ">
                    <input value="{{ $firstCardItem['title'] }}" disabled class="form-control text-left " type="text">
                </div>
            </td>


            @php
            $columnIndex = 0 ;
            @endphp
            {{-- @foreach($studyNames as $index$studyName) --}}
            @php
            $firstCardValue = number_unformat($firstCardItems[$index]['value']);
            $secondCardValue = number_unformat($secondCardItems[$index]['value']);
            $variance = ($firstCardValue - $secondCardValue);
            $variancePercentage = $secondCardValue ? (($firstCardValue / $secondCardValue) - 1)*100 : 0 ;
			$isPercentage = str_contains($firstCardItem['title'],'%');
			$prefix = $isPercentage ? ' %' : '';
            @endphp
            <td>
                <div class="d-flex align-items-center justify-content-center">
                    <x-repeat-right-dot-inputs :isNumber="true" :inlinePrefix="$prefix" :formattedInputClasses="'min-w-100'" :disabled="true" :removeThreeDotsClass="true" :removeThreeDots="true" :number-format-decimals="$isPercentage ? 2 : 0" :currentVal="$firstCardValue" :classes="'total-loans-hidden js-recalculate-equity-funding-value'" :is-percentage="false" :mark="''" :name="''" :columnIndex="$columnIndex"></x-repeat-right-dot-inputs>
                </div>
            </td>
            @php
            $columnIndex++ ;
            @endphp

            <td>
                <div class="d-flex align-items-center justify-content-center">
                    <x-repeat-right-dot-inputs :isNumber="true" :inlinePrefix="$prefix" :formattedInputClasses="'min-w-100'" :disabled="true" :removeThreeDotsClass="true" :removeThreeDots="true" :number-format-decimals="$isPercentage ? 2 : 0" :currentVal="$secondCardValue" :classes="'total-loans-hidden js-recalculate-equity-funding-value'" :is-percentage="false" :mark="''" :name="''" :columnIndex="$columnIndex"></x-repeat-right-dot-inputs>
                </div>
            </td>
            @php
            $columnIndex++ ;
            @endphp

            <td>
                <div class="d-flex align-items-center justify-content-center">
                    <x-repeat-right-dot-inputs :isNumber="true" :inlinePrefix="$prefix" :formattedInputClasses="'min-w-100'" :disabled="true" :removeThreeDotsClass="true" :removeThreeDots="true" :number-format-decimals="$isPercentage ? 2 : 0" :currentVal="$variance" :classes="'total-loans-hidden js-recalculate-equity-funding-value'" :is-percentage="false" :mark="''" :name="''" :columnIndex="$columnIndex"></x-repeat-right-dot-inputs>
                </div>
            </td>
            @php
            $columnIndex++ ;
            @endphp

            <td>
                <div class="d-flex align-items-center justify-content-center">
                    <x-repeat-right-dot-inputs :isNumber="true" :inlinePrefix="' %'" :formattedInputClasses="'min-w-100'" :disabled="true" :removeThreeDotsClass="true" :removeThreeDots="true" :number-format-decimals="2" :currentVal="$variancePercentage" :classes="'total-loans-hidden js-recalculate-equity-funding-value'" :is-percentage="false" :mark="''" :name="''" :columnIndex="$columnIndex"></x-repeat-right-dot-inputs>
                </div>
            </td>
            @php
            $columnIndex++ ;
            @endphp



            {{-- @endforeach --}}


        </tr>
        @endforeach































    </x-slot>




</x-tables.repeater-table>

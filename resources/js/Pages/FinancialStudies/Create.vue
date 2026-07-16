<template>
  <Head :title="study?.id ? `Edit Study — ${form.name || ''}` : `New Financial Study — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ── HEADER ── -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
          <Link
            :href="`/portfolio-companies/${company.id}/financial-studies`"
            class="flex items-center gap-2 text-sm text-white hover:text-white transition-colors mb-3 w-fit"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            Back to Studies
          </Link>

          <!-- Step wizard bar -->
          <div class="flex items-center gap-0 mb-5 overflow-x-auto pb-1">
            <div v-for="(step, i) in wizardSteps" :key="i"
              class="flex items-center flex-shrink-0">
              <div :class="[
                'flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors',
                i === 0
                  ? 'bg-mp-gold-dark text-white'
                  : 'text-white'
              ]">
                <span :class="[
                  'w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0',
                  i === 0 ? 'bg-white/20 text-white' : 'bg-mp-card-hover text-white'
                ]">{{ i + 1 }}</span>
                {{ step }}
              </div>
              <svg v-if="i < wizardSteps.length - 1" class="w-4 h-4 text-white mx-1 flex-shrink-0"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </div>
          </div>

          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
              <h1 class="text-2xl font-bold text-white">
                {{ study?.id ? 'Edit Study Setup' : 'New Study — Setup & Products' }}
              </h1>
              <p class="text-white text-sm mt-0.5">
                {{ company.name }} · Step 1 of {{ wizardSteps.length }}
              </p>
            </div>
            <div class="flex items-center gap-3">
              <button type="button" @click="submitForm('save')" :disabled="processing"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
                </svg>
                Save & Exit
              </button>
              <button type="button" @click="submitForm('next')" :disabled="processing"
                class="flex items-center gap-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors disabled:opacity-50">
                <svg v-if="processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                {{ processing ? 'Saving...' : 'Save & Next →' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ── FORM ── -->
      <form @submit.prevent ref="formRef">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

          <!-- ═══════════════════════════════════════
               SECTION 1 — STUDY INFO
          ════════════════════════════════════════ -->
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest">Study Info</p>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">

              <!-- Study Name -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Study Name <span class="text-mp-danger">*</span>
                </label>
                <input v-model="form.name" type="text" placeholder="e.g. Racking System BP 2025-2029"
                  :class="inputClass(errors.name)" />
                <p v-if="errors.name" class="mt-1 text-xs text-mp-danger">{{ errors.name }}</p>
              </div>

              <!-- Company Type -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Company Type <span class="text-mp-danger">*</span>
                </label>
                <div class="flex items-center gap-4 mt-3">
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.new_company" :value="true"
                      class="w-4 h-4 text-white bg-mp-card-hover border-mp-border" />
                    <span class="text-sm text-white">New Company</span>
                  </label>
                  <label class="flex items-center gap-2 cursor-pointer">
                    <input type="radio" v-model="form.new_company" :value="false"
                      class="w-4 h-4 text-white bg-mp-card-hover border-mp-border" />
                    <span class="text-sm text-white">Existing Company</span>
                  </label>
                </div>
              </div>

              <!-- Study Currency -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Study Currency <span class="text-mp-danger">*</span>
                </label>
                <select v-model="form.study_currency" :class="inputClass(errors.study_currency)">
                  <option v-for="(title, id) in currencies" :key="id" :value="id">{{ title }}</option>
                </select>
              </div>

            </div>
          </div>

          <!-- ═══════════════════════════════════════
               SECTION 2 — INTRO (collapsible)
          ════════════════════════════════════════ -->
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <button type="button" @click="showIntro = !showIntro"
              class="w-full px-6 py-4 border-b border-mp-border flex items-center justify-between hover:bg-mp-card-hover/50 transition-colors">
              <p class="text-xs font-semibold text-white uppercase tracking-widest">
                Insert Intro Paragraph
                <span class="ml-2 text-white normal-case font-normal">(optional)</span>
              </p>
              <svg :class="['w-4 h-4 text-white transition-transform', showIntro ? 'rotate-180' : '']"
                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <div v-show="showIntro" class="p-6">
              <div ref="introEditorEl" contenteditable="true"
                @input="form.intro_paragraph = $event.target.innerHTML"
                v-html="form.intro_paragraph"
                class="min-h-[140px] bg-mp-card-hover border border-mp-border rounded-lg px-5 py-4 text-white text-sm leading-relaxed focus:outline-none focus:ring-2 focus:ring-mp-teal"
                data-placeholder="Write your study introduction...">
              </div>
            </div>
          </div>

          <!-- ═══════════════════════════════════════
               SECTION 3 — GENERAL INFO
          ════════════════════════════════════════ -->
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest">
                {{ form.name || 'Study' }} — General Info
              </p>
            </div>
            <div class="p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">

              <!-- Study Start Date -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Study Start Date <span class="text-mp-danger">*</span>
                </label>
                <input type="month" v-model="form.start_date" @change="recalcEndDate"
                  :class="inputClass(errors.start_date)" />
                <p v-if="errors.start_date" class="mt-1 text-xs text-mp-danger">{{ errors.start_date }}</p>
              </div>

              <!-- Duration -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Duration (Years) <span class="text-mp-danger">*</span>
                </label>
                <select v-model="form.duration_years" @change="recalcEndDate"
                  :class="inputClass(errors.duration_years)">
                  <option value="">Select</option>
                  <option v-for="v in 20" :key="v" :value="v">{{ v }} {{ v === 1 ? 'year' : 'years' }}</option>
                </select>
                <p v-if="errors.duration_years" class="mt-1 text-xs text-mp-danger">{{ errors.duration_years }}</p>
              </div>

              <!-- End Date (computed, read-only) -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Study End Date
                </label>
                <input type="text" :value="computedEndDate || '—'" readonly
                  class="w-full bg-mp-card-hover/50 border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm cursor-not-allowed" />
              </div>

              <!-- Operation Start Date -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Operation Start Date
                </label>
                <input type="month" v-model="form.operation_start_date"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
              </div>

              <!-- Business Sector -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Business Sector
                </label>
                <input v-model="form.business_sector" type="text" placeholder="e.g. FMCG, Construction, Tech"
                  class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
              </div>

              <!-- Corporate Tax Rate -->
              <div>
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Corporate Tax Rate % <span class="text-mp-danger">*</span>
                </label>
                <input type="number" step="any" min="0" max="100" v-model="form.corporate_tax_rate"
                  placeholder="e.g. 22.5"
                  :class="inputClass(errors.corporate_tax_rate)" />
                <p v-if="errors.corporate_tax_rate" class="mt-1 text-xs text-mp-danger">{{ errors.corporate_tax_rate }}</p>
              </div>

              <!-- Required Investment Return — hidden when duration ≤ 2 years -->
              <div v-if="form.duration_years > 2">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Required Return % (WACC) <span class="text-mp-danger">*</span>
                </label>
                <input type="number" step="any" v-model="form.required_investment_return_pct"
                  placeholder="e.g. 30"
                  :class="inputClass(errors.required_investment_return_pct)" />
                <p v-if="errors.required_investment_return_pct" class="mt-1 text-xs text-mp-danger">{{ errors.required_investment_return_pct }}</p>
              </div>

              <!-- Perpetual Growth Rate — hidden when duration ≤ 2 years -->
              <div v-if="form.duration_years > 2">
                <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-2">
                  Perpetual Growth Rate % <span class="text-mp-danger">*</span>
                </label>
                <input type="number" step="any" min="0" max="10" v-model="form.perpetual_growth_rate_pct"
                  placeholder="e.g. 4"
                  :class="inputClass(errors.perpetual_growth_rate_pct)" />
                <p v-if="errors.perpetual_growth_rate_pct" class="mt-1 text-xs text-mp-danger">{{ errors.perpetual_growth_rate_pct }}</p>
                <p class="mt-1 text-xs text-white">Recommended 2.5% – 5%</p>
              </div>

              <!-- Info note shown when duration ≤ 2 (WACC/Terminal not applicable) -->
              <div v-if="form.duration_years && form.duration_years <= 2"
                class="sm:col-span-2 flex items-start gap-2 bg-mp-gold/20 border border-mp-gold/40 rounded-lg px-4 py-3">
                <span class="text-white text-sm mt-0.5">ℹ️</span>
                <p class="text-xs text-white">
                  <strong>WACC &amp; Perpetual Growth Rate</strong> are not applicable for studies of 1–2 years.
                  The Free Cash Flow &amp; Valuation section will be hidden in Results.
                </p>
              </div>

            </div>
          </div>

          <!-- ═══════════════════════════════════════
               SECTION 4 — PRODUCTS
          ════════════════════════════════════════ -->
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden">
            <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between flex-wrap gap-3">
              <div>
                <p class="text-xs font-semibold text-white uppercase tracking-widest">Products / Services</p>
                <p class="text-xs text-white mt-0.5">Define what this study will plan. At least one product required.</p>
              </div>
              <div class="flex items-center gap-2">
                <!-- Import from Sales Data button -->
                <button type="button" @click="openImportModal"
                  class="flex items-center gap-1.5 text-xs font-medium bg-mp-teal-dark/30 hover:bg-mp-teal-dark/60 text-white px-3 py-1.5 rounded-lg transition-colors border border-mp-teal/40">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                  </svg>
                  Import from Sales Data
                </button>
                <button type="button" @click="addProduct"
                  class="flex items-center gap-1.5 text-xs font-medium bg-mp-success/30 hover:bg-mp-success/60 text-mp-success px-3 py-1.5 rounded-lg transition-colors border border-mp-success/50">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                  </svg>
                  Add Product
                </button>
              </div>
            </div>

            <div v-if="errors.products" class="px-6 py-3 bg-mp-danger/30 border-b border-mp-danger/40">
              <p class="text-xs text-mp-danger">{{ errors.products }}</p>
            </div>

            <!-- Products list -->
            <div class="divide-y divide-gray-800">
              <div v-for="(product, index) in form.products" :key="index"
                class="p-6 hover:bg-mp-card-hover/20 transition-colors">

                <!-- Product header row -->
                <div class="flex items-center justify-between mb-4">
                  <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-white uppercase tracking-widest">
                      Product {{ index + 1 }}
                    </span>
                    <!-- Nature badge -->
                    <span v-if="product.nature" :class="[
                      'text-xs font-semibold px-2 py-0.5 rounded-full',
                      natureBadge(product.nature)
                    ]">
                      {{ natureLabel(product.nature) }}
                    </span>
                  </div>
                  <button v-if="form.products.length > 1" type="button" @click="removeProduct(index)"
                    class="flex items-center gap-1 text-xs text-mp-danger hover:text-mp-danger bg-mp-danger/40 hover:bg-mp-danger/70 px-2.5 py-1.5 rounded-lg transition-colors">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Remove
                  </button>
                </div>

                <!-- Product nature selector (3 cards) -->
                <div class="mb-5">
                  <p class="text-xs font-semibold text-white uppercase tracking-widest mb-2">
                    Product Nature <span class="text-mp-danger">*</span>
                  </p>
                  <div class="grid grid-cols-3 gap-3">
                    <button
                      v-for="nat in natures"
                      :key="nat.value"
                      type="button"
                      @click="product.nature = nat.value"
                      :class="[
                        'relative flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all text-center',
                        product.nature === nat.value
                          ? nat.selectedClass
                          : 'border-mp-border bg-mp-card-hover/50 hover:border-mp-border text-white'
                      ]"
                    >
                      <span class="text-2xl">{{ nat.icon }}</span>
                      <span class="text-sm font-semibold">{{ nat.label }}</span>
                      <span class="text-xs opacity-70 leading-tight">{{ nat.desc }}</span>
                      <!-- Checkmark -->
                      <span v-if="product.nature === nat.value"
                        class="absolute top-2 right-2 w-5 h-5 bg-white/20 rounded-full flex items-center justify-center">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                      </span>
                    </button>
                  </div>
                  <p v-if="errors[`products.${index}.nature`]" class="mt-1 text-xs text-mp-danger">
                    {{ errors[`products.${index}.nature`] }}
                  </p>
                </div>

                <!-- Product fields grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                  <!-- Name -->
                  <div>
                    <label class="block text-xs text-white mb-1.5">Name <span class="text-mp-danger">*</span></label>
                    <input type="text" v-model="product.name"
                      :class="['w-full bg-mp-card-hover border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal',
                        errors[`products.${index}.name`] ? 'border-mp-danger' : 'border-mp-border']" />
                    <p v-if="errors[`products.${index}.name`]" class="mt-1 text-xs text-mp-danger">{{ errors[`products.${index}.name`] }}</p>
                  </div>
                  <!-- Measurement Unit -->
                  <div>
                    <label class="block text-xs text-white mb-1.5">Unit of Measure</label>
                    <select v-model="product.measurement_unit"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                      <option value="">Select</option>
                      <option v-for="(unitName, unitId) in measurementUnits" :key="unitId" :value="unitId">{{ unitName }}</option>
                    </select>
                  </div>
                  <!-- Selling Start Date -->
                  <div>
                    <label class="block text-xs text-white mb-1.5">Selling Start Date</label>
                    <input type="month" v-model="product.selling_start_date"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
                  </div>
                  <!-- VAT Rate -->
                  <div>
                    <label class="block text-xs text-white mb-1.5">VAT Rate %</label>
                    <input type="number" step="any" min="0" v-model="product.vat_rate"
                      placeholder="0"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
                  </div>
                  <!-- Withhold Tax -->
                  <div>
                    <label class="block text-xs text-white mb-1.5">Withhold Tax %</label>
                    <input type="number" step="any" min="0" v-model="product.withhold_tax_rate"
                      placeholder="0"
                      class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- ═══════════════════════════════════════
               SECTION 5 — RAW MATERIALS
               (only shown if at least one manufactured product)
          ════════════════════════════════════════ -->
          <transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 -translate-y-2"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 -translate-y-2"
          >
            <div v-if="hasManufacturedProducts"
              class="bg-mp-card border border-mp-teal/40 rounded-xl overflow-hidden">
              <div class="px-6 py-4 border-b border-mp-border bg-mp-teal-subtle/20 flex items-center justify-between flex-wrap gap-3">
                <div>
                  <p class="text-xs font-semibold text-white uppercase tracking-widest">Raw Materials</p>
                  <p class="text-xs text-white mt-0.5">
                    Required for manufactured products. Define the inputs and their inventory terms.
                  </p>
                </div>
                <button type="button" @click="addRawMaterial"
                  class="flex items-center gap-1.5 text-xs font-medium bg-mp-teal-dark/30 hover:bg-mp-teal-dark/60 text-white px-3 py-1.5 rounded-lg transition-colors border border-mp-teal/40">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                  </svg>
                  Add Raw Material
                </button>
              </div>

              <div class="divide-y divide-gray-800">
                <div v-for="(rm, index) in form.raw_materials" :key="index"
                  class="p-6 hover:bg-mp-card-hover/20 transition-colors">
                  <div class="flex items-center justify-between mb-4">
                    <span class="text-xs font-semibold text-white uppercase tracking-widest">
                      Raw Material {{ index + 1 }}
                    </span>
                    <button v-if="form.raw_materials.length > 1" type="button" @click="removeRawMaterial(index)"
                      class="flex items-center gap-1 text-xs text-mp-danger hover:text-mp-danger bg-mp-danger/40 hover:bg-mp-danger/70 px-2.5 py-1.5 rounded-lg transition-colors">
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                      Remove
                    </button>
                  </div>
                  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                      <label class="block text-xs text-white mb-1.5">Name <span class="text-mp-danger">*</span></label>
                      <input type="text" v-model="rm.name"
                        :class="['w-full bg-mp-card-hover border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal',
                          errors[`raw_materials.${index}.name`] ? 'border-mp-danger' : 'border-mp-border']" />
                    </div>
                    <div>
                      <label class="block text-xs text-white mb-1.5">Inventory Coverage</label>
                      <select v-model="rm.rm_inventory_coverage_days"
                        class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                        <option value="">Select</option>
                        <option v-for="(title, id) in inventoryCoverageDays" :key="id" :value="id">{{ title }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs text-white mb-1.5">Unit of Measure</label>
                      <select v-model="rm.measurement_unit"
                        class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal">
                        <option value="">Select</option>
                        <option v-for="(unitName, unitId) in measurementUnits" :key="unitId" :value="unitId">{{ unitName }}</option>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs text-white mb-1.5">VAT Rate %</label>
                      <input type="number" step="any" min="0" v-model="rm.vat_rate" placeholder="0"
                        class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
                    </div>
                    <div>
                      <label class="block text-xs text-white mb-1.5">Withhold Tax %</label>
                      <input type="number" step="any" min="0" v-model="rm.withhold_tax_rate" placeholder="0"
                        class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </transition>

          <!-- ── BOTTOM ACTION BAR ── -->
          <div class="flex items-center justify-between bg-mp-card border border-mp-border rounded-xl px-6 py-4">
            <div>
              <p v-if="hasErrors" class="text-sm text-mp-danger flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ errors._general || 'Please fix the errors highlighted above.' }}
              </p>
              <p v-else class="text-xs text-white">
                All fields marked <span class="text-mp-danger">*</span> are required
              </p>
            </div>
            <div class="flex items-center gap-3">
              <button type="button" @click="submitForm('save')" :disabled="processing"
                class="flex items-center gap-2 bg-mp-card-hover hover:bg-mp-page border border-mp-border text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors disabled:opacity-50">
                Save & Exit
              </button>
              <button type="button" @click="submitForm('next')" :disabled="processing"
                class="flex items-center gap-2 bg-mp-gold-dark hover:bg-mp-gold text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors disabled:opacity-50">
                <svg v-if="processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                {{ processing ? 'Saving...' : 'Save & Next →' }}
              </button>
            </div>
          </div>

        </div>
      </form>
    </div>

    <!-- ═══════════════════════════════════════════════════════
         IMPORT FROM SALES DATA MODAL
    ════════════════════════════════════════════════════════ -->
    <Teleport to="body">
      <div v-if="importModal.show"
        class="fixed inset-0 z-[100] flex items-center justify-center p-4"
        @click.self="importModal.show = false">
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm"></div>
        <div class="relative z-10 w-full max-w-lg bg-mp-card border border-mp-border rounded-2xl shadow-2xl overflow-hidden">

          <!-- Modal header -->
          <div class="px-6 py-4 border-b border-mp-border flex items-center justify-between">
            <div>
              <h3 class="text-base font-bold text-white">Import from Sales Data</h3>
              <p class="text-xs text-white mt-0.5">Products & categories sold in the last 12 months</p>
            </div>
            <button type="button" @click="importModal.show = false"
              class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-white hover:text-white transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Loading state -->
          <div v-if="importModal.loading" class="flex items-center justify-center py-16">
            <div class="flex flex-col items-center gap-3">
              <svg class="animate-spin w-8 h-8 text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              <p class="text-sm text-white">Loading sales data…</p>
            </div>
          </div>

          <!-- Empty state -->
          <div v-else-if="importModal.products.length === 0 && importModal.categories.length === 0"
            class="flex flex-col items-center justify-center py-12 px-6 text-center">
            <svg class="w-10 h-10 text-white mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
            </svg>
            <p class="text-white font-medium mb-1">No sales data found</p>
            <p class="text-white text-sm">No products were sold by this company in the last 12 months.</p>
          </div>

          <!-- Results -->
          <div v-else class="p-5 max-h-[60vh] overflow-y-auto space-y-5">

            <!-- Products section -->
            <div v-if="importModal.products.length > 0">
              <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-semibold text-white uppercase tracking-widest">
                  Products ({{ importModal.products.length }})
                </p>
                <button type="button" @click="selectAllImport('products')"
                  class="text-xs text-white hover:text-white transition-colors">
                  {{ allProductsSelected ? 'Deselect All' : 'Select All' }}
                </button>
              </div>
              <div class="space-y-1.5">
                <label
                  v-for="p in importModal.products"
                  :key="'prod-' + p"
                  class="flex items-center gap-3 p-3 rounded-lg bg-mp-card-hover hover:bg-mp-page/70 cursor-pointer transition-colors"
                >
                  <input type="checkbox" v-model="importModal.selectedProducts" :value="p"
                    class="w-4 h-4 text-white bg-mp-page border-mp-border rounded" />
                  <span class="text-sm text-white">{{ p }}</span>
                </label>
              </div>
            </div>

            <!-- Categories section -->
            <div v-if="importModal.categories.length > 0">
              <div class="flex items-center justify-between mb-2">
                <p class="text-xs font-semibold text-white uppercase tracking-widest">
                  Categories ({{ importModal.categories.length }})
                </p>
                <button type="button" @click="selectAllImport('categories')"
                  class="text-xs text-white hover:text-white transition-colors">
                  {{ allCategoriesSelected ? 'Deselect All' : 'Select All' }}
                </button>
              </div>
              <div class="space-y-1.5">
                <label
                  v-for="c in importModal.categories"
                  :key="'cat-' + c"
                  class="flex items-center gap-3 p-3 rounded-lg bg-mp-card-hover hover:bg-mp-page/70 cursor-pointer transition-colors"
                >
                  <input type="checkbox" v-model="importModal.selectedCategories" :value="c"
                    class="w-4 h-4 text-white bg-mp-page border-mp-border rounded" />
                  <span class="text-sm text-white">{{ c }}</span>
                </label>
              </div>
            </div>

          </div>

          <!-- Modal footer -->
          <div v-if="!importModal.loading" class="px-5 py-4 border-t border-mp-border flex items-center justify-between bg-mp-card">
            <p class="text-xs text-white">
              {{ totalSelected }} item{{ totalSelected !== 1 ? 's' : '' }} selected
            </p>
            <div class="flex gap-3">
              <button type="button" @click="importModal.show = false"
                class="px-4 py-2 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
                Cancel
              </button>
              <button type="button" @click="applyImport" :disabled="totalSelected === 0"
                class="px-4 py-2 rounded-lg bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                Import Selected
              </button>
            </div>
          </div>

        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:                { type: Object, required: true },
  study:                  { type: Object, default: null },
  products:               { type: Array,  default: () => [] },
  currencies:             { type: Object, default: () => ({}) },
  measurementUnits:       { type: Object, default: () => ({}) },
  inventoryCoverageDays:  { type: Object, default: () => ({}) },
  startDate:              { type: String, default: '' },
  opStartDate:            { type: String, default: '' },
})

// ── Wizard steps ──
const wizardSteps = [
  'Setup & Products',
  'Sales Projection',
  'COGS',
  'Expenses',
  'Manpower',
  'Fixed Assets',
  'Opening Balance',
  'Results',
]

// ── Nature definitions ──
const natures = [
  {
    value:         'manufacturing',
    label:         'Manufacturing',
    icon:          '🏭',
    desc:          'Produces goods from raw materials',
    selectedClass: 'border-mp-teal bg-mp-teal-subtle/40 text-white',
  },
  {
    value:         'trading',
    label:         'Trading',
    icon:          '🛒',
    desc:          'Buys & resells finished goods',
    selectedClass: 'border-mp-gold bg-mp-gold/40 text-white',
  },
  {
    value:         'service',
    label:         'Service',
    icon:          '⚙️',
    desc:          'Provides services, no physical goods',
    selectedClass: 'border-mp-teal bg-mp-teal-subtle/40 text-white',
  },
]

// ── Default builders ──
const defaultProduct = () => ({
  id: 0,
  name: '',
  nature: '',
  measurement_unit: '',
  selling_start_date: props.startDate || new Date().toISOString().slice(0, 7),
  vat_rate: 0,
  withhold_tax_rate: 0,
})

const defaultRawMaterial = () => ({
  id: 0,
  name: '',
  rm_inventory_coverage_days: '',
  measurement_unit: '',
  vat_rate: 0,
  withhold_tax_rate: 0,
})

// ── Form ──
const form = useForm({
  name:                           props.study?.name ?? '',
  new_company:                    props.study?.new_company ?? true,
  study_currency:                 props.study?.study_currency ?? 'EGP',
  intro_paragraph:                props.study?.intro_paragraph ?? '',
  start_date:                     props.startDate ?? '',
  duration_years:                 props.study?.duration_years ?? '',
  operation_start_date:           props.opStartDate ?? '',
  business_sector:                props.study?.business_sector ?? '',
  corporate_tax_rate:             props.study?.corporate_tax_rate ?? 22.5,
  required_investment_return_pct: props.study?.required_investment_return_pct ?? 30,
  perpetual_growth_rate_pct:      props.study?.perpetual_growth_rate_pct ?? 4,
  products:    props.products.length   ? props.products   : [defaultProduct()],
  raw_materials: (props.study?.raw_materials ?? []).length
                  ? props.study.raw_materials
                  : [defaultRawMaterial()],
  submit_button: 'save',
})

// ── UI state ──
const showIntro    = ref(false)
const processing   = ref(false)
const errors       = ref({})
const computedEndDate = ref('')

const hasErrors    = computed(() => Object.keys(errors.value).length > 0)
const hasManufacturedProducts = computed(() =>
  form.products.some(p => p.nature === 'manufacturing')
)

// ── Auto-compute end date ──
function recalcEndDate() {
  if (!form.start_date || !form.duration_years) { computedEndDate.value = ''; return }
  const [year, month] = form.start_date.split('-').map(Number)
  // End date = last month of study
  // Jan 2026 + 1 year  = Dec 2026
  // Jan 2026 + 3 years = Dec 2028
  const totalMonths = (year * 12 + (month - 1)) + (parseInt(form.duration_years) * 12) - 1
  const endYear     = Math.floor(totalMonths / 12)
  const endMonth    = (totalMonths % 12) + 1
  const endDate     = new Date(endYear, endMonth - 1, 1)
  computedEndDate.value = endDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long' })
}

watch(() => [form.start_date, form.duration_years], recalcEndDate, { immediate: true })

// Clear WACC + perpetual growth when duration ≤ 2 (not applicable for short studies)
watch(() => form.duration_years, val => {
  if (val && val <= 2) {
    form.required_investment_return_pct = ''
    form.perpetual_growth_rate_pct      = ''
  }
})

// Auto-add one raw material row when manufacturing is first selected
watch(hasManufacturedProducts, (val) => {
  if (val && form.raw_materials.length === 0) {
    form.raw_materials.push(defaultRawMaterial())
  }
})

// ── Product helpers ──
function addProduct()         { form.products.push(defaultProduct()) }
function removeProduct(i)     { form.products.splice(i, 1) }
function addRawMaterial()     { form.raw_materials.push(defaultRawMaterial()) }
function removeRawMaterial(i) { form.raw_materials.splice(i, 1) }

// ── Nature helpers ──
function natureBadge(nature) {
  const map = {
    manufacturing: 'bg-mp-teal-subtle/60 text-white border border-mp-teal/50',
    trading:       'bg-mp-gold/60 text-white border border-mp-gold/50',
    service:       'bg-mp-teal-subtle/60 text-white border border-mp-teal/50',
  }
  return map[nature] || ''
}
function natureLabel(nature) {
  return { manufacturing: 'Manufacturing', trading: 'Trading', service: 'Service' }[nature] || ''
}

// ── Form submission ──
function submitForm(button) {
  form.submit_button = button
  processing.value   = true
  errors.value       = {}

  const isEdit = !!props.study?.id
  const url    = isEdit
    ? `/portfolio-companies/${props.company.id}/financial-studies/${props.study.id}`
    : `/portfolio-companies/${props.company.id}/financial-studies`
  const method = isEdit ? 'put' : 'post'

  form[method](url, {
    preserveScroll: true,
    onError:   (errs) => { errors.value = errs; processing.value = false },
    onSuccess: ()     => { processing.value = false },
    onFinish:  ()     => { processing.value = false },
  })
}

// ── Input class helper ──
function inputClass(hasError) {
  return [
    'w-full bg-mp-card-hover border rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-mp-teal transition-colors',
    hasError ? 'border-mp-danger' : 'border-mp-border',
  ]
}

// ══════════════════════════════════════
//  IMPORT FROM SALES DATA MODAL
// ══════════════════════════════════════
const importModal = reactive({
  show:               false,
  loading:            false,
  products:           [],
  categories:         [],
  selectedProducts:   [],
  selectedCategories: [],
})

const allProductsSelected   = computed(() =>
  importModal.products.length > 0 &&
  importModal.selectedProducts.length === importModal.products.length
)
const allCategoriesSelected = computed(() =>
  importModal.categories.length > 0 &&
  importModal.selectedCategories.length === importModal.categories.length
)
const totalSelected = computed(() =>
  importModal.selectedProducts.length + importModal.selectedCategories.length
)

async function openImportModal() {
  importModal.show               = true
  importModal.loading            = true
  importModal.selectedProducts   = []
  importModal.selectedCategories = []

  try {
    const xsrf = document.cookie.split('; ')
      .find(r => r.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    const res = await fetch(
      `/portfolio-companies/${props.company.id}/financial-studies/api/sales-products`,
      {
        credentials: 'include',
        headers: {
          'Accept':       'application/json',
          'X-XSRF-TOKEN': xsrf ? decodeURIComponent(xsrf) : '',
        },
      }
    )
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    importModal.products   = data.products   ?? []
    importModal.categories = data.categories ?? []
  } catch (e) {
    console.error('Failed to load sales data', e)
    importModal.products   = []
    importModal.categories = []
  } finally {
    importModal.loading = false
  }
}

function selectAllImport(type) {
  if (type === 'products') {
    importModal.selectedProducts = allProductsSelected.value
      ? []
      : [...importModal.products]
  } else {
    importModal.selectedCategories = allCategoriesSelected.value
      ? []
      : [...importModal.categories]
  }
}

function applyImport() {
  // Add selected products (avoid duplicates by name)
  const existingNames = form.products.map(p => p.name.toLowerCase().trim())

  importModal.selectedProducts.forEach(name => {
    if (!existingNames.includes(name.toLowerCase().trim())) {
      const p = defaultProduct()
      p.name  = name
      form.products.push(p)
    }
  })

  importModal.selectedCategories.forEach(name => {
    if (!existingNames.includes(name.toLowerCase().trim())) {
      const p = defaultProduct()
      p.name  = name
      form.products.push(p)
    }
  })

  // Remove the blank placeholder if it was the only row and had no name
  if (form.products.length > 1) {
    const blanks = form.products.filter(p => !p.name)
    if (blanks.length > 0 && blanks.length < form.products.length) {
      form.products = form.products.filter(p => p.name)
    }
  }

  importModal.show = false
}
</script>

<style scoped>
[contenteditable]:empty:before {
  content: attr(data-placeholder);
  color: #1490a8;
  pointer-events: none;
}
[contenteditable]:focus { outline: none; }
input[type="month"]::-webkit-calendar-picker-indicator { filter: invert(0.7); }
</style>
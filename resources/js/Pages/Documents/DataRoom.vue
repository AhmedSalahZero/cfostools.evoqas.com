<template>
  <Head :title="`Data Room — ${company.name}`" />
  <AuthenticatedLayout>
    <div class="min-h-screen bg-mp-page text-white">

      <!-- ══ PAGE HEADER ══════════════════════════════════════════════════════ -->
      <div class="bg-mp-card border-b border-mp-border">
        <div class="max-w-screen-2xl mx-auto px-6 py-5">
          <div class="flex items-center justify-between">

            <!-- Left: breadcrumb + title -->
            <div class="flex items-center gap-4">
              <Link :href="`/portfolio-companies/${company.id}`"
                class="w-9 h-9 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-white hover:text-white transition-colors flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
              </Link>
              <div>
                <p class="text-xs font-semibold text-white uppercase tracking-widest mb-0.5">{{ company.name }}</p>
                <h1 class="text-xl font-bold text-white flex items-center gap-2">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                  </svg>
                  Data Room
                </h1>
              </div>
            </div>

            <!-- Right: stats + upload button -->
            <div class="flex items-center gap-6">
              <div class="hidden md:flex items-center gap-6 text-sm">
                <div class="text-center">
                  <p class="text-2xl font-bold text-white">{{ documents.length }}</p>
                  <p class="text-xs text-white uppercase tracking-wider mt-0.5">Total Files</p>
                </div>
                <div class="w-px h-8 bg-mp-card-hover"></div>
                <div class="text-center">
                  <p class="text-2xl font-bold text-white">{{ sectionsWithFiles }}</p>
                  <p class="text-xs text-white uppercase tracking-wider mt-0.5">Sections Used</p>
                </div>
                <div class="w-px h-8 bg-mp-card-hover"></div>
                <div class="text-center">
                  <p class="text-sm font-semibold text-white">{{ lastUpload ? formatDate(lastUpload) : '—' }}</p>
                  <p class="text-xs text-white uppercase tracking-wider mt-0.5">Last Upload</p>
                </div>
              </div>
              <button @click="openUploadModal()"
                class="flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                </svg>
                Upload Document
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ FLASH MESSAGES ═══════════════════════════════════════════════════ -->
      <div class="max-w-screen-2xl mx-auto px-6 pt-4">
        <div v-if="$page.props.flash?.success"
          class="bg-mp-success/60 border border-mp-success text-mp-success px-4 py-3 rounded-lg text-sm flex items-center gap-2">
          <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          {{ $page.props.flash.success }}
        </div>
        <div v-if="$page.props.flash?.error"
          class="bg-mp-danger/60 border border-mp-danger text-mp-danger px-4 py-3 rounded-lg text-sm">
          {{ $page.props.flash.error }}
        </div>
      </div>

      <!-- ══ TWO-PANEL LAYOUT ═════════════════════════════════════════════════ -->
      <div class="max-w-screen-2xl mx-auto px-6 py-6 flex gap-6" style="min-height: calc(100vh - 200px);">

        <!-- ── LEFT SIDEBAR: Sections ────────────────────────────────────────── -->
        <div class="w-64 flex-shrink-0">
          <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden sticky top-6">

            <!-- Sidebar header -->
            <div class="px-4 py-3 border-b border-mp-border">
              <p class="text-xs font-semibold text-white uppercase tracking-widest">Sections</p>
            </div>

            <!-- All Documents shortcut -->
            <button @click="activeSection = 'all'"
              :class="[
                'w-full flex items-center justify-between px-4 py-3 text-sm transition-colors border-b border-mp-border/50',
                activeSection === 'all'
                  ? 'bg-mp-teal/15 text-white font-semibold'
                  : 'text-white hover:bg-mp-card-hover hover:text-white'
              ]">
              <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-lg flex items-center justify-center text-base"
                  :class="activeSection === 'all' ? 'bg-mp-teal/30' : 'bg-mp-card-hover'">
                  📂
                </span>
                <span>All Documents</span>
              </div>
              <span :class="[
                'text-xs font-bold px-2 py-0.5 rounded-full',
                activeSection === 'all' ? 'bg-mp-teal text-white' : 'bg-mp-card-hover text-white'
              ]">{{ documents.length }}</span>
            </button>

            <!-- Section list -->
            <template v-for="(label, key) in sections" :key="key">
              <button @click="activeSection = key"
                :class="[
                  'w-full flex items-center justify-between px-4 py-3 text-sm transition-colors',
                  activeSection === key
                    ? 'bg-mp-teal/15 text-white font-semibold'
                    : 'text-white hover:bg-mp-card-hover hover:text-white'
                ]">
                <div class="flex items-center gap-3">
                  <span class="w-7 h-7 rounded-lg flex items-center justify-center text-base"
                    :class="activeSection === key ? sectionIconActiveBg(key) : 'bg-mp-card-hover'">
                    {{ sectionIcon(key) }}
                  </span>
                  <span>{{ label }}</span>
                </div>
                <span :class="[
                  'text-xs font-bold px-2 py-0.5 rounded-full',
                  activeSection === key ? 'bg-mp-teal text-white' : 'bg-mp-card-hover text-white'
                ]">{{ sectionCounts[key] ?? 0 }}</span>
              </button>
            </template>

            <!-- Upload shortcut -->
            <div class="p-3 border-t border-mp-border mt-1">
              <button @click="openUploadModal(activeSection !== 'all' ? activeSection : '')"
                class="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg border border-dashed border-mp-border hover:border-mp-teal text-white hover:text-white text-xs font-semibold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Add to {{ activeSection === 'all' ? 'Data Room' : sections[activeSection] }}
              </button>
            </div>
          </div>
        </div>

        <!-- ── RIGHT PANEL: Documents ─────────────────────────────────────────── -->
        <div class="flex-1 min-w-0">

          <!-- Section heading -->
          <div class="flex items-center justify-between mb-5">
            <div class="flex items-center gap-3">
              <span class="text-2xl">{{ activeSection === 'all' ? '📂' : sectionIcon(activeSection) }}</span>
              <div>
                <h2 class="text-base font-bold text-white">
                  {{ activeSection === 'all' ? 'All Documents' : sections[activeSection] }}
                </h2>
                <p class="text-xs text-white mt-0.5">
                  {{ filteredDocs.length }} document{{ filteredDocs.length !== 1 ? 's' : '' }}
                </p>
              </div>
            </div>

            <!-- Search -->
            <div class="relative w-56">
              <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
              </svg>
              <input v-model="searchQuery" type="text" placeholder="Search documents…"
                class="w-full pl-9 pr-3 py-2 bg-mp-card border border-mp-border rounded-lg text-sm text-white placeholder-gray-600 focus:outline-none focus:border-mp-teal"/>
            </div>
          </div>

          <!-- Empty state -->
          <div v-if="filteredDocs.length === 0"
            class="bg-mp-card border border-mp-border border-dashed rounded-xl p-16 text-center">
            <div class="w-16 h-16 bg-mp-card-hover rounded-xl flex items-center justify-center mx-auto mb-4 text-3xl">
              {{ activeSection === 'all' ? '📂' : sectionIcon(activeSection) }}
            </div>
            <p class="text-white font-semibold mb-1">
              {{ searchQuery ? 'No documents match your search' : 'No documents here yet' }}
            </p>
            <p class="text-white text-sm mb-5">
              {{ searchQuery ? 'Try a different keyword' : `Upload your first document to ${activeSection === 'all' ? 'the data room' : sections[activeSection]}` }}
            </p>
            <button v-if="!searchQuery" @click="openUploadModal(activeSection !== 'all' ? activeSection : '')"
              class="bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors inline-flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
              </svg>
              Upload Document
            </button>
          </div>

          <!-- Document Grid -->
          <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <template v-for="doc in filteredDocs" :key="doc.id">
              <div class="bg-mp-card border border-mp-border rounded-xl overflow-hidden hover:border-mp-border transition-all group flex flex-col">

                <!-- File type banner -->
                <div :class="['h-1.5 w-full', fileTypeBanner(doc.mime_type)]"></div>

                <div class="p-4 flex flex-col gap-3 flex-1">

                  <!-- Icon + Name -->
                  <div class="flex items-start gap-3">
                    <div :class="['w-11 h-11 rounded-xl flex items-center justify-center flex-shrink-0 text-xl', fileIconBg(doc.mime_type)]">
                      {{ fileIconEmoji(doc.mime_type) }}
                    </div>
                    <div class="flex-1 min-w-0">
                      <p class="text-white text-sm font-semibold leading-tight line-clamp-2" :title="doc.name">
                        {{ doc.name }}
                      </p>
                      <p class="text-white text-xs mt-0.5">{{ fileTypeLabel(doc.mime_type) }}</p>
                    </div>
                  </div>

                  <!-- Section badge (only show in "All" view) -->
                  <div v-if="activeSection === 'all'">
                    <span :class="['text-xs font-semibold px-2.5 py-1 rounded-full', sectionBadgeClass(doc.category)]">
                      {{ sectionIcon(doc.category) }} {{ sections[doc.category] ?? doc.category }}
                    </span>
                  </div>

                  <!-- Meta -->
                  <div class="text-xs text-white space-y-1 flex-1">
                    <div class="flex items-center gap-1.5">
                      <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                      </svg>
                      <span class="truncate">{{ doc.uploader }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                      <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                      </svg>
                      <span>{{ formatDate(doc.created_at) }}</span>
                    </div>
                  </div>

                  <!-- Action buttons -->
                  <div class="flex items-center gap-1.5 pt-2 border-t border-mp-border">
                    <!-- View -->
                    <button type="button" @click="openViewModal(doc)"
                      class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg bg-mp-card-hover hover:bg-mp-teal/20 text-white hover:text-white text-xs font-semibold transition-colors">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                      </svg>
                      View
                    </button>
                    <!-- Download -->
                    <a :href="`/portfolio-companies/${company.id}/data-room/${doc.id}/download`"
                      class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-lg bg-mp-teal/10 hover:bg-mp-teal text-white hover:text-white text-xs font-semibold transition-colors">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                      </svg>
                      Download
                    </a>
                    <!-- Rename -->
                    <button @click="openRenameModal(doc)"
                      class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-warning/20 text-white hover:text-mp-warning transition-colors flex-shrink-0"
                      title="Rename">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </button>
                    <!-- Delete -->
                    <button @click="confirmDelete(doc)"
                      class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger/20 text-white hover:text-mp-danger transition-colors flex-shrink-0"
                      title="Delete">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </div>
                </div>
              </div>
            </template>
          </div>

        </div>
      </div>
    </div>

    <!-- ══════════════════════════════════════════ UPLOAD MODAL ══════════════ -->
    <Teleport to="body">
      <div v-if="uploadModal.show"
        class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-mp-card rounded-2xl border border-mp-border shadow-2xl w-full max-w-lg">

          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
            <h2 class="text-white font-bold text-base flex items-center gap-2">
              <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
              </svg>
              Upload Document
            </h2>
            <button @click="closeUploadModal" class="text-white hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="p-6 space-y-4">

            <!-- Drop Zone -->
            <div
              @dragover.prevent="uploadModal.dragging = true"
              @dragleave.prevent="uploadModal.dragging = false"
              @drop.prevent="handleDrop"
              :class="[
                'border-2 border-dashed rounded-xl p-8 text-center transition-all cursor-pointer',
                uploadModal.dragging   ? 'border-mp-teal bg-mp-teal-subtle/20 scale-[1.01]' :
                uploadModal.file       ? 'border-mp-success bg-mp-success/10' :
                                         'border-mp-border hover:border-mp-border'
              ]"
              @click="$refs.fileInput.click()">
              <input ref="fileInput" type="file" class="hidden"
                accept=".xlsx,.xls,.csv,.pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp"
                @change="handleFileSelect"/>

              <div v-if="!uploadModal.file" class="space-y-2">
                <div class="w-12 h-12 bg-mp-card-hover rounded-xl flex items-center justify-center mx-auto">
                  <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                  </svg>
                </div>
                <p class="text-white text-sm font-semibold">Drop file here or <span class="text-white">click to browse</span></p>
                <p class="text-white text-xs">Excel · CSV · PDF · Word · PowerPoint · Images · Max 50 MB</p>
              </div>

              <div v-else class="flex items-center gap-4 justify-center">
                <span class="text-3xl">{{ fileIconEmoji(uploadModal.fileMime) }}</span>
                <div class="text-left">
                  <p class="text-white text-sm font-semibold">{{ uploadModal.file.name }}</p>
                  <p class="text-white text-xs">{{ formatFileSize(uploadModal.file.size) }} · {{ fileTypeLabel(uploadModal.fileMime) }}</p>
                </div>
                <button @click.stop="clearFile"
                  class="w-7 h-7 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-danger text-white hover:text-white transition-colors">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                </button>
              </div>
            </div>

            <!-- Section selector -->
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">
                Section <span class="text-mp-danger">*</span>
              </label>
              <div class="grid grid-cols-2 gap-2">
                <template v-for="(label, key) in sections" :key="key">
                  <button @click="uploadModal.category = key" type="button"
                    :class="[
                      'flex items-center gap-2.5 px-3 py-2.5 rounded-lg border text-sm font-medium transition-all text-left',
                      uploadModal.category === key
                        ? 'border-mp-teal bg-mp-teal/15 text-white'
                        : 'border-mp-border bg-mp-card-hover text-white hover:border-mp-border hover:text-white'
                    ]">
                    <span class="text-base flex-shrink-0">{{ sectionIcon(key) }}</span>
                    <span class="text-xs leading-tight">{{ label }}</span>
                  </button>
                </template>
              </div>
            </div>

            <!-- Display Name -->
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">
                Document Name <span class="text-white font-normal normal-case">(optional)</span>
              </label>
              <input v-model="uploadModal.name" type="text"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm placeholder-gray-600 focus:outline-none focus:border-mp-teal"
                placeholder="Leave blank to use original filename"/>
            </div>

          </div>

          <div class="flex gap-3 px-6 py-4 border-t border-mp-border">
            <button @click="closeUploadModal"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
              Cancel
            </button>
            <button @click="submitUpload"
              :disabled="!uploadModal.file || !uploadModal.category || uploadModal.loading"
              :class="[
                'flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors',
                uploadModal.file && uploadModal.category && !uploadModal.loading
                  ? 'bg-mp-teal hover:bg-mp-teal-dark text-white'
                  : 'bg-mp-page text-white cursor-not-allowed'
              ]">
              <svg v-if="uploadModal.loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              {{ uploadModal.loading ? 'Uploading…' : 'Upload Document' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══════════════════════════════════════════ RENAME MODAL ══════════════ -->
    <Teleport to="body">
      <div v-if="renameModal.show"
        class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-mp-card rounded-2xl border border-mp-border shadow-2xl w-full max-w-md">

          <div class="flex items-center justify-between px-6 py-4 border-b border-mp-border">
            <h2 class="text-white font-bold text-base flex items-center gap-2">
              <svg class="w-5 h-5 text-mp-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
              </svg>
              Rename Document
            </h2>
            <button @click="renameModal.show = false" class="text-white hover:text-white transition-colors">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="p-6 space-y-4">
            <div>
              <label class="block text-xs font-semibold text-white uppercase tracking-widest mb-1.5">
                New Name
              </label>
              <input v-model="renameModal.name" type="text" @keyup.enter="submitRename"
                class="w-full bg-mp-card-hover border border-mp-border rounded-lg px-3 py-2.5 text-white text-sm focus:outline-none focus:border-mp-teal"
                placeholder="Enter document name"/>
            </div>
          </div>

          <div class="flex gap-3 px-6 py-4 border-t border-mp-border">
            <button @click="renameModal.show = false"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
              Cancel
            </button>
            <button @click="submitRename"
              :disabled="!renameModal.name.trim() || renameModal.loading"
              :class="[
                'flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors',
                renameModal.name.trim() && !renameModal.loading
                  ? 'bg-mp-warning hover:bg-mp-warning text-white'
                  : 'bg-mp-page text-white cursor-not-allowed'
              ]">
              <svg v-if="renameModal.loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              {{ renameModal.loading ? 'Saving…' : 'Save Name' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══════════════════════════════════════════ VIEW MODAL ════════════════ -->
    <Teleport to="body">
      <div v-if="viewModal.show"
        class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex flex-col p-4">
        <div class="bg-mp-card rounded-2xl border border-mp-border shadow-2xl w-full max-w-6xl mx-auto flex flex-col overflow-hidden"
          style="height: min(92vh, 920px);">

          <div class="flex items-center justify-between gap-4 px-5 py-3.5 border-b border-mp-border flex-shrink-0">
            <div class="min-w-0">
              <p class="text-white font-bold text-sm truncate" :title="viewModal.doc?.name">
                {{ viewModal.doc?.name }}
              </p>
              <p class="text-white/70 text-xs mt-0.5">{{ fileTypeLabel(viewModal.doc?.mime_type) }}</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
              <a v-if="viewModal.doc"
                :href="`/portfolio-companies/${company.id}/data-room/${viewModal.doc.id}/download`"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-mp-teal/10 hover:bg-mp-teal text-white text-xs font-semibold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download
              </a>
              <button type="button" @click="closeViewModal"
                class="w-8 h-8 flex items-center justify-center rounded-lg bg-mp-card-hover hover:bg-mp-page text-white transition-colors"
                title="Close">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
          </div>

          <div class="flex-1 min-h-0 bg-mp-page">
            <iframe v-if="viewKind === 'pdf'"
              :src="viewUrl"
              class="w-full h-full border-0 bg-white"
              title="Document preview"/>

            <div v-else-if="viewKind === 'image'" class="w-full h-full flex items-center justify-center p-4 overflow-auto">
              <img :src="viewUrl" :alt="viewModal.doc?.name" class="max-w-full max-h-full object-contain rounded-lg"/>
            </div>

            <div v-else-if="viewKind === 'csv'" class="w-full h-full overflow-auto p-4">
              <p v-if="viewModal.csvLoading" class="text-white text-sm">Loading preview…</p>
              <p v-else-if="viewModal.csvError" class="text-mp-danger text-sm">{{ viewModal.csvError }}</p>
              <pre v-else class="text-white text-xs font-mono whitespace-pre-wrap break-words">{{ viewModal.csvContent }}</pre>
            </div>

            <div v-else class="w-full h-full flex items-center justify-center p-8">
              <div class="max-w-md text-center">
                <div class="w-14 h-14 bg-mp-card-hover rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl">
                  {{ fileIconEmoji(viewModal.doc?.mime_type) }}
                </div>
                <h3 class="text-white font-bold text-base mb-2">Preview not available</h3>
                <p class="text-white/80 text-sm mb-5">
                  {{ fileTypeLabel(viewModal.doc?.mime_type) }} files cannot be previewed in the browser.
                  Download the file to open it on your computer.
                </p>
                <a v-if="viewModal.doc"
                  :href="`/portfolio-companies/${company.id}/data-room/${viewModal.doc.id}/download`"
                  class="inline-flex items-center gap-2 bg-mp-teal hover:bg-mp-teal-dark text-white text-sm font-semibold px-4 py-2.5 rounded-lg transition-colors">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                  </svg>
                  Download
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- ══════════════════════════════════════════ DELETE MODAL ══════════════ -->
    <Teleport to="body">
      <div v-if="deleteModal.show"
        class="fixed inset-0 bg-black/75 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-mp-card rounded-2xl border border-mp-border shadow-2xl w-full max-w-md">

          <div class="p-6 text-center">
            <div class="w-14 h-14 bg-mp-danger/40 rounded-2xl flex items-center justify-center mx-auto mb-4">
              <svg class="w-7 h-7 text-mp-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
            </div>
            <h3 class="text-white font-bold text-lg mb-2">Delete Document?</h3>
            <p class="text-white text-sm mb-1">You are about to permanently delete:</p>
            <p class="text-white font-semibold text-sm bg-mp-card-hover rounded-lg px-4 py-2 mb-4 truncate">
              {{ deleteModal.doc?.name }}
            </p>
            <p class="text-mp-danger text-xs">This action cannot be undone. The file will be removed from storage.</p>
          </div>

          <div class="flex gap-3 px-6 pb-6">
            <button @click="deleteModal.show = false"
              class="flex-1 px-4 py-2.5 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-sm font-medium transition-colors">
              Cancel
            </button>
            <button @click="executeDelete"
              :disabled="deleteModal.loading"
              class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg bg-mp-danger hover:bg-mp-danger text-white text-sm font-semibold transition-colors disabled:opacity-60">
              <svg v-if="deleteModal.loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              {{ deleteModal.loading ? 'Deleting…' : 'Yes, Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

  </AuthenticatedLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'

const props = defineProps({
  company:       Object,
  documents:     Array,
  sections:      Object,
  sectionCounts: Object,
  lastUpload:    String,
})

// ── State ──────────────────────────────────────────────────────────────────────
const activeSection = ref('all')
const searchQuery   = ref('')
const fileInput     = ref(null)

// ── Computed ───────────────────────────────────────────────────────────────────
const filteredDocs = computed(() => {
  let docs = props.documents ?? []
  if (activeSection.value !== 'all') {
    docs = docs.filter(d => d.category === activeSection.value)
  }
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase()
    docs = docs.filter(d =>
      d.name.toLowerCase().includes(q) ||
      (props.sections[d.category] ?? '').toLowerCase().includes(q) ||
      d.uploader.toLowerCase().includes(q)
    )
  }
  return docs
})

const sectionsWithFiles = computed(() => {
  return Object.values(props.sectionCounts ?? {}).filter(c => c > 0).length
})

// ── Upload Modal ───────────────────────────────────────────────────────────────
const uploadModal = reactive({
  show:     false,
  dragging: false,
  file:     null,
  fileMime: '',
  category: '',
  name:     '',
  loading:  false,
})

function openUploadModal(presetCategory = '') {
  uploadModal.show     = true
  uploadModal.category = presetCategory || ''
  uploadModal.name     = ''
  uploadModal.file     = null
  uploadModal.fileMime = ''
  uploadModal.loading  = false
}

function closeUploadModal() {
  uploadModal.show = false
  clearFile()
}

function handleFileSelect(e) {
  const file = e.target.files[0]
  if (file) setFile(file)
}

function handleDrop(e) {
  uploadModal.dragging = false
  const file = e.dataTransfer.files[0]
  if (file) setFile(file)
}

function setFile(file) {
  uploadModal.file     = file
  uploadModal.fileMime = file.type || guessMimeFromName(file.name)
}

function clearFile() {
  uploadModal.file     = null
  uploadModal.fileMime = ''
  if (fileInput.value) fileInput.value.value = ''
}

function guessMimeFromName(name) {
  const ext = name.split('.').pop().toLowerCase()
  const map = {
    xlsx: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    xls:  'application/vnd.ms-excel',
    csv:  'text/csv',
    pdf:  'application/pdf',
    doc:  'application/msword',
    docx: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ppt:  'application/vnd.ms-powerpoint',
    pptx: 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    jpg:  'image/jpeg', jpeg: 'image/jpeg',
    png:  'image/png', gif: 'image/gif', webp: 'image/webp',
  }
  return map[ext] || 'application/octet-stream'
}

function submitUpload() {
  if (!uploadModal.file || !uploadModal.category || uploadModal.loading) return
  uploadModal.loading = true

  router.post(
    `/portfolio-companies/${props.company.id}/data-room`,
    {
      file:     uploadModal.file,
      category: uploadModal.category,
      name:     uploadModal.name || '',
    },
    {
      forceFormData:  true,
      preserveScroll: true,
      onSuccess: () => {
        closeUploadModal()
      },
      onError: (errors) => {
        uploadModal.loading = false
        const msg = Object.values(errors)[0] ?? 'Upload failed. Please check file type and size.'
        alert(msg)
      },
      onFinish: () => {
        uploadModal.loading = false
      },
    }
  )
}

// ── Rename Modal ───────────────────────────────────────────────────────────────
const renameModal = reactive({
  show:    false,
  doc:     null,
  name:    '',
  loading: false,
})

function openRenameModal(doc) {
  renameModal.doc     = doc
  renameModal.name    = doc.name
  renameModal.loading = false
  renameModal.show    = true
}

function submitRename() {
  if (!renameModal.name.trim() || renameModal.loading) return
  renameModal.loading = true

  router.patch(
    `/portfolio-companies/${props.company.id}/data-room/${renameModal.doc.id}/rename`,
    { name: renameModal.name.trim() },
    {
      onSuccess: () => { renameModal.show = false },
      onFinish:  () => { renameModal.loading = false },
    }
  )
}

// ── View Modal ─────────────────────────────────────────────────────────────────
const viewModal = reactive({
  show:       false,
  doc:        null,
  csvContent: '',
  csvLoading: false,
  csvError:   '',
})

const viewKind = computed(() => {
  const mime = viewModal.doc?.mime_type || ''
  if (mime.includes('pdf')) return 'pdf'
  if (mime.startsWith('image/')) return 'image'
  if (mime === 'text/csv' || mime === 'text/plain' || mime.includes('csv')) return 'csv'
  return 'unsupported'
})

const viewUrl = computed(() => {
  if (!viewModal.doc) return ''
  return `/portfolio-companies/${props.company.id}/data-room/${viewModal.doc.id}/view`
})

function openViewModal(doc) {
  viewModal.doc        = doc
  viewModal.csvContent = ''
  viewModal.csvLoading = false
  viewModal.csvError   = ''
  viewModal.show       = true

  if ((doc.mime_type === 'text/csv' || doc.mime_type === 'text/plain' || (doc.mime_type || '').includes('csv'))) {
    loadCsvPreview(doc)
  }
}

function closeViewModal() {
  viewModal.show       = false
  viewModal.doc        = null
  viewModal.csvContent = ''
  viewModal.csvError   = ''
}

async function loadCsvPreview(doc) {
  viewModal.csvLoading = true
  viewModal.csvError   = ''
  try {
    const response = await fetch(`/portfolio-companies/${props.company.id}/data-room/${doc.id}/view`, {
      credentials: 'same-origin',
    })
    if (!response.ok) {
      throw new Error('Could not load file preview.')
    }
    viewModal.csvContent = await response.text()
  } catch (error) {
    viewModal.csvError = error?.message || 'Could not load file preview.'
  } finally {
    viewModal.csvLoading = false
  }
}

// ── Delete Modal ───────────────────────────────────────────────────────────────
const deleteModal = reactive({ show: false, doc: null, loading: false })

function confirmDelete(doc) {
  deleteModal.doc     = doc
  deleteModal.loading = false
  deleteModal.show    = true
}

function executeDelete() {
  if (!deleteModal.doc || deleteModal.loading) return
  deleteModal.loading = true

  router.delete(
    `/portfolio-companies/${props.company.id}/data-room/${deleteModal.doc.id}`,
    {
      onSuccess: () => { deleteModal.show = false },
      onFinish:  () => { deleteModal.loading = false },
    }
  )
}

// ── Display Helpers ────────────────────────────────────────────────────────────
function sectionIcon(key) {
  const map = {
    due_diligence:       '🔍',
    contracts_legal:     '📜',
    financial_documents: '💰',
    corporate_documents: '🏛️',
    operational:         '⚙️',
    other:               '📁',
  }
  return map[key] ?? '📄'
}

function sectionIconActiveBg(key) {
  const map = {
    due_diligence:       'bg-mp-gold-dark/30',
    contracts_legal:     'bg-mp-gold-dark/30',
    financial_documents: 'bg-mp-success/30',
    corporate_documents: 'bg-mp-teal/30',
    operational:         'bg-mp-warning/30',
    other:               'bg-mp-muted/30',
  }
  return map[key] ?? 'bg-mp-teal/30'
}

function sectionBadgeClass(key) {
  const map = {
    due_diligence:       'bg-mp-gold/50 text-white border border-mp-gold',
    contracts_legal:     'bg-mp-gold/50 text-white border border-mp-gold',
    financial_documents: 'bg-mp-success/50 text-mp-success border border-mp-success',
    corporate_documents: 'bg-mp-teal-subtle/50 text-white border border-mp-teal',
    operational:         'bg-mp-warning/50 text-mp-warning border border-mp-warning',
    other:               'bg-mp-card-hover text-white border border-mp-border',
  }
  return map[key] ?? 'bg-mp-card-hover text-white'
}

function fileIconEmoji(mime) {
  if (!mime) return '📄'
  if (mime.includes('pdf'))        return '📕'
  if (mime.includes('sheet') || mime.includes('excel') || mime.includes('csv')) return '📗'
  if (mime.includes('word') || mime.includes('document')) return '📘'
  if (mime.includes('presentation') || mime.includes('powerpoint')) return '📙'
  if (mime.includes('image'))      return '🖼️'
  return '📄'
}

function fileIconBg(mime) {
  if (!mime) return 'bg-mp-card-hover'
  if (mime.includes('pdf'))        return 'bg-mp-danger/40'
  if (mime.includes('sheet') || mime.includes('excel') || mime.includes('csv')) return 'bg-mp-success/40'
  if (mime.includes('word') || mime.includes('document')) return 'bg-mp-teal-subtle/40'
  if (mime.includes('presentation') || mime.includes('powerpoint')) return 'bg-mp-warning/40'
  if (mime.includes('image'))      return 'bg-mp-gold/40'
  return 'bg-mp-card-hover'
}

function fileTypeBanner(mime) {
  if (!mime) return 'bg-mp-page'
  if (mime.includes('pdf'))        return 'bg-mp-danger'
  if (mime.includes('sheet') || mime.includes('excel') || mime.includes('csv')) return 'bg-mp-success'
  if (mime.includes('word') || mime.includes('document')) return 'bg-mp-teal'
  if (mime.includes('presentation') || mime.includes('powerpoint')) return 'bg-mp-warning'
  if (mime.includes('image'))      return 'bg-mp-gold'
  return 'bg-mp-muted'
}

function fileTypeLabel(mime) {
  if (!mime) return 'File'
  if (mime.includes('pdf'))                 return 'PDF Document'
  if (mime.includes('sheet'))               return 'Excel Spreadsheet'
  if (mime.includes('excel'))               return 'Excel Spreadsheet'
  if (mime.includes('csv') || mime === 'text/plain') return 'CSV File'
  if (mime.includes('wordprocessingml'))    return 'Word Document'
  if (mime.includes('msword'))              return 'Word Document'
  if (mime.includes('presentationml'))      return 'PowerPoint'
  if (mime.includes('powerpoint'))          return 'PowerPoint'
  if (mime.includes('image'))               return 'Image'
  return 'File'
}

function formatDate(dateStr) {
  if (!dateStr) return '—'
  return new Date(dateStr).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

function formatFileSize(bytes) {
  if (!bytes) return ''
  if (bytes < 1024)       return bytes + ' B'
  if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / 1048576).toFixed(1) + ' MB'
}
</script>
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
        <div :class="[
            'bg-mp-card rounded-2xl border border-mp-border shadow-2xl w-full mx-auto flex flex-col overflow-hidden',
            viewModal.editing ? 'max-w-none' : 'max-w-6xl'
          ]"
          :style="viewModal.editing ? 'height: 96vh;' : 'height: min(92vh, 920px);'">

          <div class="flex items-center justify-between gap-4 px-5 py-3.5 border-b border-mp-border flex-shrink-0">
            <div class="min-w-0">
              <p class="text-white font-bold text-sm truncate" :title="viewModal.doc?.name">
                {{ viewModal.doc?.name }}
              </p>
              <p class="text-white/70 text-xs mt-0.5">
                {{ fileTypeLabel(viewModal.doc?.mime_type) }}
                <span v-if="viewModal.editing" class="text-mp-teal font-semibold"> · Editing — formulas calculate live</span>
              </p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">

              <!-- Edit / Save / Cancel -->
              <button v-if="canEditSheet && !viewModal.editing" type="button" @click="startEditing"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-mp-warning/20 hover:bg-mp-warning text-white text-xs font-semibold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit
              </button>

              <template v-if="viewModal.editing">
                <span v-if="viewModal.editFlash"
                  :class="viewModal.editFlash.type === 'success' ? 'text-mp-success' : 'text-mp-danger'"
                  class="text-xs font-semibold px-2">
                  {{ viewModal.editFlash.text }}
                </span>
                <button type="button" @click="saveEditing"
                  :disabled="viewModal.saving || viewModal.editLoading || !!viewModal.editError"
                  class="flex items-center gap-1.5 px-3 py-2 rounded-lg bg-mp-success hover:bg-mp-success text-white text-xs font-semibold transition-colors disabled:opacity-50">
                  <svg v-if="viewModal.saving" class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                  </svg>
                  <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-4-4v8m0 0l3-3m-3 3l-3-3"/>
                  </svg>
                  {{ viewModal.saving ? 'Saving…' : 'Save Changes' }}
                </button>
                <button type="button" @click="stopEditing()"
                  class="px-3 py-2 rounded-lg bg-mp-card-hover hover:bg-mp-page text-white text-xs font-semibold transition-colors">
                  Cancel
                </button>
              </template>

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

            <!-- ── EDIT MODE: live spreadsheet editor ── -->
            <div v-if="viewModal.editing" class="w-full h-full min-h-0">
              <div v-if="viewModal.editLoading" class="w-full h-full flex items-center justify-center text-white text-sm">
                Loading spreadsheet for editing…
              </div>
              <div v-else-if="viewModal.editError" class="w-full h-full flex items-center justify-center p-8">
                <p class="text-mp-danger text-sm text-center">{{ viewModal.editError }}</p>
              </div>
              <UniverSheetEditor
                v-else
                ref="sheetEditor"
                :sheets="viewModal.editSheets"
                :workbook-name="viewModal.doc?.name || 'Workbook'"
                @ready="captureEditBaseline"
                @error="viewModal.editError = $event"/>
            </div>

            <iframe v-else-if="viewKind === 'pdf'"
              :src="viewUrl"
              class="w-full h-full border-0 bg-white"
              title="Document preview"/>

            <div v-else-if="viewKind === 'image'" class="w-full h-full flex items-center justify-center p-4 overflow-auto">
              <img :src="viewUrl" :alt="viewModal.doc?.name" class="max-w-full max-h-full object-contain rounded-lg"/>
            </div>

            <div v-else-if="viewKind === 'csv'" class="w-full h-full overflow-auto p-4">
              <p v-if="viewModal.officeLoading" class="text-white text-sm">Loading preview…</p>
              <p v-else-if="viewModal.officeError" class="text-mp-danger text-sm">{{ viewModal.officeError }}</p>
              <pre v-else class="text-white text-xs font-mono whitespace-pre-wrap break-words">{{ viewModal.csvContent }}</pre>
            </div>

            <div v-else-if="viewKind === 'excel'" class="w-full h-full flex flex-col min-h-0">
              <div v-if="viewModal.officeLoading" class="flex-1 flex items-center justify-center text-white text-sm">
                Loading spreadsheet…
              </div>
              <div v-else-if="viewModal.officeError" class="flex-1 flex items-center justify-center p-8">
                <p class="text-mp-danger text-sm text-center">{{ viewModal.officeError }}</p>
              </div>
              <template v-else>
                <div class="flex-shrink-0 flex items-center gap-1 px-3 py-2 border-b border-mp-border overflow-x-auto">
                  <button
                    v-for="(sheet, idx) in viewModal.excelSheets"
                    :key="sheet.name"
                    type="button"
                    @click="viewModal.excelActiveSheet = idx"
                    :class="[
                      'px-3 py-1.5 rounded-md text-xs font-semibold whitespace-nowrap transition-colors',
                      viewModal.excelActiveSheet === idx
                        ? 'bg-mp-teal text-white'
                        : 'bg-mp-card-hover text-white hover:bg-mp-teal/20'
                    ]">
                    {{ sheet.name }}
                  </button>
                </div>
                <p v-if="activeExcelSheet?.truncated" class="flex-shrink-0 px-4 py-1.5 text-xs text-white/70 border-b border-mp-border">
                  Showing the first {{ EXCEL_PREVIEW_ROWS }} rows.
                </p>
                <div class="flex-1 min-h-0 overflow-auto bg-white">
                  <table v-if="activeExcelSheet" class="excel-preview-table">
                    <tbody>
                      <tr v-for="(row, rIdx) in activeExcelSheet.rows" :key="rIdx">
                        <td v-for="(cell, cIdx) in paddedExcelRow(row)" :key="cIdx">{{ cell }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </template>
            </div>

            <div v-else-if="viewKind === 'word'" class="w-full h-full relative min-h-0">
              <div v-if="viewModal.officeLoading"
                class="absolute inset-0 z-10 flex items-center justify-center bg-mp-page text-white text-sm">
                Loading document…
              </div>
              <p v-else-if="viewModal.officeError" class="p-8 text-mp-danger text-sm text-center">
                {{ viewModal.officeError }}
              </p>
              <div
                v-show="!viewModal.officeError"
                ref="wordPreviewEl"
                class="w-full h-full overflow-auto bg-neutral-200 p-4"
              ></div>
            </div>

            <div v-else-if="viewKind === 'powerpoint'" class="w-full h-full relative min-h-0">
              <div v-if="viewModal.officeLoading"
                class="absolute inset-0 z-10 flex items-center justify-center bg-mp-page text-white text-sm">
                Loading presentation…
              </div>
              <p v-else-if="viewModal.officeError" class="p-8 text-mp-danger text-sm text-center">
                {{ viewModal.officeError }}
              </p>
              <div
                v-show="!viewModal.officeError"
                ref="pptxPreviewEl"
                class="w-full h-full overflow-auto bg-neutral-200 p-4"
              ></div>
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
import { ref, reactive, computed, nextTick } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import UniverSheetEditor from '@/Components/UniverSheetEditor.vue'

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
const EXCEL_PREVIEW_ROWS = 500
const wordPreviewEl = ref(null)
const pptxPreviewEl = ref(null)
let previewSeq = 0
let pptxViewer = null

const sheetEditor   = ref(null)
let   editFlashTimer = null

const viewModal = reactive({
  show:             false,
  doc:              null,
  csvContent:       '',
  officeLoading:    false,
  officeError:      '',
  excelSheets:      [],
  excelActiveSheet: 0,
  // ── live editing ──
  editing:          false,
  editLoading:      false,
  editError:        '',
  editSheets:       {},
  saving:           false,
  editFlash:        null,
  editBaseline:     null,
})

const viewKind = computed(() => previewKind(viewModal.doc?.mime_type))

// Only real spreadsheets can go through the live editor — a .txt served as
// text/csv previews as text but is not a grid.
const canEditSheet = computed(() => {
  const doc = viewModal.doc
  if (!doc) return false
  const ext = (doc.name || '').split('.').pop().toLowerCase()
  return ['xlsx', 'xls', 'csv'].includes(ext)
})

const viewUrl = computed(() => {
  if (!viewModal.doc) return ''
  return `/portfolio-companies/${props.company.id}/data-room/${viewModal.doc.id}/view`
})

const activeExcelSheet = computed(() => {
  return viewModal.excelSheets[viewModal.excelActiveSheet] ?? null
})

function previewKind(mime) {
  if (!mime) return 'unsupported'
  if (mime.includes('pdf')) return 'pdf'
  if (mime.startsWith('image/')) return 'image'
  if (mime === 'text/csv' || mime === 'text/plain' || mime.includes('csv')) return 'csv'
  if (mime.includes('wordprocessingml')) return 'word'
  if (mime.includes('sheet') || mime.includes('excel')) return 'excel'
  if (mime.includes('presentationml')) return 'powerpoint'
  return 'unsupported'
}

function paddedExcelRow(row) {
  const width = activeExcelSheet.value?.colCount ?? (row?.length ?? 1)
  const cells = Array.isArray(row) ? [...row] : []
  while (cells.length < width) cells.push('')
  return cells
}

function resetViewPreview() {
  previewSeq += 1
  viewModal.csvContent = ''
  viewModal.officeLoading = false
  viewModal.officeError = ''
  viewModal.excelSheets = []
  viewModal.excelActiveSheet = 0
  if (pptxViewer?.destroy) {
    pptxViewer.destroy()
  }
  pptxViewer = null
  if (wordPreviewEl.value) wordPreviewEl.value.innerHTML = ''
  if (pptxPreviewEl.value) pptxPreviewEl.value.innerHTML = ''
}

function openViewModal(doc) {
  viewModal.doc = doc
  resetEditing()
  resetViewPreview()
  viewModal.show = true

  const kind = previewKind(doc.mime_type)
  if (kind === 'csv') loadCsvPreview(doc)
  else if (kind === 'excel') loadExcelPreview(doc)
  else if (kind === 'word') loadWordPreview(doc)
  else if (kind === 'powerpoint') loadPowerpointPreview(doc)
}

function closeViewModal() {
  if (viewModal.editing && !confirmDiscardEdits()) return
  resetEditing()
  resetViewPreview()
  viewModal.show = false
  viewModal.doc = null
}

// ── Live Spreadsheet Editing ───────────────────────────────────────────────────
function sheetsUrl(doc) {
  return `/portfolio-companies/${props.company.id}/data-room/${doc.id}/sheets`
}

// Dirty check by comparing against a baseline taken through the exact same
// export path, so type coercions inside Univer never register as a change.
async function captureEditBaseline() {
  await nextTick()
  try {
    viewModal.editBaseline = JSON.stringify(sheetEditor.value?.getSheets() ?? null)
  } catch (_) {
    viewModal.editBaseline = null
  }
}

function hasUnsavedEdits() {
  if (viewModal.editBaseline === null) return true
  try {
    return JSON.stringify(sheetEditor.value?.getSheets() ?? null) !== viewModal.editBaseline
  } catch (_) {
    return true
  }
}

function confirmDiscardEdits() {
  if (!hasUnsavedEdits()) return true
  return window.confirm('Discard unsaved changes to this spreadsheet?')
}

function resetEditing() {
  if (editFlashTimer) clearTimeout(editFlashTimer)
  viewModal.editing     = false
  viewModal.editLoading = false
  viewModal.editError   = ''
  viewModal.editSheets  = {}
  viewModal.saving      = false
  viewModal.editFlash   = null
  viewModal.editBaseline = null
}

async function startEditing() {
  const doc = viewModal.doc
  if (!doc || !canEditSheet.value) return

  viewModal.editing     = true
  viewModal.editLoading = true
  viewModal.editError   = ''
  viewModal.editSheets  = {}
  viewModal.editFlash   = null

  try {
    const { data } = await window.axios.get(sheetsUrl(doc))
    if (!viewModal.editing) return
    viewModal.editSheets = data?.sheets ?? {}
    if (Object.keys(viewModal.editSheets).length === 0) {
      viewModal.editError = 'This file has no sheets to edit.'
    }
  } catch (error) {
    if (!viewModal.editing) return
    viewModal.editError = error?.response?.data?.message || 'Could not open this file for editing.'
  } finally {
    if (viewModal.editing) viewModal.editLoading = false
  }
}

function stopEditing(skipConfirm = false) {
  if (!skipConfirm && !confirmDiscardEdits()) return
  resetEditing()
}

async function saveEditing() {
  if (viewModal.saving || !viewModal.doc) return

  let payload
  try {
    payload = sheetEditor.value?.getSheets()
  } catch (error) {
    showEditFlash('error', error?.message || 'Could not read sheet data to save.')
    return
  }
  if (!payload) {
    showEditFlash('error', 'Editor not ready yet.')
    return
  }

  viewModal.saving = true
  const doc = viewModal.doc

  try {
    const { data } = await window.axios.post(
      sheetsUrl(doc),
      { sheets: payload },
      { withXSRFToken: true },
    )
    showEditFlash('success', data?.message || 'Saved successfully.')
    captureEditBaseline()

    // Refresh the read-only preview underneath so it reflects the saved file
    if (previewKind(doc.mime_type) === 'excel')      loadExcelPreview(doc)
    else if (previewKind(doc.mime_type) === 'csv')   loadCsvPreview(doc)
  } catch (error) {
    showEditFlash('error', error?.response?.data?.message || 'Save failed. Please try again.')
  } finally {
    viewModal.saving = false
  }
}

function showEditFlash(type, text) {
  viewModal.editFlash = { type, text }
  if (editFlashTimer) clearTimeout(editFlashTimer)
  editFlashTimer = setTimeout(() => { viewModal.editFlash = null }, 5000)
}

async function fetchPreviewBuffer(doc) {
  const response = await fetch(`/portfolio-companies/${props.company.id}/data-room/${doc.id}/view`, {
    credentials: 'same-origin',
  })
  if (!response.ok) {
    throw new Error('Could not load file preview.')
  }
  return response.arrayBuffer()
}

async function loadCsvPreview(doc) {
  const seq = previewSeq
  viewModal.officeLoading = true
  viewModal.officeError = ''
  try {
    const buffer = await fetchPreviewBuffer(doc)
    if (seq !== previewSeq) return
    viewModal.csvContent = new TextDecoder().decode(buffer)
  } catch (error) {
    if (seq !== previewSeq) return
    viewModal.officeError = error?.message || 'Could not load file preview.'
  } finally {
    if (seq === previewSeq) viewModal.officeLoading = false
  }
}

async function loadExcelPreview(doc) {
  const seq = previewSeq
  viewModal.officeLoading = true
  viewModal.officeError = ''
  try {
    const [buffer, xlsxModule] = await Promise.all([
      fetchPreviewBuffer(doc),
      import('xlsx'),
    ])
    if (seq !== previewSeq) return

    const XLSX = xlsxModule.default ?? xlsxModule
    const workbook = XLSX.read(buffer, { type: 'array' })
    const names = workbook.SheetNames?.length ? workbook.SheetNames : ['Sheet1']

    viewModal.excelSheets = names.map((name) => {
      const sheet = workbook.Sheets[name]
      const rows = sheet
        ? XLSX.utils.sheet_to_json(sheet, { header: 1, raw: false, defval: '' })
        : []
      const truncated = rows.length > EXCEL_PREVIEW_ROWS
      const previewRows = rows.slice(0, EXCEL_PREVIEW_ROWS)
      const colCount = previewRows.reduce((max, row) => Math.max(max, Array.isArray(row) ? row.length : 0), 1)
      return { name, rows: previewRows, truncated, colCount }
    })
    viewModal.excelActiveSheet = 0
  } catch (error) {
    if (seq !== previewSeq) return
    viewModal.officeError = error?.message || 'Could not load spreadsheet preview.'
  } finally {
    if (seq === previewSeq) viewModal.officeLoading = false
  }
}

async function loadWordPreview(doc) {
  const seq = previewSeq
  viewModal.officeLoading = true
  viewModal.officeError = ''
  try {
    const [buffer, previewModule] = await Promise.all([
      fetchPreviewBuffer(doc),
      import('docx-preview'),
    ])
    if (seq !== previewSeq) return

    await nextTick()
    if (!wordPreviewEl.value) {
      throw new Error('Could not load file preview.')
    }
    wordPreviewEl.value.innerHTML = ''
    const renderAsync = previewModule.renderAsync ?? previewModule.default?.renderAsync
    await renderAsync(buffer, wordPreviewEl.value, undefined, {
      className: 'docx-wrapper',
      inWrapper: true,
      ignoreWidth: false,
      breakPages: true,
    })
  } catch (error) {
    if (seq !== previewSeq) return
    viewModal.officeError = error?.message || 'Could not load document preview.'
  } finally {
    if (seq === previewSeq) viewModal.officeLoading = false
  }
}

async function loadPowerpointPreview(doc) {
  const seq = previewSeq
  viewModal.officeLoading = true
  viewModal.officeError = ''
  try {
    const [buffer, pptxModule] = await Promise.all([
      fetchPreviewBuffer(doc),
      import('pptx-preview'),
    ])
    if (seq !== previewSeq) return

    await nextTick()
    if (!pptxPreviewEl.value) {
      throw new Error('Could not load file preview.')
    }
    pptxPreviewEl.value.innerHTML = ''
    const init = pptxModule.init ?? pptxModule.default?.init
    const width = Math.min(Math.max(pptxPreviewEl.value.clientWidth - 32, 320), 960)
    const height = Math.round(width * 9 / 16)
    pptxViewer = init(pptxPreviewEl.value, { width, height, mode: 'list' })
    await pptxViewer.preview(buffer)
  } catch (error) {
    if (seq !== previewSeq) return
    viewModal.officeError = error?.message || 'Could not load presentation preview.'
  } finally {
    if (seq === previewSeq) viewModal.officeLoading = false
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

<style>
.excel-preview-table {
  border-collapse: collapse;
  min-width: 100%;
  font-size: 12px;
  color: #111;
}
.excel-preview-table td {
  border: 1px solid #d4d4d4;
  padding: 4px 8px;
  white-space: nowrap;
  max-width: 28rem;
  overflow: hidden;
  text-overflow: ellipsis;
  background: #fff;
}
.excel-preview-table tr:first-child td {
  font-weight: 600;
  background: #f4f4f5;
}
.docx-wrapper {
  background: #fff;
  margin: 0 auto;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
}
</style>
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class InvestaDocsController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // Resolve the organization for the current user.
    // super-admin can pass ?org_id=X, others are locked to their own org.
    // ─────────────────────────────────────────────────────────────────────────
    private function resolveOrg(int $orgId): object
    {
        $user = auth()->user();
        if (!$user->hasRole('super-admin')) {
            abort_unless((int) $orgId === (int) $user->organization_id, 403);
        }
        $org = DB::table('organizations')->find($orgId);
        if (!$org) {
            abort(404);
        }
        return $org;
    }

    private function assertCompanyBelongsToOrg(int $orgId, ?int $portfolioCompanyId): void
    {
        if (!$portfolioCompanyId) {
            return;
        }
        $belongs = DB::table('portfolio_companies')
            ->where('id', $portfolioCompanyId)
            ->where('organization_id', $orgId)
            ->exists();
        abort_unless($belongs, 403, 'Company does not belong to this organization.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // INDEX — org-level document library
    // ─────────────────────────────────────────────────────────────────────────
    public function index(int $orgId)
    {
        $org       = $this->resolveOrg($orgId);
        $templates = DB::table('doc_templates')->orderBy('sort_order')->get();

        $docs = DB::table('investadocs as d')
            ->join('doc_templates as t', 't.id', '=', 'd.doc_template_id')
            ->join('users as u', 'u.id', '=', 'd.created_by')
            ->leftJoin('portfolio_companies as pc', 'pc.id', '=', 'd.portfolio_company_id')
            ->where('d.organization_id', $orgId)
            ->select(
                'd.id', 'd.title', 'd.status', 'd.created_at', 'd.sent_at', 'd.signed_at',
                'd.notes', 'd.target_company_name', 'd.portfolio_company_id',
                't.slug as template_slug', 't.name as template_name',
                't.short_name', 't.icon', 't.category',
                'u.name as created_by_name',
                'pc.name as portfolio_company_name'
            )
            ->orderByDesc('d.created_at')
            ->get();

        // Group by deal stage category
        $categorized = [
            'pre_loi'        => ['label' => 'Pre-LOI / Early Stage',   'icon' => '🔍', 'docs' => []],
            'due_diligence'  => ['label' => 'Due Diligence',            'icon' => '📋', 'docs' => []],
            'valuation'      => ['label' => 'Valuation & Negotiation',  'icon' => '💹', 'docs' => []],
            'closing'        => ['label' => 'Closing',                  'icon' => '✅', 'docs' => []],
            'post_investment'=> ['label' => 'Post-Investment',          'icon' => '📊', 'docs' => []],
        ];
        foreach ($docs as $d) {
            if (isset($categorized[$d->category])) {
                $categorized[$d->category]['docs'][] = $d;
            }
        }

        // Portfolio companies for this org (for the "link to company" dropdown)
        $portfolioCompanies = DB::table('portfolio_companies')
            ->where('organization_id', $orgId)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return Inertia::render('InvestaDocs/Index', [
            'org'                => $org,
            'templates'          => $templates,
            'documents'          => $docs,
            'categorized'        => $categorized,
            'portfolioCompanies' => $portfolioCompanies,
            'stats'              => [
                'total'    => $docs->count(),
                'draft'    => $docs->where('status', 'draft')->count(),
                'sent'     => $docs->where('status', 'sent')->count(),
                'signed'   => $docs->where('status', 'signed')->count(),
                'archived' => $docs->where('status', 'archived')->count(),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CREATE — document builder form
    // ─────────────────────────────────────────────────────────────────────────
    public function create(int $orgId, Request $request)
    {
        $org  = $this->resolveOrg($orgId);
        $slug = $request->query('template');

        $template  = $slug ? DB::table('doc_templates')->where('slug', $slug)->first() : null;
        $templates = DB::table('doc_templates')->orderBy('sort_order')->get();

        $portfolioCompanies = DB::table('portfolio_companies')
            ->where('organization_id', $orgId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('InvestaDocs/Create', [
            'org'                => $org,
            'templates'          => $templates,
            'template'           => $template,
            'portfolioCompanies' => $portfolioCompanies,
            'prefill'            => ['disclosing_party' => $org->name],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STORE — generate and save document
    // ─────────────────────────────────────────────────────────────────────────
    public function store(Request $request, int $orgId)
    {
        $org = $this->resolveOrg($orgId);

        $request->validate([
            'doc_template_id'      => 'required|exists:doc_templates,id',
            'title'                => 'required|string|max:255',
            'target_company_name'  => 'nullable|string|max:255',
            'portfolio_company_id' => 'nullable|exists:portfolio_companies,id',
            'variables_data'       => 'required|array',
            'notes'                => 'nullable|string|max:2000',
        ]);

        $template = DB::table('doc_templates')->find($request->doc_template_id);
        $vars     = $request->variables_data;
        $content  = $this->generateDocumentContent($template, $vars);

        $this->assertCompanyBelongsToOrg($orgId, $request->portfolio_company_id ? (int) $request->portfolio_company_id : null);

        $filename = 'investadocs/' . $orgId . '/' . $template->slug . '_' . now()->format('Ymd_His') . '.html';
        Storage::disk('private')->put($filename, $content);

        $docId = DB::table('investadocs')->insertGetId([
            'organization_id'      => $orgId,
            'doc_template_id'      => $request->doc_template_id,
            'created_by'           => auth()->id(),
            'portfolio_company_id' => $request->portfolio_company_id ?: null,
            'target_company_name'  => $request->target_company_name,
            'title'                => $request->title,
            'status'               => 'draft',
            'variables_data'       => json_encode($vars),
            'file_path'            => $filename,
            'notes'                => $request->notes,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        return redirect()->route('investadocs.show', [$orgId, $docId])
            ->with('success', 'Document created successfully.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHOW — view document details
    // ─────────────────────────────────────────────────────────────────────────
    public function show(int $orgId, int $docId)
    {
        $org = $this->resolveOrg($orgId);

        $doc = DB::table('investadocs as d')
            ->join('doc_templates as t', 't.id', '=', 'd.doc_template_id')
            ->join('users as u', 'u.id', '=', 'd.created_by')
            ->leftJoin('portfolio_companies as pc', 'pc.id', '=', 'd.portfolio_company_id')
            ->where('d.id', $docId)
            ->where('d.organization_id', $orgId)
            ->select(
                'd.*',
                't.name as template_name', 't.short_name', 't.icon',
                't.slug as template_slug', 't.variables',
                'u.name as created_by_name',
                'pc.name as portfolio_company_name'
            )
            ->first();

        if (!$doc) abort(404);

        $doc->variables_data = json_decode($doc->variables_data, true);
        $doc->variables      = json_decode($doc->variables, true);

        $portfolioCompanies = DB::table('portfolio_companies')
            ->where('organization_id', $orgId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('InvestaDocs/Show', [
            'org'                => $org,
            'doc'                => $doc,
            'portfolioCompanies' => $portfolioCompanies,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UPDATE STATUS
    // ─────────────────────────────────────────────────────────────────────────
    public function updateStatus(Request $request, int $orgId, int $docId)
    {
        $request->validate(['status' => 'required|in:draft,sent,signed,archived']);

        $update = ['status' => $request->status, 'updated_at' => now()];
        if ($request->status === 'sent')   $update['sent_at']   = now();
        if ($request->status === 'signed') $update['signed_at'] = now();

        DB::table('investadocs')
            ->where('id', $docId)
            ->where('organization_id', $orgId)
            ->update($update);

        return back()->with('success', 'Status updated.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // LINK TO PORTFOLIO COMPANY — attach a doc to a company in the system
    // ─────────────────────────────────────────────────────────────────────────
    public function linkCompany(Request $request, int $orgId, int $docId)
    {
        $this->resolveOrg($orgId);
        $request->validate(['portfolio_company_id' => 'nullable|exists:portfolio_companies,id']);
        $this->assertCompanyBelongsToOrg($orgId, $request->portfolio_company_id ? (int) $request->portfolio_company_id : null);

        DB::table('investadocs')
            ->where('id', $docId)
            ->where('organization_id', $orgId)
            ->update([
                'portfolio_company_id' => $request->portfolio_company_id ?: null,
                'updated_at'           => now(),
            ]);

        return back()->with('success', 'Company link updated.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DOWNLOAD
    // ─────────────────────────────────────────────────────────────────────────
    public function download(int $orgId, int $docId)
    {
        $this->resolveOrg($orgId);

        $doc = DB::table('investadocs')
            ->where('id', $docId)
            ->where('organization_id', $orgId)
            ->first();

        if (!$doc || !Storage::disk('private')->exists($doc->file_path)) {
            abort(404, 'File not found.');
        }

        $template = DB::table('doc_templates')->find($doc->doc_template_id);
        $filename = $doc->title . ' - ' . $template->short_name . '.html';

        return Storage::disk('private')->download($doc->file_path, $filename);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // DESTROY
    // ─────────────────────────────────────────────────────────────────────────
    public function destroy(int $orgId, int $docId)
    {
        $this->resolveOrg($orgId);

        $doc = DB::table('investadocs')
            ->where('id', $docId)
            ->where('organization_id', $orgId)
            ->first();

        if ($doc) {
            if ($doc->file_path && Storage::disk('private')->exists($doc->file_path)) {
                Storage::disk('private')->delete($doc->file_path);
            }
            DB::table('investadocs')->where('id', $docId)->delete();
        }

        return redirect()->route('investadocs.index', $orgId)
            ->with('success', 'Document deleted.');
    }

    // ═════════════════════════════════════════════════════════════════════════
    //  DOCUMENT GENERATION ENGINE
    // ═════════════════════════════════════════════════════════════════════════
    private function generateDocumentContent(object $template, array $vars): string
    {
        $css = $this->baseCSS();
        return match ($template->slug) {
            'nda'                       => $this->generateNDA($vars, $css),
            'loi'                       => $this->generateLOI($vars, $css),
            'term_sheet'                => $this->generateTermSheet($vars, $css),
            'sha'                       => $this->generateSHA($vars, $css),
            'offering_proposal'         => $this->generateOfferingProposal($vars, $css),
            'subscription_agreement'    => $this->generateSubscriptionAgreement($vars, $css),
            // Pre-LOI
            'teaser'                    => $this->generateTeaser($vars, $css),
            'information_memorandum'    => $this->generateIM($vars, $css),
            'management_presentation'   => $this->generateMgmtPresentation($vars, $css),
            // Due Diligence
            'dd_request_list'           => $this->generateDDRequestList($vars, $css),
            'dd_summary_report'         => $this->generateDDSummaryReport($vars, $css),
            'data_room_access_letter'   => $this->generateDataRoomLetter($vars, $css),
            // Valuation & Negotiation
            'valuation_summary'         => $this->generateValuationSummary($vars, $css),
            'indicative_offer_letter'   => $this->generateIndicativeOffer($vars, $css),
            // Closing
            'board_resolution'          => $this->generateBoardResolution($vars, $css),
            'completion_checklist'      => $this->generateCompletionChecklist($vars, $css),
            'share_transfer_agreement'  => $this->generateSTA($vars, $css),
            'loan_agreement'            => $this->generateLoanAgreement($vars, $css),
            // Post-Investment
            'board_meeting_minutes'     => $this->generateBoardMinutes($vars, $css),
            'quarterly_report'          => $this->generateQuarterlyReport($vars, $css),
            'exit_notice_letter'        => $this->generateExitNotice($vars, $css),
            default                     => '<p>Template not found.</p>',
        };
    }

    private function baseCSS(): string
    {
        return '<style>
            * { margin:0; padding:0; box-sizing:border-box; }
            body { font-family:"Georgia","Times New Roman",serif; font-size:11pt; line-height:1.75; color:#1a1a1a; background:#fff; }
            .page { max-width:210mm; margin:0 auto; padding:25mm 22mm; min-height:297mm; }
            h1 { font-size:17pt; font-weight:bold; text-align:center; margin-bottom:6px; letter-spacing:.5px; }
            h2 { font-size:12pt; font-weight:bold; margin:22px 0 6px; border-bottom:1.5px solid #1a1a1a; padding-bottom:3px; }
            h3 { font-size:11pt; font-weight:bold; margin:14px 0 4px; }
            p  { margin-bottom:10px; text-align:justify; }
            .sub { text-align:center; font-size:11pt; color:#444; margin-bottom:4px; }
            .dt  { text-align:center; font-size:10pt; color:#666; margin-bottom:30px; }
            .box { border:1px solid #aaa; padding:13px 17px; margin:16px 0; background:#fafafa; border-radius:2px; }
            .box p { margin-bottom:4px; }
            table.kv { width:100%; border-collapse:collapse; margin:10px 0 16px; }
            table.kv td { padding:7px 10px; border:1px solid #ddd; vertical-align:top; }
            table.kv td:first-child { font-weight:bold; width:42%; background:#f5f5f5; }
            .tr { display:flex; padding:7px 0; border-bottom:1px solid #eee; }
            .tl { font-weight:bold; min-width:46%; font-size:10.5pt; }
            .tv { flex:1; font-size:10.5pt; }
            .hl { border-left:4px solid #1a3a6b; padding:10px 14px; background:#f0f4ff; margin:14px 0; border-radius:0 4px 4px 0; }
            .sig { display:flex; gap:40px; margin-top:50px; }
            .sc  { flex:1; }
            .sl  { border-bottom:1px solid #333; margin-bottom:6px; height:36px; }
            .slb { font-size:9.5pt; color:#555; }
            .ft  { font-size:8.5pt; color:#777; text-align:center; margin-top:40px; border-top:1px solid #ddd; padding-top:10px; }
            .miss { color:#c00; background:#fff3f3; padding:0 3px; }
            ol li, ul li { margin-bottom:6px; margin-left:20px; }
            @media print { body{font-size:10.5pt;} .page{padding:15mm 15mm;} .nb{page-break-inside:avoid;} }
        </style>';
    }

    /** Return value or a red placeholder */
    private function v(array $v, string $key, string $default = '[__________]'): string
    {
        $raw = trim($v[$key] ?? '');
        if ($raw !== '') return nl2br(htmlspecialchars($raw));
        return "<span class='miss'>{$default}</span>";
    }

    // ── 1. NDA ────────────────────────────────────────────────────────────────
    private function generateNDA(array $v, string $css): string
    {
        $type   = $v['nda_type'] ?? 'Mutual';
        $mutual = strtolower($type) === 'mutual'
            ? 'Both Parties may disclose and receive Confidential Information under this Agreement.'
            : 'Only the Disclosing Party shall disclose Confidential Information under this Agreement.';

        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>NDA</title>{$css}</head><body><div class='page'>
<h1>NON-DISCLOSURE AGREEMENT</h1>
<p class='sub'>{$type} Confidentiality Agreement</p>
<p class='dt'>Dated: {$this->v($v,'effective_date')}</p>

<div class='box'>
  <p><strong>Disclosing Party:</strong> {$this->v($v,'disclosing_party')}</p>
  <p><strong>Receiving Party:</strong> {$this->v($v,'receiving_party')}</p>
</div>

<p>This Non-Disclosure Agreement (<strong>&ldquo;Agreement&rdquo;</strong>) is entered into as of {$this->v($v,'effective_date')} between {$this->v($v,'disclosing_party')} (<strong>&ldquo;Disclosing Party&rdquo;</strong>) and {$this->v($v,'receiving_party')} (<strong>&ldquo;Receiving Party&rdquo;</strong>), collectively the <strong>&ldquo;Parties.&rdquo;</strong></p>

<h2>1. Purpose</h2>
<p>The Parties wish to explore a potential business relationship in connection with: <strong>{$this->v($v,'purpose')}</strong> (the <strong>&ldquo;Purpose&rdquo;</strong>). {$mutual}</p>

<h2>2. Definition of Confidential Information</h2>
<p><strong>&ldquo;Confidential Information&rdquo;</strong> means any non-public, confidential, or proprietary information disclosed by the Disclosing Party to the Receiving Party, whether in writing, orally, or by inspection, that is designated as confidential or that reasonably should be understood to be confidential. This includes without limitation: financial data, business plans, strategies, customer lists, technical specifications, trade secrets, and all other non-public business information.</p>

<h2>3. Exclusions</h2>
<p>Confidential Information does not include information that: (a) is or becomes generally available to the public other than through any act or omission of the Receiving Party; (b) was in the Receiving Party&rsquo;s lawful possession prior to disclosure; (c) is independently developed by the Receiving Party without use of the Confidential Information; or (d) is required to be disclosed by law or court order, provided that prior written notice is given to the Disclosing Party.</p>

<h2>4. Obligations of the Receiving Party</h2>
<p>The Receiving Party agrees to: (a) hold all Confidential Information in strict confidence; (b) not disclose any Confidential Information to third parties without prior written consent; (c) use the Confidential Information solely for the Purpose; (d) limit access to those employees and advisors who have a genuine need to know and are bound by equivalent confidentiality obligations; and (e) promptly notify the Disclosing Party of any unauthorized disclosure.</p>

<h2>5. Term</h2>
<p>This Agreement is effective as of the date above and continues for <strong>{$this->v($v,'term_years')} years</strong>. Confidentiality obligations survive termination.</p>

<h2>6. Return or Destruction</h2>
<p>Upon written request or termination, the Receiving Party shall promptly return or certifiably destroy all Confidential Information and confirm in writing.</p>

<h2>7. No License</h2>
<p>Nothing herein grants any rights, by license or otherwise, to any Confidential Information or any intellectual property right.</p>

<h2>8. Governing Law</h2>
<p>This Agreement is governed by the laws of <strong>{$this->v($v,'governing_law')}</strong>. Any dispute shall be submitted to the competent courts of that jurisdiction.</p>

<h2>9. Entire Agreement</h2>
<p>This Agreement constitutes the entire agreement between the Parties regarding its subject matter and supersedes all prior discussions. Amendments require the written agreement of both Parties.</p>

<div class='sig nb'>
  <div class='sc'>
    <p><strong>DISCLOSING PARTY</strong></p>
    <p style='font-size:10pt;color:#555;margin-top:2px'>{$this->v($v,'disclosing_party')}</p>
    <div class='sl'></div>
    <p class='slb'>Signature</p>
    <p style='margin-top:8px'><strong>{$this->v($v,'disclosing_signatory')}</strong></p>
    <p class='slb'>{$this->v($v,'disclosing_title')}</p>
    <p class='slb'>Date: _______________________</p>
  </div>
  <div class='sc'>
    <p><strong>RECEIVING PARTY</strong></p>
    <p style='font-size:10pt;color:#555;margin-top:2px'>{$this->v($v,'receiving_party')}</p>
    <div class='sl'></div>
    <p class='slb'>Signature</p>
    <p style='margin-top:8px'><strong>{$this->v($v,'receiving_signatory')}</strong></p>
    <p class='slb'>{$this->v($v,'receiving_title')}</p>
    <p class='slb'>Date: _______________________</p>
  </div>
</div>
<p class='ft'>CONFIDENTIAL &mdash; This document is strictly confidential and intended solely for the named parties.</p>
</div></body></html>";
    }

    // ── 2. LOI ────────────────────────────────────────────────────────────────
    private function generateLOI(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>LOI</title>{$css}</head><body><div class='page'>
<h1>LETTER OF INTENT</h1>
<p class='sub'>Non-Binding &mdash; Subject to Definitive Documentation</p>
<p class='dt'>{$this->v($v,'effective_date')}</p>

<p>To the Board of Directors of <strong>{$this->v($v,'target_company')}</strong></p>
<p>From: <strong>{$this->v($v,'investor_name')}</strong></p>
<p>Dear Board Members,</p>
<p><strong>{$this->v($v,'investor_name')}</strong> (&ldquo;Investor&rdquo;) is pleased to submit this Letter of Intent (&ldquo;LOI&rdquo;) setting out the principal terms on which the Investor proposes to invest in <strong>{$this->v($v,'target_company')}</strong> (the &ldquo;Company&rdquo;). Except where expressly stated, this LOI is non-binding.</p>

<h2>1. Proposed Transaction</h2>
<div class='tr'><span class='tl'>Transaction Structure</span><span class='tv'>{$this->v($v,'transaction_structure')}</span></div>
<div class='tr'><span class='tl'>Investment Amount</span><span class='tv'><strong>{$this->v($v,'proposed_investment')}</strong></span></div>
<div class='tr'><span class='tl'>Target Equity Stake</span><span class='tv'>{$this->v($v,'equity_stake')}</span></div>
<div class='tr'><span class='tl'>Pre-Money Valuation</span><span class='tv'>{$this->v($v,'pre_money_valuation')}</span></div>

<h2>2. Use of Proceeds</h2>
<p>{$this->v($v,'use_of_proceeds')}</p>

<h2>3. Due Diligence</h2>
<p>This investment is subject to satisfactory completion of financial, legal, commercial and technical due diligence over approximately <strong>{$this->v($v,'dd_duration_days')} days</strong> from signing. The Company shall provide full access to all relevant information, documents, management and key personnel.</p>

<h2>4. Exclusivity <span style='font-size:9pt;font-weight:normal;color:#555'>(Legally Binding)</span></h2>
<div class='hl'>
  <p>From the date of signing, and for <strong>{$this->v($v,'exclusivity_days')} days</strong>, the Company and its shareholders shall not solicit, entertain, or enter into discussions with any third party regarding a sale, investment, or similar transaction without the Investor&rsquo;s prior written consent. <strong>This Section 4 is legally binding on both Parties.</strong></p>
</div>

<h2>5. Conditions Precedent</h2>
<p>{$this->v($v,'conditions','Satisfactory due diligence; no material adverse change; execution of definitive transaction documents; all required regulatory and corporate approvals.')}</p>

<h2>6. Confidentiality <span style='font-size:9pt;font-weight:normal;color:#555'>(Legally Binding)</span></h2>
<p>This LOI and all related information shall be treated as confidential and may not be disclosed to any third party without prior written consent. <strong>This Section 6 is legally binding.</strong></p>

<h2>7. Governing Law</h2>
<p>This LOI is governed by the laws of <strong>{$this->v($v,'governing_law')}</strong>.</p>

<p style='margin-top:20px'>We look forward to working with the Company toward a successful transaction. Please indicate acceptance by countersigning below.</p>

<div class='sig nb'>
  <div class='sc'>
    <p><strong>INVESTOR</strong></p>
    <p style='font-size:10pt;color:#555'>{$this->v($v,'investor_name')}</p>
    <div class='sl'></div>
    <p class='slb'>Signature</p>
    <p style='margin-top:8px'><strong>{$this->v($v,'investor_signatory')}</strong></p>
    <p class='slb'>{$this->v($v,'investor_title')}</p>
    <p class='slb'>Date: _______________________</p>
  </div>
  <div class='sc'>
    <p><strong>ACKNOWLEDGED BY THE COMPANY</strong></p>
    <p style='font-size:10pt;color:#555'>{$this->v($v,'target_company')}</p>
    <div class='sl'></div>
    <p class='slb'>Authorised Signatory &amp; Title</p>
    <p style='margin-top:8px'>&nbsp;</p>
    <p class='slb'>Date: _______________________</p>
  </div>
</div>
<p class='ft'>CONFIDENTIAL NON-BINDING LOI &mdash; Subject to due diligence and definitive documentation.</p>
</div></body></html>";
    }

    // ── 3. TERM SHEET ─────────────────────────────────────────────────────────
    private function generateTermSheet(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Term Sheet</title>{$css}</head><body><div class='page'>
<h1>INVESTMENT TERM SHEET</h1>
<p class='sub'>{$this->v($v,'company_name')} &mdash; {$this->v($v,'investor_name')}</p>
<p class='dt'>Dated: {$this->v($v,'effective_date')}</p>
<div class='hl'><p><strong>Note:</strong> This Term Sheet summarises the principal terms of a proposed investment and is non-binding except where stated. It is subject to due diligence and definitive documentation.</p></div>

<h2>A. Transaction</h2>
<div class='tr'><span class='tl'>Company</span><span class='tv'>{$this->v($v,'company_name')}</span></div>
<div class='tr'><span class='tl'>Investor</span><span class='tv'>{$this->v($v,'investor_name')}</span></div>
<div class='tr'><span class='tl'>Investment Amount</span><span class='tv'><strong>{$this->v($v,'investment_amount')}</strong></span></div>
<div class='tr'><span class='tl'>Pre-Money Valuation</span><span class='tv'>{$this->v($v,'pre_money_valuation')}</span></div>
<div class='tr'><span class='tl'>Post-Money Equity %</span><span class='tv'>{$this->v($v,'equity_percentage')}</span></div>

<h2>B. Securities</h2>
<div class='tr'><span class='tl'>Share Class</span><span class='tv'>{$this->v($v,'share_class')}</span></div>
<div class='tr'><span class='tl'>Liquidation Preference</span><span class='tv'>{$this->v($v,'liquidation_preference')}</span></div>
<div class='tr'><span class='tl'>Anti-Dilution Protection</span><span class='tv'>{$this->v($v,'anti_dilution')}</span></div>
<div class='tr'><span class='tl'>Dividend Policy</span><span class='tv'>{$this->v($v,'dividend_policy')}</span></div>

<h2>C. Governance</h2>
<div class='tr'><span class='tl'>Total Board Size</span><span class='tv'>{$this->v($v,'board_total')} members</span></div>
<div class='tr'><span class='tl'>Investor Board Seats</span><span class='tv'>{$this->v($v,'board_seats')}</span></div>
<div class='tr'><span class='tl'>Founder Vesting</span><span class='tv'>{$this->v($v,'vesting_founders','Not specified')}</span></div>

<h2>D. Investor Protections</h2>
<div class='tr'><span class='tl'>Tag-Along Rights</span><span class='tv'>{$this->v($v,'tag_along')}</span></div>
<div class='tr'><span class='tl'>Drag-Along Rights</span><span class='tv'>{$this->v($v,'drag_along')}</span></div>
<div class='tr'><span class='tl'>Right of First Refusal</span><span class='tv'>{$this->v($v,'rofr')}</span></div>
<div class='tr'><span class='tl'>Pro-Rata Rights</span><span class='tv'>{$this->v($v,'pro_rata')}</span></div>

<h2>E. Exit</h2>
<div class='tr'><span class='tl'>Target Exit Horizon</span><span class='tv'>{$this->v($v,'exit_horizon','To be agreed')}</span></div>

<h2>F. Legal</h2>
<div class='tr'><span class='tl'>Governing Law</span><span class='tv'>{$this->v($v,'governing_law')}</span></div>

<div class='sig nb'>
  <div class='sc'>
    <p><strong>INVESTOR</strong><br><span style='font-size:10pt;color:#555'>{$this->v($v,'investor_name')}</span></p>
    <div class='sl'></div>
    <p class='slb'>Authorised Signatory &nbsp;&nbsp; Date: _______________</p>
  </div>
  <div class='sc'>
    <p><strong>COMPANY</strong><br><span style='font-size:10pt;color:#555'>{$this->v($v,'company_name')}</span></p>
    <div class='sl'></div>
    <p class='slb'>Authorised Signatory &nbsp;&nbsp; Date: _______________</p>
  </div>
</div>
<p class='ft'>NON-BINDING TERM SHEET &mdash; For discussion purposes only. Subject to due diligence and definitive documentation.</p>
</div></body></html>";
    }

    // ── 4. SHAREHOLDERS' AGREEMENT ────────────────────────────────────────────
    private function generateSHA(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>SHA</title>{$css}</head><body><div class='page'>
<h1>SHAREHOLDERS&rsquo; AGREEMENT</h1>
<p class='sub'>{$this->v($v,'company_name')}</p>
<p class='dt'>Dated: {$this->v($v,'effective_date')}</p>

<p>This Shareholders&rsquo; Agreement (<strong>&ldquo;Agreement&rdquo;</strong>) is entered into as of {$this->v($v,'effective_date')} between:</p>
<div class='box'>
  <p><strong>(1)</strong> {$this->v($v,'company_name')}, registered under number {$this->v($v,'company_registration')} (the <strong>&ldquo;Company&rdquo;</strong>);</p>
  <p><strong>(2)</strong> {$this->v($v,'investor_name')} (the <strong>&ldquo;Investor&rdquo;</strong>); and</p>
  <p><strong>(3)</strong> {$this->v($v,'founder_names')} (the <strong>&ldquo;Founders&rdquo;</strong>).</p>
</div>

<h2>1. Share Capital &amp; Ownership</h2>
<p>Total issued share capital: {$this->v($v,'total_shares')} shares at {$this->v($v,'share_price')} per share. The Investor holds {$this->v($v,'investor_equity')} of issued share capital.</p>

<h2>2. Board of Directors</h2>
<p>Board composition: {$this->v($v,'board_composition')}. Decisions by simple majority unless a Reserved Matter requires a higher threshold. Quorum requires at least one Investor-appointed director.</p>

<h2>3. Reserved Matters</h2>
<p>The following require the Investor&rsquo;s prior written consent: {$this->v($v,'reserved_matters','(a) new share issuances; (b) asset acquisitions or disposals above an agreed threshold; (c) material change of business; (d) appointment or removal of CEO; (e) related-party transactions; (f) incurring material indebtedness.')}</p>

<h2>4. Dividend Policy</h2>
<p>{$this->v($v,'dividend_policy','No dividends shall be declared without the prior written approval of the Investor.')}</p>

<h2>5. Transfer Restrictions</h2>
<h3>5.1 Right of First Refusal (ROFR)</h3>
<p>No Shareholder may transfer shares to a third party without first offering them pro-rata to the other Shareholders at the same price and terms. Such offer must remain open for <strong>{$this->v($v,'rofr_period_days')} days</strong>.</p>
<h3>5.2 Tag-Along</h3>
<p>If any Shareholder proposes to transfer shares representing {$this->v($v,'tag_along_threshold','50% or more')} of issued share capital, the Investor has the right to participate pro-rata on the same terms.</p>
<h3>5.3 Drag-Along</h3>
<p>If Shareholders holding {$this->v($v,'drag_along_threshold','75% or more')} approve a sale of the Company, all Shareholders are obliged to transfer their shares on the same terms.</p>

<h2>6. Deadlock Resolution</h2>
<p>In the event of a deadlock on any Reserved Matter, the Parties shall attempt good-faith resolution for 30 days. If unresolved, the matter shall be referred to: <strong>{$this->v($v,'deadlock_mechanism')}</strong>.</p>

<h2>7. Non-Compete &amp; Non-Solicitation</h2>
<p>For {$this->v($v,'non_compete_years','2')} years following cessation of involvement, each Founder shall not: (a) engage in any competing business; (b) solicit or hire any Company employee; or (c) solicit any Company customer.</p>

<h2>8. Representations &amp; Warranties</h2>
<p>Each Party warrants that it has full legal capacity and authority to enter into this Agreement, that this Agreement constitutes its valid and binding obligation, and that no additional consents are required.</p>

<h2>9. Governing Law &amp; Dispute Resolution</h2>
<p>This Agreement is governed by the laws of <strong>{$this->v($v,'governing_law')}</strong>. Disputes shall be resolved by <strong>{$this->v($v,'dispute_resolution')}</strong>.</p>

<div class='sig nb'>
  <div class='sc'>
    <p><strong>INVESTOR</strong><br><span style='font-size:10pt;color:#555'>{$this->v($v,'investor_name')}</span></p>
    <div class='sl'></div><p class='slb'>Authorised Signatory &nbsp; Date: ________________</p>
  </div>
  <div class='sc'>
    <p><strong>COMPANY</strong><br><span style='font-size:10pt;color:#555'>{$this->v($v,'company_name')}</span></p>
    <div class='sl'></div><p class='slb'>Authorised Signatory &nbsp; Date: ________________</p>
  </div>
</div>
<div class='sig nb' style='margin-top:20px'>
  <div class='sc'>
    <p><strong>FOUNDERS</strong></p>
    <div class='sl'></div><p class='slb'>Founder Signature(s) &nbsp; Date: ________________</p>
  </div>
</div>
<p class='ft'>STRICTLY CONFIDENTIAL &mdash; {$this->v($v,'company_name')} Shareholders&rsquo; Agreement &mdash; {$this->v($v,'effective_date')}</p>
</div></body></html>";
    }

    // ── 5. OFFERING PROPOSAL ──────────────────────────────────────────────────
    private function generateOfferingProposal(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Offering Proposal</title>{$css}</head><body><div class='page'>
<div style='text-align:center;font-size:36pt;margin-bottom:20px'>📊</div>
<h1>INVESTMENT OFFERING PROPOSAL</h1>
<p class='sub'>{$this->v($v,'company_name')}</p>
<p class='dt'>Prepared by {$this->v($v,'presenter_firm')} &mdash; {$this->v($v,'document_date')}</p>
<div class='hl'><strong>Confidential:</strong> {$this->v($v,'confidentiality_note','This document is strictly confidential and intended solely for the named recipient. It may not be reproduced or distributed without prior written consent.')}</div>

<h2>Executive Summary</h2>
<table class='kv'>
  <tr><td>Opportunity</td><td>{$this->v($v,'company_name')}</td></tr>
  <tr><td>Sector</td><td>{$this->v($v,'sector')}</td></tr>
  <tr><td>Geography</td><td>{$this->v($v,'geography')}</td></tr>
  <tr><td>Investment Ask</td><td><strong>{$this->v($v,'investment_ask')}</strong></td></tr>
  <tr><td>Pre-Money Valuation</td><td>{$this->v($v,'valuation')}</td></tr>
  <tr><td>Revenue (TTM)</td><td>{$this->v($v,'revenue_ttm','Not disclosed')}</td></tr>
  <tr><td>EBITDA (TTM)</td><td>{$this->v($v,'ebitda_ttm','Not disclosed')}</td></tr>
  <tr><td>Prepared By</td><td>{$this->v($v,'presenter_firm')}</td></tr>
</table>

<h2>Investment Highlights</h2>
<p>{$this->v($v,'investment_highlights')}</p>

<h2>Market Opportunity</h2>
<p>Total Addressable Market: <strong>{$this->v($v,'market_size','To be provided')}</strong></p>

<h2>Competitive Advantage &amp; Moat</h2>
<p>{$this->v($v,'competitive_advantage','To be provided.')}</p>

<h2>Use of Funds</h2>
<p>{$this->v($v,'use_of_funds')}</p>

<h2>Exit Strategy</h2>
<p>{$this->v($v,'exit_strategy','To be discussed with the investor.')}</p>

<h2>Key Risk Factors</h2>
<p>{$this->v($v,'risk_factors','To be provided.')}</p>

<h2>Contact &amp; Next Steps</h2>
<table class='kv'>
  <tr><td>Contact Person</td><td>{$this->v($v,'contact_person')}</td></tr>
  <tr><td>Email</td><td>{$this->v($v,'contact_email')}</td></tr>
  <tr><td>Presenting Firm</td><td>{$this->v($v,'presenter_firm')}</td></tr>
</table>
<p class='ft'>CONFIDENTIAL INVESTMENT PROPOSAL &mdash; {$this->v($v,'company_name')} &mdash; {$this->v($v,'presenter_firm')} &mdash; {$this->v($v,'document_date')}<br>This document does not constitute an offer to sell or solicitation of an offer to buy any security. Investments involve risks.</p>
</div></body></html>";
    }

    // ── 6. SUBSCRIPTION AGREEMENT ─────────────────────────────────────────────
    private function generateSubscriptionAgreement(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Subscription Agreement</title>{$css}</head><body><div class='page'>
<h1>SUBSCRIPTION AGREEMENT</h1>
<p class='sub'>{$this->v($v,'company_name')}</p>
<p class='dt'>Dated: {$this->v($v,'effective_date')}</p>

<p>This Subscription Agreement (<strong>&ldquo;Agreement&rdquo;</strong>) is entered into as of {$this->v($v,'effective_date')} between:</p>
<div class='box'>
  <p><strong>The Company:</strong> {$this->v($v,'company_name')}, registered at {$this->v($v,'company_address')}; and</p>
  <p><strong>The Investor:</strong> {$this->v($v,'investor_name')}, registered at {$this->v($v,'investor_address')}.</p>
</div>

<h2>1. Subscription</h2>
<p>The Company agrees to issue, and the Investor agrees to subscribe for, <strong>{$this->v($v,'shares_subscribed')} {$this->v($v,'share_class')}</strong> at <strong>{$this->v($v,'price_per_share')}</strong> per share, for a total consideration of <strong>{$this->v($v,'total_subscription')}</strong> (the <strong>&ldquo;Subscription Price&rdquo;</strong>).</p>

<h2>2. Payment</h2>
<p>The Investor shall pay the Subscription Price in <strong>{$this->v($v,'currency')}</strong> by wire transfer within <strong>{$this->v($v,'payment_deadline_days')} business days</strong> of the Closing Date to:</p>
<div class='box'><p>{$this->v($v,'payment_account')}</p></div>

<h2>3. Conditions to Closing</h2>
<p>{$this->v($v,'closing_conditions','(a) Board resolution approving issuance; (b) completion of required regulatory filings; (c) updated cap table reflecting new shareholding structure.')}</p>

<h2>4. Representations &amp; Warranties</h2>
<p><strong>Company warrants:</strong> it is duly incorporated and validly existing; Subscription Shares are validly issued, fully paid and free of encumbrances; no undisclosed liabilities or pending litigation; financial information provided is accurate. {$this->v($v,'rep_warranties','')}</p>
<p><strong>Investor warrants:</strong> it has full authority to enter this Agreement; subscription is for investment purposes only; it has conducted its own independent analysis.</p>

<h2>5. Allotment &amp; Registration</h2>
<p>Following receipt of the Subscription Price, the Company shall promptly: (a) allot and issue the Subscription Shares; (b) register the Investor in the shareholders&rsquo; register; (c) deliver share certificates (if applicable) within 10 business days of Closing.</p>

<h2>6. Governing Law</h2>
<p>This Agreement is governed by the laws of <strong>{$this->v($v,'governing_law')}</strong>.</p>

<div class='sig nb'>
  <div class='sc'>
    <p><strong>THE COMPANY</strong><br><span style='font-size:10pt;color:#555'>{$this->v($v,'company_name')}</span></p>
    <div class='sl'></div>
    <p class='slb'>Signature</p>
    <p style='margin-top:8px'><strong>{$this->v($v,'company_signatory')}</strong></p>
    <p class='slb'>{$this->v($v,'company_signatory_title')}</p>
    <p class='slb'>Date: _______________________</p>
  </div>
  <div class='sc'>
    <p><strong>THE INVESTOR</strong><br><span style='font-size:10pt;color:#555'>{$this->v($v,'investor_name')}</span></p>
    <div class='sl'></div>
    <p class='slb'>Signature</p>
    <p style='margin-top:8px'><strong>{$this->v($v,'investor_signatory')}</strong></p>
    <p class='slb'>{$this->v($v,'investor_signatory_title')}</p>
    <p class='slb'>Date: _______________________</p>
  </div>
</div>
<p class='ft'>STRICTLY CONFIDENTIAL &mdash; Subscription Agreement &mdash; {$this->v($v,'company_name')} / {$this->v($v,'investor_name')} &mdash; {$this->v($v,'effective_date')}</p>
</div></body></html>";
    }

    // ══════════════════════════════════════════════════════════════════════════
    // NEW TEMPLATES — Pre-LOI / Early Stage
    // ══════════════════════════════════════════════════════════════════════════

    // ── 7. TEASER / ONE-PAGER ─────────────────────────────────────────────────
    private function generateTeaser(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Teaser</title>{$css}</head><body><div class='page'>
<div style='text-align:center;font-size:40pt;margin-bottom:10px'>📄</div>
<h1>INVESTMENT TEASER</h1>
<p class='sub'>{$this->v($v,'opportunity_codename')}</p>
<p class='dt'>Prepared by: {$this->v($v,'contact_person')} &mdash; {$this->v($v,'document_date')}</p>
<div class='hl'><strong>Confidential:</strong> {$this->v($v,'confidentiality_note','This document is strictly confidential and intended solely for the named recipient.')}</div>

<h2>Opportunity Overview</h2>
<table class='kv'>
  <tr><td>Code Name</td><td><strong>{$this->v($v,'opportunity_codename')}</strong></td></tr>
  <tr><td>Sector</td><td>{$this->v($v,'sector')}</td></tr>
  <tr><td>Geography</td><td>{$this->v($v,'geography')}</td></tr>
  <tr><td>Investment Ask</td><td><strong>{$this->v($v,'investment_ask')}</strong></td></tr>
  <tr><td>Revenue (TTM)</td><td>{$this->v($v,'revenue_ttm','Not disclosed at this stage')}</td></tr>
  <tr><td>EBITDA (TTM)</td><td>{$this->v($v,'ebitda_ttm','Not disclosed at this stage')}</td></tr>
  <tr><td>Revenue Growth</td><td>{$this->v($v,'growth_cagr','Not disclosed at this stage')}</td></tr>
</table>

<h2>Business Description</h2>
<p>{$this->v($v,'business_description')}</p>

<h2>Key Investment Highlights</h2>
<p>{$this->v($v,'key_highlights')}</p>

<h2>Exit Outlook</h2>
<p>{$this->v($v,'exit_outlook','To be discussed with interested parties post-NDA.')}</p>

<h2>Contact &amp; Next Steps</h2>
<p>Interested parties should execute a Non-Disclosure Agreement (NDA) to receive the full Information Memorandum.</p>
<table class='kv'>
  <tr><td>Contact</td><td>{$this->v($v,'contact_person')}</td></tr>
  <tr><td>Email</td><td>{$this->v($v,'contact_email')}</td></tr>
  <tr><td>Phone / WhatsApp</td><td>{$this->v($v,'contact_phone','—')}</td></tr>
</table>
<p class='ft'>CONFIDENTIAL TEASER &mdash; {$this->v($v,'opportunity_codename')} &mdash; {$this->v($v,'document_date')}<br>This document does not constitute an offer to sell or a solicitation of an offer to buy any security.</p>
</div></body></html>";
    }

    // ── 8. INFORMATION MEMORANDUM ─────────────────────────────────────────────
    private function generateIM(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Information Memorandum</title>{$css}</head><body><div class='page'>
<div style='text-align:center;font-size:36pt;margin-bottom:10px'>📘</div>
<h1>INFORMATION MEMORANDUM</h1>
<p class='sub'>{$this->v($v,'company_name')}</p>
<p class='dt'>Prepared by {$this->v($v,'prepared_by')} &mdash; {$this->v($v,'document_date')}</p>
<div class='hl'><strong>Confidential &mdash; Post-NDA:</strong> {$this->v($v,'confidentiality_note','Strictly confidential. Prepared solely for the named recipient post-NDA. Not for distribution.')}</div>

<h2>1. Company Overview</h2>
<table class='kv'>
  <tr><td>Legal Name</td><td>{$this->v($v,'company_name')}</td></tr>
  <tr><td>Registered Address</td><td>{$this->v($v,'company_address')}</td></tr>
  <tr><td>Founded</td><td>{$this->v($v,'founded_year')}</td></tr>
  <tr><td>Legal Structure</td><td>{$this->v($v,'legal_structure')}</td></tr>
  <tr><td>Sector</td><td>{$this->v($v,'sector')}</td></tr>
  <tr><td>Geography</td><td>{$this->v($v,'geography')}</td></tr>
</table>
<p>{$this->v($v,'business_overview')}</p>

<h2>2. Products &amp; Services</h2>
<p>{$this->v($v,'products_services')}</p>

<h2>3. Revenue Model</h2>
<p>{$this->v($v,'revenue_model')}</p>

<h2>4. Customer Base</h2>
<p>{$this->v($v,'customer_base','To be provided.')}</p>

<h2>5. Management Team</h2>
<p>{$this->v($v,'management_team')}</p>

<h2>6. Financial Highlights</h2>
<p>{$this->v($v,'financials_summary')}</p>

<h2>7. Growth Strategy</h2>
<p>{$this->v($v,'growth_strategy','To be provided.')}</p>

<h2>8. Competitive Landscape</h2>
<p>{$this->v($v,'competitive_landscape','To be provided.')}</p>

<h2>9. Investment Opportunity</h2>
<table class='kv'>
  <tr><td>Investment Ask</td><td><strong>{$this->v($v,'investment_ask')}</strong></td></tr>
  <tr><td>Use of Proceeds</td><td>{$this->v($v,'use_of_proceeds')}</td></tr>
  <tr><td>Exit Strategy</td><td>{$this->v($v,'exit_strategy','To be discussed.')}</td></tr>
</table>

<h2>10. Key Risks &amp; Mitigants</h2>
<p>{$this->v($v,'key_risks','To be provided.')}</p>

<h2>Contact</h2>
<table class='kv'>
  <tr><td>Contact Person</td><td>{$this->v($v,'contact_person')}</td></tr>
  <tr><td>Email</td><td>{$this->v($v,'contact_email')}</td></tr>
  <tr><td>Prepared By</td><td>{$this->v($v,'prepared_by')}</td></tr>
</table>
<p class='ft'>STRICTLY CONFIDENTIAL &mdash; Information Memorandum &mdash; {$this->v($v,'company_name')} &mdash; {$this->v($v,'document_date')}</p>
</div></body></html>";
    }

    // ── 9. MANAGEMENT PRESENTATION ────────────────────────────────────────────
    private function generateMgmtPresentation(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Management Presentation</title>{$css}</head><body><div class='page'>
<div style='text-align:center;font-size:36pt;margin-bottom:10px'>🎤</div>
<h1>MANAGEMENT PRESENTATION</h1>
<p class='sub'>{$this->v($v,'company_name')}</p>
<p class='sub' style='font-size:10pt'>{$this->v($v,'company_tagline')}</p>
<p class='dt'>Presented to: {$this->v($v,'investor_audience')} &mdash; {$this->v($v,'presentation_date')}</p>
<div class='hl'>{$this->v($v,'confidentiality_note','Confidential. Not for distribution without prior written consent.')}</div>

<h2>Presenting Team</h2>
<p>{$this->v($v,'presenter_names')}</p>

<h2>1. The Problem</h2>
<p>{$this->v($v,'problem_statement')}</p>

<h2>2. Our Solution</h2>
<p>{$this->v($v,'solution')}</p>

<h2>3. Market Opportunity</h2>
<p><strong>Total Addressable Market:</strong> {$this->v($v,'market_size','To be provided.')}</p>

<h2>4. Business Model</h2>
<p>{$this->v($v,'business_model')}</p>

<h2>5. Traction &amp; Milestones</h2>
<p>{$this->v($v,'traction')}</p>

<h2>6. Competitive Advantage</h2>
<p>{$this->v($v,'competitive_advantage','To be provided.')}</p>

<h2>7. Financial Snapshot</h2>
<p>{$this->v($v,'financial_snapshot')}</p>

<h2>8. The Ask</h2>
<p>{$this->v($v,'investment_ask')}</p>

<h2>9. Exit Strategy</h2>
<p>{$this->v($v,'exit_strategy','To be discussed.')}</p>

<h2>Contact</h2>
<p>{$this->v($v,'contact_email')}</p>
<p class='ft'>CONFIDENTIAL &mdash; Management Presentation &mdash; {$this->v($v,'company_name')} &mdash; {$this->v($v,'presentation_date')}</p>
</div></body></html>";
    }

    // ══════════════════════════════════════════════════════════════════════════
    // NEW TEMPLATES — Due Diligence
    // ══════════════════════════════════════════════════════════════════════════

    // ── 10. DD REQUEST LIST ───────────────────────────────────────────────────
    private function generateDDRequestList(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Due Diligence Request List</title>{$css}</head><body><div class='page'>
<div style='text-align:center;font-size:36pt;margin-bottom:10px'>🗂️</div>
<h1>DUE DILIGENCE REQUEST LIST</h1>
<p class='sub'>{$this->v($v,'target_company')}</p>
<p class='dt'>Issued by {$this->v($v,'investor_name')} &mdash; {$this->v($v,'document_date')}</p>

<h2>Coordination</h2>
<table class='kv'>
  <tr><td>Investor DD Coordinator</td><td>{$this->v($v,'dd_coordinator_investor')}</td></tr>
  <tr><td>Target DD Coordinator</td><td>{$this->v($v,'dd_coordinator_target')}</td></tr>
  <tr><td>Submission Deadline</td><td><strong>{$this->v($v,'submission_deadline')}</strong></td></tr>
  <tr><td>Data Room Platform</td><td>{$this->v($v,'data_room_platform','To be confirmed')}</td></tr>
  <tr><td>DD Scope</td><td>{$this->v($v,'dd_scope')}</td></tr>
  <tr><td>Financial Periods</td><td>{$this->v($v,'financial_years')}</td></tr>
</table>

<h2>A. Corporate &amp; Legal</h2>
<ol>
  <li>Certificate of Incorporation and all amendments</li>
  <li>Memorandum &amp; Articles of Association (current version)</li>
  <li>Commercial Register extract (current)</li>
  <li>Full capitalization table (cap table) — all share classes, options, warrants</li>
  <li>Board and shareholder meeting minutes (last 3 years)</li>
  <li>All existing shareholder agreements, investment agreements, side letters</li>
  <li>List of all subsidiaries, affiliates, joint ventures</li>
  <li>All material contracts &mdash; customer, supplier, distribution, licensing</li>
  <li>IP ownership documentation (patents, trademarks, copyrights, domain names)</li>
  <li>Any litigation, disputes, or regulatory investigations (pending or threatened)</li>
  <li>All regulatory licenses and permits</li>
</ol>

<h2>B. Financial</h2>
<ol>
  <li>Audited financial statements for {$this->v($v,'financial_years')}</li>
  <li>Management accounts (monthly) for the last 12 months</li>
  <li>Current year budget vs. actuals (YTD)</li>
  <li>3–5 year financial projections with assumptions</li>
  <li>Accounts receivable aging schedule</li>
  <li>Accounts payable aging schedule</li>
  <li>Debt schedule — all outstanding loans, facilities, covenants</li>
  <li>Off-balance sheet items and contingent liabilities</li>
  <li>Tax returns and tax correspondence (last 3 years)</li>
  <li>Bank statements (last 12 months)</li>
  <li>List of all related-party transactions</li>
</ol>

<h2>C. Commercial &amp; Operational</h2>
<ol>
  <li>Customer list (top 20 by revenue) with revenue contribution %</li>
  <li>Sales pipeline and CRM data</li>
  <li>Churn / retention analysis</li>
  <li>Pricing structure and discount policy</li>
  <li>Supplier list (top 10) and supply agreements</li>
  <li>Organizational chart and headcount by department</li>
  <li>Key employee contracts and compensation packages</li>
  <li>Employee benefit plans, bonus schemes, ESOP</li>
</ol>

<h2>D. Additional / Custom Requests</h2>
<p>{$this->v($v,'additional_requests','None at this stage.')}</p>

<p class='ft'>Due Diligence Request List &mdash; {$this->v($v,'target_company')} &mdash; {$this->v($v,'investor_name')} &mdash; {$this->v($v,'document_date')}</p>
</div></body></html>";
    }

    // ── 11. DD SUMMARY REPORT ─────────────────────────────────────────────────
    private function generateDDSummaryReport(array $v, string $css): string
    {
        $recColor = match($v['overall_recommendation'] ?? '') {
            'Proceed — No Material Issues'       => '#16a34a',
            'Proceed — Subject to Conditions'    => '#ca8a04',
            'Requires Further Investigation'     => '#ea580c',
            'Do Not Proceed'                     => '#dc2626',
            default => '#6b7280',
        };
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>DD Summary Report</title>{$css}</head><body><div class='page'>
<div style='text-align:center;font-size:36pt;margin-bottom:10px'>🔎</div>
<h1>DUE DILIGENCE SUMMARY REPORT</h1>
<p class='sub'>{$this->v($v,'target_company')}</p>
<p class='dt'>Prepared by {$this->v($v,'prepared_by')} &mdash; DD Period: {$this->v($v,'dd_period')} &mdash; Report Date: {$this->v($v,'document_date')}</p>

<div style='text-align:center;margin:20px 0;padding:14px;border:2px solid {$recColor};border-radius:6px;background:{$recColor}18'>
  <p style='font-size:13pt;font-weight:bold;color:{$recColor};margin:0'>IC RECOMMENDATION: {$this->v($v,'overall_recommendation')}</p>
</div>

<h2>Executive Summary</h2>
<p>{$this->v($v,'executive_summary')}</p>

<h2>Financial DD Findings</h2>
<p>{$this->v($v,'financial_findings')}</p>

<h2>Legal DD Findings</h2>
<p>{$this->v($v,'legal_findings')}</p>

<h2>Commercial DD Findings</h2>
<p>{$this->v($v,'commercial_findings','Not in scope for this engagement.')}</p>

<h2>Red Flags &amp; Deal-Breakers</h2>
<p>{$this->v($v,'red_flags','No material red flags identified.')}</p>

<h2>Conditions to Close</h2>
<p>{$this->v($v,'conditions_to_close','None identified at this stage.')}</p>

<h2>Valuation Adjustments</h2>
<p>{$this->v($v,'valuation_impact','No adjustments required.')}</p>

<p class='ft'>STRICTLY CONFIDENTIAL &mdash; DD Summary Report &mdash; {$this->v($v,'target_company')} &mdash; {$this->v($v,'document_date')}</p>
</div></body></html>";
    }

    // ── 12. DATA ROOM ACCESS LETTER ───────────────────────────────────────────
    private function generateDataRoomLetter(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Data Room Access Letter</title>{$css}</head><body><div class='page'>
<h1>DATA ROOM ACCESS LETTER</h1>
<p class='dt'>Date: {$this->v($v,'letter_date')}</p>
<p><strong>To:</strong> {$this->v($v,'recipient_firm')}</p>
<p><strong>From:</strong> {$this->v($v,'issuing_company')} &mdash; {$this->v($v,'issuing_signatory')}</p>
<p><strong>Re:</strong> Controlled Data Room Access — {$this->v($v,'issuing_company')}</p>

<p>Dear Sir / Madam,</p>
<p>With reference to the confidentiality agreement dated {$this->v($v,'nda_reference')}, we hereby grant the following authorized representatives of <strong>{$this->v($v,'recipient_firm')}</strong> controlled access to our virtual data room for the purposes of due diligence evaluation.</p>

<h2>Authorized Users</h2>
<div class='box'><p>{$this->v($v,'authorized_users')}</p></div>

<h2>Access Details</h2>
<table class='kv'>
  <tr><td>Platform &amp; Link</td><td>{$this->v($v,'data_room_platform')}</td></tr>
  <tr><td>Access Level</td><td><strong>{$this->v($v,'access_level')}</strong></td></tr>
  <tr><td>Access Start Date</td><td>{$this->v($v,'access_start_date')}</td></tr>
  <tr><td>Access Expiry Date</td><td><strong>{$this->v($v,'access_expiry_date')}</strong></td></tr>
</table>

<h2>Usage Restrictions</h2>
<p>{$this->v($v,'restrictions','All information accessed through the data room is strictly confidential. Documents may not be printed, forwarded, shared, or reproduced without prior written consent from the Company.')}</p>

<h2>Contact for Queries</h2>
<p>For any questions regarding the data room or documents therein, please contact: <strong>{$this->v($v,'contact_for_queries')}</strong></p>

<div class='sig nb'>
  <div class='sc'>
    <p><strong>{$this->v($v,'issuing_company')}</strong></p>
    <div class='sl'></div>
    <p class='slb'>{$this->v($v,'issuing_signatory')}</p>
    <p class='slb'>Date: {$this->v($v,'letter_date')}</p>
  </div>
</div>
<p class='ft'>Data Room Access Letter &mdash; {$this->v($v,'issuing_company')} &mdash; {$this->v($v,'letter_date')}</p>
</div></body></html>";
    }

    // ══════════════════════════════════════════════════════════════════════════
    // NEW TEMPLATES — Valuation & Negotiation
    // ══════════════════════════════════════════════════════════════════════════

    // ── 13. VALUATION SUMMARY ─────────────────────────────────────────────────
    private function generateValuationSummary(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Valuation Summary</title>{$css}</head><body><div class='page'>
<div style='text-align:center;font-size:36pt;margin-bottom:10px'>💹</div>
<h1>VALUATION SUMMARY</h1>
<p class='sub'>{$this->v($v,'target_company')}</p>
<p class='dt'>Prepared by {$this->v($v,'prepared_by')} &mdash; Valuation Date: {$this->v($v,'valuation_date')}</p>

<h2>1. EV/EBITDA Multiple Analysis</h2>
<table class='kv'>
  <tr><td>LTM EBITDA</td><td><strong>{$this->v($v,'ltm_ebitda')}</strong></td></tr>
  <tr><td>Multiple Range</td><td>{$this->v($v,'ebitda_multiple_low')} &mdash; {$this->v($v,'ebitda_multiple_high')}</td></tr>
  <tr><td>Implied EV (Low)</td><td>{$this->v($v,'ebitda_implied_low','—')}</td></tr>
  <tr><td>Implied EV (High)</td><td>{$this->v($v,'ebitda_implied_high','—')}</td></tr>
</table>

<h2>2. DCF Analysis</h2>
<table class='kv'>
  <tr><td>WACC</td><td>{$this->v($v,'dcf_wacc','—')}</td></tr>
  <tr><td>Terminal Growth Rate</td><td>{$this->v($v,'dcf_terminal_growth','—')}</td></tr>
  <tr><td>DCF Enterprise Value</td><td>{$this->v($v,'dcf_enterprise_value','—')}</td></tr>
</table>

<h2>3. Comparable Transactions</h2>
<p>{$this->v($v,'comparable_transactions','Not available.')}</p>

<h2>4. Concluded Valuation</h2>
<table class='kv'>
  <tr><td>Net Debt / (Cash)</td><td>{$this->v($v,'net_debt','—')}</td></tr>
  <tr><td>Concluded Enterprise Value</td><td><strong>{$this->v($v,'concluded_ev')}</strong></td></tr>
  <tr><td>Concluded Equity Value</td><td><strong>{$this->v($v,'concluded_equity_value')}</strong></td></tr>
  <tr><td>Proposed Stake</td><td>{$this->v($v,'proposed_stake')}</td></tr>
  <tr><td>Investment Amount</td><td><strong>{$this->v($v,'investment_amount')}</strong></td></tr>
</table>

<h2>5. Key Assumptions</h2>
<p>{$this->v($v,'key_assumptions','To be provided.')}</p>

<h2>6. Sensitivity &amp; Scenarios</h2>
<p>{$this->v($v,'sensitivity_note','Bear / Base / Bull scenarios to be provided separately.')}</p>

<p class='ft'>STRICTLY CONFIDENTIAL &mdash; Valuation Summary &mdash; {$this->v($v,'target_company')} &mdash; {$this->v($v,'valuation_date')}</p>
</div></body></html>";
    }

    // ── 14. INDICATIVE OFFER LETTER ───────────────────────────────────────────
    private function generateIndicativeOffer(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Indicative Offer Letter</title>{$css}</head><body><div class='page'>
<h1>INDICATIVE OFFER LETTER</h1>
<p class='dt'>Date: {$this->v($v,'letter_date')}</p>
<div class='box'>
  <p><strong>From:</strong> {$this->v($v,'investor_name')}, {$this->v($v,'investor_address')}</p>
  <p><strong>To:</strong> {$this->v($v,'recipient_name')}, {$this->v($v,'target_company')}</p>
</div>

<p>Dear {$this->v($v,'recipient_name')},</p>
<p>We are pleased to submit this indicative non-binding offer for a proposed investment in <strong>{$this->v($v,'target_company')}</strong>. This letter reflects our current assessment based on information received to date and is subject to satisfactory completion of due diligence.</p>

<h2>Indicative Terms</h2>
<table class='kv'>
  <tr><td>Indicative Enterprise Value</td><td><strong>{$this->v($v,'indicative_valuation')}</strong></td></tr>
  <tr><td>Indicative Stake</td><td>{$this->v($v,'indicative_stake')}</td></tr>
  <tr><td>Indicative Investment Amount</td><td><strong>{$this->v($v,'indicative_investment')}</strong></td></tr>
  <tr><td>Proposed Structure</td><td>{$this->v($v,'transaction_structure')}</td></tr>
</table>

<h2>Basis of Offer</h2>
<p>{$this->v($v,'basis_of_offer')}</p>

<h2>Key Conditions</h2>
<p>{$this->v($v,'key_conditions')}</p>

<h2>Proposed Next Steps</h2>
<p>{$this->v($v,'next_steps','To be agreed upon mutual acceptance of this letter.')}</p>

<p>This offer remains valid for <strong>{$this->v($v,'offer_validity_days')} calendar days</strong> from the date hereof. It is non-binding and does not constitute a commitment to invest.</p>

<div class='sig nb'>
  <div class='sc'>
    <p><strong>{$this->v($v,'investor_name')}</strong></p>
    <div class='sl'></div>
    <p class='slb'>{$this->v($v,'investor_signatory')}</p>
    <p class='slb'>{$this->v($v,'investor_title')}</p>
    <p class='slb'>Date: {$this->v($v,'letter_date')}</p>
  </div>
</div>
<p class='ft'>Non-Binding Indicative Offer &mdash; {$this->v($v,'target_company')} &mdash; {$this->v($v,'letter_date')}</p>
</div></body></html>";
    }

    // ══════════════════════════════════════════════════════════════════════════
    // NEW TEMPLATES — Closing
    // ══════════════════════════════════════════════════════════════════════════

    // ── 15. BOARD RESOLUTION ──────────────────────────────────────────────────
    private function generateBoardResolution(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Board Resolution</title>{$css}</head><body><div class='page'>
<h1>BOARD RESOLUTION</h1>
<p class='sub'>{$this->v($v,'company_name')}</p>
<p class='dt'>{$this->v($v,'meeting_type')} &mdash; {$this->v($v,'meeting_date')}</p>

<h2>Meeting Details</h2>
<table class='kv'>
  <tr><td>Company</td><td>{$this->v($v,'company_name')}</td></tr>
  <tr><td>Registration No.</td><td>{$this->v($v,'company_registration','—')}</td></tr>
  <tr><td>Meeting Type</td><td>{$this->v($v,'meeting_type')}</td></tr>
  <tr><td>Date</td><td>{$this->v($v,'meeting_date')}</td></tr>
  <tr><td>Location</td><td>{$this->v($v,'meeting_location')}</td></tr>
  <tr><td>Governing Law</td><td>{$this->v($v,'governing_law')}</td></tr>
</table>

<h2>Directors Present</h2>
<div class='box'><p>{$this->v($v,'quorum_present')}</p></div>

<h2>Transaction Being Approved</h2>
<p>{$this->v($v,'transaction_description')}</p>

<h2>Resolutions Passed</h2>
<p>{$this->v($v,'resolutions_passed')}</p>

<h2>Authorized Signatories</h2>
<p>{$this->v($v,'authorized_signatories')}</p>

<div class='sig nb' style='margin-top:50px'>
  <div class='sc'>
    <p><strong>CHAIRMAN</strong></p>
    <div class='sl'></div>
    <p class='slb'>{$this->v($v,'chairman_name')}</p>
    <p class='slb'>Date: ___________________</p>
  </div>
  <div class='sc'>
    <p><strong>SECRETARY</strong></p>
    <div class='sl'></div>
    <p class='slb'>{$this->v($v,'secretary_name')}</p>
    <p class='slb'>Date: ___________________</p>
  </div>
</div>
<p class='ft'>Board Resolution &mdash; {$this->v($v,'company_name')} &mdash; {$this->v($v,'meeting_date')}</p>
</div></body></html>";
    }

    // ── 16. COMPLETION CHECKLIST ──────────────────────────────────────────────
    private function generateCompletionChecklist(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Completion Checklist</title>{$css}</head><body><div class='page'>
<div style='text-align:center;font-size:36pt;margin-bottom:10px'>✅</div>
<h1>COMPLETION CHECKLIST</h1>
<p class='sub'>{$this->v($v,'transaction_name')}</p>
<p class='dt'>Target Closing Date: {$this->v($v,'target_closing_date')} &mdash; Prepared: {$this->v($v,'document_date')}</p>

<h2>Transaction Parties</h2>
<table class='kv'>
  <tr><td>Target Company</td><td>{$this->v($v,'target_company')}</td></tr>
  <tr><td>Investor</td><td>{$this->v($v,'investor_name')}</td></tr>
  <tr><td>Legal Advisor — Investor</td><td>{$this->v($v,'legal_advisor_investor','TBD')}</td></tr>
  <tr><td>Legal Advisor — Target</td><td>{$this->v($v,'legal_advisor_target','TBD')}</td></tr>
  <tr><td>Funds Flow Coordinator</td><td>{$this->v($v,'funds_flow_coordinator','TBD')}</td></tr>
</table>

<h2>A. Pre-Closing Conditions Precedent</h2>
<table class='kv'>
  <tr><td>☐ Executed Investment Agreement / Term Sheet</td><td>Responsible: Both parties</td></tr>
  <tr><td>☐ Board Resolution — Target approving issuance</td><td>Responsible: Target legal counsel</td></tr>
  <tr><td>☐ Board Resolution — Investor approving investment</td><td>Responsible: Investor</td></tr>
  <tr><td>☐ Shareholders approving capital increase (if required)</td><td>Responsible: Target</td></tr>
  <tr><td>☐ Regulatory / competition approval obtained</td><td>Responsible: Both parties</td></tr>
  <tr><td>☐ Updated cap table agreed and signed off</td><td>Responsible: Target CFO</td></tr>
  <tr><td>☐ All reps &amp; warranties confirmed accurate</td><td>Responsible: Target legal counsel</td></tr>
</table>

<h2>B. Closing Documents to Be Executed</h2>
<table class='kv'>
  <tr><td>☐ Subscription Agreement / STA</td><td>Status: ____________</td></tr>
  <tr><td>☐ Shareholders Agreement (SHA)</td><td>Status: ____________</td></tr>
  <tr><td>☐ Updated Articles of Association</td><td>Status: ____________</td></tr>
  <tr><td>☐ Share Certificates / Ledger Update</td><td>Status: ____________</td></tr>
  <tr><td>☐ Commercial Registry Filing</td><td>Status: ____________</td></tr>
</table>

<h2>C. Funds Flow</h2>
<table class='kv'>
  <tr><td>Settlement Account</td><td>{$this->v($v,'settlement_account','To be confirmed')}</td></tr>
  <tr><td>☐ Wire transfer instructions confirmed</td><td>Status: ____________</td></tr>
  <tr><td>☐ Funds received and confirmed by target bank</td><td>Status: ____________</td></tr>
</table>

<h2>D. Additional Conditions</h2>
<p>{$this->v($v,'additional_conditions','None.')}</p>

<p class='ft'>Completion Checklist &mdash; {$this->v($v,'transaction_name')} &mdash; {$this->v($v,'document_date')}</p>
</div></body></html>";
    }

    // ── 17. SHARE TRANSFER AGREEMENT ──────────────────────────────────────────
    private function generateSTA(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Share Transfer Agreement</title>{$css}</head><body><div class='page'>
<h1>SHARE TRANSFER AGREEMENT</h1>
<p class='sub'>{$this->v($v,'company_name')}</p>
<p class='dt'>Dated: {$this->v($v,'effective_date')}</p>

<p>This Share Transfer Agreement (<strong>&ldquo;Agreement&rdquo;</strong>) is entered into as of <strong>{$this->v($v,'effective_date')}</strong> between:</p>
<div class='box'>
  <p><strong>Transferor (Seller):</strong> {$this->v($v,'transferor_name')} (ID/Passport: {$this->v($v,'transferor_id','—')}); and</p>
  <p><strong>Transferee (Buyer):</strong> {$this->v($v,'transferee_name')}, registered at {$this->v($v,'transferee_address')}.</p>
</div>

<h2>1. Transfer of Shares</h2>
<p>The Transferor hereby sells, assigns, and transfers to the Transferee <strong>{$this->v($v,'shares_transferred')}</strong> in <strong>{$this->v($v,'company_name')}</strong> (Registration No.: {$this->v($v,'company_registration','—')}), free and clear of all liens and encumbrances.</p>

<h2>2. Consideration</h2>
<p>The Transferee shall pay the Transferor a total consideration of <strong>{$this->v($v,'total_consideration')} ({$this->v($v,'currency')})</strong>, being <strong>{$this->v($v,'price_per_share')}</strong> per share, by <strong>{$this->v($v,'payment_method')}</strong> within <strong>{$this->v($v,'payment_deadline_days')} business days</strong> of the signing date.</p>

<h2>3. Pre-emption Rights</h2>
<p>{$this->v($v,'rofo_waiver','The Transferor confirms that all applicable pre-emption and right-of-first-offer rights have been duly waived or complied with prior to the execution of this Agreement.')}</p>

<h2>4. Representations &amp; Warranties</h2>
<p>The Transferor represents and warrants that: (a) it is the legal and beneficial owner of the Shares; (b) the Shares are free from any encumbrance, pledge, or third-party claim; (c) it has full authority to execute this Agreement; and (d) {$this->v($v,'rep_warranties','no other representations outstanding.')}</p>

<h2>5. Governing Law</h2>
<p>This Agreement is governed by the laws of <strong>{$this->v($v,'governing_law')}</strong>.</p>

<div class='sig nb'>
  <div class='sc'>
    <p><strong>TRANSFEROR</strong></p>
    <div class='sl'></div>
    <p class='slb'>{$this->v($v,'transferor_signatory')}</p>
    <p class='slb'>Date: _______________________</p>
  </div>
  <div class='sc'>
    <p><strong>TRANSFEREE</strong></p>
    <div class='sl'></div>
    <p class='slb'>{$this->v($v,'transferee_signatory')} &mdash; {$this->v($v,'transferee_title')}</p>
    <p class='slb'>Date: _______________________</p>
  </div>
</div>
<p class='ft'>STRICTLY CONFIDENTIAL &mdash; Share Transfer Agreement &mdash; {$this->v($v,'company_name')} &mdash; {$this->v($v,'effective_date')}</p>
</div></body></html>";
    }

    // ── 18. LOAN AGREEMENT / CONVERTIBLE NOTE ─────────────────────────────────
    private function generateLoanAgreement(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Loan Agreement</title>{$css}</head><body><div class='page'>
<h1>{$this->v($v,'instrument_type','LOAN AGREEMENT')}</h1>
<p class='dt'>Dated: {$this->v($v,'effective_date')}</p>

<p>This Agreement is entered into as of <strong>{$this->v($v,'effective_date')}</strong> between:</p>
<div class='box'>
  <p><strong>Lender:</strong> {$this->v($v,'lender_name')}, registered at {$this->v($v,'lender_address')}; and</p>
  <p><strong>Borrower:</strong> {$this->v($v,'borrower_name')}, registered at {$this->v($v,'borrower_address')}.</p>
</div>

<h2>1. Facility</h2>
<table class='kv'>
  <tr><td>Instrument Type</td><td>{$this->v($v,'instrument_type')}</td></tr>
  <tr><td>Principal Amount</td><td><strong>{$this->v($v,'principal_amount')} ({$this->v($v,'currency')})</strong></td></tr>
  <tr><td>Interest Rate</td><td>{$this->v($v,'interest_rate')} ({$this->v($v,'interest_type')})</td></tr>
  <tr><td>Maturity Date</td><td><strong>{$this->v($v,'maturity_date')}</strong></td></tr>
  <tr><td>Repayment Schedule</td><td>{$this->v($v,'repayment_schedule')}</td></tr>
</table>

<h2>2. Use of Proceeds</h2>
<p>{$this->v($v,'use_of_proceeds')}</p>

<h2>3. Security &amp; Collateral</h2>
<p>{$this->v($v,'security_collateral','Unsecured.')}</p>

<h2>4. Conversion Terms</h2>
<p>{$this->v($v,'conversion_terms','Not applicable — this is a standard term loan.')}</p>

<h2>5. Covenants</h2>
<p>{$this->v($v,'covenants','Standard financial covenants to be agreed.')}</p>

<h2>6. Events of Default</h2>
<p>{$this->v($v,'events_of_default','Missed payment, insolvency, material breach of covenants, change of control without lender consent.')}</p>

<h2>7. Governing Law</h2>
<p>This Agreement is governed by the laws of <strong>{$this->v($v,'governing_law')}</strong>.</p>

<div class='sig nb'>
  <div class='sc'>
    <p><strong>LENDER</strong></p>
    <div class='sl'></div>
    <p class='slb'>{$this->v($v,'lender_signatory')}</p>
    <p class='slb'>Date: _______________________</p>
  </div>
  <div class='sc'>
    <p><strong>BORROWER</strong></p>
    <div class='sl'></div>
    <p class='slb'>{$this->v($v,'borrower_signatory')}</p>
    <p class='slb'>Date: _______________________</p>
  </div>
</div>
<p class='ft'>STRICTLY CONFIDENTIAL &mdash; {$this->v($v,'instrument_type')} &mdash; {$this->v($v,'lender_name')} / {$this->v($v,'borrower_name')} &mdash; {$this->v($v,'effective_date')}</p>
</div></body></html>";
    }

    // ══════════════════════════════════════════════════════════════════════════
    // NEW TEMPLATES — Post-Investment
    // ══════════════════════════════════════════════════════════════════════════

    // ── 19. BOARD MEETING MINUTES ─────────────────────────────────────────────
    private function generateBoardMinutes(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Board Meeting Minutes</title>{$css}</head><body><div class='page'>
<h1>BOARD MEETING MINUTES</h1>
<p class='sub'>{$this->v($v,'company_name')}</p>
<p class='dt'>{$this->v($v,'meeting_number','Board Meeting')} &mdash; {$this->v($v,'meeting_date')}</p>

<h2>Meeting Details</h2>
<table class='kv'>
  <tr><td>Date</td><td>{$this->v($v,'meeting_date')}</td></tr>
  <tr><td>Time</td><td>{$this->v($v,'meeting_time','—')}</td></tr>
  <tr><td>Location / Platform</td><td>{$this->v($v,'meeting_location')}</td></tr>
  <tr><td>Chairman</td><td>{$this->v($v,'chairman')}</td></tr>
  <tr><td>Secretary</td><td>{$this->v($v,'secretary')}</td></tr>
  <tr><td>Quorum</td><td>{$this->v($v,'quorum_confirmed')}</td></tr>
</table>

<h2>Attendees</h2>
<div class='box'><p><strong>Board Members:</strong><br>{$this->v($v,'board_members_present')}</p>
<p style='margin-top:8px'><strong>Invitees:</strong><br>{$this->v($v,'invitees','None.')}</p></div>

<h2>Agenda</h2>
<p>{$this->v($v,'agenda_items')}</p>

<h2>Financial Performance Update</h2>
<p>{$this->v($v,'financial_update','Not presented at this meeting.')}</p>

<h2>Resolutions Passed</h2>
<p>{$this->v($v,'resolutions_passed','No formal resolutions passed.')}</p>

<h2>Action Items</h2>
<p>{$this->v($v,'actions_agreed','No actions recorded.')}</p>

<h2>Next Meeting</h2>
<p>{$this->v($v,'next_meeting_date','To be confirmed.')}</p>

<div class='sig nb' style='margin-top:50px'>
  <div class='sc'>
    <p><strong>CHAIRMAN</strong></p>
    <div class='sl'></div>
    <p class='slb'>{$this->v($v,'chairman')}</p>
    <p class='slb'>Date: ___________________</p>
  </div>
  <div class='sc'>
    <p><strong>SECRETARY</strong></p>
    <div class='sl'></div>
    <p class='slb'>{$this->v($v,'secretary')}</p>
    <p class='slb'>Minutes prepared: {$this->v($v,'document_date')}</p>
  </div>
</div>
<p class='ft'>Board Meeting Minutes &mdash; {$this->v($v,'company_name')} &mdash; {$this->v($v,'meeting_date')}</p>
</div></body></html>";
    }

    // ── 20. QUARTERLY REPORTING TEMPLATE ──────────────────────────────────────
    private function generateQuarterlyReport(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Quarterly Report</title>{$css}</head><body><div class='page'>
<div style='text-align:center;font-size:36pt;margin-bottom:10px'>📊</div>
<h1>QUARTERLY PERFORMANCE REPORT</h1>
<p class='sub'>{$this->v($v,'company_name')}</p>
<p class='dt'>{$this->v($v,'reporting_quarter')} {$this->v($v,'reporting_year')} &mdash; Prepared by {$this->v($v,'prepared_by')} &mdash; {$this->v($v,'report_date')}</p>
<p><strong>Submitted to:</strong> {$this->v($v,'investor_recipient')}</p>

<h2>1. Executive Summary</h2>
<p>{$this->v($v,'executive_summary')}</p>

<h2>2. Financial Highlights — Quarter ({$this->v($v,'currency')})</h2>
<p>{$this->v($v,'financial_highlights')}</p>

<h2>3. YTD Performance vs. Budget</h2>
<p>{$this->v($v,'ytd_performance','YTD figures to be provided.')}</p>

<h2>4. Operational Update</h2>
<p>{$this->v($v,'operational_update','No material operational changes in the quarter.')}</p>

<h2>5. Key KPI Update</h2>
<p>{$this->v($v,'kpi_update','KPI data to be provided.')}</p>

<h2>6. Key Risks &amp; Issues</h2>
<p>{$this->v($v,'risks_and_issues','No material new risks identified.')}</p>

<h2>7. Outlook — {$this->v($v,'reporting_year')}</h2>
<p>{$this->v($v,'outlook','Full-year guidance to be confirmed.')}</p>

<h2>8. Support Required from Investor</h2>
<p>{$this->v($v,'support_needed','No specific support required at this time.')}</p>

<p class='ft'>CONFIDENTIAL &mdash; Quarterly Report &mdash; {$this->v($v,'company_name')} &mdash; {$this->v($v,'reporting_quarter')} {$this->v($v,'reporting_year')}</p>
</div></body></html>";
    }

    // ── 21. EXIT NOTICE LETTER ────────────────────────────────────────────────
    private function generateExitNotice(array $v, string $css): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Exit Notice Letter</title>{$css}</head><body><div class='page'>
<h1>EXIT NOTICE LETTER</h1>
<p class='dt'>Date: {$this->v($v,'letter_date')}</p>
<p><strong>To:</strong> {$this->v($v,'recipient_name')}, {$this->v($v,'company_name')}</p>
<p><strong>From:</strong> {$this->v($v,'exiting_signatory')}, {$this->v($v,'exiting_investor')}</p>
<p><strong>Re:</strong> Notice of Intention to Exit — {$this->v($v,'company_name')}</p>

<p>Dear {$this->v($v,'recipient_name')},</p>
<p>We write pursuant to the <strong>{$this->v($v,'sha_reference')}</strong> to formally notify you of our intention to exit our shareholding in <strong>{$this->v($v,'company_name')}</strong>.</p>

<h2>Exit Details</h2>
<table class='kv'>
  <tr><td>Exiting Investor</td><td>{$this->v($v,'exiting_investor')}</td></tr>
  <tr><td>Shares Subject to Exit</td><td><strong>{$this->v($v,'shares_to_exit')}</strong></td></tr>
  <tr><td>Proposed Exit Route</td><td><strong>{$this->v($v,'exit_route')}</strong></td></tr>
  <tr><td>Indicative Valuation</td><td>{$this->v($v,'indicative_price','To be determined through formal process')}</td></tr>
  <tr><td>Prospective Buyer</td><td>{$this->v($v,'prospective_buyer','Confidential')}</td></tr>
</table>

<h2>Right of First Offer (ROFO)</h2>
<p>In accordance with the SHA, existing shareholders have <strong>{$this->v($v,'rofo_notice_period_days')} days</strong> from the date of this notice to exercise their pre-emption rights. The deadline for ROFO election is <strong>{$this->v($v,'rofo_exercise_deadline')}</strong>. Failure to respond within this period will be deemed a waiver of ROFO rights.</p>

<h2>Drag-Along</h2>
<p><strong>Drag-Along Status:</strong> {$this->v($v,'drag_along_triggered','Not triggered at this stage.')}</p>

<h2>Proposed Next Steps</h2>
<p>{$this->v($v,'next_steps','To be discussed with all shareholders.')}</p>

<p>We remain committed to a smooth and value-maximizing exit process in the best interests of all shareholders.</p>

<div class='sig nb'>
  <div class='sc'>
    <p><strong>{$this->v($v,'exiting_investor')}</strong></p>
    <div class='sl'></div>
    <p class='slb'>{$this->v($v,'exiting_signatory')}</p>
    <p class='slb'>Date: {$this->v($v,'letter_date')}</p>
  </div>
</div>
<p class='ft'>EXIT NOTICE &mdash; {$this->v($v,'company_name')} &mdash; {$this->v($v,'exiting_investor')} &mdash; {$this->v($v,'letter_date')}</p>
</div></body></html>";
    }
}
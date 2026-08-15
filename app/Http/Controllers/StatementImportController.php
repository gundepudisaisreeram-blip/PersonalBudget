<?php

namespace App\Http\Controllers;

use App\Domain\Exceptions\StatementImportException;
use App\Domain\Services\StatementImportService;
use App\Domain\Statements\Exceptions\StatementParseException;
use App\Domain\Statements\Exceptions\UnsupportedStatementFormatException;
use App\Domain\Statements\ProfileRegistry;
use App\Domain\Statements\Readers\CsvFileReader;
use App\Domain\Statements\Readers\XlsxFileReader;
use App\Models\Account;
use App\Models\StatementImport;
use App\Models\StatementTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Strictly staging-domain controller (PHASE_7_DECISION_PACKAGE.md). No
 * action here creates, updates, or deletes a Transaction, LedgerEntry, or
 * PaymentObligation -- every write goes through StatementImportService /
 * ParseStatementImportJob into statement_imports / statement_transactions
 * only. No delete/edit route exists for a retained import, its rows, or
 * its raw file (section 7/10, "No V1 Deletion").
 */
class StatementImportController extends Controller
{
    public function __construct(private readonly StatementImportService $imports) {}

    public function index(Request $request): View
    {
        $imports = $request->user()->statementImports()
            ->with('account')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('imports.index', compact('imports'));
    }

    public function create(Request $request): View
    {
        $accounts = $request->user()->accounts()->orderBy('name')->get();

        return view('imports.create', compact('accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'account_id' => ['required', 'integer'],
            'file' => [
                'required', 'file',
                'max:'.(5 * 1024),
                'mimes:csv,xlsx,txt',
            ],
        ]);

        $account = Account::findOrFail($request->integer('account_id'));

        try {
            $result = $this->imports->initiateUpload($request->user(), $account, $request->file('file'));
        } catch (UnsupportedStatementFormatException|StatementParseException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        if ($result['candidates'] !== []) {
            return redirect()
                ->route('imports.show', $result['import'])
                ->with('status', 'Multiple bank formats matched this file. Please select the correct one below.');
        }

        return redirect()->route('imports.show', $result['import'])->with('status', 'File uploaded and queued for processing.');
    }

    public function show(StatementImport $statementImport): View
    {
        $this->authorize('view', $statementImport);

        $statementImport->load('account');

        $rows = null;
        if (in_array($statementImport->status, [StatementImport::STATUS_PREVIEW_READY, StatementImport::STATUS_CONFIRMED, StatementImport::STATUS_COMPLETED], true)) {
            $rows = $statementImport->statementTransactions()->orderBy('transaction_date')->paginate(50)->withQueryString();
        }

        $candidates = [];
        if ($statementImport->status === StatementImport::STATUS_UPLOADED && $statementImport->parser_profile === null) {
            $matches = app(ProfileRegistry::class)->detect($statementImport->file_type, $this->rereadRows($statementImport));
            $candidates = collect($matches)->mapWithKeys(fn ($profile) => [$profile->key() => $profile->label()])->all();
        }

        return view('imports.show', [
            'import' => $statementImport,
            'rows' => $rows,
            'candidates' => $candidates,
            'duplicateCandidateCount' => $rows === null ? 0 : $statementImport->statementTransactions()
                ->where('duplicate_status', StatementTransaction::DUPLICATE_STATUS_CANDIDATE)->count(),
        ]);
    }

    public function selectProfile(Request $request, StatementImport $statementImport): RedirectResponse
    {
        $this->authorize('view', $statementImport);

        $request->validate(['parser_profile' => ['required', 'string']]);

        try {
            $this->imports->selectProfile($request->user(), $statementImport, $request->string('parser_profile')->toString());
        } catch (StatementImportException $e) {
            return back()->withErrors(['parser_profile' => $e->getMessage()]);
        }

        return redirect()->route('imports.show', $statementImport)->with('status', 'Profile selected. File queued for processing.');
    }

    public function confirm(Request $request, StatementImport $statementImport): RedirectResponse
    {
        $this->authorize('view', $statementImport);

        if ($statementImport->status !== StatementImport::STATUS_PREVIEW_READY) {
            return back()->withErrors(['import' => 'This import is not ready for confirmation.']);
        }

        $statementImport->update(['status' => StatementImport::STATUS_CONFIRMED]);
        $statementImport->update(['status' => StatementImport::STATUS_COMPLETED]);

        return redirect()->route('imports.show', $statementImport)->with('status', 'Import confirmed and completed.');
    }

    public function download(StatementImport $statementImport): StreamedResponse
    {
        $this->authorize('view', $statementImport);

        return Storage::disk('local')->download($statementImport->storage_path, $statementImport->original_filename);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private function rereadRows(StatementImport $statementImport): array
    {
        $reader = $statementImport->file_type === 'csv'
            ? app(CsvFileReader::class)
            : app(XlsxFileReader::class);

        return $reader->read(Storage::disk('local')->path($statementImport->storage_path));
    }
}

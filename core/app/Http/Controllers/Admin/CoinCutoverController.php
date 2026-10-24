<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinCutoverLog;
use App\Services\CoinCutover;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CoinCutoverController extends Controller
{
    public function index()
    {
        $pageTitle = 'Coin Cutover';
        $logs = CoinCutoverLog::latest()->paginate(getPaginate());

        return view('admin.coin.cutover', compact('pageTitle', 'logs'));
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|in:convert,reset_test',
            'reset_amount' => 'nullable|integer|min:0',
        ]);

        try {
            $preview = app(CoinCutover::class)->preview($data['mode'], $data['reset_amount'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->with('preview', $preview)->withNotify([['success', 'Dry-run preview computed below - nothing has been changed yet.']]);
    }

    public function execute(Request $request)
    {
        $data = $request->validate([
            'mode' => 'required|in:convert,reset_test',
            'reset_amount' => 'nullable|integer|min:0',
            'confirm' => 'required|accepted',
        ]);

        try {
            $log = app(CoinCutover::class)->execute($data['mode'], $data['reset_amount'] ?? null, auth('admin')->id());
        } catch (InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return to_route('admin.coin.cutover.index')->withNotify([['success', "Cutover complete - log #{$log->id}. See the summary below."]]);
    }
}

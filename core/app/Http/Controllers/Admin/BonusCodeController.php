<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\BonusCode;
use App\Models\BonusCodeRedemption;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use App\Models\Currency;

class BonusCodeController extends Controller
{
    public function index()
    {
        $pageTitle = 'Bonus Codes';
        $codes     = BonusCode::orderBy('id', 'desc')->get();
        return view('admin.bonus_code.index', compact('pageTitle', 'codes'));
    }

    public function create()
    {
        $pageTitle = 'Add Bonus Code';
        $currencies = Schema::hasTable('currencies') ? Currency::where('enabled', true)->orderBy('code')->get() : collect();
        return view('admin.bonus_code.create', compact('pageTitle', 'currencies'));
    }

    public function store(Request $request)
    {
        $rules = [
            'code'       => 'required|string|max:60|unique:bonus_codes,code',
            'amount'     => 'required|numeric|gt:0',
            'max_uses'   => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
        ];
        if (Schema::hasColumn('bonus_codes', 'currency_id')) $rules['currency_id'] = ['required', 'integer', Rule::exists('currencies', 'id')->where(fn ($query) => $query->where('enabled', true))];
        $request->validate($rules);

        $code             = new BonusCode();
        $code->code       = strtoupper($request->code);
        $code->amount     = $request->amount;
        if (Schema::hasColumn('bonus_codes', 'currency_id')) $code->currency_id = $request->integer('currency_id');
        $code->max_uses   = $request->max_uses ?: null;
        $code->expires_at = $request->expires_at ?: null;
        $code->status     = Status::ENABLE;
        $code->save();

        $notify[] = ['success', 'Bonus code added successfully'];
        return to_route('admin.bonus.code.index')->withNotify($notify);
    }

    public function edit($id)
    {
        $code      = BonusCode::findOrFail($id);
        $pageTitle = 'Update ' . $code->code;
        $currencies = Schema::hasTable('currencies') ? Currency::where('enabled', true)->orderBy('code')->get() : collect();
        return view('admin.bonus_code.edit', compact('pageTitle', 'code', 'currencies'));
    }

    public function update(Request $request, $id)
    {
        $code = BonusCode::findOrFail($id);

        $rules = [
            'code'       => ['required', 'string', 'max:60', Rule::unique('bonus_codes', 'code')->ignore($code->id)],
            'amount'     => 'required|numeric|gt:0',
            'max_uses'   => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
        ];
        if (Schema::hasColumn('bonus_codes', 'currency_id')) $rules['currency_id'] = ['required', 'integer', Rule::exists('currencies', 'id')->where(fn ($query) => $query->where('enabled', true))];
        $request->validate($rules);
        if (Schema::hasColumn('bonus_codes', 'currency_id') && $code->used_count > 0 && (int) $code->currency_id !== $request->integer('currency_id')) {
            return back()->withErrors(['currency_id' => 'The currency cannot change after this code has been redeemed.'])->withInput();
        }

        $code->code       = strtoupper($request->code);
        $code->amount     = $request->amount;
        if (Schema::hasColumn('bonus_codes', 'currency_id')) $code->currency_id = $request->integer('currency_id');
        $code->max_uses   = $request->max_uses ?: null;
        $code->expires_at = $request->expires_at ?: null;
        $code->save();

        $notify[] = ['success', 'Bonus code updated successfully'];
        return to_route('admin.bonus.code.index')->withNotify($notify);
    }

    public function status($id)
    {
        return BonusCode::changeStatus($id);
    }

    public function destroy($id)
    {
        BonusCode::findOrFail($id)->delete();
        $notify[] = ['success', 'Bonus code deleted successfully'];
        return back()->withNotify($notify);
    }

    public function redemptions()
    {
        $pageTitle   = 'Bonus Code Redemptions';
        $redemptions = BonusCodeRedemption::with(['user', 'bonusCode'])->orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.bonus_code.redemptions', compact('pageTitle', 'redemptions'));
    }
}

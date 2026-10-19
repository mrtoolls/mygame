<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessPromotion;
use App\Models\BusinessPromotionRate;
use App\Models\Currency;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BusinessPromotionController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('business_promotions'), 503, 'Run the database updater to enable business promotions.');
        $status = $request->query('status', 'pending');
        $query = BusinessPromotion::with(['user', 'currency'])->latest();
        if ($status === 'active') $query->where('status', 'active')->where('ends_at', '>', now());
        elseif ($status === 'expired') $query->where(fn ($q) => $q->where('status', 'expired')->orWhere(fn ($ended) => $ended->where('status', 'active')->where('ends_at', '<=', now())));
        elseif (in_array($status, ['pending', 'rejected'], true)) $query->where('status', $status);
        $promotions = $query->paginate(20)->withQueryString();
        $pageTitle = 'Business Promotions';
        return view('admin.business_promotions.index', compact('promotions', 'status', 'pageTitle'));
    }

    public function rates()
    {
        abort_unless(Schema::hasTable('business_promotion_rates'), 503, 'Run the database updater to enable business promotion pricing.');
        $currencies = Currency::orderBy('code')->get();
        $rates = BusinessPromotionRate::whereIn('currency_id', $currencies->pluck('id'))->get()->keyBy('currency_id');
        $pageTitle = 'Business Promotion Pricing';
        return view('admin.business_promotions.rates', compact('currencies', 'rates', 'pageTitle'));
    }

    public function saveRates(Request $request)
    {
        abort_unless(Schema::hasTable('business_promotion_rates'), 503, 'Run the database updater to enable business promotion pricing.');
        $request->validate(['prices' => 'required|array', 'prices.*.currency_id' => 'required|integer|exists:currencies,id', 'prices.*.day' => 'required|numeric|min:0|max:1000000000000', 'prices.*.week' => 'required|numeric|min:0|max:1000000000000', 'prices.*.month' => 'required|numeric|min:0|max:1000000000000']);
        $money = app(Money::class);
        $convertedPrices = [];
        try {
            foreach ($request->prices as $entry) {
                $currency = Currency::findOrFail($entry['currency_id']);
                $convertedPrices[] = [
                    'currency_id' => $currency->id,
                    'daily_price_minor' => (int) $money->toMinor((string) $entry['day'], (int) $currency->decimals),
                    'weekly_price_minor' => (int) $money->toMinor((string) $entry['week'], (int) $currency->decimals),
                    'monthly_price_minor' => (int) $money->toMinor((string) $entry['month'], (int) $currency->decimals),
                ];
            }
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['prices' => $e->getMessage()]);
        }
        DB::transaction(function () use ($convertedPrices) {
            foreach ($convertedPrices as $prices) {
                BusinessPromotionRate::updateOrCreate(['currency_id' => $prices['currency_id']], $prices);
            }
        });
        return back()->withNotify([['success', 'Business promotion prices updated.']]);
    }

    public function approve($id)
    {
        $promotion = DB::transaction(function () use ($id) {
            $promotion = BusinessPromotion::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($promotion->status !== 'pending') abort(422, 'Only pending business promotions can be approved.');
            $start = now();
            $end = match ($promotion->duration) {
                'day' => $start->copy()->addDay(),
                'week' => $start->copy()->addWeek(),
                'month' => $start->copy()->addMonth(),
            };
            $promotion->status = 'active';
            $promotion->approved_at = $start;
            $promotion->starts_at = $start;
            $promotion->ends_at = $end;
            $promotion->save();
            return $promotion;
        });
        return back()->withNotify([['success', 'Business promotion approved and published.']]);
    }

    public function reject(Request $request, $id)
    {
        $request->validate(['admin_note' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($id, $request) {
            $promotion = BusinessPromotion::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($promotion->status !== 'pending') abort(422, 'Only pending business promotions can be rejected.');

            if (!$promotion->refunded_at) {
                $user = User::whereKey($promotion->user_id)->lockForUpdate()->firstOrFail();
                if ((int) $user->currency_id !== (int) $promotion->currency_id) abort(422, 'Promotion and wallet currencies do not match; refund requires manual review.');
                $currency = Currency::findOrFail($promotion->currency_id);
                $money = app(Money::class);
                $currentMinor = (int) $money->toMinor((string) $user->balance, (int) $currency->decimals);
                $postBalance = $money->formatMinor((string) ($currentMinor + $promotion->amount_minor), (int) $currency->decimals);
                $amount = $money->formatMinor((string) $promotion->amount_minor, (int) $currency->decimals);
                $user->balance = $postBalance;
                $user->save();

                $transaction = new Transaction();
                $transaction->user_id = $user->id;
                $transaction->amount = $amount;
                $transaction->charge = 0;
                $transaction->post_balance = $postBalance;
                $transaction->trx_type = '+';
                $transaction->details = 'Refund for rejected business promotion';
                $transaction->trx = 'BP-REFUND-' . $promotion->id;
                $transaction->remark = 'business_promotion_refund';
                if (Schema::hasColumn('transactions', 'currency_id')) $transaction->currency_id = $promotion->currency_id;
                $transaction->save();
                $promotion->refunded_at = now();
            }

            $promotion->status = 'rejected';
            $promotion->admin_note = $request->admin_note;
            $promotion->save();
        });
        return back()->withNotify([['success', 'Promotion rejected and the payment was refunded.']]);
    }
}

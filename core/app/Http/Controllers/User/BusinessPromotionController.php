<?php

namespace App\Http\Controllers\User;

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
use Throwable;

class BusinessPromotionController extends Controller
{
    public function index()
    {
        abort_unless(Schema::hasTable('business_promotions') && Schema::hasTable('business_promotion_rates'), 503, 'Run the database updater to enable business promotions.');
        $user = auth()->user();
        $currency = Currency::whereKey($user->currency_id)->where('enabled', true)->first();
        $rates = $currency ? BusinessPromotionRate::where('currency_id', $currency->id)->first() : null;
        $promotions = BusinessPromotion::where('user_id', $user->id)->latest()->paginate(10);
        $submissionKey = (string) \Illuminate\Support\Str::uuid();
        $pageTitle = 'Promote a Business';
        return view('Template::user.business_promotions', compact('currency', 'rates', 'promotions', 'pageTitle', 'submissionKey'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'idempotency_key' => 'required|uuid',
            'business_name' => 'required|string|max:120',
            'title' => 'required|string|max:160',
            'description' => 'required|string|max:1200',
            'destination_url' => ['required', 'url', 'max:2048'],
            'duration' => 'required|in:day,week,month',
            'image' => 'required|image|mimes:jpg,jpeg,png|max:5120',
        ]);

        $user = auth()->user();
        $currency = Currency::whereKey($user->currency_id)->where('enabled', true)->firstOrFail();
        $imageName = fileUploader($request->file('image'), 'assets/images/business-promotions');
        $reference = strtoupper(str_replace('-', '', $request->idempotency_key));

        try {
            $created = DB::transaction(function () use ($request, $user, $currency, $imageName, $reference) {
                $lockedUser = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $existing = BusinessPromotion::where('payment_reference', $reference)->first();
                if ($existing) {
                    abort_unless((int) $existing->user_id === (int) $lockedUser->id, 409, 'This submission key has already been used. Refresh the page and try again.');
                    return false;
                }
                if (!$lockedUser->currency_id || (int) $lockedUser->currency_id !== (int) $currency->id) {
                    throw ValidationException::withMessages(['duration' => 'Your wallet currency changed. Refresh and try again.']);
                }

                $rate = BusinessPromotionRate::where('currency_id', $currency->id)->lockForUpdate()->first();
                $priceColumn = match ($request->duration) {
                    'day' => 'daily_price_minor',
                    'week' => 'weekly_price_minor',
                    'month' => 'monthly_price_minor',
                };
                $priceMinor = (int) ($rate?->$priceColumn ?? 0);
                if ($priceMinor < 1) throw ValidationException::withMessages(['duration' => 'Business promotions are not available for this currency yet.']);

                $money = app(Money::class);
                $balanceMinor = (int) $money->toMinor((string) $lockedUser->balance, (int) $currency->decimals);
                if ($balanceMinor < $priceMinor) throw ValidationException::withMessages(['duration' => 'Your wallet balance is too low for this promotion.']);
                $postBalance = $money->formatMinor((string) ($balanceMinor - $priceMinor), (int) $currency->decimals);
                $amount = $money->formatMinor((string) $priceMinor, (int) $currency->decimals);

                $promotion = BusinessPromotion::create([
                    'user_id' => $lockedUser->id,
                    'currency_id' => $currency->id,
                    'business_name' => $request->business_name,
                    'title' => $request->title,
                    'description' => $request->description,
                    'image' => $imageName,
                    'destination_url' => $request->destination_url,
                    'duration' => $request->duration,
                    'amount_minor' => $priceMinor,
                    'payment_reference' => $reference,
                    'status' => 'pending',
                    'paid_at' => now(),
                ]);

                $lockedUser->balance = $postBalance;
                $lockedUser->save();

                $transaction = new Transaction();
                $transaction->user_id = $lockedUser->id;
                $transaction->amount = $amount;
                $transaction->charge = 0;
                $transaction->post_balance = $postBalance;
                $transaction->trx_type = '-';
                $transaction->details = 'Business promotion payment (' . $request->duration . ')';
                $transaction->trx = $reference;
                $transaction->remark = 'business_promotion';
                if (Schema::hasColumn('transactions', 'currency_id')) $transaction->currency_id = $currency->id;
                $transaction->save();
                return true;
            });
        } catch (Throwable $e) {
            @unlink('assets/images/business-promotions/' . $imageName);
            throw $e;
        }

        if (!$created) {
            @unlink('assets/images/business-promotions/' . $imageName);
            return back()->withNotify([['success', 'This campaign submission was already received.']]);
        }

        return back()->withNotify([['success', 'Payment received. Your business promotion is now awaiting admin review.']]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Models\VipRakebackClaim;
use App\Models\VipTier;
use Illuminate\Http\Request;

class VipTierController extends Controller
{
    public function index()
    {
        $pageTitle = 'VIP Tiers';
        $tiers     = VipTier::orderBy('sort_order')->orderBy('min_wagered')->get();
        return view('admin.vip.index', compact('pageTitle', 'tiers'));
    }

    public function create()
    {
        $pageTitle = 'Add VIP Tier';
        return view('admin.vip.create', compact('pageTitle'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'                         => 'required|string|max:60',
            'badge'                        => 'nullable|string|max:60',
            'badge_image'                  => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'min_wagered'                  => 'required|numeric|min:0',
            'bonus_amount'                 => 'required|numeric|min:0',
            'rakeback_percent'             => 'required|numeric|min:0|max:100',
            'max_bet'                      => 'nullable|numeric|min:0',
            'daily_reward_amount'          => 'nullable|numeric|min:0',
            'birthday_bonus_amount'        => 'nullable|numeric|min:0',
            'withdrawal_processing_hours'  => 'nullable|integer|min:0',
            'monthly_withdrawal_limit'     => 'nullable|numeric|min:0',
            'support_level'                => 'nullable|string|max:60',
            'exclusive_promotions'         => 'nullable|string|max:60',
            'referral_bonus_boost_percent' => 'nullable|numeric|min:0|max:100',
            'sort_order'                   => 'nullable|integer',
        ]);

        $tier                              = new VipTier();
        $tier->name                        = $request->name;
        $tier->badge                       = $request->badge;
        $tier->min_wagered                 = $request->min_wagered;
        $tier->bonus_amount                = $request->bonus_amount;
        $tier->rakeback_percent            = $request->rakeback_percent;
        $tier->max_bet                     = $request->max_bet ?: null;
        $tier->daily_reward_amount         = $request->daily_reward_amount ?: 0;
        $tier->birthday_bonus_amount       = $request->birthday_bonus_amount ?: 0;
        $tier->withdrawal_processing_hours = $request->withdrawal_processing_hours ?: null;
        $tier->monthly_withdrawal_limit    = $request->monthly_withdrawal_limit ?: null;
        $tier->support_level               = $request->support_level ?: null;
        $tier->exclusive_promotions        = $request->exclusive_promotions ?: null;
        $tier->referral_bonus_boost_percent = $request->referral_bonus_boost_percent ?: 0;
        $tier->sort_order                  = $request->sort_order ?: 0;
        $tier->status                      = Status::ENABLE;
        if ($request->hasFile('badge_image')) {
            $tier->badge_image = fileUploader($request->file('badge_image'), 'assets/images/vip-tiers', '160x160');
        }
        $tier->save();

        $notify[] = ['success', 'VIP tier added successfully'];
        return to_route('admin.vip.index')->withNotify($notify);
    }

    public function edit($id)
    {
        $tier      = VipTier::findOrFail($id);
        $pageTitle = 'Update ' . $tier->name;
        return view('admin.vip.edit', compact('pageTitle', 'tier'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name'                         => 'required|string|max:60',
            'badge'                        => 'nullable|string|max:60',
            'badge_image'                  => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'remove_badge_image'           => 'nullable|boolean',
            'min_wagered'                  => 'required|numeric|min:0',
            'bonus_amount'                 => 'required|numeric|min:0',
            'rakeback_percent'             => 'required|numeric|min:0|max:100',
            'max_bet'                      => 'nullable|numeric|min:0',
            'daily_reward_amount'          => 'nullable|numeric|min:0',
            'birthday_bonus_amount'        => 'nullable|numeric|min:0',
            'withdrawal_processing_hours'  => 'nullable|integer|min:0',
            'monthly_withdrawal_limit'     => 'nullable|numeric|min:0',
            'support_level'                => 'nullable|string|max:60',
            'exclusive_promotions'         => 'nullable|string|max:60',
            'referral_bonus_boost_percent' => 'nullable|numeric|min:0|max:100',
            'sort_order'                   => 'nullable|integer',
        ]);

        $tier                               = VipTier::findOrFail($id);
        $tier->name                         = $request->name;
        $tier->badge                        = $request->badge;
        $tier->min_wagered                  = $request->min_wagered;
        $tier->bonus_amount                 = $request->bonus_amount;
        $tier->rakeback_percent             = $request->rakeback_percent;
        $tier->max_bet                      = $request->max_bet ?: null;
        $tier->daily_reward_amount          = $request->daily_reward_amount ?: 0;
        $tier->birthday_bonus_amount        = $request->birthday_bonus_amount ?: 0;
        $tier->withdrawal_processing_hours  = $request->withdrawal_processing_hours ?: null;
        $tier->monthly_withdrawal_limit     = $request->monthly_withdrawal_limit ?: null;
        $tier->support_level                = $request->support_level ?: null;
        $tier->exclusive_promotions         = $request->exclusive_promotions ?: null;
        $tier->referral_bonus_boost_percent = $request->referral_bonus_boost_percent ?: 0;
        $tier->sort_order                   = $request->sort_order ?: 0;
        if ($request->hasFile('badge_image')) {
            $tier->badge_image = fileUploader($request->file('badge_image'), 'assets/images/vip-tiers', '160x160', $tier->badge_image ?: null);
        } elseif ($request->boolean('remove_badge_image') && $tier->badge_image) {
            @unlink('assets/images/vip-tiers/' . $tier->badge_image);
            $tier->badge_image = null;
        }
        $tier->save();

        $notify[] = ['success', 'VIP tier updated successfully'];
        return to_route('admin.vip.index')->withNotify($notify);
    }

    public function status($id)
    {
        return VipTier::changeStatus($id);
    }

    public function destroy($id)
    {
        $tier = VipTier::findOrFail($id);
        if ($tier->badge_image) {
            @unlink('assets/images/vip-tiers/' . $tier->badge_image);
        }
        $tier->delete();
        $notify[] = ['success', 'VIP tier deleted successfully'];
        return back()->withNotify($notify);
    }

    public function claims()
    {
        $pageTitle = 'VIP Rakeback Claims';
        $claims    = VipRakebackClaim::with(['user', 'tier'])->orderBy('id', 'desc')->paginate(getPaginate());
        return view('admin.vip.claims', compact('pageTitle', 'claims'));
    }
}

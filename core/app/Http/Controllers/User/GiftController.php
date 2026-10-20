<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use App\Models\UserGiftInventory;
use App\Services\GiftService;
use Illuminate\Http\Request;

class GiftController extends Controller
{
    public function index(Request $request)
    {
        $pageTitle = 'My Gifts';
        $catalog   = Gift::active()->orderBy('sort_order')->get();
        $inventory = UserGiftInventory::with('gift')->where('user_id', $request->user()->id)->where('quantity', '>', 0)->get();
        return view('Template::user.gifts.index', compact('pageTitle', 'catalog', 'inventory'));
    }

    public function buy(Request $request, GiftService $service, int $gift)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:999']);
        $giftModel = Gift::findOrFail($gift);
        try {
            $service->buy($request->user(), $giftModel, (int) $data['quantity']);
        } catch (\Throwable $exception) {
            $notify[] = ['error', $exception->getMessage()];
            return back()->withNotify($notify);
        }
        $notify[] = ['success', "You bought {$data['quantity']}x {$giftModel->name}."];
        return back()->withNotify($notify);
    }

    public function cashOut(Request $request, GiftService $service, int $gift)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:999']);
        $giftModel = Gift::findOrFail($gift);
        try {
            $service->cashOut($request->user(), $giftModel, (int) $data['quantity']);
        } catch (\Throwable $exception) {
            $notify[] = ['error', $exception->getMessage()];
            return back()->withNotify($notify);
        }
        $notify[] = ['success', "Cashed out {$data['quantity']}x {$giftModel->name} to your wallet."];
        return back()->withNotify($notify);
    }
}

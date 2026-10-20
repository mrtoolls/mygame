<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GiftController extends Controller
{
    public function index()
    {
        $pageTitle = 'Gifts';
        $gifts     = Gift::orderBy('sort_order')->orderBy('id', 'desc')->get();
        return view('admin.gift.index', compact('pageTitle', 'gifts'));
    }

    public function create()
    {
        $pageTitle = 'Add Gift';
        return view('admin.gift.create', compact('pageTitle'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:60',
            'buy_price'     => 'required|numeric|min:0.00000001',
            'cashout_value' => 'required|numeric|min:0|lte:buy_price',
            'sort_order'    => 'nullable|integer',
            'image'         => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'cashout_value.lte' => 'The cash-out value cannot be more than the buy price.',
        ]);

        $gift              = new Gift();
        $gift->name        = $request->name;
        $gift->slug        = $this->uniqueSlug($request->name);
        $gift->buy_price   = $request->buy_price;
        $gift->cashout_value = $request->cashout_value;
        $gift->sort_order  = $request->sort_order ?: 0;
        $gift->status      = 1;

        try {
            $gift->image = getFilePath('gift') . '/' . fileUploader($request->image, getFilePath('gift'), getFileSize('gift'));
        } catch (\Exception $e) {
            $notify[] = ['error', 'Could not upload the gift image.'];
            return back()->withNotify($notify);
        }

        $gift->save();

        $notify[] = ['success', 'Gift added successfully'];
        return to_route('admin.gift.index')->withNotify($notify);
    }

    public function edit($id)
    {
        $gift      = Gift::findOrFail($id);
        $pageTitle = 'Update ' . $gift->name;
        return view('admin.gift.edit', compact('pageTitle', 'gift'));
    }

    public function update(Request $request, $id)
    {
        $gift = Gift::findOrFail($id);

        $request->validate([
            'name'          => 'required|string|max:60',
            'buy_price'     => 'required|numeric|min:0.00000001',
            'cashout_value' => 'required|numeric|min:0|lte:buy_price',
            'sort_order'    => 'nullable|integer',
            'image'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], [
            'cashout_value.lte' => 'The cash-out value cannot be more than the buy price.',
        ]);

        $gift->name          = $request->name;
        $gift->buy_price     = $request->buy_price;
        $gift->cashout_value = $request->cashout_value;
        $gift->sort_order    = $request->sort_order ?: 0;

        if ($request->hasFile('image')) {
            $oldImage = basename((string) $gift->image);
            try {
                $gift->image = getFilePath('gift') . '/' . fileUploader($request->image, getFilePath('gift'), getFileSize('gift'), $oldImage);
            } catch (\Exception $e) {
                $notify[] = ['error', 'Could not upload the gift image.'];
                return back()->withNotify($notify);
            }
        }

        $gift->save();

        $notify[] = ['success', 'Gift updated successfully'];
        return to_route('admin.gift.index')->withNotify($notify);
    }

    public function status($id)
    {
        return Gift::changeStatus($id);
    }

    public function destroy($id)
    {
        Gift::findOrFail($id)->delete();
        $notify[] = ['success', 'Gift deleted successfully'];
        return back()->withNotify($notify);
    }

    private function uniqueSlug(string $name): string
    {
        $base = \Illuminate\Support\Str::slug($name) ?: 'gift';
        $slug = $base;
        $i    = 1;
        while (Gift::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}

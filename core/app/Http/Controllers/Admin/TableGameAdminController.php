<?php

namespace App\Http\Controllers\Admin;

use App\Games\TableGames\TableGameRules;
use App\Games\TableGames\TableGameService;
use App\Http\Controllers\Controller;
use App\Models\TableGame;
use App\Models\TableGameCommission;
use App\Models\TableGameSetting;
use App\Models\TableGameUserLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;

class TableGameAdminController extends Controller
{
    public function index(TableGameService $service)
    {
        if (!Schema::hasTable('table_games') || !Schema::hasTable('table_game_settings')) {
            return view('admin.table_games.pending')->with('pageTitle', 'Table Game Management');
        }
        $service->sweepExpired();
        $settings = TableGameSetting::all()->keyBy('game_type');
        $liveTables = TableGame::with('creator', 'players.user')->whereIn('status', ['waiting', 'active'])->latest()->paginate(20, ['*'], 'live_page');
        $history = TableGame::with('creator')->whereIn('status', ['finished', 'cancelled', 'force_closed'])->latest()->paginate(20, ['*'], 'history_page');
        $commissionTotal = TableGameCommission::sum('amount');
        $commissionByGame = TableGameCommission::selectRaw('game_type, SUM(amount) as total, COUNT(*) as settled_tables')->groupBy('game_type')->get();
        $limits = TableGameUserLimit::with('user')->latest()->paginate(15, ['*'], 'limits_page');
        return view('admin.table_games.index', compact('settings', 'liveTables', 'history', 'commissionTotal', 'commissionByGame', 'limits'))
            ->with('pageTitle', 'Table Game Management')
            ->with('gameDefinitions', TableGameRules::GAMES)
            ->with('availableGames', TableGameRules::AVAILABLE);
    }

    public function settings(Request $request)
    {
        $definitions = ['*' => 'Global defaults'] + array_fill_keys(array_keys(TableGameRules::GAMES), '');
        $rules = [];
        foreach ($definitions as $key => $_) {
            $rules["settings.$key.enabled"] = 'required|boolean';
            $rules["settings.$key.commission_percent"] = 'required|numeric|min:0|max:100|decimal:0,2';
            $rules["settings.$key.min_stake"] = 'required|numeric|min:0.00000001|decimal:0,8';
            $rules["settings.$key.max_stake"] = 'required|numeric|gt:settings.' . $key . '.min_stake|decimal:0,8';
            $rules["settings.$key.turn_seconds"] = 'required|integer|min:10|max:3600';
            $rules["settings.$key.max_open_tables_per_user"] = 'required|integer|min:1|max:100';
            $rules["settings.$key.max_concurrent_tables"] = 'required|integer|min:1|max:100000';
        }
        $validated = Validator::make($request->all(), $rules)->validate();
        Validator::make($request->all(), [
            'game_images' => 'sometimes|array',
            'game_images.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ])->validate();

        $imageUploads = $request->file('game_images', []);
        if (!empty($imageUploads) && !Schema::hasColumn('table_game_settings', 'card_image')) {
            return back()->withNotify([['error', 'Run the pending database update before uploading live game images.']]);
        }
        foreach ($validated['settings'] as $game => $values) {
            $values['empty_timeout_minutes'] = 1440;
            $setting = TableGameSetting::updateOrCreate(['game_type' => $game], $values);
            if ($game !== '*' && isset($imageUploads[$game])) {
                try {
                    $setting->card_image = fileUploader($imageUploads[$game], getFilePath('game'), getFileSize('game'), $setting->card_image);
                    $setting->save();
                } catch (\Throwable $exception) {
                    report($exception);
                    return back()->withNotify([['error', 'Could not upload the live game image. Please try again.']]);
                }
            }
        }
        return back()->withNotify([['success', 'Table game settings saved.']]);
    }

    public function forceClose(string $id, TableGameService $service)
    {
        $service->forceClose($id);
        return back()->withNotify([['success', 'Table closed and unsettled stakes refunded safely.']]);
    }

    public function userLimit(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'banned' => 'required|boolean',
            'max_open_tables' => 'nullable|integer|min:1|max:100',
            'note' => 'nullable|string|max:255',
        ]);
        TableGameUserLimit::updateOrCreate(['user_id' => $data['user_id']], [
            'banned' => $data['banned'],
            'max_open_tables' => $data['max_open_tables'] ?? null,
            'note' => $data['note'] ?? null,
            'updated_by' => auth('admin')->id(),
        ]);
        return back()->withNotify([['success', 'Table access settings updated.']]);
    }
}

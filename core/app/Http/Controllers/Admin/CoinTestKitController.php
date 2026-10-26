<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CoinListing;
use App\Models\SupportAgent;
use App\Models\User;
use App\Services\CoinLedger;
use App\Services\CoinMarketplace;
use App\Services\CoinPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Concurrency;

/**
 * Hidden behind general_settings.coin_marketplace_test_kit_enabled (off by default) - lets the
 * admin stand up a full set of test accounts and sample data by hand, since there are no real
 * players to test against yet. Every action here is clearly labeled as test-kit data; nothing
 * it does is reachable once the setting is off.
 */
class CoinTestKitController extends Controller
{
    public function index()
    {
        $this->assertEnabled();
        $pageTitle = 'Marketplace Test Kit';
        $testAgents = SupportAgent::where('username', 'like', 'testkit_%')->get();
        $testUsers = User::where('username', 'like', 'testkit_%')->get();
        $sampleListings = CoinListing::whereIn('seller_user_id', $testUsers->pluck('id'))->latest()->get();

        return view('admin.coin.test_kit', compact('pageTitle', 'testAgents', 'testUsers', 'sampleListings'));
    }

    public function createTestAgent(Request $request)
    {
        $this->assertEnabled();
        $type = $request->input('type', 'agent'); // agent or supervisor
        $username = 'testkit_agent_' . Str::random(6);
        $password = Str::random(14);

        $agent = SupportAgent::forceCreate([
            'name' => 'Test ' . ucfirst($type) . ' ' . substr($username, -6),
            'email' => $username . '@testkit.local',
            'username' => $username,
            'password' => Hash::make($password),
            'is_active' => true,
            'is_payment_agent' => true,
            'is_selling_coins' => false,
            'is_marketplace_supervisor' => $type === 'supervisor',
        ]);

        return back()->withNotify([['success', "Created {$type} '{$username}' / password: {$password} - save this now, it will not be shown again."]]);
    }

    public function createTestUser(Request $request)
    {
        $this->assertEnabled();
        $username = 'testkit_user_' . Str::random(6);
        $id = DB::table('users')->insertGetId([
            'username' => $username,
            'email' => $username . '@testkit.local',
            'password' => Hash::make(Str::random(14)),
            'balance' => 0,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->withNotify([['success', "Created test user '{$username}' (id #{$id})."]]);
    }

    /** A direct mint, not the real two-approver float-grant flow - clearly a test-kit shortcut, logged as such in the ledger reason. */
    public function grantTestFloat(Request $request)
    {
        $this->assertEnabled();
        $data = $request->validate(['support_agent_id' => 'required|integer|exists:support_agents,id', 'amount' => 'required|integer|gt:0']);

        app(CoinLedger::class)->mint('agent', $data['support_agent_id'], (string) $data['amount'], 'Test kit float grant', 'testkit_float:' . $data['support_agent_id'] . ':' . now()->timestamp, null, null, 'admin', auth('admin')->id());

        return back()->withNotify([['success', 'Test float minted to the agent wallet.']]);
    }

    public function grantTestCoins(Request $request)
    {
        $this->assertEnabled();
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'amount' => 'required|integer|gt:0']);

        app(CoinLedger::class)->mint('user', $data['user_id'], (string) $data['amount'], 'Test kit coin grant', 'testkit_coins:' . $data['user_id'] . ':' . now()->timestamp, null, null, 'admin', auth('admin')->id());

        return back()->withNotify([['success', 'Test coins minted to the user wallet.']]);
    }

    public function setDemoRates()
    {
        $this->assertEnabled();
        $results = app(CoinPricing::class)->recomputeAll();
        $failed = array_filter($results, fn ($r) => !$r['ok']);

        return back()->withNotify([[$failed ? 'error' : 'success', $failed ? ('Recomputed with errors: ' . implode(', ', array_map(fn ($r) => $r['error'], $failed))) : 'Demo rates computed for all enabled currencies.']]);
    }

    public function createSampleListing(Request $request)
    {
        $this->assertEnabled();
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'amount' => 'required|integer|gt:0']);
        $user = User::findOrFail($data['user_id']);

        try {
            app(CoinMarketplace::class)->createListing($user, (string) $data['amount']);
        } catch (\InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        return back()->withNotify([['success', 'Sample listing created.']]);
    }

    public function simulateTimeout(Request $request)
    {
        $this->assertEnabled();
        $data = $request->validate(['listing_id' => 'required|integer|exists:coin_listings,id', 'support_agent_id' => 'required|integer|exists:support_agents,id']);

        $listing = CoinListing::findOrFail($data['listing_id']);
        $agent = SupportAgent::findOrFail($data['support_agent_id']);

        try {
            $trade = app(CoinMarketplace::class)->claimListing($listing, $agent);
        } catch (\InvalidArgumentException $exception) {
            return back()->withNotify([['error', $exception->getMessage()]]);
        }

        \App\Models\CoinTrade::whereKey($trade->id)->update(['payment_deadline_at' => now()->subMinute()]);
        $expired = app(CoinMarketplace::class)->sweepPaymentWindowExpiry();

        return back()->withNotify([['success', "Simulated a payment-window timeout on trade #{$trade->id} - it should now show as expired and the listing should be back on sale. Swept {$expired} trade(s)."]]);
    }

    /** Exercise row-lock safety using five genuinely concurrent MySQL worker processes. */
    public function concurrencySelfTest()
    {
        $this->assertEnabled();
        abort_unless(DB::connection()->getDriverName() === 'mysql', 422, 'Concurrency self-test requires MySQL/InnoDB.');

        $tables = ['coin_vendor_applications', 'coin_vendors', 'coin_wallets', 'coin_ledger_entries', 'coin_listings', 'coin_trades'];
        $engines = DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->whereIn('TABLE_NAME', $tables)->pluck('ENGINE', 'TABLE_NAME');
        $wrongEngines = collect($tables)->filter(fn ($table) => strtoupper((string) $engines->get($table)) !== 'INNODB')->values();
        abort_if($wrongEngines->isNotEmpty(), 500, 'Concurrency test stopped: these required tables are missing or not InnoDB: ' . $wrongEngines->implode(', '));

        $country = DB::table('countries')->where('coin_marketplace_enabled', true)->first();
        abort_unless($country, 422, 'Concurrency test needs one country enabled for the Coin Marketplace.');
        $suffix = Str::lower(Str::random(8));
        $userId = DB::table('users')->insertGetId([
            'username' => 'testkit_race_' . $suffix,
            'email' => 'testkit_race_' . $suffix . '@testkit.local',
            'password' => Hash::make(Str::random(32)),
            'balance' => 0,
            'status' => 1,
            'country_id' => $country->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $seller = User::findOrFail($userId);
        $amount = '100';
        app(CoinLedger::class)->mint('user', $seller->id, $amount, 'TEST DATA: marketplace concurrency self-test', 'testkit_race_mint:' . $suffix, null, null, 'admin', auth('admin')->id());
        $listing = app(CoinMarketplace::class)->createListing($seller, $amount);

        $agentIds = collect(range(1, 5))->map(function () {
            $username = 'testkit_race_' . Str::lower(Str::random(6));
            return SupportAgent::forceCreate([
                'name' => 'TEST DATA concurrency claimant',
                'email' => $username . '@testkit.local',
                'username' => $username,
                'password' => Hash::make(Str::random(32)),
                'is_active' => true,
                'is_payment_agent' => true,
                'is_selling_coins' => false,
            ])->id;
        });

        $listingId = $listing->id;
        $results = Concurrency::run($agentIds->map(fn ($agentId) => function () use ($listingId, $agentId) {
            try {
                $trade = app(CoinMarketplace::class)->claimListing(CoinListing::findOrFail($listingId), SupportAgent::findOrFail($agentId));
                return ['ok' => true, 'trade_id' => $trade->id];
            } catch (\Throwable $exception) {
                return ['ok' => false, 'message' => $exception->getMessage()];
            }
        })->all());

        $successes = collect($results)->where('ok', true)->count();
        $reconciliation = app(CoinLedger::class)->reconcile();
        $summary = "TEST DATA only — listing #{$listing->id}; {$successes}/5 concurrent claims succeeded; reconciliation " . ($reconciliation['ok'] ? 'passed' : 'FAILED') . '. Exactly one claim must succeed.';
        $passed = $successes === 1 && $reconciliation['ok'];

        return back()->withNotify([[$passed ? 'success' : 'error', $summary]]);
    }

    private function assertEnabled(): void
    {
        abort_unless((bool) gs('coin_marketplace_test_kit_enabled'), 404);
    }
}

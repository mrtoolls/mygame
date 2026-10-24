<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentCoinFloatGrant;
use App\Models\SupportAgent;
use App\Services\CoinLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Agent coin float: an admin-granted top-up of an agent's coin wallet (so they can buy coins
 * from sellers on the marketplace without having bought them through a gateway themselves).
 * Mandatory written reason, and - unless allow_single_approver is on (testing only, shows a
 * warning banner in the view) - the admin who approves must be a different admin than the one
 * who requested it.
 */
class AgentCoinFloatController extends Controller
{
    public function index()
    {
        $pageTitle = 'Agent Coin Float Grants';
        $grants = AgentCoinFloatGrant::with(['agent', 'requestedBy', 'approvedBy'])->latest()->paginate(getPaginate());
        $agents = SupportAgent::where('is_payment_agent', true)->orWhere('is_active', true)->orderBy('name')->get();

        return view('admin.coin.float_grants', compact('pageTitle', 'grants', 'agents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'support_agent_id' => 'required|integer|exists:support_agents,id',
            'amount' => 'required|numeric|gt:0',
            'reason' => 'required|string|max:1000',
        ]);

        AgentCoinFloatGrant::create([
            'support_agent_id' => $data['support_agent_id'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'requested_by_admin_id' => auth('admin')->id(),
            'status' => 'pending',
        ]);

        return back()->withNotify([['success', 'Float grant requested - it needs a second admin to approve it before coins are issued.']]);
    }

    public function approve(Request $request, int $id)
    {
        $grant = AgentCoinFloatGrant::findOrFail($id);
        if ($grant->status !== 'pending') {
            return back()->withNotify([['error', 'This grant has already been decided.']]);
        }

        $singleApproverAllowed = (bool) gs('allow_single_approver');
        if (!$singleApproverAllowed && (int) $grant->requested_by_admin_id === (int) auth('admin')->id()) {
            return back()->withNotify([['error', 'A different admin must approve this grant (single-approver mode is off).']]);
        }

        DB::transaction(function () use ($grant) {
            $locked = AgentCoinFloatGrant::whereKey($grant->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending') return;

            $locked->status = 'approved';
            $locked->approved_by_admin_id = auth('admin')->id();
            $locked->decided_at = now();
            $locked->save();

            app(CoinLedger::class)->mint(
                'agent',
                $locked->support_agent_id,
                (string) $locked->amount,
                'Admin-approved float grant #' . $locked->id . ': ' . $locked->reason,
                'float_grant:' . $locked->id,
                'AgentCoinFloatGrant',
                $locked->id,
                'admin',
                auth('admin')->id()
            );
        });

        return back()->withNotify([['success', 'Float grant approved and coins issued to the agent.']]);
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);
        $grant = AgentCoinFloatGrant::findOrFail($id);
        if ($grant->status !== 'pending') {
            return back()->withNotify([['error', 'This grant has already been decided.']]);
        }

        $grant->status = 'rejected';
        $grant->approved_by_admin_id = auth('admin')->id();
        $grant->decided_at = now();
        if ($data['reason'] ?? null) $grant->reason .= "\n\nRejected: " . $data['reason'];
        $grant->save();

        return back()->withNotify([['success', 'Float grant rejected.']]);
    }
}

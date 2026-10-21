<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Constants\Status;
use App\Models\SupportAgent;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SupportAgentController extends Controller
{
    public function index()
    {
        $pageTitle = 'Live Support Agents';
        $agents = SupportAgent::withCount(['conversations as open_chats_count' => fn ($query) => $query->where('channel', 'live')->where('status', '!=', Status::TICKET_CLOSE)])
            ->orderByDesc('id')->paginate(getPaginate());

        return view('admin.support.agents.index', compact('pageTitle', 'agents'));
    }

    public function create()
    {
        $pageTitle = 'Add Support Agent';
        $agent = new SupportAgent();
        $countries = Schema::hasTable('countries') ? Country::with('currency')->where('is_enabled', true)->where('is_allowed', true)->orderBy('name')->get() : collect();

        return view('admin.support.agents.form', compact('pageTitle', 'agent', 'countries'));
    }

    public function store(Request $request)
    {
        $countryRules = Schema::hasTable('countries')
            ? [Rule::requiredIf($request->boolean('is_payment_agent')), 'nullable', 'integer', Rule::exists('countries', 'id')->where(fn ($query) => $query->where('is_enabled', true)->where('is_allowed', true))]
            : ['nullable', 'integer'];
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:support_agents,email'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:60', 'unique:support_agents,username'],
            'password' => ['required', 'string', 'min:10', 'max:100'],
            'title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'is_active' => ['nullable', 'boolean'],
            'is_payment_agent' => ['nullable', 'boolean'],
            'payment_details' => ['required_if:is_payment_agent,1', 'nullable', 'string', 'max:5000'],
            'country_id' => $countryRules,
            'is_verified' => ['nullable', 'boolean'],
            'speed_override' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $agent = new SupportAgent($data);
        $agent->password = Hash::make($data['password']);
        $agent->is_active = $request->boolean('is_active', true);
        $agent->is_payment_agent = $request->boolean('is_payment_agent');
        $agent->payment_details = $agent->is_payment_agent ? ($data['payment_details'] ?? null) : null;
        $agent->is_verified = $request->boolean('is_verified');
        $agent->speed_override = $request->filled('speed_override') ? $request->integer('speed_override') : null;
        if (!empty($data['country_id']) && Schema::hasTable('countries')) {
            $country = Country::with('currency')->findOrFail($data['country_id']);
            if (!$country->currency?->enabled) return back()->withErrors(['country_id' => 'Choose a country with an enabled wallet currency.'])->withInput();
            $agent->country_id = $country->id;
            $agent->currency_id = $country->currency_id;
        }
        if ($request->hasFile('photo')) {
            $agent->photo = $this->savePhoto($request);
        }
        $agent->save();

        return to_route('admin.support.agents.index')->withNotify([['success', 'Support agent created. Share the login details with the agent securely.']]);
    }

    public function edit(int $id)
    {
        $agent = SupportAgent::findOrFail($id);
        $pageTitle = 'Edit Support Agent';

        $countries = Schema::hasTable('countries') ? Country::with('currency')->where('is_enabled', true)->where('is_allowed', true)->orderBy('name')->get() : collect();
        $speedService = app(\App\Support\AgentSpeed::class);
        $speedScore = $speedService->scoreFor($agent);
        $fastStreakCount = $speedService->fastStreakCountThisMonth($agent);
        return view('admin.support.agents.form', compact('pageTitle', 'agent', 'countries', 'speedScore', 'fastStreakCount'));
    }

    public function update(Request $request, int $id)
    {
        $agent = SupportAgent::findOrFail($id);
        $countryRules = Schema::hasTable('countries')
            ? [Rule::requiredIf($request->boolean('is_payment_agent')), 'nullable', 'integer', Rule::exists('countries', 'id')->where(fn ($query) => $query->where('is_enabled', true)->where('is_allowed', true))]
            : ['nullable', 'integer'];
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:support_agents,email,' . $agent->id],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:60', 'unique:support_agents,username,' . $agent->id],
            'password' => ['nullable', 'string', 'min:10', 'max:100'],
            'title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'is_active' => ['nullable', 'boolean'],
            'is_payment_agent' => ['nullable', 'boolean'],
            'payment_details' => ['required_if:is_payment_agent,1', 'nullable', 'string', 'max:5000'],
            'country_id' => $countryRules,
            'is_verified' => ['nullable', 'boolean'],
            'speed_override' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $agent->fill(collect($data)->except(['password', 'photo', 'is_verified', 'speed_override'])->all());
        $agent->is_active = $request->boolean('is_active');
        $agent->is_payment_agent = $request->boolean('is_payment_agent');
        $agent->payment_details = $agent->is_payment_agent ? ($data['payment_details'] ?? null) : null;
        $agent->is_verified = $request->boolean('is_verified');
        $agent->speed_override = $request->filled('speed_override') ? $request->integer('speed_override') : null;
        if (!empty($data['country_id']) && Schema::hasTable('countries')) {
            $country = Country::with('currency')->findOrFail($data['country_id']);
            if (!$country->currency?->enabled) return back()->withErrors(['country_id' => 'Choose a country with an enabled wallet currency.'])->withInput();
            if (Schema::hasColumn('gateway_currencies', 'support_agent_id') && DB::table('gateway_currencies')->where('support_agent_id', $agent->id)->whereRaw('UPPER(currency) <> ?', [strtoupper($country->currency->code)])->exists()) {
                return back()->withErrors(['country_id' => 'Update or remove this agent’s deposit methods before changing their country currency.'])->withInput();
            }
            if (Schema::hasColumn('withdraw_methods', 'support_agent_id') && DB::table('withdraw_methods')->where('support_agent_id', $agent->id)->whereRaw('UPPER(currency) <> ?', [strtoupper($country->currency->code)])->exists()) {
                return back()->withErrors(['country_id' => 'Update or remove this agent’s withdrawal methods before changing their country currency.'])->withInput();
            }
            $agent->country_id = $country->id;
            $agent->currency_id = $country->currency_id;
        }
        if (!empty($data['password'])) {
            $agent->password = Hash::make($data['password']);
        }
        if ($request->hasFile('photo')) {
            $this->deletePhoto($agent->photo);
            $agent->photo = $this->savePhoto($request);
        }
        $agent->save();

        return to_route('admin.support.agents.index')->withNotify([['success', 'Support agent updated.']]);
    }

    public function status(int $id)
    {
        $agent = SupportAgent::findOrFail($id);
        $agent->is_active = !$agent->is_active;
        $agent->save();

        return back()->withNotify([['success', 'Support agent availability updated.']]);
    }

    public function destroy(int $id)
    {
        $agent = SupportAgent::findOrFail($id);
        $this->deletePhoto($agent->photo);
        $agent->delete();

        return back()->withNotify([['success', 'Support agent removed.']]);
    }

    private function savePhoto(Request $request): string
    {
        $directory = public_path('assets/images/support-agents');
        File::ensureDirectoryExists($directory);
        $photo = $request->file('photo');
        $filename = Str::uuid() . '.' . $photo->getClientOriginalExtension();
        $photo->move($directory, $filename);

        return $filename;
    }

    private function deletePhoto(?string $filename): void
    {
        if ($filename) {
            $path = public_path('assets/images/support-agents/' . basename($filename));
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }
}

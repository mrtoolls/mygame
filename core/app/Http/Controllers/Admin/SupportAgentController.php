<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Constants\Status;
use App\Models\SupportAgent;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
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
        $data = $this->validatePaymentMethods($request, $this->baseRules($request, null));

        $agent = new SupportAgent(collect($data)->except(['payment_methods', 'payment_instructions'])->all());
        $agent->password = Hash::make($data['password']);
        $agent->is_active = $request->boolean('is_active', true);
        $agent->is_payment_agent = $request->boolean('is_payment_agent');
        $agent->payment_details = $this->buildPaymentDetails($agent->is_payment_agent, $data);
        $agent->is_verified = $request->boolean('is_verified');
        $agent->speed_override = $request->filled('speed_override') ? $request->integer('speed_override') : null;
        $agent->is_moderator = $request->boolean('is_moderator');
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
        $data = $this->validatePaymentMethods($request, $this->baseRules($request, $agent->id));

        $agent->fill(collect($data)->except(['password', 'photo', 'is_verified', 'speed_override', 'is_moderator', 'payment_methods', 'payment_instructions'])->all());
        $agent->is_active = $request->boolean('is_active');
        $agent->is_payment_agent = $request->boolean('is_payment_agent');
        $agent->payment_details = $this->buildPaymentDetails($agent->is_payment_agent, $data);
        $agent->is_verified = $request->boolean('is_verified');
        $agent->speed_override = $request->filled('speed_override') ? $request->integer('speed_override') : null;
        $agent->is_moderator = $request->boolean('is_moderator');
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

    private function baseRules(Request $request, ?int $agentId): array
    {
        $countryRules = Schema::hasTable('countries')
            ? [Rule::requiredIf($request->boolean('is_payment_agent')), 'nullable', 'integer', Rule::exists('countries', 'id')->where(fn ($query) => $query->where('is_enabled', true)->where('is_allowed', true))]
            : ['nullable', 'integer'];

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('support_agents', 'email')->ignore($agentId)],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:60', Rule::unique('support_agents', 'username')->ignore($agentId)],
            'password' => $agentId ? ['nullable', 'string', 'min:10', 'max:100'] : ['required', 'string', 'min:10', 'max:100'],
            'title' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'is_active' => ['nullable', 'boolean'],
            'is_payment_agent' => ['nullable', 'boolean'],
            'country_id' => $countryRules,
            'is_verified' => ['nullable', 'boolean'],
            'speed_override' => ['nullable', 'integer', 'between:0,100'],
            'is_moderator' => ['nullable', 'boolean'],
        ];
    }

    // Payment methods are a repeatable, typed list (bank/mobile money/crypto/other) instead of a
    // single free-text blob, so the structured fields that matter for each type (e.g. a bank
    // transfer needs an account number, a crypto method needs a wallet address) are required for
    // that type specifically rather than the whole block being one all-or-nothing text field.
    private function validatePaymentMethods(Request $request, array $rules): array
    {
        $rules['payment_methods'] = ['nullable', 'array'];
        $rules['payment_methods.*.type'] = ['required', 'in:bank,mobile_money,crypto,other'];
        $rules['payment_methods.*.label'] = ['nullable', 'string', 'max:120'];
        $rules['payment_methods.*.bank_name'] = ['nullable', 'string', 'max:160'];
        $rules['payment_methods.*.account_number'] = ['nullable', 'string', 'max:60'];
        $rules['payment_methods.*.account_name'] = ['nullable', 'string', 'max:160'];
        $rules['payment_methods.*.network'] = ['nullable', 'string', 'max:60'];
        $rules['payment_methods.*.wallet_address'] = ['nullable', 'string', 'max:200'];
        $rules['payment_methods.*.memo'] = ['nullable', 'string', 'max:120'];
        $rules['payment_methods.*.provider'] = ['nullable', 'string', 'max:120'];
        $rules['payment_methods.*.phone_number'] = ['nullable', 'string', 'max:60'];
        $rules['payment_methods.*.details'] = ['nullable', 'string', 'max:500'];
        $rules['payment_instructions'] = ['nullable', 'string', 'max:2000'];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
        $validator->after(function ($validator) use ($request) {
            if (!$request->boolean('is_payment_agent')) {
                return;
            }
            $methods = array_values(array_filter((array) $request->input('payment_methods', []), fn ($m) => !empty($m['type'])));
            if (!count($methods) && !$request->filled('payment_instructions')) {
                $validator->errors()->add('payment_methods', 'Add at least one payment method, or general instructions, for a payment agent.');
                return;
            }
            foreach ($methods as $index => $method) {
                if ($method['type'] === 'bank') {
                    foreach (['bank_name' => 'Bank name', 'account_number' => 'Account number', 'account_name' => 'Account name'] as $field => $label) {
                        if (empty($method[$field])) {
                            $validator->errors()->add("payment_methods.$index.$field", "$label is required for a bank transfer method.");
                        }
                    }
                } elseif ($method['type'] === 'crypto') {
                    if (empty($method['wallet_address'])) {
                        $validator->errors()->add("payment_methods.$index.wallet_address", 'Wallet address is required for a crypto method.');
                    }
                } elseif ($method['type'] === 'mobile_money') {
                    foreach (['provider' => 'Provider', 'phone_number' => 'Phone number', 'account_name' => 'Account name'] as $field => $label) {
                        if (empty($method[$field])) {
                            $validator->errors()->add("payment_methods.$index.$field", "$label is required for a mobile money method.");
                        }
                    }
                } elseif ($method['type'] === 'other' && empty($method['details'])) {
                    $validator->errors()->add("payment_methods.$index.details", 'Details are required for a custom payment method.');
                }
            }
        });

        return $validator->validate();
    }

    // Only the fields that belong to a method's own type are kept - e.g. switching a row from
    // bank to crypto in the UI leaves the old bank_name/account_number values still sitting in
    // the submitted form data (they're just hidden, not cleared), so without this filter they'd
    // get saved alongside the crypto fields instead of being dropped.
    private function buildPaymentDetails(bool $isPaymentAgent, array $data): ?array
    {
        if (!$isPaymentAgent) {
            return null;
        }
        $fieldsByType = [
            'bank' => ['bank_name', 'account_number', 'account_name'],
            'mobile_money' => ['provider', 'phone_number', 'account_name'],
            'crypto' => ['network', 'wallet_address', 'memo'],
            'other' => ['details'],
        ];
        $methods = [];
        foreach ((array) ($data['payment_methods'] ?? []) as $method) {
            $type = $method['type'] ?? null;
            if (empty($type) || !isset($fieldsByType[$type])) {
                continue;
            }
            $clean = ['type' => $type, 'label' => trim((string) ($method['label'] ?? ''))];
            foreach ($fieldsByType[$type] as $field) {
                if (!empty($method[$field])) {
                    $clean[$field] = trim((string) $method[$field]);
                }
            }
            $methods[] = $clean;
        }

        return ['methods' => $methods, 'instructions' => $data['payment_instructions'] ?? null];
    }

    // Was hand-rolling its own upload (public_path('assets/images/support-agents'), which on
    // this deployment resolves to a location "assets/..." URLs aren't actually served from -
    // the photo saved successfully but every agent card showed a broken image - and trusted the
    // client-supplied filename extension for what got written to disk, the same pattern that
    // was a confirmed RCE elsewhere in this app (FileManager.php) before it was fixed. Routed
    // through the same fileUploader()/FileManager helper every other upload in this app already
    // uses, which fixes both: the real bare-relative-path convention, and content-sniffed
    // extension detection instead of trusting the client.
    private function savePhoto(Request $request): string
    {
        return fileUploader($request->file('photo'), 'assets/images/support-agents', '480x480');
    }

    private function deletePhoto(?string $filename): void
    {
        if ($filename) {
            $path = 'assets/images/support-agents/' . basename($filename);
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }
}

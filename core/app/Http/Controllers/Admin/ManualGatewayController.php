<?php

namespace App\Http\Controllers\Admin;

use App\Constants\Status;
use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Models\Gateway;
use App\Models\GatewayCurrency;
use App\Models\SupportAgent;
use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Lib\RequiredConfig;
use Illuminate\Support\Facades\Schema;

class ManualGatewayController extends Controller {
    public function index() {
        $pageTitle = 'Manual Gateways';
        $gateways  = Gateway::manual()->with('singleCurrency.supportAgent:id,name')->orderBy('id', 'desc')->get();
        return view('admin.gateways.manual.list', compact('pageTitle', 'gateways'));
    }

    public function create() {
        $pageTitle = 'Add Manual Gateway';
        $query = SupportAgent::paymentAgents();
        if (Schema::hasTable('countries')) $query->with('country.currency');
        $paymentAgents = $query->orderBy('name')->get();
        return view('admin.gateways.manual.create', compact('pageTitle', 'paymentAgents'));
    }

    public function store(Request $request) {
        $formProcessor = new FormProcessor();
        $this->validation($request, $formProcessor);

        $lastMethod = Gateway::manual()->orderBy('id', 'desc')->first();
        $methodCode = 1000;
        if ($lastMethod) {
            $methodCode = $lastMethod->code + 1;
        }

        $generate = $formProcessor->generate('manual_deposit');

        $filename = null;
        if ($request->hasFile('image')) {
            try {
                $filename = fileUploader($request->image, getFilePath('gateway'));
            } catch (\Exception $exp) {
                $notify[] = ['errors', 'Image could not be uploaded'];
                return back()->withNotify($notify);
            }
        }

        $method                       = new Gateway();
        $method->code                 = $methodCode;
        $method->form_id              = @$generate->id ?? 0;
        $method->name                 = $request->name;
        $method->image                = $filename;
        $method->alias                = strtolower(trim(str_replace(' ', '_', $request->name)));
        $method->status               = Status::ENABLE;
        $method->gateway_parameters   = json_encode([]);
        $method->supported_currencies = [];
        $method->crypto               = $request->boolean('crypto') ? Status::ENABLE : Status::DISABLE;
        $method->description          = $request->instruction;
        $method->save();

        $gatewayCurrency                 = new GatewayCurrency();
        $gatewayCurrency->name           = $request->name;
        $gatewayCurrency->gateway_alias  = strtolower(trim(str_replace(' ', '_', $request->name)));
        $gatewayCurrency->currency       = $request->currency;
        $gatewayCurrency->symbol         = '';
        $gatewayCurrency->method_code    = $methodCode;
        $gatewayCurrency->support_agent_id = $request->input('payment_agent_id');
        $gatewayCurrency->min_amount     = $request->min_limit;
        $gatewayCurrency->max_amount     = $request->max_limit;
        $gatewayCurrency->fixed_charge   = $request->fixed_charge;
        $gatewayCurrency->percent_charge = $request->percent_charge;
        $gatewayCurrency->rate           = $request->rate;
        if ($request->boolean('crypto')) {
            $gatewayCurrency->wallet_address = $request->wallet_address;
            $gatewayCurrency->network_label  = $request->network_label;
        }
        $gatewayCurrency->save();

        RequiredConfig::configured('deposit_method');

        $notify[] = ['success', $method->name . ' Manual gateway has been added.'];
        return back()->withNotify($notify);
    }

    public function edit($alias) {
        $pageTitle = 'Edit Manual Gateway';
        $method    = Gateway::manual()->with('singleCurrency')->where('alias', $alias)->firstOrFail();
        $form      = $method->form;
        $query = SupportAgent::paymentAgents();
        if (Schema::hasTable('countries')) $query->with('country.currency');
        $paymentAgents = $query->orderBy('name')->get();
        return view('admin.gateways.manual.edit', compact('pageTitle', 'method', 'form', 'paymentAgents'));
    }

    public function update(Request $request, $code) {
        $formProcessor = new FormProcessor();
        $this->validation($request, $formProcessor, true);

        $method = Gateway::manual()->where('code', $code)->firstOrFail();

        $filename = $method->image;
        if ($request->hasFile('image')) {
            try {
                $filename = fileUploader($request->image, getFilePath('gateway'), old: $filename);
            } catch (\Exception $exp) {
                $notify[] = ['errors', 'Image could not be uploaded'];
                return back()->withNotify($notify);
            }
        }

        $generate                     = $formProcessor->generate('manual_deposit', true, 'id', $method->form_id);
        $method->name                 = $request->name;
        $method->image                = $filename;
        $method->alias                = strtolower(trim(str_replace(' ', '_', $request->name)));
        $method->gateway_parameters   = json_encode([]);
        $method->supported_currencies = [];
        $method->crypto               = $request->boolean('crypto') ? Status::ENABLE : Status::DISABLE;
        $method->description          = $request->instruction;
        $method->form_id              = @$generate->id ?? 0;
        $method->save();

        $singleCurrency = $method->singleCurrency;
        if ($singleCurrency) {
            $singleCurrency->name           = $request->name;
            $singleCurrency->gateway_alias  = strtolower(trim(str_replace(' ', '_', $method->name)));
            $singleCurrency->currency       = $request->currency;
            $singleCurrency->symbol         = '';
            $singleCurrency->min_amount     = $request->min_limit;
            $singleCurrency->max_amount     = $request->max_limit;
            $singleCurrency->fixed_charge   = $request->fixed_charge;
            $singleCurrency->percent_charge = $request->percent_charge;
            $singleCurrency->rate           = $request->rate;
            $singleCurrency->support_agent_id = $request->input('payment_agent_id');
            if ($request->boolean('crypto')) {
                $singleCurrency->wallet_address = $request->wallet_address;
                $singleCurrency->network_label  = $request->network_label;
            } else {
                $singleCurrency->wallet_address = null;
                $singleCurrency->network_label  = null;
            }
            $singleCurrency->save();
        }

        $notify[] = ['success', $method->name . ' manual gateway updated successfully'];
        return to_route('admin.gateway.manual.edit', [$method->alias])->withNotify($notify);
    }

    private function validation($request, $formProcessor, $isUpdate = false) {
        $validation = [
            'name'           => 'required',
            'rate'           => 'required|numeric|gt:0',
            'currency'       => 'required',
            'min_limit'      => 'required|numeric|gt:0',
            'max_limit'      => 'required|numeric|gt:min_limit',
            'fixed_charge'   => 'required|numeric|gte:0',
            'percent_charge' => 'required|numeric|between:0,100',
            'image'          => [$isUpdate ? 'nullable' : 'required', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
            'instruction'    => 'required',
            'crypto'           => 'nullable|boolean',
            'wallet_address'   => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->boolean('crypto'))],
            'network_label'    => 'nullable|string|max:60',
            'payment_agent_id' => ['nullable', 'integer', Rule::exists('support_agents', 'id')->where(fn ($query) => $query->where('is_active', true)->where('is_payment_agent', true))],
        ];

        $generatorValidation = $formProcessor->generatorValidation();
        $validation          = array_merge($validation, $generatorValidation['rules']);
        $request->validate($validation, $generatorValidation['messages']);

        // Crypto methods use the native receipt/paid-from-address fields on the new
        // deposit confirmation modal instead of the generic form builder, so the
        // required-file-field rule below only applies to non-crypto agent methods.
        if ($request->filled('payment_agent_id') && Schema::hasTable('countries')) {
            $agent = SupportAgent::paymentAgents()->with('country.currency')->findOrFail($request->integer('payment_agent_id'));
            if (!$agent->country?->is_allowed || !$agent->country?->is_enabled || strtoupper((string) $agent->country?->currency?->code) !== strtoupper((string) $request->input('currency'))) {
                throw ValidationException::withMessages(['payment_agent_id' => 'The payment agent country currency must match this manual deposit method currency.']);
            }
            if (!$request->boolean('crypto')) {
                $formTypes = $request->input('form_generator.form_type', []);
                $required = $request->input('form_generator.is_required', []);
                $hasRequiredReceipt = collect($formTypes)->contains(fn ($type, $index) => $type === 'file' && ($required[$index] ?? null) === 'required');
                if (!$hasRequiredReceipt) {
                    throw ValidationException::withMessages([
                        'form_generator' => 'Agent-assisted deposits must include a required file upload field for payment proof.',
                    ]);
                }
            }
        }
    }

    public function status($id) {
        return Gateway::changeStatus($id);
    }
}

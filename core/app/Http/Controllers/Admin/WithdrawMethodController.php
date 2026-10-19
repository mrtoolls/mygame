<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Lib\FormProcessor;
use App\Models\WithdrawMethod;
use App\Models\SupportAgent;
use App\Rules\FileTypeValidate;
use Illuminate\Http\Request;
use App\Lib\RequiredConfig;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;

class WithdrawMethodController extends Controller {
    public function methods() {
        $pageTitle = 'Withdrawal Methods';
        $methods   = WithdrawMethod::with('supportAgent:id,name')->orderBy('name')->orderBy('id')->get();
        return view('admin.withdraw.index', compact('pageTitle', 'methods'));
    }

    public function create() {
        $pageTitle = 'New Withdrawal Method';
        $query = SupportAgent::paymentAgents();
        if (Schema::hasTable('countries')) $query->with('country.currency');
        $paymentAgents = $query->orderBy('name')->get();
        return view('admin.withdraw.create', compact('pageTitle', 'paymentAgents'));
    }

    public function store(Request $request) {
        $validation = [
            'name'           => 'required',
            'rate'           => 'required|numeric|gt:0',
            'currency'       => 'required',
            'fixed_charge'   => 'required|numeric|gte:0',
            'percent_charge' => 'required|numeric|between:0,100',
            'min_limit'      => 'required|numeric|gt:fixed_charge',
            'max_limit'      => 'required|numeric|gt:min_limit',
            'image'          => ['required', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
            'instruction'    => 'required',
            'payment_agent_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('support_agents', 'id')->where(fn ($query) => $query->where('is_active', true)->where('is_payment_agent', true))],
        ];

        $formProcessor       = new FormProcessor();
        $generatorValidation = $formProcessor->generatorValidation();
        $validation          = array_merge($validation, $generatorValidation['rules']);
        $request->validate($validation, $generatorValidation['messages']);
        $this->validatePaymentAgentCurrency($request);

        $generate = $formProcessor->generate('withdraw_method');

        $filename = null;
        if ($request->hasFile('image')) {
            try {
                $filename = fileUploader($request->image, getFilePath('withdrawMethod'));
            } catch (\Exception $exp) {
                $notify[] = ['errors', 'Image could not be uploaded'];
                return back()->withNotify($notify);
            }
        }

        $method                 = new WithdrawMethod();
        $method->name           = $request->name;
        $method->image          = $filename;
        $method->form_id        = @$generate->id ?? 0;
        $method->rate           = $request->rate;
        $method->min_limit      = $request->min_limit;
        $method->max_limit      = $request->max_limit;
        $method->fixed_charge   = $request->fixed_charge;
        $method->percent_charge = $request->percent_charge;
        $method->currency       = $request->currency;
        $method->description    = $request->instruction;
        $method->support_agent_id = $request->input('payment_agent_id');
        $method->save();

        RequiredConfig::configured('withdrawal_method');

        $notify[] = ['success', 'Withdrawal method added successfully'];
        return to_route('admin.withdraw.method.index')->withNotify($notify);
    }

    public function edit($id) {
        $pageTitle = 'Update Withdrawal Method';
        $method    = WithdrawMethod::with('form')->findOrFail($id);
        $form      = $method->form;
        $query = SupportAgent::paymentAgents();
        if (Schema::hasTable('countries')) $query->with('country.currency');
        $paymentAgents = $query->orderBy('name')->get();
        return view('admin.withdraw.edit', compact('pageTitle', 'method', 'form', 'paymentAgents'));
    }

    public function update(Request $request, $id) {
        $validation = [
            'name'           => 'required',
            'rate'           => 'required|numeric|gt:0',
            'image'          => ['nullable', 'image', new FileTypeValidate(['jpg', 'jpeg', 'png'])],
            'min_limit'      => 'required|numeric|gt:0',
            'max_limit'      => 'required|numeric|gt:min_limit',
            'fixed_charge'   => 'required|numeric|gte:0',
            'percent_charge' => 'required|numeric|between:0,100',
            'currency'       => 'required',
            'instruction'    => 'required',
            'payment_agent_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('support_agents', 'id')->where(fn ($query) => $query->where('is_active', true)->where('is_payment_agent', true))],
        ];

        $formProcessor       = new FormProcessor();
        $generatorValidation = $formProcessor->generatorValidation();
        $validation          = array_merge($validation, $generatorValidation['rules']);
        $request->validate($validation, $generatorValidation['messages']);
        $this->validatePaymentAgentCurrency($request);

        $method = WithdrawMethod::findOrFail($id);

        $filename = $method->image;
        if ($request->hasFile('image')) {
            try {
                $filename = fileUploader($request->image, getFilePath('withdrawMethod'), old: $filename);
            } catch (\Exception $exp) {
                $notify[] = ['errors', 'Image could not be uploaded'];
                return back()->withNotify($notify);
            }
        }

        $generate               = $formProcessor->generate('withdraw_method', true, 'id', $method->form_id);
        $method->form_id        = @$generate->id ?? 0;
        $method->name           = $request->name;
        $method->image          = $filename;
        $method->rate           = $request->rate;
        $method->min_limit      = $request->min_limit;
        $method->max_limit      = $request->max_limit;
        $method->fixed_charge   = $request->fixed_charge;
        $method->percent_charge = $request->percent_charge;
        $method->description    = $request->instruction;
        $method->currency       = $request->currency;
        $method->support_agent_id = $request->input('payment_agent_id');
        $method->save();

        $notify[] = ['success', 'Withdrawal method updated successfully'];
        return back()->withNotify($notify);
    }

    public function status($id) {
        return WithdrawMethod::changeStatus($id);
    }

    private function validatePaymentAgentCurrency(Request $request): void
    {
        if (!$request->filled('payment_agent_id') || !Schema::hasTable('countries')) return;
        $agent = SupportAgent::paymentAgents()->with('country.currency')->findOrFail($request->integer('payment_agent_id'));
        if (!$agent->country?->is_allowed || !$agent->country?->is_enabled || strtoupper((string) $agent->country?->currency?->code) !== strtoupper((string) $request->input('currency'))) {
            throw ValidationException::withMessages(['payment_agent_id' => 'The payment agent country currency must match this withdrawal method currency.']);
        }
    }

}

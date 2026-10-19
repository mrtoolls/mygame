<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Constants\Status;
use App\Models\SupportAgent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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

        return view('admin.support.agents.form', compact('pageTitle', 'agent'));
    }

    public function store(Request $request)
    {
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
        ]);

        $agent = new SupportAgent($data);
        $agent->password = Hash::make($data['password']);
        $agent->is_active = $request->boolean('is_active', true);
        $agent->is_payment_agent = $request->boolean('is_payment_agent');
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

        return view('admin.support.agents.form', compact('pageTitle', 'agent'));
    }

    public function update(Request $request, int $id)
    {
        $agent = SupportAgent::findOrFail($id);
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
        ]);

        $agent->fill(collect($data)->except(['password', 'photo'])->all());
        $agent->is_active = $request->boolean('is_active');
        $agent->is_payment_agent = $request->boolean('is_payment_agent');
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

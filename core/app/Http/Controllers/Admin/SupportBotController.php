<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetting;
use App\Models\SupportBotTraining;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SupportBotController extends Controller
{
    public function index()
    {
        $pageTitle = 'Support Bot';
        $trainings = SupportBotTraining::orderBy('sort_order')->orderBy('id')->paginate(getPaginate());

        return view('admin.support.bot.index', compact('pageTitle', 'trainings'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'chatbot_name' => ['required', 'string', 'max:60'],
            'chatbot_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
        ]);

        $settings = GeneralSetting::first();
        $settings->chatbot_name = $data['chatbot_name'];
        if ($request->hasFile('chatbot_photo')) {
            $this->deletePhoto($settings->chatbot_photo);
            $settings->chatbot_photo = $this->savePhoto($request);
        }
        $settings->save();

        return to_route('admin.support.bot.index')->withNotify([['success', 'Support bot identity updated.']]);
    }

    public function trainingCreate()
    {
        $pageTitle = 'Add Trained Response';
        $training = new SupportBotTraining();

        return view('admin.support.bot.training_form', compact('pageTitle', 'training'));
    }

    public function trainingStore(Request $request)
    {
        $data = $this->validateTraining($request);
        SupportBotTraining::create($data);
        Cache::forget('support_bot_trainings');

        return to_route('admin.support.bot.index')->withNotify([['success', 'Trained response added.']]);
    }

    public function trainingEdit(int $id)
    {
        $pageTitle = 'Edit Trained Response';
        $training = SupportBotTraining::findOrFail($id);

        return view('admin.support.bot.training_form', compact('pageTitle', 'training'));
    }

    public function trainingUpdate(Request $request, int $id)
    {
        $training = SupportBotTraining::findOrFail($id);
        $training->update($this->validateTraining($request));
        Cache::forget('support_bot_trainings');

        return to_route('admin.support.bot.index')->withNotify([['success', 'Trained response updated.']]);
    }

    public function trainingStatus(int $id)
    {
        $training = SupportBotTraining::findOrFail($id);
        $training->is_active = !$training->is_active;
        $training->save();
        Cache::forget('support_bot_trainings');

        return back()->withNotify([['success', 'Trained response availability updated.']]);
    }

    public function trainingDestroy(int $id)
    {
        SupportBotTraining::findOrFail($id)->delete();
        Cache::forget('support_bot_trainings');

        return back()->withNotify([['success', 'Trained response removed.']]);
    }

    private function validateTraining(Request $request): array
    {
        $data = $request->validate([
            'trigger_phrases' => ['required', 'string', 'max:1000'],
            'response' => ['required', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['sort_order'] = $data['sort_order'] ?? 0;

        return $data;
    }

    private function savePhoto(Request $request): string
    {
        $directory = public_path('assets/images/chatbot');
        File::ensureDirectoryExists($directory);
        $photo = $request->file('chatbot_photo');
        $filename = Str::uuid() . '.' . $photo->getClientOriginalExtension();
        $photo->move($directory, $filename);

        return $filename;
    }

    private function deletePhoto(?string $filename): void
    {
        if ($filename) {
            $path = public_path('assets/images/chatbot/' . basename($filename));
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }
}

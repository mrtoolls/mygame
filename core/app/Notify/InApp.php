<?php

namespace App\Notify;

use App\Notify\Notifiable;
use App\Notify\NotifyProcess;
use App\Models\UserNotification;

class InApp extends NotifyProcess implements Notifiable {

    /**
     * Assign value to properties
     *
     * @return void
     */
    public function __construct() {
        $this->statusField    = 'inapp_status';
        $this->body           = 'inapp_body';
        $this->globalTemplate = 'inapp_template';
    }

    /**
     * Send notification
     *
     * Unlike email/sms/push, the in-app channel's "log" IS the user-visible feed item (the bell
     * dropdown reads straight from user_notifications), so this writes there directly instead of
     * going through NotifyProcess::createLog(), which only produces an audit row with no
     * icon/url/actor fields a dropdown needs.
     *
     * @return void|bool
     */
    public function send()
    {
        if (!$this->user || !@$this->user->id) {
            return false;
        }

        $message = $this->getMessage();
        if (!$message) {
            return false;
        }

        $title = $this->template->inapp_title ?? null;
        if ($title) {
            $title = $this->replaceTemplateShortCode($title);
        }

        UserNotification::create([
            'user_id'      => $this->user->id,
            'type'         => $this->templateName,
            'title'        => $title,
            'message'      => strip_tags($message),
            'icon'         => $this->shortCodes['notif_icon'] ?? null,
            'url'          => $this->shortCodes['notif_url'] ?? null,
            'actor_name'   => $this->shortCodes['notif_actor_name'] ?? null,
            'actor_avatar' => $this->shortCodes['notif_actor_avatar'] ?? null,
        ]);
    }

    public function prevConfiguration() {
        if ($this->user) {
            $this->receiverName = $this->user->fullname;
            $this->toAddress    = $this->user->username;
        }
    }
}

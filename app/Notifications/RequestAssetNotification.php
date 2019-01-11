<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;

class RequestAssetNotification extends Notification
{
    /**
     * @var
     */
    private $params;

    /**
     * Create a new notification instance.
     *
     * @param $params
     */
    public function __construct($params)
    {
        $this->target = $params['target'];
        $this->item = $params['item'];
        $this->item_type = $params['item_type'];
        $this->item_quantity = $params['item_quantity'];
        $this->note = '';
        $this->last_checkout = '';
        $this->expected_checkin = '';
        $this->requested_date = \App\Helpers\Helper::getFormattedDateObject($params['requested_date'], 'datetime',
            false);
        $this->settings = Setting::getSettings();

        if (array_key_exists('note', $params)) {
            $this->note = $params['note'];
        }

        if ($this->item->last_checkout) {
            $this->last_checkout = \App\Helpers\Helper::getFormattedDateObject($this->item->last_checkout, 'date',
                false);
        }

        if ($this->item->expected_checkin) {
            $this->expected_checkin = \App\Helpers\Helper::getFormattedDateObject($this->item->expected_checkin, 'date',
                false);
        }


    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via()
    {

        $notifyBy = [];

        if (Setting::getSettings()->slack_endpoint!='') {
            \Log::debug('use slack');
            $notifyBy[] = 'slack';
        }


        $notifyBy[] = 'mail';

        return $notifyBy;
    }

    public function toSlack()
    {


        $target = $this->target;
        $qty = $this->item_quantity;
        $item = $this->item;
        $note = $this->note;
        $botname = ($this->settings->slack_botname) ? $this->settings->slack_botname : 'Snipe-Bot' ;

        $fields = [
            'QTY' => $qty,
            'Requested By' => '<'.$target->present()->viewUrl().'|'.$target->present()->fullName().'>',
        ];

        return (new SlackMessage)
            ->content(trans('mail.Item_Requested'))
            ->from($botname)
            ->attachment(function ($attachment) use ($item, $note, $fields) {
                $attachment->title(htmlspecialchars_decode($item->present()->name), $item->present()->viewUrl())
                    ->fields($fields)
                    ->content($note);
            });
    }
    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail()
    {

        $fields = [];

        // Check if the item has custom fields associated with it
        if (($this->item->model) && ($this->item->model->fieldset)) {
            $fields = $this->item->model->fieldset->fields;
        }

        $message = (new MailMessage)->markdown('notifications.markdown.asset-requested',
            [
                'item'          => $this->item,
                'note'          => $this->note,
                'requested_by'  => $this->target,
                'requested_date' => $this->requested_date,
                'fields'        => $fields,
                'last_checkout' => $this->last_checkout,
                'expected_checkin'  => $this->expected_checkin,
                'intro_text'        => trans('mail.a_user_requested'),
                'qty'           => $this->item_quantity,
            ])
            ->subject(trans('mail.Item_Requested'));


        return $message;


    }

}

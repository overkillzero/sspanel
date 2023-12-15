<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Setting;
use Exception;
use Mailgun\Mailgun as MG;
use Psr\Http\Client\ClientExceptionInterface;
use function basename;

final class Mailgun extends Base
{
    private MG $mg;
    private mixed $domain;
    private mixed $sender;

    public function __construct()
    {
        $configs = Setting::getClass('mailgun');

        $this->mg = MG::create($configs['mailgun_key']);
        $this->domain = $configs['mailgun_domain'];
        $this->sender = $configs['mailgun_sender_name'] . ' <' . $configs['mailgun_sender'] . '>';
    }

    /**
     * @throws Exception
     * @throws ClientExceptionInterface
     */
    public function send($to, $subject, $text, $files): void
    {
        $inline = [];

        if ($files !== []) {
            foreach ($files as $file_raw) {
                $inline[] = ['filePath' => $file_raw, 'filename' => basename($file_raw)];
            }
        }

        $this->mg->messages()->send($this->domain, [
            'from' => $this->sender,
            'to' => $to,
            'subject' => $subject,
            'html' => $text,
            'inline' => $inline,
        ]);
    }
}

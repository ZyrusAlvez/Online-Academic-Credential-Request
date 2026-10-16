<?php

namespace App\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;

/**
 * Never sends mail to reserved example domains (RFC 2606 / 6761). Demo accounts use them, so demo
 * data on local and staging can't bounce or use up the Gmail sending limit.
 */
class DropUndeliverableRecipients
{
    private const RESERVED = ['example.com', 'example.net', 'example.org', 'example', 'test', 'invalid', 'localhost'];

    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;

        $to = $this->deliverable($message->getTo());
        $cc = $this->deliverable($message->getCc());
        $bcc = $this->deliverable($message->getBcc());

        if ($to === [] && $cc === [] && $bcc === []) {
            return false;
        }

        $headers = $message->getHeaders();
        $headers->remove('To');
        $headers->remove('Cc');
        $headers->remove('Bcc');

        if ($to !== []) {
            $message->to(...$to);
        }
        if ($cc !== []) {
            $message->cc(...$cc);
        }
        if ($bcc !== []) {
            $message->bcc(...$bcc);
        }

        return null;
    }

    /**
     * @param  array<Address>  $addresses
     * @return list<Address>
     */
    private function deliverable(array $addresses): array
    {
        return array_values(array_filter($addresses, function (Address $address): bool {
            $domain = strtolower(substr(strrchr($address->getAddress(), '@') ?: '', 1));

            foreach (self::RESERVED as $reserved) {
                if ($domain === $reserved || str_ends_with($domain, '.'.$reserved)) {
                    return false;
                }
            }

            return true;
        }));
    }
}

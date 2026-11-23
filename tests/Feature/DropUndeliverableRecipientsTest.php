<?php

use App\Listeners\DropUndeliverableRecipients;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * @param  array<Address>  $addresses
 * @return list<string>
 */
function emailAddresses(array $addresses): array
{
    return array_values(array_map(fn (Address $address) => $address->getAddress(), $addresses));
}

test('mail only to reserved demo domains is never sent', function () {
    $message = (new Email)->to('student@example.com', 'ccs@mail.example.org', 'tester@demo.test');

    expect((new DropUndeliverableRecipients)->handle(new MessageSending($message)))->toBeFalse();
});

test('real recipients still get the email when others are on reserved domains', function () {
    $message = (new Email)
        ->to('student@example.com', 'maria@gmail.com')
        ->cc('staff@uphsl.edu.ph', 'lib@example.com');

    expect((new DropUndeliverableRecipients)->handle(new MessageSending($message)))->toBeNull()
        ->and(emailAddresses($message->getTo()))->toBe(['maria@gmail.com'])
        ->and(emailAddresses($message->getCc()))->toBe(['staff@uphsl.edu.ph']);
});

{{-- Help: Payment (REQ-22-03, 04). --}}
<x-faq-item question="When do I pay?">
    <p>Only after the Office of the Registrar approves your request. Its status then becomes <strong>Awaiting payment</strong>, and its page shows the amount due and the payment instructions. Please don't pay before that: a request can still be returned or declined during the evaluation.</p>
</x-faq-item>

<x-faq-item question="How do I pay?">
    <ol>
        <li>Choose <strong>Pay online</strong> on your request page, which opens the UPHSL payment portal (<a href="{{ config('uphsl.payment_url') }}" target="_blank" rel="noopener">{{ config('uphsl.payment_url') }}</a>), and pay. Keep your receipt.</li>
        <li>Back on your request page, upload your proof of payment (a JPG, PNG, WebP or PDF of up to 5 MB) with the payment reference number, the amount paid and the date paid, then choose <strong>Submit proof of payment</strong>.</li>
    </ol>
</x-faq-item>

<x-faq-item question="How much do I pay?">
    <p>The credential's fee, shown on its card on New Request and as the amount due on your request page. One request is one document.</p>
</x-faq-item>

<x-faq-item question="What happens after I upload my proof of payment?">
    <p>Your request shows <strong>Payment verification</strong> while the Office of the Registrar checks your payment against the university's official record and records your official receipt number. Uploading a proof alone doesn't mark you as paid.</p>
    <p>Once verified, your request is <strong>accepted for processing</strong>: the processing period starts, and you see your expected release date.</p>
</x-faq-item>

<x-faq-item question="My proof of payment was not accepted.">
    <p>Your request goes back to <strong>Awaiting payment</strong>, and its page shows why. Upload a clearer or correct proof the same way. Your earlier proofs stay listed on the page.</p>
</x-faq-item>

<x-faq-item question="My credential is free. Do I need to do anything?">
    <p>No. A free credential skips payment: once the evaluation is done, it is accepted for processing right away.</p>
</x-faq-item>

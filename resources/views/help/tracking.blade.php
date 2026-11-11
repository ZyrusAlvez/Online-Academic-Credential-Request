{{-- Help: Tracking & Statuses (REQ-22-03, 04). --}}
<x-faq-item question="Where do I see my requests?">
    <p>In the <a href="{{ route('student.dashboard') }}" wire:navigate>Request Tracker</a>, newest first. Search by credential or tracking number, and open a request to see its progress, the office that has your document, its dates and its history.</p>
</x-faq-item>

<x-faq-item question="What does each status mean?">
    <ul>
        <li><strong>Submitted</strong>: received, waiting for the evaluation.</li>
        <li><strong>Under evaluation</strong>: the Office of the Registrar is checking your details, records and requirements.</li>
        <li><strong>Returned for clarification</strong>: correct what its page says on <strong>Edit Request</strong> and resubmit.</li>
        <li><strong>Pending compliance</strong>: bring what its page lists to the Office of the Registrar.</li>
        <li><strong>Awaiting payment</strong>: pay and upload your proof of payment.</li>
        <li><strong>Payment verification</strong>: your payment is being checked.</li>
        <li><strong>Accepted for processing</strong>: everything is complete; the processing period has started.</li>
        <li><strong>In progress</strong>: your document is being prepared and signed by the offices on its route.</li>
        <li><strong>For final approval</strong>: back at the Office of the Registrar for a final check.</li>
        <li><strong>Ready for release</strong>: you can claim your credential.</li>
        <li><strong>Released</strong>: it has been claimed.</li>
        <li><strong>Declined</strong>: the request can't go ahead; the reason is on its page.</li>
        <li><strong>Cancelled</strong>: you cancelled it.</li>
    </ul>
</x-faq-item>

<x-faq-item question="When does the processing period start? When will my credential be ready?">
    <p>The processing period starts when your request is <strong>accepted for processing</strong> (when your payment is verified, or after the evaluation of a free credential), not when you submit it.</p>
    <p>It is counted in working days: Monday to Saturday, without holidays and office closures. Once accepted, your request page shows the processing period and the expected release date.</p>
</x-faq-item>

<x-faq-item question="Who has my document right now?">
    <p>Your request page shows the office currently holding it, such as your college's Dean or the Office of the Chancellor. Each credential passes through its offices in order, and each signs or stamps it before sending it on.</p>
</x-faq-item>

<x-faq-item question="How will I know when something changes?">
    <p>You are notified in the app (the bell at the top) and by email: when your request is received, returned to you, approved with payment instructions, when your payment is verified or not accepted, when your credential is ready for release, and if it is declined or overdue.</p>
    <p>You can turn the emails off in <a href="{{ route('settings.notifications') }}" wire:navigate>Settings → Email notifications</a>; the notifications in the app always stay on.</p>
</x-faq-item>

<x-faq-item question="My request is overdue. What happens now?">
    <p>It has passed its expected release date. You are told once, and the office holding your document is alerted too. It keeps moving through its offices, and you are notified when it is ready for release.</p>
</x-faq-item>

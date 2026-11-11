{{-- Help: Your Details & Valid ID (REQ-22-03, 04). Details verified once: spec 21. --}}
<x-faq-item question="Do I have to give my details and upload my ID on every request?">
    <p><strong>No.</strong> You fill in your personal information, academic information and valid ID once, on your first request. They are saved, so every <a href="{{ route('student.requests.create') }}" wire:navigate>New Request</a> starts filled in.</p>
    <p>The Office of the Registrar also <strong>verifies them only once</strong>, when it evaluates your first request. On your later requests they are already confirmed, as long as they haven't changed, so you don't wait for the same check again.</p>
</x-faq-item>

<x-faq-item question="How do I know whether my details are verified?">
    <p>On <a href="{{ route('student.requests.create') }}" wire:navigate>New Request</a> and in <a href="{{ route('settings.profile') }}" wire:navigate>Settings → Profile</a>, each of the sections Personal Information, Academic Information and Valid ID shows a badge:</p>
    <ul>
        <li><strong>Verified</strong>: the Office of the Registrar has checked it, and it hasn't changed since.</li>
        <li><strong>For verification</strong>: it hasn't been checked yet, or you changed it after it was checked. It is checked when the Office of the Registrar evaluates your next request.</li>
    </ul>
    <p>"For verification" never stops you from submitting a request.</p>
</x-faq-item>

<x-faq-item question="Can I still change my details after they are verified?">
    <p>Yes, at any time, in <a href="{{ route('settings.profile') }}" wire:navigate>Settings → Profile</a> or directly on New Request. The section you change goes back to <strong>For verification</strong> and is checked again on your next request; the sections you didn't change stay verified.</p>
    <p>Only change your details to correct them. A name or student number that doesn't match the university's records can get your request returned.</p>
</x-faq-item>

<x-faq-item question="I updated my details. Why does my earlier request still show the old ones?">
    <p>Each request keeps the details and ID it was sent with, so that a request already under way is never changed behind the evaluator's back. Your changes apply to your next requests.</p>
    <p>If the Office of the Registrar returns a request to you for clarification, you can correct its details on <strong>Edit Request</strong>.</p>
</x-faq-item>

<x-faq-item question="Which IDs and files are accepted?">
    <p>Any of these, as long as it is valid and readable:</p>
    <ul>
        @foreach (\App\Models\IdentityDocument::ID_TYPES as $idType)
            <li>{{ $idType }}</li>
        @endforeach
    </ul>
    <p>Upload a photo or scan as a JPG, PNG, WebP or PDF file of up to 5 MB.</p>
</x-faq-item>

<x-faq-item question="How do I use a different ID?">
    <p>You keep one saved ID. To use another, choose <strong>Remove</strong> beside your saved ID (on New Request or in <a href="{{ route('settings.profile') }}" wire:navigate>Settings → Profile</a>), then upload the new one. Requests you already sent keep the ID they were sent with.</p>
    <p>A new ID is <strong>For verification</strong> until the Office of the Registrar checks it on your next request.</p>
</x-faq-item>

<x-faq-item question="What if my ID is not accepted?">
    <p>Your request is <strong>returned for clarification</strong>, and its page says why. Choose <strong>Edit Request</strong>, upload another valid ID and resubmit. The new ID becomes your saved ID.</p>
</x-faq-item>

<x-faq-item question="Who can see my ID and the files I upload?">
    <p>Only you and the staff of the Office of the Registrar (and the system's administrators). Your ID, the copies of your requirements, an SPA and your proof of payment are stored privately and are never shown to the offices that sign your credential or to other requesters.</p>
</x-faq-item>

<x-faq-item question="Why do I tick the data privacy box every time?">
    <p>The Data Privacy Act of 2012 (RA 10173) requires your consent before your personal data is processed. You give it on the summary each time you submit a request or save your profile; nothing is sent until it is ticked.</p>
</x-faq-item>

@props(['attachments'])

@if($attachments->isEmpty())
    <p class="public-empty">Belum ada mata kuliah pada semester ini.</p>
@else
    <ul class="public-course-list">
        @foreach($attachments as $attachment)
            <li>
                <div class="public-course-identity"><span class="faculty-code">{{ $attachment->course_code }}</span><strong>{{ $attachment->course_name }}</strong></div>
                <div class="public-course-facts"><span>{{ $attachment->sks }} SKS</span><span class="public-badge public-badge-archive">{{ $attachment->course_type->label() }}</span></div>
                @if(filled($attachment->prerequisite_notes))
                    <p class="public-course-prerequisite preserve-lines"><strong>Prasyarat:</strong> {{ $attachment->prerequisite_notes }}</p>
                @endif
            </li>
        @endforeach
    </ul>
@endif

<x-mail.layout :message="$message ?? null" :reference="$ticket->reference()" :preheader="\Illuminate\Support\Str::limit($text, 120)">
    {!! $body !!}
</x-mail.layout>

<x-mail.layout :message="$message ?? null" :preheader="\Illuminate\Support\Str::limit($text, 120)">
    {!! $body !!}
</x-mail.layout>

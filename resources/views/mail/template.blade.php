<x-mail.layout :message="$message ?? null" :brand="$brand ?? null" :preheader="\Illuminate\Support\Str::limit($text, 120)">
    {!! $body !!}
</x-mail.layout>

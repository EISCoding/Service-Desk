{{-- Placeholder for areas that are built in later phases. --}}
<x-layouts::intern :title="$title">
    <x-ui.page-header :title="$title" />

    <x-ui.card :padding="false">
        <x-ui.empty-state :icon="$icon" :title="$heading" :description="$description" />
    </x-ui.card>
</x-layouts::intern>

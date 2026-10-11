{{--
    Sets the `dark` class on <html> before the first paint, so the page never
    flashes in the wrong theme. Must be rendered in <head> before the stylesheet.
    Preference: user setting (data-theme from the server) → localStorage → system.

    wire:navigate swaps the page without re-running this script and replaces the
    <html> attributes with the server rendered ones (no `dark` class for
    "System"), so the theme is applied again right after every swap.
--}}
<script>
    (function () {
        var root = document.documentElement;
        var media = window.matchMedia('(prefers-color-scheme: dark)');

        function storedPreference() {
            try { return localStorage.getItem('theme'); } catch (e) { return null; }
        }

        function currentPreference() {
            var preference = root.dataset.theme;

            if (preference) {
                // Remember the user's setting for guest pages (e.g. the login page after logout).
                try { localStorage.setItem('theme', preference); } catch (e) {}
                return preference;
            }

            return storedPreference();
        }

        window.applyTheme = function (theme) {
            var preference = ['light', 'dark'].indexOf(theme) === -1 ? 'system' : theme;
            root.dataset.theme = preference;
            root.classList.toggle('dark', preference === 'dark' || (preference === 'system' && media.matches));
        };

        window.applyTheme(currentPreference());

        media.addEventListener('change', function () { window.applyTheme(currentPreference()); });

        document.addEventListener('livewire:navigating', function (event) {
            event.detail.onSwap(function () { window.applyTheme(currentPreference()); });
        });
        document.addEventListener('livewire:navigated', function () { window.applyTheme(currentPreference()); });
    })();
</script>

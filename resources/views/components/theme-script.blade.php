{{--
    Sets the `dark` class on <html> before the first paint, so the page never
    flashes in the wrong theme. Must be rendered in <head> before the stylesheet.
    Preference: user setting (data-theme from the server) → localStorage → system.
--}}
<script>
    (function () {
        var root = document.documentElement;
        var media = window.matchMedia('(prefers-color-scheme: dark)');
        var preference = root.dataset.theme;

        try {
            if (preference) {
                // Remember the user's setting for guest pages (e.g. the login page after logout).
                localStorage.setItem('theme', preference);
            } else {
                preference = localStorage.getItem('theme');
            }
        } catch (e) {}

        window.applyTheme = function (theme) {
            preference = ['light', 'dark'].indexOf(theme) === -1 ? 'system' : theme;
            root.dataset.theme = preference;
            root.classList.toggle('dark', preference === 'dark' || (preference === 'system' && media.matches));
        };

        window.applyTheme(preference);
        media.addEventListener('change', function () { window.applyTheme(preference); });
    })();
</script>

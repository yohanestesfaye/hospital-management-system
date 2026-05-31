document.addEventListener('DOMContentLoaded', function () {
    var toasts = document.querySelectorAll('.toast');
    toasts.forEach(function (toastEl) {
        new bootstrap.Toast(toastEl);
    });

    var alerts = document.querySelectorAll('.alert.alert-success, .alert.alert-warning, .alert.alert-info');
    alerts.forEach(function (el) {
        var delay = 5000;
        setTimeout(function () {
            try {
                var bsAlert = new bootstrap.Alert(el);
                bsAlert.close();
            } catch (e) {
                el.style.transition = 'opacity .5s';
                el.style.opacity = '0';
                setTimeout(function(){ el.remove(); }, 500);
            }

            // Remove ?msg=... from URL so the alert does not return on refresh
            try {
                var url = new URL(window.location.href);
                if (url.searchParams.has('msg')) {
                    url.searchParams.delete('msg');
                    window.history.replaceState({}, document.title, url.pathname + (url.search ? '?' + url.searchParams.toString() : '') + url.hash);
                }
            } catch (e2) { }
        }, delay);
    });
});



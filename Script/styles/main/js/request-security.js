(function () {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (!meta) return;
    const token = meta.content;
    const routes = new Set(JSON.parse(meta.dataset.routes));
    function isWrite(url, data) {
        const route = url.searchParams.get('p') || url.pathname.replace(/^\/+|\/+$/g, '');
        const attrs = new URLSearchParams(typeof data === 'string' ? data : data || {});
        const has = key => attrs.has(key) || url.searchParams.has(key);
        return routes.has(route) || /^watch\/[^/]+\/delete$/.test(route) ||
            ((route.startsWith('admin/') || route.startsWith('settings/')) && (has('action') || has('restore'))) ||
            (route === 'admin/payments' && has('id'));
    }

    function addToken(form) {
        if (form.elements.namedItem('csrf_token')) return;
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'csrf_token';
        input.value = token;
        form.append(input);
    }

    document.addEventListener('submit', event => {
        const form = event.target;
        if (form.method.toUpperCase() === 'POST' && new URL(form.action, location.href).origin === location.origin) addToken(form);
    }, true);

    document.addEventListener('click', event => {
        const link = event.target.closest('a[href],button[data-post-url]');
        if (!link) return;
        const url = new URL(link.dataset.postUrl || link.href, location.href);
        if (url.origin !== location.origin || !isWrite(url)) return;
        if (link.matches('[data-ajax-action],.ajax-action,[data-ajax]') && !link.dataset.postUrl) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        if (link.classList.contains('confirm') && !window.confirm(link.dataset.message || link.textContent.trim() || 'Confirm?')) return;
        const form = document.createElement('form');
        form.method = 'post';
        form.action = url.href;
        addToken(form);
        document.body.append(form);
        form.submit();
    }, true);

    if (window.jQuery) jQuery.ajaxPrefilter((options, original, xhr) => {
        const url = new URL(options.url, location.href);
        if (url.origin !== location.origin) return;
        if (isWrite(url, options.data)) options.type = 'POST';
        if (options.type.toUpperCase() === 'POST') xhr.setRequestHeader('X-CSRF-Token', token);
    });
})();

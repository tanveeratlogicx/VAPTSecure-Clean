(function () {
  if (typeof wp === 'undefined' || !wp.apiFetch) return;
  if (wp.apiFetch.__vaptsecure_patched) return;

  const vaptLog = window.vaptLog || {
    log: () => { },
    warn: () => { },
    error: (...args) => console.error('[VAPT]', ...args),
    debug: () => { },
    info: () => { }
  };

  const getSettings = () => window.vaptSecureSettings || {};
  const getEffectiveNonce = () => {
    const wpNonce = (window.wpApiSettings && window.wpApiSettings.nonce) || '';
    const vaptNonce = (getSettings() && getSettings().nonce) || '';
    return wpNonce || vaptNonce;
  };

  const getHomeUrl = () => {
    const s = getSettings();
    const home = s.homeUrl || '';
    const origin = (window.location && window.location.origin) || '';
    return (home || origin || '').replace(/\/$/, '');
  };

  const safeGetLocalStorage = (key) => {
    try {
      return localStorage.getItem(key);
    } catch (e) {
      return null;
    }
  };

  const safeSetLocalStorage = (key, value) => {
    try {
      localStorage.setItem(key, value);
    } catch (e) { }
  };

  let localBroken = safeGetLocalStorage('vaptsecure_rest_broken') === '1';
  const originalApiFetch = wp.apiFetch;

  const getFallbackUrl = (pathOrUrl) => {
    const home = getHomeUrl();
    if (!home || !pathOrUrl) return null;

    const nonce = getEffectiveNonce();
    const path = typeof pathOrUrl === 'string' && pathOrUrl.includes('/wp-json/')
      ? pathOrUrl.split('/wp-json/')[1]
      : pathOrUrl;

    const cleanPath = String(path).replace(/^\//, '');
    const basePath = cleanPath.split('?')[0];
    const queryParams = cleanPath.includes('?') ? '&' + cleanPath.split('?')[1] : '';
    const nonceParam = nonce ? '&_wpnonce=' + nonce : '';

    return home + '/?rest_route=/' + basePath + queryParams + nonceParam;
  };

  const patchedApiFetch = (rawArgs) => {
    const args = rawArgs ? Object.assign({}, rawArgs) : {};
    const method = String(args.method || 'GET').toUpperCase();

    const nonce = getEffectiveNonce();
    if (nonce && method !== 'GET') {
      if (!args.headers) args.headers = {};
      if (typeof args.headers.set === 'function') {
        if (!args.headers.has('X-WP-Nonce')) args.headers.set('X-WP-Nonce', nonce);
      } else {
        args.headers = Object.assign({}, args.headers, { 'X-WP-Nonce': nonce });
      }
    }

    if (localBroken && (args.path || args.url)) {
      const fallbackUrl = getFallbackUrl(args.path || args.url);
      if (fallbackUrl) {
        const fallbackArgs = Object.assign({}, args, { url: fallbackUrl });
        delete fallbackArgs.path;
        return originalApiFetch(fallbackArgs);
      }
    }

    return originalApiFetch(args).catch((err) => {
      const status = err && (err.status || (err.data && err.data.status));
      const isFallbackTrigger = status === 404 || status === 403 || (err && (err.code === 'rest_no_route' || err.code === 'invalid_json'));

      if (isFallbackTrigger && (args.path || args.url)) {
        const fallbackUrl = getFallbackUrl(args.path || args.url);
        if (!fallbackUrl) throw err;

        if (!localBroken) {
          vaptLog.warn('Switching to Pre-emptive Mode (Silent) for REST API.');
          localBroken = true;
          safeSetLocalStorage('vaptsecure_rest_broken', '1');
        }

        const fallbackArgs = Object.assign({}, args, { url: fallbackUrl });
        delete fallbackArgs.path;
        return originalApiFetch(fallbackArgs);
      }

      throw err;
    });
  };

  Object.keys(originalApiFetch).forEach((key) => {
    patchedApiFetch[key] = originalApiFetch[key];
  });

  patchedApiFetch.__vaptsecure_patched = true;
  wp.apiFetch = patchedApiFetch;
})();

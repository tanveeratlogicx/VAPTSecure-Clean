// React Component to Render Generated Interfaces
// Version 3.0 - Global Driver & Probe Architecture
// Expects props: { feature, onUpdate }

// Debug mode control - set to true to enable console logs for debugging
var VAPT_DEBUG = window.VAPT_DEBUG || false;

// Helper function for conditional logging
var vaptLog = window.vaptLog || {
  log: (...args) => VAPT_DEBUG && console.log('[VAPT]', ...args),
  warn: (...args) => VAPT_DEBUG && console.warn('[VAPT]', ...args),
  error: (...args) => console.error('[VAPT]', ...args), // Always show errors
  debug: (...args) => VAPT_DEBUG && console.debug('[VAPT]', ...args),
  info: (...args) => VAPT_DEBUG && console.info('[VAPT]', ...args)
};

(function () {
  const { createElement: el, createPortal, useState, useEffect, useRef, useMemo } = wp.element;
  const { Button, TextControl, ToggleControl, SelectControl, TextareaControl, Modal, Icon, Tooltip } = wp.components;
  const { __, sprintf } = wp.i18n;
  const apiFetch = wp.apiFetch;

  /**
   * Placeholder Metadata (v4.1.0)
   * Maps code placeholders to UI metadata for user configuration.
   */
  const PLACEHOLDER_METADATA = {
    'YOUR_SITE_KEY': {
      meta_key: 'vapt_risk_009_site_key',
      label: __('reCAPTCHA v3 Site Key', 'vaptsecure'),
      help: __('Enter your Google reCAPTCHA v3 Site Key.', 'vaptsecure'),
      tutorial: 'https://www.google.com/recaptcha/admin',
      tutorialLabel: __('How to get reCAPTCHA keys', 'vaptsecure'),
      risk_id: 'RISK-009'
    },
    '6LelVOQsAAAAAAucOFHcAsC0H8CWHRGa8e17whwY': {
      meta_key: 'vapt_risk_009_site_key',
      label: __('reCAPTCHA v3 Site Key', 'vaptsecure'),
      help: __('Enter your Google reCAPTCHA v3 Site Key.', 'vaptsecure'),
      tutorial: 'https://www.google.com/recaptcha/admin',
      tutorialLabel: __('How to get reCAPTCHA keys', 'vaptsecure'),
      risk_id: 'RISK-009'
    },
    'unique-key-here': {
      meta_key: 'vapt_risk_061_key',
      label: __('Security Auth Key', 'vaptsecure'),
      help: __('Provide a unique security salt string.', 'vaptsecure'),
      tutorial: 'https://api.wordpress.org/secret-key/1.1/salt/',
      tutorialLabel: __('Generate WordPress Salts', 'vaptsecure'),
      risk_id: 'RISK-061'
    }
  };

  /**
   * Dynamic Configuration Section (v4.1.0)
   * Rendered as a card below Implementation Control if placeholders are detected.
   */
  const DynamicConfigSection = ({ feature, verificationFeatureData, currentData, handleChange }) => {
    // [v4.1.3] Local state for inputs to ensure responsiveness while typing
    const [localValues, setLocalValues] = useState({});
    const isTyping = useRef(false);
    const typingTimer = useRef(null);
    
    // Sync local state with currentData only when NOT typing to avoid cursor jumps
    useEffect(() => {
      if (isTyping.current) return;

      const nextLocal = {};
      Object.keys(PLACEHOLDER_METADATA).forEach(p => {
        const key = PLACEHOLDER_METADATA[p].meta_key;
        if (currentData[key] !== undefined) {
          nextLocal[key] = currentData[key];
        }
      });
      setLocalValues(prev => ({ ...prev, ...nextLocal }));
    }, [currentData]);

    const rawId = (feature.key || feature.id || '').toString();
    const riskId = rawId.toUpperCase().replace(/[^A-Z0-9]/g, '');

    const detectedPlaceholders = Object.keys(PLACEHOLDER_METADATA).filter(p => {
      const meta = PLACEHOLDER_METADATA[p];
      const metaRiskId = (meta.risk_id || '').toString().toUpperCase().replace(/[^A-Z0-9]/g, '');
      
      if (metaRiskId && riskId && metaRiskId === riskId) return true;

      let impls = verificationFeatureData.platform_implementations || {};
      if (typeof impls === 'string') {
        try { impls = JSON.parse(impls); } catch (e) { impls = {}; }
      }

      return Object.values(impls).some(details => {
        const code = (typeof details === 'string' ? details : (details.code || details.wrapped_code || '')).toString();
        return code.includes(p);
      });
    });

    const normalizedKeyPart = rawId.toLowerCase().replace('risk-', '').replace(/-/g, '_');
    const riskKey = `vapt_risk_${normalizedKeyPart}_enabled`;
    
    const isEnforced = !!(
      feature.is_enabled || 
      feature.is_enforced || 
      currentData.feat_enabled || 
      currentData.enabled || 
      currentData.prot_enabled || 
      currentData[riskKey]
    );

    if (detectedPlaceholders.length === 0) return null;
    if (!isEnforced) return null;

    // Deduplicate by meta_key to prevent multiple inputs for the same setting
    const seenMetaKeys = new Set();
    const uniquePlaceholders = detectedPlaceholders.filter(p => {
      const metaKey = PLACEHOLDER_METADATA[p].meta_key;
      if (seenMetaKeys.has(metaKey)) return false;
      seenMetaKeys.add(metaKey);
      return true;
    });

    return el('div', {
      className: 'vapt-dynamic-config-panel',
      id: 'vapt-dynamic-config-root',
      key: 'dynamic-config-row-inner',
      style: {
        margin: '15px 0 0 0',
        padding: '15px 0 0 0',
        borderTop: '1px solid #f1f5f9',
        width: '100%',
        maxWidth: '100%',
        boxSizing: 'border-box',
        overflow: 'hidden',
        background: 'transparent'
      }
    }, [
      el('h4', { 
        style: { 
          margin: '0 0 12px 0', 
          fontSize: '11px', 
          fontWeight: 700, 
          color: '#475569', 
          display: 'flex', 
          alignItems: 'center', 
          gap: '8px',
          textTransform: 'uppercase',
          letterSpacing: '0.025em'
        } 
      }, [
        Icon ? el(Icon, { icon: 'admin-settings', size: 14 }) : '⚙️',
        __('Required Configuration', 'vaptsecure')
      ]),
      uniquePlaceholders.map(p => {
        const meta = PLACEHOLDER_METADATA[p];
        const metaKey = meta.meta_key;
        
        const handleLocalChange = (val) => {
          isTyping.current = true;
          setLocalValues(prev => ({ ...prev, [metaKey]: val }));
          
          if (typingTimer.current) clearTimeout(typingTimer.current);
          typingTimer.current = setTimeout(() => {
            isTyping.current = false;
            handleChange(metaKey, val);
          }, 500); // 500ms debounce
        };

        // Fallback to plain input if TextControl is missing
        if (!TextControl) {
          return el('div', { key: p, style: { marginBottom: '15px', width: '100%' } }, [
            el('label', { style: { display: 'block', fontSize: '12px', fontWeight: '600', marginBottom: '5px', color: '#334155' } }, meta.label),
            el('input', {
              type: 'text',
              value: localValues[metaKey] || '',
              onChange: (e) => handleLocalChange(e.target.value),
              style: { width: '100%', padding: '8px', border: '1px solid #d1d5db', borderRadius: '4px', boxSizing: 'border-box' }
            }),
            el('p', { style: { fontSize: '11px', color: '#64748b', marginTop: '4px' } }, meta.help)
          ]);
        }

        return el('div', { 
          key: p, 
          className: 'vapt-dynamic-config-row',
          style: { 
            marginBottom: '10px',
            width: '100%',
            boxSizing: 'border-box'
          } 
        }, [
          el(TextControl, {
            label: el('span', { style: { fontSize: '12px', color: '#334155', fontWeight: '600' } }, meta.label),
            help: meta.help,
            value: localValues[metaKey] || '',
            onChange: handleLocalChange,
            placeholder: `e.g. ${p}`,
            __nextHasNoMarginBottom: true,
            style: { width: '100%' }
          }),
          meta.tutorial && el('div', { style: { marginTop: '5px' } }, [
            el('a', {
              href: meta.tutorial,
              target: '_blank',
              rel: 'noopener noreferrer',
              style: { fontSize: '11px', color: '#2563eb', display: 'flex', alignItems: 'center', gap: '4px', textDecoration: 'none', fontWeight: '500' }
            }, [
              Icon ? el(Icon, { icon: 'external', size: 12 }) : '🔗',
              meta.tutorialLabel || __('Help Tutorial', 'vaptsecure')
            ])
          ])
        ]);
      })
    ]);
  };

  /**
   * Universal URL Resolver (v3.13.2)
   * Standardizes on vaptSecureSettings.homeUrl and detects absolute paths/URLs.
   */
  const resolveUrl = (path, configUrl, featureKey = '') => {
    let homeUrl = (window.vaptSecureSettings && window.vaptSecureSettings.homeUrl) ? window.vaptSecureSettings.homeUrl.replace(/\/$/, '') : window.location.origin;

    // 🛡️ Origin Alignment (v3.3.52): Force current protocol/host/port if hostname matches to avoid CORS
    const alignUrl = (urlStr) => {
      try {
        if (!urlStr || !urlStr.startsWith('http')) return urlStr;
        const u = new URL(urlStr);
        const loc = window.location;
        if (u.hostname === loc.hostname) {
          u.protocol = loc.protocol;
          u.host = loc.host;
        }
        return u.toString();
      } catch (e) { }
      return urlStr;
    };

    homeUrl = alignUrl(homeUrl);

    // 🛡️ Logic Refinement (v3.13.8): Context-Aware Specificity
    let base = homeUrl;
    let sub = path || '';

    // [FIX v2.4.11] Handle query strings properly - split path and query
    // If path contains '?', separate the path portion from query parameters
    let queryPart = '';
    if (sub.includes('?')) {
      const parts = sub.split('?');
      sub = parts[0]; // Path portion (e.g., '/' or '/wp-json')
      queryPart = '?' + parts.slice(1).join('?'); // Query string (e.g., '?vapt=1')
    }

    // If path is root default but feature implies a specific target, nudge it (v3.13.8)
    if ((!path || path === '/') && !configUrl && featureKey) {
      if (featureKey.includes('cron') || featureKey === 'RISK-001') sub = 'wp-cron.php?doing_wp_cron=1';
      else if (featureKey.includes('xmlrpc')) sub = 'xmlrpc.php';
      else if (featureKey.includes('login') || featureKey === 'RISK-007' || featureKey === 'RISK-008') sub = 'wp-login.php';
      else if (featureKey === 'RISK-009') sub = 'wp-login.php?action=register';
      else if (featureKey === 'RISK-003' || featureKey === 'RISK-006') sub = 'wp-json/wp/v2/users';
      else if (featureKey === 'RISK-005') sub = '?author=1';
    }

    if (configUrl) {
      if (configUrl.startsWith('http')) {
        configUrl = alignUrl(configUrl.replace(/\/$/, ''));
        const normalizedConfig = configUrl;
        // If configUrl is just the root domain, AND we have a better sub, JOIN them.
        if (normalizedConfig === homeUrl && (sub && sub !== '/' && !sub.startsWith('http'))) {
          // sub is already set above or from path
        } else {
          return configUrl; // Absolute override (now port-aligned)
        }
      } else {
        sub = configUrl; // Relative override
      }
    } else if (path && path.startsWith('http')) {
      return alignUrl(path); // Path is already absolute (now port-aligned)
    }

    const normalizedPath = sub.startsWith('/') ? sub : '/' + sub;
    let result = base.replace(/\/$/, '') + (normalizedPath === '/' ? '' : normalizedPath);

    // [FIX v2.4.11] Append query string if it was separated
    if (queryPart) {
      result += queryPart;
    }

    // 🛡️ Trailing Slash Resilience (v3.3.53) - File-Aware Patch (v2.4.3)
    // Only append if result doesn't look like a file (e.g., ends in .php or .html)
    const isFile = /\.[a-z0-9]+$/i.test(result);
    if (!result.includes('/', 8) && !isFile) result += '/';

    return result;
  };

  /**
   * Helper: Infer a feature-appropriate probe path when the configured path is generic.
   */
  const inferProbePath = (featureData, featureKey = '', control = {}) => {
    const parts = [
      featureKey,
      featureData && (featureData.label || featureData.title || featureData.name || ''),
      featureData && (featureData.summary || featureData.description || featureData.remediation || ''),
      featureData && Array.isArray(featureData.wp_paths) ? featureData.wp_paths.join(' ') : '',
      featureData && featureData.context && Array.isArray(featureData.context.wp_paths) ? featureData.context.wp_paths.join(' ') : '',
      featureData && Array.isArray(featureData.available_platforms) ? featureData.available_platforms.join(' ') : '',
      control && control.label ? control.label : ''
    ].filter(Boolean);

    if (featureData && featureData.platform_implementations && typeof featureData.platform_implementations === 'object') {
      Object.values(featureData.platform_implementations).forEach(impl => {
        if (!impl || typeof impl !== 'object') return;
        if (impl.target_file) parts.push(impl.target_file);
        if (impl.path) parts.push(impl.path);
        if (impl.request_path) parts.push(impl.request_path);
      });
    }

    const text = parts.join(' ').toLowerCase();
    const has = (...terms) => terms.some(term => text.includes(term));

    if (text.includes('/wp-json/wp/v2/users') || has('wordpress rest api', 'endpoint disclosure')) return '/wp-json/wp/v2/users';
    if (text.includes('/?author=1') || has('author archives', 'author enumeration')) return '/?author=1';
    if (has('registration', 'register')) return '/wp-login.php?action=register';
    if (has('contact', 'form', 'comment')) return '/wp-comments-post.php';
    if (has('login', 'brute', 'password reset', 'lost password')) return '/wp-login.php';
    if (has('cron')) return '/wp-cron.php?doing_wp_cron=1';
    if (has('xmlrpc', 'xml-rpc')) return '/xmlrpc.php';
    if (has('directory', 'indexing', 'uploads')) return '/wp-content/uploads/';

    return '/';
  };

  const detectSurfaceFamily = (featureData = {}, featureKey = '', control = {}) => {
    const blob = [
      featureKey,
      featureData && (featureData.risk_id || ''),
      featureData && (featureData.label || featureData.title || featureData.name || ''),
      featureData && (featureData.summary || featureData.description || featureData.remediation || ''),
      featureData && (featureData.generated_schema ? JSON.stringify(featureData.generated_schema) : ''),
      featureData && (featureData.implementation_data ? JSON.stringify(featureData.implementation_data) : ''),
      featureData && (featureData.platform_implementations ? JSON.stringify(featureData.platform_implementations) : ''),
      featureData && (featureData.available_platforms ? JSON.stringify(featureData.available_platforms) : ''),
      control && control.label ? control.label : '',
      control && control.help ? control.help : '',
      control && control.test_config ? JSON.stringify(control.test_config) : ''
    ].filter(Boolean).join(' ').toLowerCase();

    if (blob.includes('/wp-json/wp/v2/users') || blob.includes('wordpress rest api') || blob.includes('rest api')) {
      return 'rest_users';
    }
    if (blob.includes('/?author=1') || blob.includes('author query') || blob.includes('author archives') || blob.includes('author enumeration')) {
      return 'author_query';
    }
    if (blob.includes('rate limit') || blob.includes('rate-limiting') || blob.includes('brute force') || blob.includes('bruteforce') || blob.includes('php-rate-limit')) {
      return 'rate_limit';
    }
    if (blob.includes('/wp-login.php') || blob.includes('login_errors') || blob.includes('invalid credentials')) {
      return 'login_error';
    }
    if (blob.includes('pingback') || blob.includes('xmlrpc') || blob.includes('xml-rpc')) {
      return 'xmlrpc';
    }
    if (blob.includes('cron') || blob.includes('wp-cron')) {
      return 'cron';
    }
    return '';
  };

  const collectPlatformHints = (featureData = {}) => {
    const hints = new Set();

    if (Array.isArray(featureData.available_platforms)) {
      featureData.available_platforms.forEach(platform => {
        const normalized = normalizeEnforcerValue(platform);
        if (normalized) hints.add(normalized);
      });
    }

    if (featureData.platform_implementations && typeof featureData.platform_implementations === 'object') {
      Object.entries(featureData.platform_implementations).forEach(([platformName, impl]) => {
        const candidates = [platformName];
        if (impl && typeof impl === 'object') {
          if (impl.lib_key) candidates.push(impl.lib_key);
          if (impl.target_file) candidates.push(impl.target_file);
          if (impl.request_path) candidates.push(impl.request_path);
          if (impl.implementation_type) candidates.push(impl.implementation_type);
          if (impl.operation) candidates.push(impl.operation);
        }

        candidates.forEach(candidate => {
          const normalized = normalizeEnforcerValue(candidate);
          if (normalized) hints.add(normalized);
        });
      });
    }

    return hints;
  };

  // [v4.0.x-SSoT-Fix] Remove stale enforcers - fail2ban/htaccess no longer canonical for any risk
  // Canonical platforms are now defined in interface_schema_v2.0.json available_platforms
  // [v4.0.x-SSoT] Canonical platforms for WordPress-only hosting: htaccess, nginx, cloudflare, php-functions, wp-config
const resolvePrimaryPlatform = (featureData = {}) => {
  const availablePlatforms = Array.isArray(featureData.available_platforms) ? featureData.available_platforms : [];
  if (availablePlatforms.length > 0) {
    return availablePlatforms[0];
  }

  const impls = featureData.platform_implementations && typeof featureData.platform_implementations === 'object'
    ? Object.entries(featureData.platform_implementations)
    : [];
  if (impls.length > 0) {
    const [platformName, implementation] = impls[0];
    return implementation?.target_file || platformName || '';
  }

  const hints = collectPlatformHints(featureData);
  const fallbackPriority = [
    'php-functions',
    'php-headers',
    'php-cron',
    'wp-config',
    'nginx',
    'cloudflare',
    'litespeed',
    'htaccess',
    'apache',
    'wordpress_core',
    'server_cron'
  ];

  for (const candidate of fallbackPriority) {
    if (hints.has(normalizeEnforcerValue(candidate))) {
      return candidate;
    }
  }

  return '';
};
  const resolveExpectedEnforcer = (primaryPlatform, operation = '') => {
    const platform = normalizeEnforcerValue(primaryPlatform);
    const op = normalizeEnforcerValue(operation);

    if (platform === 'htaccess' || platform === 'apache') return 'htaccess';
    if (platform === 'litespeed') return 'litespeed';
    if (platform === 'nginx') return 'nginx';
    if (platform === 'cloudflare') return 'cloudflare';
    // Removed: caddy, iis, fail2ban — not applicable to WordPress hosting
    if (platform === 'wp_config' || platform === 'wpconfig' || op.includes('constant') || op.includes('config')) return 'wp_config';
    if (platform === 'php_functions' || platform === 'php-headers' || op.includes('hook') || op.includes('wordpress')) return 'php_functions';
    if (platform === 'php_cron' || platform === 'server-cron' || op.includes('cron')) return 'php_cron';
    return platform || 'php_functions';
  };

  const isWpLoginLoginErrorFeature = (featureData = {}, featureKey = '', control = {}) => {
    return detectSurfaceFamily(featureData, featureKey, control) === 'login_error';
  };

  /**
   * Helper: Consistent Boolean Type Casting (v3.14.2)
   */
  const toBool = (val) => {
    if (val === true || val === 1 || val === '1' || val === 'true' || val === 'on') return true;
    return false;
  };

  const normalizeEnforcerValue = (value) => {
    let normalized = String(value || '')
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '');

    if (normalized === 'apache' || normalized === 'apache-htaccess' || normalized === 'htaccess') {
      return 'htaccess';
    }
    if (normalized === 'wp-config' || normalized === 'wp-config-php' || normalized === 'wpconfig' || normalized === 'config') {
      return 'wp_config';
    }
    if (normalized === 'php-functions' || normalized === 'php-functions-php' || normalized === 'hook' || normalized === 'wordpress' || normalized === 'wordpress-core' || normalized === 'wordpress_core') {
      return 'php_functions';
    }
    if (normalized === 'php-cron' || normalized === 'server-cron' || normalized === 'server_cron') {
      return 'php_cron';
    }
    // Removed: caddy, iis, fail2ban — not applicable to WordPress hosting environment
    return normalized;
  };

  const expectedEnforcerAliases = (expected) => {
    const normalized = normalizeEnforcerValue(expected);
    // [v4.0.x-SSoT] Removed: caddy, iis, fail2ban — not applicable to WordPress hosting; nginx retained
    const aliasMap = {
      'htaccess': ['htaccess', 'apache', '.htaccess', 'apache-htaccess'],
      'apache': ['htaccess', 'apache', '.htaccess', 'apache-htaccess'],
      'litespeed': ['litespeed', 'lscache'],
      'nginx': ['nginx', 'nginx-config'],
      'cloudflare': ['cloudflare'],
      'wp-config': ['wp-config', 'wp_config', 'config'],
      'wpconfig': ['wp-config', 'wp_config', 'config'],
      'wp_config': ['wp-config', 'wp_config', 'config'],
      'php-functions': ['php-headers', 'php-functions', 'hook', 'wordpress', 'wordpress-core'],
      'php_functions': ['php-headers', 'php-functions', 'hook', 'wordpress', 'wordpress-core'],
      'php-headers': ['php-headers', 'hook', 'wordpress', 'wordpress-core'],
      'wordpress-core': ['php-headers', 'php-functions', 'hook', 'wordpress', 'wordpress-core'],
      'wordpress_core': ['php-headers', 'php-functions', 'hook', 'wordpress', 'wordpress-core'],
      'php-rate-limit': ['php-rate-limit'],
      'php-xmlrpc': ['php-xmlrpc'],
      'php-author-enum': ['php-author-enum'],
      'php-dir': ['php-dir'],
      'php-null-byte': ['php-null-byte'],
      'php-cron': ['php-cron'],
      'php_cron': ['php-cron', 'server-cron'],
      'server-cron': ['php-cron', 'server-cron'],
      'server_cron': ['php-cron', 'server-cron'],
      'hook': ['php-headers', 'hook', 'wordpress', 'wordpress-core']
    };

    return aliasMap[normalized] || [normalized];
  };

  const matchesExpectedEnforcer = (actual, expected) => {
    if (!actual || !expected) return false;
    const actualNormalized = normalizeEnforcerValue(actual);
    return expectedEnforcerAliases(expected).some(alias => actualNormalized.includes(normalizeEnforcerValue(alias)));
  };

  /**
   * Helper: Determines if a feature is enabled (defaults to true for A+ Architecture)
   */
  const isFeatureEnabled = (featureData) => {
    if (!featureData) return true;
    
    // Check all possible toggle keys (v4.1.5)
    const riskId = (featureData.key || featureData.id || featureData.risk_id || '').toString().toLowerCase();
    const riskSuffix = riskId.replace('risk-', '').replace(/-/g, '_');
    const autoKey = `vapt_risk_${riskSuffix}_enabled`;

    const candidates = [
      featureData[autoKey],
      featureData.feat_enabled,
      featureData.enabled,
      featureData.prot_enabled
    ];

    for (const val of candidates) {
      if (val !== undefined) return toBool(val);
    }

    return true; // Default to enabled if no toggle found
  };

  /**
   * Helper: Safely render a value that might be an object or a URL to linkify
   */
  const safeRender = (val) => {
    if (val === null || val === undefined) return '';
    if (typeof val === 'string') {
      // Linkify URLs in text
      const urlRegex = /(https?:\/\/[^\s]+)/g;
      const parts = val.split(urlRegex);
      if (parts.length > 1) {
        return parts.map((part, i) =>
          part.match(urlRegex)
            ? el('a', { key: i, href: part, target: '_blank', rel: 'noopener noreferrer', style: { color: '#2563eb', textDecoration: 'underline' } }, part)
            : part
        );
      }
      return val;
    }
    if (typeof val === 'number' || typeof val === 'boolean') return val.toString();
    if (typeof val === 'object') {
      if (val.label) return safeRender(val.label);
      if (val.message) return safeRender(val.message);
      if (val.content) return safeRender(val.content);
      return JSON.stringify(val);
    }
    return '';
  };

  /**
   * PROBE REGISTRY: Global Verification Handlers
   */
  const PROBE_REGISTRY = {
    // 1. Header Probe: Verifies HTTP response headers
    check_headers: async (siteUrl, control, featureData, featureKey) => {
      // [FIX v2.4.11] Use test_config.path if available, otherwise default to root
      const configuredPath = control.test_config?.path || '/';
      const configPath = (configuredPath === '/' || configuredPath === '/index.php' || configuredPath.startsWith('/?vapt_header_check=') || configuredPath.startsWith('/?vaptsecure_header_check='))
        ? inferProbePath(featureData, featureKey, control)
        : configuredPath;
      const url = resolveUrl(configPath, control.config?.url, featureKey);
      const contextParam = (featureKey && (featureKey.includes('login') || featureKey.includes('brute'))) ? '&vaptsecure_test_context=login' : '';
      const finalUrl = url + (url.includes('?') ? '&' : '?') + 'vapt_header_check=1';
      vaptLog.log(`Header Probe: Fetching ${finalUrl}`);
      const response = await fetch(finalUrl, { method: 'GET', cache: 'no-store' });
      const headers = {};
      response.headers.forEach((v, k) => { headers[k] = v; });
      vaptLog.log("Full Response Headers:", headers);

      const vaptEnforced = response.headers.get('x-vapt-enforced');
      const enforcedFeature = response.headers.get('x-vapt-feature'); // Can be comma-separated

      let headerStr = '';
      const keepHeaders = ['strict-transport-security', 'x-vapt-enforced', 'x-frame-options', 'x-content-type-options', 'x-xss-protection', 'referrer-policy', 'permissions-policy', 'content-security-policy'];
      const isSuperAdmin = window.vaptSecureSettings && window.vaptSecureSettings.isSuper;

      for (const [k, v] of Object.entries(headers)) {
        if (k.toLowerCase() === 'x-vapt-feature' && !isSuperAdmin) continue; // Explicitly hide the feature list for non-superadmins (v3.13.19)
        if (keepHeaders.includes(k.toLowerCase()) || k.toLowerCase().startsWith('x-') || (isSuperAdmin && k.toLowerCase() === 'x-vapt-feature')) {
          headerStr += `\n${k}: ${v}`;
        }
      }

      // [FIX v2.5.2] State-Aware Success Logic: Verify headers match toggle state
      const hasExpectedHeaders = control.test_config && control.test_config.expected_headers;

      if (hasExpectedHeaders) {
        // The reliable marker is x-vapt-enforced being present with a valid enforcer value.
        const expectedEnforcer = control.test_config?.expected_enforcer || '';
        const expectedEnforcers = Array.isArray(control.test_config?.expected_enforcers)
          ? control.test_config.expected_enforcers.filter(Boolean)
          : (expectedEnforcer ? [expectedEnforcer] : []);
        // [v4.0.x-SSoT] Valid enforcers aligned with canonical schema (interface_schema_v2.0.json)
        const validEnforcers = expectedEnforcers.length > 0
          ? expectedEnforcers
          : ['htaccess', 'litespeed', 'nginx', 'cloudflare', 'wp-config', 'php-headers', 'php-functions', 'php-rate-limit', 'php-xmlrpc', 'php-pingback', 'php-author-enum', 'php-dir', 'php-null-byte', 'php-cron'];
        const isValidEnforcer = vaptEnforced && validEnforcers.some(e => matchesExpectedEnforcer(vaptEnforced, e));
        const isProtectionEnabled = isFeatureEnabled(featureData);
        const isPingbackFeature = (featureKey && (featureKey.toLowerCase().includes('xmlrpc') || featureKey.toLowerCase().includes('pingback')))
          || (control.label && control.label.toLowerCase().includes('pingback'))
          || ((featureData && (featureData.title || featureData.label || featureData.summary || featureData.description)) &&
            String(featureData.title || featureData.label || featureData.summary || featureData.description).toLowerCase().includes('pingback'));

        if (isProtectionEnabled && isPingbackFeature && !isValidEnforcer) {
          try {
            const body = await response.clone().text();
            const bodyLower = body.toLowerCase();
            const pingbackBlocked =
              !bodyLower.includes('pingback.ping') ||
              bodyLower.includes('fault') ||
              bodyLower.includes('not allowed') ||
              bodyLower.includes('access denied') ||
              bodyLower.includes('forbidden');

            if (pingbackBlocked) {
              return {
                success: true,
                message: `Pingback protection is active. XML-RPC no longer exposes pingback.ping.`,
                raw: `URL: ${url} | Status: ${response.status} | Pingback: Disabled\n\n${headerStr.trim()}\n\n${body.substring(0, 800)}`
              };
            }
          } catch (err) {
            // Ignore body inspection failures and continue with header-based logic.
          }
        }

        // [FIX v2.5.2] State-Aware Success: Align result with user intent (toggle state)
        // +-------------------+------------------+-------------------------------------------------------------+---------+
        // | Feature Toggle    | Server Enforce   | Resulting Message                                           | Status  |
        // +-------------------+------------------+-------------------------------------------------------------+---------+
        // | ON                | Detected         | "Plugin is actively enforcing protection."                 | SUCCESS |
        // | ON                | Not Detected     | "Protection toggle is ON but server is NOT enforcing."     | FAILURE |
        // | OFF               | Detected         | "Warning: Toggle is OFF but server is STILL enforcing."     | FAILURE |
        // | OFF               | Not Detected     | "Protection correctly disabled. No enforcement detected."   | SUCCESS |
        // +-------------------+------------------+-------------------------------------------------------------+---------+

        if (isProtectionEnabled === false) {
          // Toggle is OFF: Check if THIS specific feature is still being enforced
          if (isValidEnforcer) {
            const activeFeatures = enforcedFeature ? enforcedFeature.split(',').map(f => f.trim()) : [];
            const isThisFeatureEnforced = activeFeatures.includes(featureKey);
            const otherFeaturesUsingSameProtection = activeFeatures.filter(f => f !== featureKey);
            const displayCount = 5;
            const displayList = otherFeaturesUsingSameProtection.slice(0, displayCount).join(', ');
            const hiddenCount = otherFeaturesUsingSameProtection.length - displayCount;
            const fullList = otherFeaturesUsingSameProtection.join(', ');
            // Only show other features message for superadmins and when there are other features using same protection
            const displayMessageSnippet = (isSuperAdmin && otherFeaturesUsingSameProtection.length > 0)
              ? ` But the following other feature's (RiskID's) are still offering the same Protection: ${hiddenCount > 0 ? `${displayList} (+${hiddenCount} more)` : displayList}`
              : '';

            if (isThisFeatureEnforced) {
              // FAILURE: Toggle is OFF but THIS feature is still being enforced
              return {
                success: false,
                message: `Warning: ${featureKey} toggle is OFF but server is STILL enforcing it (${vaptEnforced}).${displayMessageSnippet}`,
                raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced} | Same Protection: ${fullList || 'none'}\n\n${headerStr.trim()}`
              };
            } else {
              // SUCCESS: Toggle is OFF, headers present but THIS feature is NOT in the list
              return {
                success: false, unprotected: true,
                message: `Protection currently disabled for this feature.${displayMessageSnippet}`,
                raw: `URL: ${url} | Status: ${response.status} | This Feature: Not Enforced | Same Protection: ${fullList || 'none'}\n\n${headerStr.trim()}`
              };
            }
          }
          // SUCCESS: Toggle is OFF and no enforcement detected - correctly disabled
          return {
            success: false, unprotected: true,
            message: `Protection correctly disabled. No enforcement headers detected.`,
            raw: `URL: ${url} | Status: ${response.status} | Enforcement: None\n\n${headerStr.trim()}`
          };
        }

        // Toggle is ON: Success depends on whether headers are detected
        if (!isValidEnforcer) {
          // FAILURE: Toggle is ON but headers missing - protection not active!
          return {
            success: false,
            message: expectedEnforcer
              ? `Protection toggle is ON but the expected VAPT enforcement header was not found. Expected ${expectedEnforcer}. Got: ${vaptEnforced || 'none'}.`
              : `Protection toggle is ON but VAPT enforcement headers not found. Expected x-vapt-enforced. Got: ${vaptEnforced || 'none'}.`,
            raw: `URL: ${url} | Status: ${response.status} | Expected: A+ Headers\n\n${headerStr.trim()}`
          };
        }
        // SUCCESS: Toggle is ON and headers present - protection is active
        return { success: true, message: `Plugin is actively enforcing protection (${vaptEnforced}).`, raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced}\n\n${headerStr.trim()}` };
      }

      // Legacy behavior for tests without expected_headers
      if (vaptEnforced === 'php-headers' || vaptEnforced === 'htaccess' || vaptEnforced?.includes('php')) {
        if (featureKey && enforcedFeature) {
          const activeFeatures = enforcedFeature.split(',').map(f => f.trim());
          if (activeFeatures.includes(featureKey)) {
            if (isFeatureEnabled(featureData) === false) {
              return { success: false, message: `Warning: Feature is DISABLED in UI but still being ENFORCED by server policy.`, raw: `URL: ${url} | Active Features: ${enforcedFeature}\n\n${headerStr.trim()}` };
            }
            return { success: true, message: `Plugin is actively enforcing headers (${vaptEnforced}).`, raw: `URL: ${url} | Status: ${response.status} | Expected: A+ Headers\n\n${headerStr.trim()}` };
          } else {
            // Case: Global headers exist but this feature isn't in the active list (intentional non-enforcement)
            if (isFeatureEnabled(featureData) === false) {
              return { success: false, unprotected: true, message: `Baseline Test: No enforcement headers for this feature. The system is unprotected for this risk vector.`, raw: `URL: ${url} | Active Features: ${enforcedFeature}\n\n${headerStr.trim()}` };
            }
            return { success: false, message: `Discrepancy: Global headers found, but this specific feature ('${featureKey}') is NOT matching enforcement policy.`, raw: `URL: ${url} | Status: ${response.status} | Active Features: ${enforcedFeature}\n\n${headerStr.trim()}` };
          }
        }
        // If no feature list is provided, we can only verify global enforcement
        return { success: true, message: `Global security headers detected (${vaptEnforced}).`, raw: `URL: ${url} | Status: ${response.status} | Expected: A+ Headers\n\n${headerStr.trim()}` };
      }

      if (isFeatureEnabled(featureData) === false) {
        return { success: false, unprotected: true, message: `Baseline Test: No enforcement detected. You are currently unprotected for this feature.`, raw: `URL: ${url} | Status: ${response.status} | Expected: No VAPT Headers\n\n${headerStr.trim()}` };
      }

      return { success: false, message: `Security headers present, but NOT by this plugin. VAPT enforcement header missing.`, raw: `URL: ${url} | Status: ${response.status} | Expected: A+ Headers\n\n${headerStr.trim()}` };
    },

    // 2. Batch Probe: Verifies Rate Limiting (Sends 125% of RPM) (v3.6.25 Sequential)
    spam_requests: async (siteUrl, control, featureData, featureKey, onProgress) => {
      try {
        let rpm = parseInt(control.numTests || featureData['rpm'] || featureData['rate_limit'], 10);
        const probeMethod = String(control?.test_config?.method || 'GET').toUpperCase();
        const probeParams = control?.test_config?.params && typeof control.test_config.params === 'object'
          ? control.test_config.params
          : {};

        // Dynamic Context Detection (v3.3.40 / v3.6.24 expanded)
        let contextParam = '';
        const loginKeywords = ['login', 'brute', 'auth', 'password', 'email', 'reset'];
        if (featureKey && loginKeywords.some(kw => featureKey.toLowerCase().includes(kw))) {
          contextParam = '&vaptsecure_test_context=login';
        }

        if (isNaN(rpm)) {
          const limitKey = Object.keys(featureData).find(k => k.includes('limit') || k.includes('max') || k.includes('rpm'));
          if (limitKey) rpm = parseInt(featureData[limitKey], 10);
        }

        // Fallback for custom strictness keywords (v3.6.25/26)
        if (isNaN(rpm)) {
          const val = control.numTests || featureData['rpm'] || featureData['rate_limit'];
          if (val === 'strict') rpm = 5;
          else if (val === 'moderate') rpm = 10;
          else if (val === 'permissive') rpm = 20;
        }

        if (isNaN(rpm)) rpm = 5;

        vaptLog.log(`spam_requests Debug: rpm=${rpm}, load=${Math.ceil(rpm * 1.25)}, data=`, featureData);
        if (isNaN(rpm) || rpm <= 0) {
          throw new Error('Invalid rate limit configuration. RPM must be a positive number.');
        }

        const load = Math.ceil(rpm * 1.25);
        if (load > 1000) {
          vaptLog.warn('Warning: Rate limit test sending more than 1000 requests. This may impact server performance.');
        }

        try {
          const resetRes = await fetch(siteUrl + '/wp-json/vaptsecure/v1/reset-limit', { method: 'POST', cache: 'no-store' });
          const resetJson = await resetRes.json();
          vaptLog.log('Rate limit reset debug:', resetJson);
        } catch (e) {
          vaptLog.warn('Failed to reset rate limit:', e);
        }

        const responses = [];
        const stats = {};
        let debugInfo = '';
        let lastCount = -1;
        let traceInfo = '';
        let hasVaptHeader = false;

        // Process sequentially for real-time reporting (v3.6.25)
        for (let i = 0; i < load; i++) {
          try {
            const probePath = inferProbePath(featureData, featureKey, control);
            const baseUrl = resolveUrl(probePath, control.config?.url, featureKey);
            const separator = baseUrl.includes('?') ? '&' : '?';
            const url = probeMethod === 'POST'
              ? baseUrl
              : baseUrl + separator + 'vaptsecure_test_spike=' + i + contextParam;
            const fetchOptions = probeMethod === 'POST'
              ? (() => {
                  const bodyParams = new URLSearchParams();
                  Object.entries(probeParams).forEach(([key, value]) => {
                    if (value !== undefined && value !== null) {
                      bodyParams.set(key, String(value));
                    }
                  });
                  if (!bodyParams.has('log')) bodyParams.set('log', 'vaptsecure_nonexistent_user');
                  if (!bodyParams.has('pwd')) bodyParams.set('pwd', 'invalid-password');
                  if (!bodyParams.has('wp-submit')) bodyParams.set('wp-submit', 'Log In');
                  if (!bodyParams.has('redirect_to')) bodyParams.set('redirect_to', `${siteUrl}/wp-admin/`);
                  if (!bodyParams.has('testcookie')) bodyParams.set('testcookie', '1');
                  bodyParams.set('vaptsecure_test_spike', String(i));
                  if (contextParam.includes('login')) {
                    bodyParams.set('vaptsecure_test_context', 'login');
                  }
                  return {
                    method: 'POST',
                    cache: 'no-store',
                    headers: {
                      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: bodyParams.toString()
                  };
                })()
              : { cache: 'no-store' };
            const r = await fetch(url, fetchOptions);
            const respData = { status: r.status, headers: r.headers };
            responses.push(respData);

            // Update stats
            stats[r.status] = (stats[r.status] || 0) + 1;
            if (r.headers.has('x-vapt-debug')) debugInfo = r.headers.get('x-vapt-debug');
            if (r.headers.has('x-vapt-count')) lastCount = r.headers.get('x-vapt-count');
            if (r.headers.has('x-vapt-trace')) traceInfo = r.headers.get('x-vapt-trace');
            if (r.headers.get('x-vapt-enforced') === 'php-rate-limit') hasVaptHeader = true;

            // Report progress every 2 requests or if blocked
            if (onProgress && (i % 2 === 0 || r.status === 429 || i === load - 1)) {
              onProgress({
                total: load,
                current: i + 1,
                accepted: stats[200] || 0,
                blocked: stats[429] || 0,
                errors: stats[500] || 0
              });

              // Always trigger monitor refresh for immediate feedback (v3.6.26/28)
              // Standardized to lowercase (v3.6.28)
              window.dispatchEvent(new CustomEvent('vapt-refresh-stats', { detail: { featureKey: featureKey.toLowerCase() } }));
            }
          } catch (err) {
            vaptLog.warn(`Request ${i} failed:`, err);
            stats[0] = (stats[0] || 0) + 1;
          }
        }

        const blocked = stats[429] || 0;
        const total = load;
        const successCount = stats[200] || 0;
        const errorCount = stats[500] || 0;
        const debugMsg = `(Debug: ${debugInfo || 'None'}, Count: ${lastCount}, Trace: ${traceInfo || 'None'})`;

        const resultMeta = {
          total: total,
          accepted: successCount,
          blocked: blocked,
          errors: errorCount,
          details: debugMsg
        };

        const isEnabled = isFeatureEnabled(featureData);
        const platformHints = Array.isArray(featureData?.available_platforms)
          ? featureData.available_platforms.map(p => String(p || '').toLowerCase())
          : [];
        const isExternalBlocking = (control?.test_config?.enforcement_mode || '').toLowerCase() === 'external';
          // [v4.0.x-SSoT-Fix] removed platformHints.includes('fail2ban') - fail2ban not in canonical catalog
        
        vaptLog.log(`spam_requests result evaluation: blocked=${blocked}, hasVaptHeader=${hasVaptHeader}, isExternalBlocking=${isExternalBlocking}, isEnabled=${isEnabled}, featureKey=${featureKey}`);
        
        const probePath = inferProbePath(featureData, featureKey, control);
        const actualTargetUrl = resolveUrl(probePath, control?.test_config?.url || control?.config?.url, featureKey);

        if (blocked > 0 && (hasVaptHeader || isExternalBlocking)) {
          window.dispatchEvent(new CustomEvent('vapt-refresh-stats', { detail: { featureKey } }));
          if (!isEnabled) {
            // FAILURE: Toggle OFF but rate limiter still active
            return {
              success: false,
              message: `Warning: Protection is disabled but rate limiter is STILL blocking traffic (${blocked} blocked).`,
              meta: resultMeta,
              raw: `URL: ${actualTargetUrl} | Status: 429 | Blocked: ${blocked}`
            };
          }
          // SUCCESS: Toggle ON and rate limiter active
          return {
            success: true,
            message: isExternalBlocking
              ? `Login protection is ACTIVE. External rate limiting blocked ${blocked} request(s).`
              : `Rate limiter is ACTIVE. Security measures are working correctly (${blocked} requests blocked).`,
            meta: resultMeta,
            raw: `URL: ${actualTargetUrl} | Status: 429 | Blocked: ${blocked}`
          };
        }

        if (successCount > 0 || lastCount > 0) {
          window.dispatchEvent(new CustomEvent('vapt-refresh-stats', { detail: { featureKey } }));
        }

        if (errorCount > 0) {
          return {
            success: false,
            message: `Server Error (500). Internal configuration or logic error detected.`,
            meta: resultMeta,
            raw: `URL: ${actualTargetUrl} | Status: 500 | Expected: 429`
          };
        }

        if (!isEnabled) {
          // Toggle is OFF: Check if rate limiting is inactive
          if (blocked === 0) {
            // SUCCESS: Toggle OFF and no rate limiting - correctly disabled
            return {
              success: false, 
              skipped: true,
              message: `Verification Successful: Protection is currently disabled, and the endpoint is accessible as expected.`,
              meta: resultMeta,
              raw: `URL: ${actualTargetUrl} | Status: 200 | Rate Limiting: Inactive`
            };
          }
          // FAILURE: Toggle OFF but external rate limiting detected
          return {
            success: false,
            external_block: true,
            message: `Warning: Protection toggle is OFF but external rate limiting detected (${blocked} blocked).`,
            meta: resultMeta,
            raw: `URL: ${actualTargetUrl} | Status: 429 | External Rate Limiting`
          };
        }

        // Toggle is ON but rate limiter not active
        return {
          success: false,
          message: isExternalBlocking
            ? `Protection toggle is ON but external login rate limiting is NOT active. All requests were accepted.`
            : `Protection toggle is ON but rate limiter is NOT active. All requests were accepted.`,
          meta: resultMeta,
          raw: `URL: ${actualTargetUrl} | Status: 200 | Rate Limiting: Inactive`
        };
      } catch (err) {
        return {
          success: false,
          message: `Test Error: ${err.message}. Rate limit test could not complete.`,
          raw: { error: err.message, stack: err.stack }
        };
      }
    },

    // 3. Status Probe: Verifies specific file block (e.g., XML-RPC)
    block_xmlrpc: async (siteUrl, control, featureData, featureKey) => {
      const url = resolveUrl('/xmlrpc.php', control.config?.url, featureKey);
      vaptLog.log(`XML-RPC Probe: Fetching ${url}`);
      const response = await fetch(url, { method: 'POST', body: '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params></params></methodCall>' });
      const vaptEnforced = response.headers.get('x-vapt-enforced');
      const enforcedFeature = response.headers.get('x-vapt-feature');

      const isEnabled = isFeatureEnabled(featureData);

      // [FIX v2.5.2] State-Aware Success Logic for XML-RPC Blocking
      // +-------------------+------------------+-------------------------------------------------------------+---------+
      // | Feature Toggle    | Server Enforce   | Resulting Message                                           | Status  |
      // +-------------------+------------------+-------------------------------------------------------------+---------+
      // | ON                | Blocked (403)    | "Plugin is actively blocking XML-RPC."                      | SUCCESS |
      // | ON                | Not Blocked      | "Protection ON but XML-RPC is OPEN and VULNERABLE."         | FAILURE |
      // | OFF               | Blocked (403)    | "Warning: Disabled but XML-RPC STILL blocked."             | FAILURE |
      // | OFF               | Not Blocked (200)| "Protection correctly disabled. XML-RPC accessible."        | SUCCESS |
      // +-------------------+------------------+-------------------------------------------------------------+---------+

      const isBlocked = response.status === 403 || response.status === 404 || response.status === 405 || response.status === 401 || vaptEnforced === 'php-xmlrpc';

      if (vaptEnforced === 'php-xmlrpc') {
        if (featureKey && enforcedFeature && enforcedFeature !== featureKey) {
          return { success: false, message: `Inconclusive: XML-RPC is blocked by another VAPT feature ('${enforcedFeature}'). You must disable it there to verify this control independently.`, raw: `URL: ${url} | Status: ${response.status} | Expected: 403` };
        }
        if (!isEnabled) {
          // FAILURE: Toggle OFF but still enforcing
          return { success: false, message: `Warning: Protection is disabled but XML-RPC is STILL being blocked (${vaptEnforced}).`, raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced}` };
        }
        // SUCCESS: Toggle ON and blocking active
        return { success: true, message: `Plugin is actively blocking XML-RPC (${vaptEnforced}).`, raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced}` };
      }

      const isVulnerable = response.status === 200;

      if (!isEnabled) {
        // Toggle is OFF: Check if XML-RPC is accessible
        if (isVulnerable) {
          // SUCCESS: Toggle OFF and XML-RPC is accessible - correctly disabled
          return {
            success: false, 
            skipped: true,
            message: `Protection correctly disabled. XML-RPC is accessible (HTTP 200).`,
            raw: `URL: ${url} | Status: ${response.status} | Enforcement: None`
          };
        }
        // FAILURE: Toggle OFF but XML-RPC is blocked by external system
        return {
          success: false,
          external_block: true,
          message: `Warning: Protection is disabled but XML-RPC is still blocked (HTTP ${response.status}). External protection detected.`,
          raw: `URL: ${url} | Status: ${response.status} | External Block`
        };
      }

      // Toggle is ON: Check if XML-RPC is blocked
      if (isBlocked) {
        return {
          success: true,
          message: vaptEnforced === 'php-xmlrpc'
            ? `Plugin is actively blocking XML-RPC (${vaptEnforced}).`
            : `Plugin is actively blocking XML-RPC (HTTP ${response.status}).`,
          raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced || 'HTTP ' + response.status}`
        };
      }

      return {
        success: false,
        message: isVulnerable
          ? `SECURITY FAILURE: Protection is enabled but XML-RPC is OPEN and VULNERABLE (HTTP 200).`
          : `XML-RPC is blocked (HTTP ${response.status}), but NOT by this plugin. VAPT enforcement header missing.`,
        raw: `URL: ${url} | Status: ${response.status} | Expected: 403`
      };
    },

    // 3a. Pingback Probe: verifies pingback methods are disabled
    disable_xmlrpc_pingback: async (siteUrl, control, featureData, featureKey) => {
      const url = resolveUrl('/xmlrpc.php', control.config?.url, featureKey);
      vaptLog.log(`XML-RPC Pingback Probe: Fetching ${url}`);
      const payload = '<?xml version="1.0"?><methodCall><methodName>system.listMethods</methodName><params></params></methodCall>';
      const response = await fetch(url, { method: 'POST', body: payload, cache: 'no-store' });
      const body = await response.clone().text();
      const bodyLower = body.toLowerCase();
      const vaptEnforced = response.headers.get('x-vapt-enforced');
      const enforcedFeature = response.headers.get('x-vapt-feature');
      const vaptOrigin = response.headers.get('x-vapt-origin');
      const vaptReason = response.headers.get('x-vapt-reason');
      const isEnabled = isFeatureEnabled(featureData);
      let auditSummary = [];
      if (typeof apiFetch === 'function') {
        try {
          const auditResp = await apiFetch({
            path: `vaptsecure/v1/features/${encodeURIComponent(featureKey)}/verify`,
            method: 'POST'
          });
          if (Array.isArray(auditResp?.audit_summary)) {
            auditSummary = auditResp.audit_summary;
          }
        } catch (e) {
          vaptLog.warn('Pingback ownership audit failed:', e);
        }
      }

      const hasFileOwnership = auditSummary.some(item => String(item?.status || '').toLowerCase() === 'present');
      const auditAvailable = auditSummary.length > 0;
      const auditLabel = auditSummary.length
        ? auditSummary.map(item => `${item?.label || item?.target || 'target'}=${item?.status || 'unknown'}`).join(', ')
        : 'unavailable';

      const pingbackExposed = bodyLower.includes('pingback.ping') || bodyLower.includes('pingback.extensions.getpingbacks');
      const hasPluginOwnership = (vaptOrigin || '').toLowerCase() === 'vaptsecure' || vaptEnforced === 'php-pingback';
      const httpBlocked = response.status === 401 || response.status === 403 || response.status === 404 || response.status === 405;
      const pingbackBlocked = httpBlocked || hasPluginOwnership || !pingbackExposed;

      if (!hasPluginOwnership && hasFileOwnership) {
        return {
          success: true,
          message: `Plugin enforcement confirmed: the target file contains this feature's XML-RPC rule.`,
          raw: `URL: ${url} | Status: ${response.status} | File Audit: ${auditLabel}`
        };
      }

      if (hasPluginOwnership) {
        if (featureKey && enforcedFeature && enforcedFeature !== featureKey) {
          return { inconclusive: true, success: false, message: `Inconclusive: XML-RPC pingback is blocked by another VAPT feature ('${enforcedFeature}').`, raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced || 'php-pingback'} | Origin: ${vaptOrigin || 'unknown'}` };
        }
        if (!isEnabled) {
          return { success: false, message: `External block detected: pingback enforcement is present but the feature is disabled.`, raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced || 'php-pingback'} | Origin: ${vaptOrigin || 'unknown'} | Reason: ${vaptReason || 'n/a'}` };
        }
        return { success: true, message: `Plugin enforcement confirmed: XML-RPC pingback is disabled by this plugin.`, raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced || 'php-pingback'} | Origin: ${vaptOrigin || 'vaptsecure'} | Reason: ${vaptReason || 'pingback-removed'}` };
      }

      if (!isEnabled) {
        if (hasFileOwnership) {
          return {
            inconclusive: true,
            success: false,
            message: `Cleanup required: the feature is disabled, but the target file still contains this feature's XML-RPC rule.`,
            raw: `URL: ${url} | Status: ${response.status} | File Audit: ${auditLabel}`
          };
        }
        if (httpBlocked || hasPluginOwnership) {
          return {
            success: false,
            external_block: true,
            message: `External block detected: XML-RPC returned HTTP ${response.status} while this feature is disabled and no plugin-owned rule was found.`,
            raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced || 'none'} | File Audit: ${auditLabel}`
          };
        }
        return {
          success: false,
          skipped: true,
          message: `Protection disabled: no plugin-owned XML-RPC enforcement was detected for this feature.`,
          raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced || 'none'} | File Audit: ${auditLabel}`
        };
      }

      if (pingbackBlocked) {
        if (!auditAvailable) {
          return {
            inconclusive: true,
            success: false,
            message: `Verification audit unavailable: XML-RPC returned HTTP ${response.status}, but the verifier could not read the target-file audit for ${featureKey}.`,
            raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced || 'none'} | Origin: ${vaptOrigin || 'none'} | File Audit: unavailable`
          };
        }
        if (!hasPluginOwnership && !hasFileOwnership) {
          return {
            success: false,
            external_block: true,
            message: `Blocked, but not by this feature: XML-RPC returned HTTP ${response.status} and the fresh file audit did not find a plugin-owned ${featureKey} rule.`,
            raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced || 'none'} | Origin: ${vaptOrigin || 'none'} | File Audit: ${auditLabel}`
          };
        }
        return {
          success: true,
          message: `Plugin enforcement confirmed: XML-RPC pingback is disabled by this plugin.`,
          raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced || 'php-pingback'} | Origin: ${vaptOrigin || 'vaptsecure'} | Reason: ${vaptReason || 'pingback-removed'}`
        };
      }

      return {
        inconclusive: true,
        success: false,
        message: hasFileOwnership
          ? `Target file contains this feature's rule, but XML-RPC still exposes pingback methods.`
          : `Inconclusive: XML-RPC pingback state could not be attributed to this plugin from the fresh file audit.`,
        raw: `URL: ${url} | Status: ${response.status} | Expected: pingback methods removed | File Audit: ${auditLabel}`
      };
    },

    // 3b. Username Enumeration Probe: verifies REST users endpoint is blocked
    block_author_enumeration: async (siteUrl, control, featureData, featureKey) => {
      const configPath = String(control?.config?.path || '').trim();
      const featureText = `${featureKey} ${(featureData?.label || featureData?.title || featureData?.name || '')} ${(featureData?.summary || featureData?.description || '')}`.toLowerCase();
      const surfaceFamily = detectSurfaceFamily(featureData, featureKey, control);
      const isLoginErrorSurface = surfaceFamily === 'login_error' || configPath.includes('/wp-login.php') || /login_errors|invalid credentials/.test(featureText);
      const isRestUsersSurface = surfaceFamily === 'rest_users' || configPath.includes('/wp-json/wp/v2/users') || /rest api|wp-json/.test(featureText);
      const isAuthorSurface = surfaceFamily === 'author_query' || configPath.includes('/?author=1') || /author query|author archives|author enumeration/.test(featureText);
      if (isLoginErrorSurface) {
        const loginUrl = resolveUrl('/wp-login.php', control.config?.url, featureKey);
        const payload = new URLSearchParams({
          log: 'vaptsecure_nonexistent_user',
          pwd: 'invalid-password',
          'wp-submit': 'Log In',
          redirect_to: `${siteUrl}/wp-admin/`,
          testcookie: '1'
        });
        vaptLog.log(`Login Error Consistency Probe: Fetching ${loginUrl}`);
        const response = await fetch(loginUrl, {
          method: 'POST',
          cache: 'no-store',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: payload
        });
        const body = await response.clone().text();
        const bodyLower = body.toLowerCase();
        const vaptEnforced = response.headers.get('x-vapt-enforced');
        const enforcedFeature = response.headers.get('x-vapt-feature');
        const isEnabled = isFeatureEnabled(featureData);
        const genericMessage = bodyLower.includes('invalid credentials') || bodyLower.includes('please try again');
        const leaksUsername = bodyLower.includes('is not registered') || bodyLower.includes('incorrect password') || bodyLower.includes('invalid username');

        if (vaptEnforced === 'php-headers' || vaptEnforced === 'php-functions' || vaptEnforced === 'php-rate-limit') {
          if (featureKey && enforcedFeature && enforcedFeature !== featureKey) {
            return { success: false, message: `Inconclusive: login error protection is attributed to another VAPT feature ('${enforcedFeature}').`, raw: `URL: ${loginUrl} | Status: ${response.status} | Enforcement: ${vaptEnforced}` };
          }
          if (vaptEnforced === 'php-rate-limit' && response.status !== 429 && response.status !== 403) {
            return { success: false, message: `Inconclusive: login rate limiting is active but the response status was HTTP ${response.status} instead of a blocking status.`, raw: `URL: ${loginUrl} | Status: ${response.status} | Enforcement: ${vaptEnforced}` };
          }
          return {
            success: true,
            message: vaptEnforced === 'php-rate-limit'
              ? 'Plugin is actively rate-limiting wp-login.php.'
              : 'Plugin is actively normalizing wp-login.php error messages.',
            raw: `URL: ${loginUrl} | Status: ${response.status} | Enforcement: ${vaptEnforced}`
          };
        }

        if (!isEnabled) {
          return {
            success: false,
            skipped: true,
            message: leaksUsername
              ? 'Protection disabled: wp-login.php reveals enumeration hints as expected.'
              : 'Protection disabled: wp-login.php does not reveal enumeration hints (system naturally secure).',
            raw: `URL: ${loginUrl} | Status: ${response.status} | Result: ${genericMessage ? 'generic-login-error' : 'enumeration-leak'}`
          };
        }

        if (genericMessage && !leaksUsername) {
          return {
            success: true,
            message: 'Plugin is actively blocking login-error based enumeration.',
            raw: `URL: ${loginUrl} | Status: ${response.status} | Result: generic-login-error`
          };
        }

        return {
          success: false,
          message: 'SECURITY FAILURE: Protection is enabled but wp-login.php still reveals username-specific error text.',
          raw: `URL: ${loginUrl} | Status: ${response.status} | Expected: generic login error`
        };
      }

      const probePath = configPath || (isRestUsersSurface ? '/wp-json/wp/v2/users' : (isAuthorSurface ? '/?author=1' : '/?author=1'));
      const url = resolveUrl(probePath, control.config?.url, featureKey);
      vaptLog.log(`REST User Enumeration Probe: Fetching ${url}`);
      const response = await fetch(url, { method: 'GET', cache: 'no-store' });
      const vaptEnforced = response.headers.get('x-vapt-enforced');
      const enforcedFeature = response.headers.get('x-vapt-feature');
      const isEnabled = isFeatureEnabled(featureData);
      const isBlocked = response.status === 401 || response.status === 403 || response.status === 404 || response.status === 405 || vaptEnforced === 'php-author-enum';
      const modeLabel = isRestUsersSurface ? 'REST user enumeration' : 'author enumeration';

      if (vaptEnforced === 'php-author-enum') {
        if (featureKey && enforcedFeature && enforcedFeature !== featureKey) {
          return { success: false, message: `Inconclusive: ${modeLabel} blocked by another VAPT feature ('${enforcedFeature}').`, raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced}` };
        }
        if (!isEnabled) {
          return { success: false, message: `Warning: Protection is disabled but ${modeLabel} is STILL being blocked (${vaptEnforced}).`, raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced}` };
        }
        return { success: true, message: `Plugin is actively blocking ${modeLabel} (${vaptEnforced}).`, raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced}` };
      }

      if (!isEnabled) {
        if (response.status === 200) {
          return {
            success: false,
            skipped: true,
            message: `Protection correctly disabled. ${modeLabel} is accessible (HTTP 200).`,
            raw: `URL: ${url} | Status: ${response.status} | Enforcement: None`
          };
        }
        return {
          success: false,
          external_block: true,
          message: `Warning: Protection is disabled but ${modeLabel} is still blocked (HTTP ${response.status}). External protection detected.`,
          raw: `URL: ${url} | Status: ${response.status} | External Block`
        };
      }

      if (isBlocked) {
        return {
          success: true,
          message: response.status === 200
            ? `${modeLabel} is still exposed (HTTP 200).`
            : `Plugin is actively blocking ${modeLabel} (HTTP ${response.status}).`,
          raw: `URL: ${url} | Status: ${response.status} | Enforcement: ${vaptEnforced || 'HTTP ' + response.status}`
        };
      }

      return {
        success: false,
        message: `SECURITY FAILURE: Protection is enabled but ${modeLabel} remains accessible (HTTP 200).`,
        raw: `URL: ${url} | Status: ${response.status} | Expected: 403/404`
      };
    },

    // 4. Directory Probe: Verifies Indexing Block
    disable_directory_browsing: async (siteUrl, control, featureData, featureKey) => {
      const target = resolveUrl('/wp-content/uploads/', control.config?.url);
      const resp = await fetch(target, { cache: 'no-store' });
      const text = await resp.text();
      const snippet = text.substring(0, 500);
      const vaptEnforced = resp.headers.get('x-vapt-enforced');
      const enforcedFeature = resp.headers.get('x-vapt-feature');

      const isEnabled = isFeatureEnabled(featureData);

      // [FIX v2.5.2] State-Aware Success Logic for Directory Browsing
      // +-------------------+------------------+-------------------------------------------------------------+---------+
      // | Feature Toggle    | Server Enforce   | Resulting Message                                           | Status  |
      // +-------------------+------------------+-------------------------------------------------------------+---------+
      // | ON                | Blocked (403)    | "Plugin is actively blocking directory listing."           | SUCCESS |
      // | ON                | Not Blocked      | "Protection ON but directory browsing is ACCESSIBLE."      | FAILURE |
      // | OFF               | Blocked (403)    | "Warning: Disabled but directory STILL blocked."            | FAILURE |
      // | OFF               | Not Blocked (200)| "Protection correctly disabled. Directory accessible."     | SUCCESS |
      // +-------------------+------------------+-------------------------------------------------------------+---------+

      const isBlocked = resp.status === 403 || resp.status === 404 || vaptEnforced === 'php-dir';

      if (vaptEnforced === 'php-dir') {
        if (featureKey && enforcedFeature && enforcedFeature !== featureKey) {
          return { success: false, message: `Inconclusive: Directory browsing blocked by '${enforcedFeature}'.`, raw: `URL: ${target} | Status: ${resp.status}\n\n${snippet}` };
        }
        if (!isEnabled) {
          // FAILURE: Toggle OFF but still enforcing
          return { success: false, message: `Warning: Protection is disabled but directory listing is STILL being blocked (${vaptEnforced}).`, raw: `URL: ${target} | Status: ${resp.status} | Enforcement: ${vaptEnforced}` };
        }
        // SUCCESS: Toggle ON and blocking active
        return { success: true, message: `Plugin is actively blocking directory listing (${vaptEnforced}).`, raw: `URL: ${target} | Status: ${resp.status} | Enforcement: ${vaptEnforced}` };
      }

      if (!isEnabled) {
        // Toggle is OFF: Check if directory browsing is accessible
        if (resp.status === 200) {
          // SUCCESS: Toggle OFF and directory is accessible - correctly disabled
          return {
            success: false, 
            skipped: true,
            message: `Protection correctly disabled. Directory browsing is accessible (HTTP ${resp.status}).`,
            raw: `URL: ${target} | Status: ${resp.status} | Enforcement: None`
          };
        }
        // FAILURE: Toggle OFF but directory is blocked by external system
        return {
          success: false,
          external_block: true,
          message: `Warning: Protection is disabled but directory is still blocked (HTTP ${resp.status}). External protection detected.`,
          raw: `URL: ${target} | Status: ${resp.status} | External Block\n\n${snippet}`
        };
      }

      // Toggle is ON: Check if directory browsing is blocked
      return { success: false, message: `Directory browsing blocked (HTTP ${resp.status}), but NOT by this plugin. VAPT enforcement header missing.`, raw: `URL: ${target} | Status: ${resp.status}\n\n${snippet}` };
    },

    // 5. Null Byte Probe (and aliases)
    inject_null_unicode: async (siteUrl, control, featureData) => {
      return PROBE_REGISTRY.block_null_byte_injection(siteUrl, control, featureData);
    },
    block_null_byte_injection: async (siteUrl, control, featureData) => {
      const target = resolveUrl('/', control.config?.url) + '?vaptsecure_test_param=safe&vaptsecure_attack=test%00payload';
      const resp = await fetch(target, { cache: 'no-store' });
      const vaptEnforced = resp.headers.get('x-vapt-enforced');

      const isEnabled = isFeatureEnabled(featureData);

      // [FIX v2.5.2] State-Aware Success Logic for Null Byte Injection
      // +-------------------+------------------+-------------------------------------------------------------+---------+
      // | Feature Toggle    | Server Enforce   | Resulting Message                                           | Status  |
      // +-------------------+------------------+-------------------------------------------------------------+---------+
      // | ON                | Blocked (400)    | "Plugin is actively blocking null byte injection."         | SUCCESS |
      // | ON                | Not Blocked      | "Protection ON but null byte payload ACCEPTED."           | FAILURE |
      // | OFF               | Blocked (400)    | "Warning: Disabled but null byte STILL being blocked."     | FAILURE |
      // | OFF               | Not Blocked (200)| "Protection correctly disabled. Null byte accessible."      | SUCCESS |
      // +-------------------+------------------+-------------------------------------------------------------+---------+

      const isBlocked = resp.status === 400 || resp.status === 403 || vaptEnforced === 'php-null-byte';

      if (vaptEnforced === 'php-null-byte' || resp.status === 400) {
        if (!isEnabled && vaptEnforced === 'php-null-byte') {
          // FAILURE: Toggle OFF but still enforcing
          return { success: false, message: `Warning: Protection is disabled but null byte injection is STILL being blocked (${vaptEnforced}).`, raw: `URL: ${target} | Status: ${resp.status} | Enforcement: ${vaptEnforced}` };
        }
        // SUCCESS: Either toggle ON with blocking, or server-level block (400)
        if (isEnabled) {
          return { success: true, message: `Plugin is actively blocking null byte injection (HTTP ${resp.status}). Enforcer: ${vaptEnforced || 'Server'}`, raw: `URL: ${target} | Status: ${resp.status} | Enforcement: ${vaptEnforced || 'Server'}` };
        }
      }

      if (!isEnabled) {
        // Toggle is OFF: Check if null byte payload is accepted
        if (resp.status === 200) {
          // SUCCESS: Toggle OFF and null byte is accepted - correctly disabled
          return {
            success: false, 
            skipped: true,
            message: `Protection correctly disabled. Null byte payload accepted (HTTP ${resp.status}).`,
            raw: `URL: ${target} | Status: ${resp.status} | Enforcement: None`
          };
        }
        // FAILURE: Toggle OFF but null byte is blocked by external system
        return {
          success: false,
          external_block: true,
          message: `Warning: Protection is disabled but null byte payload is still blocked (HTTP ${resp.status}). External protection detected.`,
          raw: `URL: ${target} | Status: ${resp.status} | External Block`
        };
      }

      // Toggle is ON: Check if null byte is blocked
      return { success: false, message: `SECURITY FAILURE: Protection toggle is ON but null byte payload was ACCEPTED (HTTP ${resp.status}).`, raw: `URL: ${target} | Status: ${resp.status} | Expected: 400 or 403` };
    },

    // 6. Version Hide Probe
    hide_wp_version: async (siteUrl, control, featureData) => {
      const url = resolveUrl('/', control.config?.url);
      const resp = await fetch(url + '?vaptsecure_version_check=1', { method: 'GET', cache: 'no-store' });
      const text = await resp.text();
      const vaptEnforced = resp.headers.get('x-vapt-enforced');

      const hasGenerator = text.toLowerCase().includes('name="generator" content="wordpress');
      const isEnabled = isFeatureEnabled(featureData);

      if (!isEnabled) {
        if (!hasGenerator) {
          return { success: false, unprotected: true, message: `Protection correctly disabled. WordPress generator tag is hidden by external policy.`, raw: `URL: ${url} | Status: ${resp.status} | State: Secure` };
        }
        return { success: false, unprotected: true, message: `Baseline Test: WordPress generator tag is present. You are unprotected for this risk vector.`, raw: `URL: ${url} | Status: ${resp.status} | State: Vulnerable` };
      }

      if (!hasGenerator) {
        return { success: true, message: `Secure: WordPress generator tag is hidden.`, raw: `URL: ${url} | Status: ${resp.status} | Expected: No generator tag` };
      }

      return { success: false, message: `Vulnerable: WordPress generator tag is present in the page source.`, raw: `URL: ${url} | Status: ${resp.status} | Expected: No generator tag` };
    },

    // 7. Universal Payload Probe (Dynamic Real-World Testing)
    universal_probe: async (siteUrl, control, featureData, featureKey) => {
      const config = control.test_config || {};
      const method = config.method || 'GET';
      const configuredPath = config.path || '/';
      const path = (configuredPath === '/' || configuredPath === '/index.php' || configuredPath.startsWith('/?vapt_header_check=') || configuredPath.startsWith('/?vaptsecure_header_check='))
        ? inferProbePath(featureData, featureKey, control)
        : configuredPath;
      const params = config.params || {};
      const headers = config.headers || {};
      const body = config.body || null;
      const expectedStatus = config.expected_status;
      const expectedText = config.expected_text;
      const expectedHeaders = config.expected_headers;

      let url = resolveUrl(path, config.url, featureKey);
      const contextParam = (featureKey && (featureKey.includes('login') || featureKey.includes('brute'))) ? 'vaptsecure_test_context=login' : '';

      if (method === 'GET') {
        const urlParams = new URLSearchParams(params);
        if (contextParam) urlParams.append('vaptsecure_test_context', 'login');
        const qs = urlParams.toString();
        if (qs) url = url + (url.includes('?') ? '&' : '?') + qs;
      } else if (contextParam) {
        url = url + (url.includes('?') ? '&' : '?') + contextParam;
      }

      const fetchOptions = {
        method: method,
        headers: headers,
        cache: 'no-store'
      };

      if (method !== 'GET' && body) {
        fetchOptions.body = typeof body === 'object' ? JSON.stringify(body) : body;
        if (typeof body === 'object' && !fetchOptions.headers['Content-Type']) {
          fetchOptions.headers['Content-Type'] = 'application/json';
        }
      } else if (method !== 'GET' && Object.keys(params).length > 0) {
        const formData = new URLSearchParams();
        for (const k in params) formData.append(k, params[k]);
        fetchOptions.body = formData;
        if (!fetchOptions.headers['Content-Type']) {
          fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }
      }

      const resp = await fetch(url, fetchOptions);
      const text = await resp.text();

      let isSecure = false;
      let statusMatches = false;
      let headerMatches = false;
      const code = resp.status;

      let expectedStatusArray = [];
      if (expectedStatus) {
        expectedStatusArray = Array.isArray(expectedStatus)
          ? expectedStatus.map(s => parseInt(s))
          : [parseInt(expectedStatus)];
      }

      if (expectedStatusArray.length > 0) {
        statusMatches = expectedStatusArray.includes(code);
      }

      if (expectedHeaders && typeof expectedHeaders === 'object') {
        headerMatches = true;
        const responseHeaders = {};
        resp.headers.forEach((v, k) => { responseHeaders[k.toLowerCase()] = v; });

        for (const [key, expectedValue] of Object.entries(expectedHeaders)) {
          const actualValue = responseHeaders[key.toLowerCase()];
          // Support multi-value OR logic with | separator (v3.13.17)
          let expectedValueTransformed = expectedValue;

          // Global Platform Normalization: Alias 'htaccess' or 'nginx' to allow fallbacks (v3.13.18)
          // This prevents false failures on Nginx/PHP environments for legacy probes.
          if (key.toLowerCase() === 'x-vapt-enforced' && (expectedValue === 'htaccess' || expectedValue === 'nginx' || expectedValue === 'php-headers' || expectedValue === 'php-cron')) {
            expectedValueTransformed = 'htaccess|nginx|php-headers|php-cron';
          }

          const expectedOptions = expectedValueTransformed.split('|').map(v => v.trim().toLowerCase());
          if (!actualValue || !expectedOptions.includes(actualValue.toLowerCase())) {
            headerMatches = false;
            break;
          }
        }
      }

      const isEnabled = isFeatureEnabled(featureData);
      const vaptEnforced = resp.headers.get('x-vapt-enforced');
      const enforcedFeature = resp.headers.get('x-vapt-feature');
      const expectedEnforcer = config.expected_enforcer || control.test_config?.expected_enforcer || '';
      if (!headerMatches && expectedEnforcer) {
        headerMatches = matchesExpectedEnforcer(vaptEnforced, expectedEnforcer);
      }

      const expectsBlock = expectedStatusArray.length > 0 && expectedStatusArray.every(s => s >= 400);
      const expectsAllow = expectedStatusArray.includes(200);
      const hasHeaderCheck = expectedHeaders && typeof expectedHeaders === 'object';

      let message = '';

      // [FIX v2.5.2] State-Aware Success Logic for Universal Probe
      // +-------------------+------------------+-------------------------------------------------------------+---------+
      // | Feature Toggle    | Server Enforce   | Resulting Message                                           | Status  |
      // +-------------------+------------------+-------------------------------------------------------------+---------+
      // | ON                | Detected/Blocked | "Protection is active. Attack blocked or headers present."  | SUCCESS |
      // | ON                | Not Detected     | "Protection ON but server NOT enforcing expected response." | FAILURE |
      // | OFF               | Detected         | "Warning: Disabled but server STILL enforcing."             | FAILURE |
      // | OFF               | Not Detected     | "Protection correctly disabled. No enforcement detected."   | SUCCESS |
      // +-------------------+------------------+-------------------------------------------------------------+---------+

      if (!isEnabled) {
        // Toggle is OFF: Success depends on whether enforcement is detected
        const activeFeatures = enforcedFeature ? enforcedFeature.split(',').map(f => f.trim()) : [];
        const isThisFeatureEnforcing = activeFeatures.includes(featureKey);

        if (isThisFeatureEnforcing) {
          // FAILURE: Toggle OFF but this specific feature is still enforcing
          return {
            success: false,
            message: `Warning: Feature '${featureKey}' is OFF but server is STILL enforcing it ('${vaptEnforced}').`,
            raw: `URL: ${url} | Status: ${code} | Enforcement: ${vaptEnforced}`
          };
        }

        if (hasHeaderCheck && headerMatches) {
          // FAILURE: Toggle OFF but expected headers detected
          return {
            success: false,
            message: `Warning: Protection toggle is OFF but expected headers are still present (${vaptEnforced || 'headers matched'}).`,
            raw: `URL: ${url} | Status: ${code} | Headers: Matched`
          };
        }

        if (vaptEnforced && (expectsBlock ? expectedStatusArray.includes(code) : headerMatches)) {
          // FAILURE: Toggle OFF but server appears to be enforcing
          return {
            success: false,
            message: `Warning: Protection toggle is OFF but server is STILL enforcing (${vaptEnforced}).`,
            raw: `URL: ${url} | Status: ${code} | Enforcement: ${vaptEnforced}`
          };
        }

        // SUCCESS: Toggle OFF and no enforcement detected - correctly disabled
        if (expectsBlock && !expectedStatusArray.includes(code) && code === 200) {
          return {
            success: false, unprotected: true,
            message: `Protection correctly disabled. Target is accessible (HTTP ${code}).`,
            raw: `URL: ${url} | Status: ${code} | Enforcement: None`
          };
        }
        if (expectsAllow && code === 200) {
          return {
            success: false, unprotected: true,
            message: `Protection correctly disabled. Target responded normally (HTTP ${code}).`,
            raw: `URL: ${url} | Status: ${code} | Enforcement: None`
          };
        }
        return {
          success: false, unprotected: true,
          message: `Protection correctly disabled. No enforcement detected.`,
          raw: `URL: ${url} | Status: ${code} | Enforcement: None`
        };
      } else if (hasHeaderCheck) {
        isSecure = headerMatches && (code === 200 || expectsAllow || statusMatches);
      } else if (expectsBlock) {
        // Allow 404 and 400 as valid "Blocks" (Global Security Best Practice & REST API Protection)
        const is404Acceptable = code === 404;
        const is400Acceptable = code === 400;
        isSecure = (statusMatches || is404Acceptable || is400Acceptable) && code >= 400;
      } else if (statusMatches) {
        isSecure = true;
      } else {
        // 🛡️ Resilience Nudge (v3.14.3): If VAPT header exists, 403/404/429 are valid "Secure" blocks 
        if (vaptEnforced && [403, 404, 429].includes(code)) {
          isSecure = true;
          headerMatches = true;
        } else if (expectsAllow) {
          isSecure = code === 200 && (expectedText ? text.includes(expectedText) : true);
        } else {
          isSecure = code === 200;
        }
      }

      // Helper to resolve feature aliases (v3.6.20)
      const areFeaturesEquivalent = (f1, f2) => {
        if (!f1 || !f2) return false;
        if (f1 === f2) return true;

        // Normalize known aliases
        const aliases = {
          'user-enumeration': ['username-enumeration-via-wordpress-rest-api', 'block-user-enumeration'],
          'username-enumeration-via-wordpress-rest-api': ['user-enumeration', 'block-user-enumeration'],
          'xmlrpc': ['block-xmlrpc', 'disable-xmlrpc'],
          'block-xmlrpc': ['xmlrpc', 'disable-xmlrpc']
        };

        return (aliases[f1] && aliases[f1].includes(f2));
      };


      if (isSecure && expectsBlock && featureKey && enforcedFeature && !areFeaturesEquivalent(enforcedFeature, featureKey)) {
        isSecure = false;
        return {
          success: false,
          message: `Inconclusive: Request blocked by overlapping feature '${enforcedFeature}'. Disable it to verify this control.`,
          raw: `URL: ${url} | Status: ${code} | Enforcer: ${enforcedFeature} vs ${featureKey}`
        };
      }

      if (message) {
        // Message already set by leak detection logic
      } else if (isSecure) {
        if (hasHeaderCheck && headerMatches) {
          message = `Protection Headers Present (HTTP ${code}). All expected headers verified.`;
        } else if (expectsBlock && statusMatches) {
          message = `Attack Blocked (HTTP ${code}). Expected block code (${expectedStatus}).`;
        } else if (expectsBlock && code === 404) {
          message = `Attack Blocked (HTTP 404). Resource hidden successfully.`;
        } else if (expectsBlock && code === 400) {
          message = `Attack Blocked (HTTP 400). Request rejected (Expected ${expectedStatus}).`;
        } else if (expectsAllow && code === 200) {
          message = `Normal Response (HTTP ${code}) with protection indicators.`;
        } else {
          message = `Expected Response Received (HTTP ${code}).`;
        }
      } else {
        if (code === 200 && expectsBlock) {
          message = `Attack Accepted (HTTP 200). Expected Block (${expectedStatus}).`;
        } else if (hasHeaderCheck && !headerMatches) {
          if (expectsBlock && statusMatches) {
            isSecure = true;
            message = `PASS: Request was blocked (HTTP ${code}). Note: Server-Level block detected.`;
          } else {
            const vaptEnforcedHeader = resp.headers.get('x-vapt-enforced');
            if (vaptEnforcedHeader) {
              message = `Header Mismatch (HTTP ${code}). VAPT is active but headers do not match expected values.`;
            } else {
              message = `Missing Protection Headers (HTTP ${code}). Verification failed.`;
            }
          }
        } else if (statusMatches === false && expectedStatus) {
          message = `Mismatch: Got HTTP ${code}, expected ${expectedStatus}.`;
        } else {
          message = `Unexpected Response (HTTP ${code}). Could not verify security.`;
        }
      }

      return {
        success: isSecure,
        message: message,
        raw: `URL: ${url} | Status: ${code} | Expected: ${expectedStatus || 'N/A'}`
      };
    },

    verify_implementation: async (siteUrl, control, featureData, featureKey) => {
      try {
        const featureKeySanitized = encodeURIComponent(featureKey || '');
        const restPath = `vaptsecure/v1/features/${featureKeySanitized}/verify`;
        let payload;

        // 🛡️ Standard WordPress API Fetch (v3.15.3) - Handles Nonce & Root automatically
        if (window.wp && window.wp.apiFetch) {
          try {
            payload = await window.wp.apiFetch({ 
              path: restPath, 
              method: 'POST' 
            });
          } catch (apiErr) {
            vaptLog.error(`apiFetch critical failure for ${featureKey}:`, apiErr);
            // If apiFetch specifically returned an error object with message/code
            const errorMsg = apiErr.message || (typeof apiErr === 'string' ? apiErr : JSON.stringify(apiErr));
            throw new Error(`REST API Error: ${errorMsg}`);
          }
        }

        if (!payload) {
          const root = (window.vaptSecureSettings && window.vaptSecureSettings.root) ? window.vaptSecureSettings.root : (siteUrl + '/wp-json/');
          // Normalize slashes: ensure root ends with / and restPath does NOT start with /
          const normalizedRoot = root.endsWith('/') ? root : root + '/';
          const url = `${normalizedRoot}${restPath}`;
          const nonce = window.vaptSecureSettings?.nonce;
          
          const response = await fetch(url, { 
            method: 'POST', 
            cache: 'no-store', 
            headers: { 
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              ...(nonce ? { 'X-WP-Nonce': nonce } : {})
            } 
          });

          const contentType = response.headers.get('content-type');
          if (!contentType || !contentType.includes('application/json')) {
              const text = await response.text();
              vaptLog.error('Verification non-JSON response:', text.substring(0, 500));
              throw new Error(`Server returned ${response.status} ${response.statusText} at ${url}. Expected JSON but got ${contentType || 'text/html'}.`);
          }

          payload = await response.json();
        }

        if (payload && typeof payload === 'object') {
          // 🛡️ Format a clean raw trace for the UI (v3.15.4)
          const probe = payload.probe || {};
          let traceStr = `URL: ${probe.path || restPath}\n`;
          traceStr += `Status: ${payload.status || 'unknown'}\n`;
          
          if (payload.config_trace && typeof payload.config_trace === 'object') {
            Object.entries(payload.config_trace).forEach(([label, val]) => {
              traceStr += `${label}: ${val}\n`;
            });
          }

          if (payload.audit_summary && Array.isArray(payload.audit_summary)) {
            payload.audit_summary.forEach(item => {
              traceStr += `${item.label || item.target}: ${item.status || 'unknown'}\n`;
            });
          }

          return {
            success: !!payload.success,
            message: payload.message || (payload.success ? 'Implementation verified.' : 'Implementation verification failed.'),
            meta: payload,
            raw: traceStr.trim() || JSON.stringify(payload)
          };
        }

        return {
          success: false,
          message: 'Implementation verification returned an empty response.',
          raw: `URL: ${restPath} | Status: OK`
        };
      } catch (err) {
        vaptLog.error(`Verification Error for ${featureKey}:`, err);
        return {
          success: false,
          message: `Implementation verification failed: ${err.message}`,
          raw: { error: err.message, stack: err.stack }
        };
      }
    },

    // 🛡️ Verification Aliases (v3.15.2)
    verify_protection: async (siteUrl, control, featureData, featureKey) => {
      return PROBE_REGISTRY.verify_implementation(siteUrl, control, featureData, featureKey);
    },
    block_wp_cron: async (siteUrl, control, featureData, featureKey) => {
      return PROBE_REGISTRY.verify_implementation(siteUrl, control, featureData, featureKey);
    },

    // 8. Default Generic Probe
    default: async (siteUrl, control, featureData) => {
      const resp = await fetch(siteUrl + '?vaptsecure_ping=1');
      const isEnabled = isFeatureEnabled(featureData);
      if (!isEnabled) {
        return { success: false, unprotected: true, message: `Protection correctly disabled. Verification probe active (HTTP ${resp.status}).`, raw: `URL: ${siteUrl} | Status: ${resp.status}` };
      }
      return { success: resp.ok, message: `Probe result: HTTP ${resp.status}`, raw: `URL: ${siteUrl} | Status: ${resp.status} | Time: ${new Date().toISOString()}` };
    }
  };

  /*
   * Evidence Gallery Component (v3.5.2)
   * Handles multiple screenshot rendering with modal preview
   */
  const EvidenceGallery = ({ screenshots }) => {
    const [selectedImage, setSelectedImage] = useState(null);

    if (!screenshots || !Array.isArray(screenshots) || screenshots.length === 0) return null;

    return el('div', { className: 'vapt-evidence-gallery', style: { marginTop: '10px' } }, [
      el('div', { style: { fontSize: '11px', fontWeight: '700', textTransform: 'uppercase', color: '#64748b', marginBottom: '6px' } },
        sprintf(__('%d Evidence Captured', 'vaptsecure'), screenshots.length)
      ),
      el('div', {
        style: {
          display: 'flex',
          gap: '8px',
          overflowX: 'auto',
          padding: '4px',
          background: '#f1f5f9',
          borderRadius: '4px',
          border: '1px solid #e2e8f0'
        }
      }, screenshots.map((url, i) =>
        el('div', {
          key: i,
          onClick: () => setSelectedImage(url),
          style: {
            width: '60px',
            height: '60px',
            flexShrink: 0,
            cursor: 'pointer',
            backgroundImage: `url(${url})`,
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            borderRadius: '3px',
            border: '1px solid #cbd5e1',
            position: 'relative'
          }
        }, el(Icon, { icon: 'search', size: 12, style: { position: 'absolute', bottom: '2px', right: '2px', background: 'rgba(255,255,255,0.8)', borderRadius: '50%', padding: '2px' } }))
      )),

      selectedImage && el(Modal, {
        title: __('Evidence Detail', 'vaptsecure'),
        onRequestClose: () => setSelectedImage(null),
        style: { maxWidth: '90vw', maxHeight: '90vh' }
      }, [
        el('div', { style: { display: 'flex', justifyContent: 'center', background: '#000', borderRadius: '4px', overflow: 'hidden' } },
          el('img', { src: selectedImage, style: { maxWidth: '100%', maxHeight: '70vh' } })
        ),
        el('div', { style: { marginTop: '15px', textAlign: 'right' } },
          el(Button, { isPrimary: true, onClick: () => setSelectedImage(null) }, __('Close', 'vaptsecure'))
        )
      ])
    ]);
  };

  /*
   * File Inspector Component (v3.13.15)
   * Specialized rendering for file contents/directory listings
   */
  const FileInspector = ({ content, label = __('Verification Trace', 'vaptsecure'), testContext = '' }) => {
    if (!content) return null;

    // 🛡️ Robust Type Handling (v3.13.15)
    let displayContent = content;
    if (typeof content === 'object') {
      try {
        displayContent = JSON.stringify(content, null, 2);
      } catch (e) {
        displayContent = String(content);
      }
    }

    // Format headers to appear on separate lines
    if (typeof displayContent === 'string') {
      // Ensure each header starts on a new line for clarity
      // Match headers that might be concatenated without proper line breaks
      const headerPatterns = [
        'x-powered-by:', 'x-frame-options:', 'x-content-type-options:', 'x-xss-protection:',
        'strict-transport-security:', 'content-security-policy:', 'referrer-policy:', 'permissions-policy:',
        'server:', 'cache-control:', 'pragma:', 'expires:', 'vary:',
        'access-control-allow-origin:', 'access-control-allow-methods:', 'access-control-allow-headers:',
        'access-control-expose-headers:', 'access-control-max-age:', 'access-control-allow-credentials:',
        'x-vapt-enforced:', 'x-vapt-feature:'
      ];

      // For each header pattern, ensure it's preceded by a newline if not already
      headerPatterns.forEach(pattern => {
        const regex = new RegExp(`([^\\n])\\s*(${pattern})`, 'gi');
        displayContent = displayContent.replace(regex, '$1\n$2');
      });

      // Clean up multiple consecutive newlines
      displayContent = displayContent.replace(/\n\s*\n/g, '\n').trim();
    }

    // Auto-detect if content implies a directory listing
    const isDir = typeof displayContent === 'string' && (displayContent.includes('Index of /') || displayContent.includes('Parent Directory'));
    const isTrace = label === __('Verification Trace', 'vaptsecure');
    const displayLabel = isDir ? __('Directory Listing Exposed', 'vaptsecure') : label;
    const resolvedIcon = isTrace ? 'info' : 'media-code';

    return (isTrace && typeof displayContent === 'string' && displayContent.startsWith('URL: ')) ? el('div', { className: 'vapt-file-inspector', style: { marginTop: '10px', maxWidth: '100%', overflow: 'hidden' } }, [
      el(Tooltip, {
        text: el('div', { style: { textAlign: 'left', maxWidth: '300px' } }, [
          testContext ? el('div', { style: { marginBottom: '8px', lineHeight: '1.4' } }, testContext) : null,
          el('div', { style: { color: '#94a3b8', whiteSpace: 'pre-wrap' } }, displayContent.split(' | ').slice(1).join(' | '))
        ]), placement: 'top'
      },
        el('span', { style: { fontSize: '10px', fontWeight: '700', textTransform: 'uppercase', cursor: 'pointer', display: 'inline-flex', alignItems: 'center', gap: '5px' } }, [
          el('span', { style: { color: '#3b82f6', display: 'flex', alignItems: 'center' } }, el(Icon, { icon: 'info', size: 14 })),
          el('span', { style: { color: '#94a3b8' } }, displayLabel)
        ])
      )
    ]) : el('div', { className: 'vapt-file-inspector', style: { marginTop: '10px', display: 'flex', flexDirection: 'column', maxWidth: '100%', overflow: 'hidden' } }, [
      el('div', { style: { fontSize: '10px', fontWeight: '700', textTransform: 'uppercase', color: '#94a3b8', marginBottom: '4px', display: 'flex', alignItems: 'center', gap: '5px' } }, [
        el(Icon, { icon: resolvedIcon, size: 14 }),
        displayLabel
      ]),
            typeof displayContent === 'string' && displayContent.startsWith('URL: ') ? (() => {
        // Parse the trace content into structured lines
        const lines = [];
        const parts = displayContent.split(/\n/);
        
        parts.forEach(part => {
          if (part.startsWith('URL: ')) {
            // Extract URL and status info
            const urlMatch = part.match(/URL:\s*(https?:\/\/[^\s|]+)/);
            const remainingContent = part.split(' | ').slice(1);
            
            if (urlMatch) {
              lines.push({ type: 'url', value: urlMatch[1] });
            }
            if (remainingContent.length > 0) {
              lines.push({ type: 'status', value: remainingContent.join(' | ') });
            }
          } else if (part.includes(':')) {
            // Header lines (x-powered-by:, x-vapt-enforced:, etc.)
            lines.push({ type: 'header', value: part.trim() });
          }
        });
        
        return el('div', {
          style: {
            fontSize: '11px',
            background: '#f8fafc',
            border: '1px solid #e2e8f0',
            borderRadius: '4px',
            padding: '12px',
            color: '#334155',
            fontFamily: 'monospace',
            textAlign: 'left',
            maxWidth: '100%',
            overflow: 'hidden',
            display: 'flex',
            flexDirection: 'column',
            gap: '4px',
            lineHeight: '1.5'
          }
        }, lines.map((line, i) => {
          if (line.type === 'url') {
            return el('div', { key: i }, [
              el('span', { key: 'label', style: { color: '#64748b' } }, 'URL: '),
              el('a', { 
                key: 'link', 
                href: line.value, 
                target: '_blank', 
                rel: 'noopener noreferrer', 
                style: { color: '#2563eb', textDecoration: 'underline', fontWeight: 'bold', wordBreak: 'break-all' } 
              }, line.value)
            ]);
          }
          return el('div', { key: i, style: { wordBreak: 'break-word' } }, line.value);
        }));
      })() : el('pre', {

        style: {
          fontSize: '10px',
          fontFamily: 'monospace',
          background: '#fff',
          border: '1px solid #e2e8f0',
          borderRadius: '4px',
          padding: '10px',
          maxHeight: '200px',
          maxWidth: '100%',
          overflow: 'auto',
          whiteSpace: 'pre-wrap',
          color: '#334155',
          wordBreak: 'break-word'
        }
      }, typeof displayContent === 'string' ? displayContent.split(/(https?:\/\/[^\s]+)/g).map((part, i) =>
        part.match(/^https?:\/\//)
          ? el('a', { key: i, href: part, target: '_blank', rel: 'noopener noreferrer', style: { color: '#2563eb', textDecoration: 'underline' } }, part)
          : part
      ) : displayContent)
    ]);
  };

  /*
   * Sync/Async Toggle Component (v3.5.2)
   * Stateful toggle for execution mode
   */


  const TestRunnerControl = ({ control, featureData, featureKey, globalProtection, showTechnicalTrace = false, showVerificationDetails = true }) => {
    const [status, setStatus] = useState('idle');
    const [result, setResult] = useState(null);
    const [progress, setProgress] = useState(null);
    const [numTests, setNumTests] = useState(''); // Custom test count (v3.6.26)
    const [traceLog, setTraceLog] = useState([]); // [v4.1.5] Real-time trace log

    const config = control?.test_config || control?.config || {};
    const targetPath = String(config.path || '/').trim() || '/';
    const targetUrl = resolveUrl(targetPath, config.url, featureKey);

    const runTest = async () => {
      setStatus('running');
      setResult(null);
      setTraceLog([{ time: new Date().toLocaleTimeString(), message: `Initiating probe: ${control.test_logic}`, type: 'info' }]);

      const { test_logic } = control;
      const siteUrl = window.location.origin;
      const handler = PROBE_REGISTRY[test_logic] || PROBE_REGISTRY['default'];

      try {
        const timeoutPromise = new Promise((_, reject) =>
          setTimeout(() => reject(new Error('Test timeout after 120 seconds')), 120000)
        );

        const progressCallback = (p) => {
          setProgress(p);
          if (p.message) {
            setTraceLog(prev => [...prev, { time: new Date().toLocaleTimeString(), message: p.message, type: 'step' }]);
          }
        };

        const handlerPromise = handler(siteUrl, { ...control, isAsync: false, numTests }, featureData, featureKey, progressCallback);
        const res = await Promise.race([handlerPromise, timeoutPromise]);

        if (res && typeof res === 'object') {
          setTraceLog(prev => [...prev, { time: new Date().toLocaleTimeString(), message: `Probe Complete: ${res.message || (res.success ? 'Success' : 'Failed')}`, type: res.success ? 'success' : 'error' }]);
          if (res.raw) {
            setTraceLog(prev => [...prev, { time: new Date().toLocaleTimeString(), message: `Raw Response Data Captured`, type: 'debug' }]);
          }
          
          if (res.inconclusive) {
            setStatus('inconclusive');
          } else if (res.unprotected) {
            setStatus('unprotected');
          } else if (res.external_block) {
            setStatus('external_block');
          } else if (res.skipped) {
            setStatus('skipped');
          } else {
            setStatus(res.success ? 'success' : 'error');
          }
          setResult(res);
        } else {
          throw new Error('Invalid test result format');
        }
      } catch (err) {
        vaptLog.error(`Probe Execution Error for ${control.test_logic}:`, err);
        setTraceLog(prev => [...prev, { time: new Date().toLocaleTimeString(), message: `Critical Error: ${err.message}`, type: 'error' }]);
        setStatus('error');
        setResult({
          success: false,
          message: `Error: ${err.message}`,
          raw: `URL: ${targetUrl} | Error: ${err.message}`
        });
      }
    };

    const handleClick = () => {
      runTest();
    };

    let rpmValue = parseInt(featureData['rpm'] || featureData['rate_limit'], 10);
    if (isNaN(rpmValue)) {
      const limitKey = Object.keys(featureData).find(k => k.includes('limit') || k.includes('max') || k.includes('rpm'));
      if (limitKey) rpmValue = parseInt(featureData[limitKey], 10);
    }
    if (isNaN(rpmValue)) rpmValue = 5;

    const currentRPM = parseInt(numTests || rpmValue, 10);
    const loadValue = Math.ceil(currentRPM * 1.25);
    const displayLabel = control.test_logic === 'spam_requests'
      ? control.label.replace(/\(\s*\d+.*\)/g, '').trim() + ` (${loadValue} requests)`
      : control.label;

    return el('div', { className: 'vapt-test-runner', style: { padding: '15px', background: '#f8fafc', border: '1px solid #e2e8f0', borderRadius: '6px', marginBottom: '10px' } }, [
      el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '2px' } }, [
        el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, [
          el('strong', { style: { fontSize: '12px', color: '#334155' } }, displayLabel)
        ]),
        el(Button, { isSecondary: true, isSmall: true, isBusy: status === 'running', onClick: handleClick, disabled: status === 'running' }, 'Run Verify')
      ]),
      el('div', { style: { marginBottom: '8px', fontSize: '10px', color: '#64748b', wordBreak: 'break-all' } }, [
        el('strong', { style: { color: '#475569' } }, __('Target URL:', 'vaptsecure')),
        ' ',
        el('a', {
          href: targetUrl,
          target: '_blank',
          rel: 'noopener noreferrer',
          style: { color: '#2563eb', textDecoration: 'underline' }
        }, targetUrl)
      ]),
      !globalProtection && el('div', { style: { marginBottom: '10px', padding: '8px 12px', background: '#fff7ed', border: '1px solid #fed7aa', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '8px' } }, [
        el(Icon, { icon: 'warning', size: 16, style: { color: '#ea580c' } }),
        el('span', { style: { fontSize: '11px', color: '#9a3412', fontWeight: '600' } }, __('Global Protection is OFF. This real-time test will likely report "System Vulnerable".', 'vaptsecure'))
      ]),
      control.help && el('p', { style: { margin: '2px 0 0', fontSize: '11px', color: '#64748b', opacity: 0.8 } }, control.help),

      // Real-time Progress Bar (v3.6.25)
      status === 'running' && progress && el('div', { style: { marginTop: '10px', background: '#e2e8f0', borderRadius: '4px', height: '4px', overflow: 'hidden' } }, [
        el('div', { style: { background: '#2563eb', width: `${(progress.current / progress.total) * 100}%`, height: '100%', transition: 'width 0.3s' } })
      ]),
      status === 'running' && progress && el('div', { style: { display: 'flex', justifyContent: 'space-between', marginTop: '4px', fontSize: '10px', color: '#64748b' } }, [
        el('span', null, sprintf(__('Testing: %d/%d requests...', 'vaptsecure'), progress.current, progress.total)),
        el('div', { style: { display: 'flex', gap: '8px' } }, [
          el('span', { style: { color: '#10b981' } }, `${progress.accepted} Accepted`),
          el('span', { style: { color: progress.blocked > 0 ? '#ef4444' : '#64748b' } }, `${progress.blocked} Blocked`)
        ])
      ]),

      // Custom Test Count Input (v3.6.26/28)
      // Visible whenever NOT running (v3.6.28)
      status !== 'running' && control.test_logic === 'spam_requests' && el('div', { style: { marginTop: '10px', display: 'flex', alignItems: 'center', gap: '10px' } }, [
        el('div', { style: { flex: 1 } }, [
          el(TextControl, {
            label: __('Number of Tests to Run', 'vaptsecure'),
            value: numTests,
            type: 'number',
            placeholder: sprintf(__('Default: %d', 'vaptsecure'), rpmValue),
            onChange: (val) => setNumTests(val),
            style: { marginBottom: 0 }
          })
        ]),
        el('div', { style: { fontSize: '11px', color: '#64748b', marginTop: '20px' } },
          numTests ? sprintf(__('Target: %d requests', 'vaptsecure'), Math.ceil(parseInt(numTests) * 1.25)) : ''
        )
      ]),

      status !== 'idle' && status !== 'running' && result && el('div', {
        className: 'vapt-result-container',
        style: {
          marginTop: '15px',
          padding: '16px',
          background: status === 'success' ? 'rgba(16, 185, 129, 0.04)' : (status === 'unprotected' ? 'rgba(239, 68, 68, 0.04)' : (status === 'external_block' ? 'rgba(59, 130, 246, 0.04)' : (status === 'inconclusive' ? 'rgba(245, 158, 11, 0.04)' : (status === 'skipped' ? 'rgba(245, 158, 11, 0.04)' : 'rgba(239, 68, 68, 0.04)')))),
          border: `1px solid ${status === 'success' ? 'rgba(16, 185, 129, 0.2)' : (status === 'unprotected' ? 'rgba(239, 68, 68, 0.2)' : (status === 'external_block' ? 'rgba(59, 130, 246, 0.2)' : (status === 'inconclusive' ? 'rgba(245, 158, 11, 0.2)' : (status === 'skipped' ? 'rgba(245, 158, 11, 0.2)' : 'rgba(239, 68, 68, 0.2)'))))}`,
          borderRadius: '10px',
          transition: 'all 0.3s ease-in-out'
        }
      }, [
        el('div', { style: { display: 'flex', alignItems: 'center', gap: '8px', marginBottom: '10px' } }, [
          el(Icon, {
            icon: status === 'success' ? 'yes' : (status === 'unprotected' ? 'warning' : (status === 'external_block' ? 'shield' : (status === 'inconclusive' ? 'info' : (status === 'skipped' ? 'warning' : 'no')))),
            size: 18,
            style: { color: status === 'success' ? '#10b981' : (status === 'unprotected' ? '#dc2626' : (status === 'external_block' ? '#2563eb' : (status === 'inconclusive' ? '#d97706' : (status === 'skipped' ? '#d97706' : '#ef4444')))) }
          }),
          el('span', {
            style: {
              fontSize: '12px',
              fontWeight: 800,
              color: status === 'success' ? '#065f46' : (status === 'unprotected' ? '#991b1b' : (status === 'external_block' ? '#1e3a8a' : (status === 'inconclusive' ? '#92400e' : (status === 'skipped' ? '#92400e' : '#991b1b')))),
              textTransform: 'uppercase',
              letterSpacing: '0.025em'
            }
          }, status === 'success' ? __('Plugin Enforcement Confirmed', 'vaptsecure') : (status === 'unprotected' ? __('System Vulnerable (Unprotected)', 'vaptsecure') : (status === 'external_block' ? __('External Block Detected', 'vaptsecure') : (status === 'inconclusive' ? __('Inconclusive', 'vaptsecure') : (status === 'skipped' ? __('Protection Disabled', 'vaptsecure') : __('Verification Failure', 'vaptsecure'))))))
        ]),

        el('div', { style: { fontSize: '13px', color: '#334155', lineHeight: '1.5', marginBottom: '12px', fontWeight: 500 } }, result.message),

        // 🛡️ Clean Summary Display: URL, Status, Toggle, Enforcement (v3.4.0+)
        showVerificationDetails && (typeof result.raw === 'string' && result.raw.includes('URL: ')) && (() => {
          // Parse the raw string to extract key information
          const urlMatch = result.raw.match(/URL:\s*([^\s|]+)/i);
          const statusMatch = result.raw.match(/Status:\s*([^\s|]+)/i);
          const enforcementMatch = result.raw.match(/Enforcement:\s*([^\s|]+)/i);

          const targetUrl = urlMatch ? urlMatch[1].trim() : '';
          const status = statusMatch ? statusMatch[1].trim() : '';
          const enforcement = enforcementMatch ? enforcementMatch[1].trim() : '';

          const displayUrl = (() => {
            try {
              return new URL(targetUrl).hostname;
            } catch (e) {
              return targetUrl.replace(/^https?:\/\//, '').replace(/\/$/, '');
            }
          })();

          return el('div', {
            style: {
              marginTop: '12px',
              padding: '0',
              background: 'transparent',
              border: 'none',
              borderRadius: '0',
              fontSize: '11px',
              color: '#334155',
              lineHeight: '1.4',
              maxWidth: '100%',
              overflow: 'hidden'
            }
          }, [
            el('div', { style: { display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '8px' } }, [
              el(Icon, { icon: 'info', size: 12, style: { color: '#64748b', flexShrink: 0 } }),
              el('strong', { style: { fontSize: '10px', color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em', whiteSpace: 'nowrap', fontWeight: '800' } }, __('Verification Details', 'vaptsecure'))
            ]),
            el('div', { style: { display: 'flex', flexDirection: 'column', gap: '4px', fontSize: '11px' } }, [
              // Row 1: URL span full width
              el('div', { style: { padding: '4px 0', background: 'transparent', borderRadius: '0', border: 'none', display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '6px' } }, [
                el('span', { style: { color: '#64748b', fontWeight: '600' } }, __('URL:', 'vaptsecure')),
                el('a', {
                  href: targetUrl,
                  target: '_blank',
                  rel: 'noopener noreferrer',
                  style: {
                    color: '#0284c7',
                    textDecoration: 'none',
                    fontWeight: '700',
                    wordBreak: 'break-all',
                    fontSize: '11px'
                  },
                  onClick: (e) => {
                    e.stopPropagation();
                    window.open(targetUrl, '_blank');
                  }
                }, displayUrl || targetUrl)
              ]),
              // Row 2: Status, Toggle, Enforcement
              el('div', { style: { padding: '4px 0', background: 'transparent', borderRadius: '0', border: 'none', display: 'flex', alignItems: 'center', flexWrap: 'wrap', gap: '8px', color: '#475569' } }, [
                // Status box
                el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px' } }, [
                  el('span', { style: { color: '#64748b', fontWeight: '600' } }, __('Status:', 'vaptsecure')),
                  el('span', {
                    style: {
                      color: status === '200' ? '#059669' : '#dc2626',
                      fontWeight: '700'
                    }
                  }, status)
                ]),
                // Separator
                enforcement ? el('span', { style: { color: '#cbd5e1' } }, '|') : null,
                // Enforcement conditionally generated
                enforcement ? el('div', { style: { display: 'flex', alignItems: 'center', gap: '4px' } }, [
                  el('span', { style: { color: '#64748b', fontWeight: '600' } }, __('Enforcement:', 'vaptsecure')),
                  el('span', {
                    style: {
                      color: enforcement.includes('php') ? '#7c3aed' : '#0284c7',
                      fontWeight: '700',
                      background: enforcement.includes('php') ? '#f5f3ff' : '#f0f9ff',
                      padding: '1px 4px',
                      borderRadius: '3px'
                    }
                  }, enforcement)
                ]) : null
              ])
            ])
          ]);
        })(),

      // Technical Trace section (restored for workbench)
      showTechnicalTrace && (result.raw || traceLog.length > 0) && el('div', {
        style: {
          maxWidth: '100%',
          overflow: 'hidden',
          marginTop: '10px',
          borderTop: '1px solid #e2e8f0',
          paddingTop: '10px'
        }
      }, [
        el('div', { style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '8px' } }, [
          el('div', { style: { display: 'flex', alignItems: 'center', gap: '6px' } }, [
            el(Icon, { icon: 'editor-code', size: 12, style: { color: '#64748b' } }),
            el('strong', { style: { fontSize: '10px', color: '#64748b', textTransform: 'uppercase', letterSpacing: '0.05em', fontWeight: '800' } }, __('Technical Trace', 'vaptsecure'))
          ]),
          control.test_logic === 'spam_requests' && el(Button, {
            isLink: true,
            isDestructive: true,
            style: { fontSize: '10px', height: 'auto', padding: 0 },
            onClick: async () => {
              try {
                await apiFetch({ path: 'vaptsecure/v1/reset-limit', method: 'POST' });
                setTraceLog(prev => [...prev, { time: new Date().toLocaleTimeString(), message: 'Rate limit counter reset signal sent.', type: 'info' }]);
                window.dispatchEvent(new CustomEvent('vapt-refresh-stats', { detail: { featureKey: featureKey.toLowerCase() } }));
              } catch (e) {}
            }
          }, __('Reset My Counter', 'vaptsecure'))
        ]),
        traceLog.length > 0 && el('div', {
            style: {
              marginBottom: '10px',
              padding: '8px',
              background: '#0f172a',
              borderRadius: '6px',
              fontFamily: 'monospace',
              fontSize: '10px',
              color: '#e2e8f0',
              maxHeight: '150px',
              overflowY: 'auto'
            }
          }, traceLog.map((log, i) => el('div', { key: i, style: { marginBottom: '2px', color: log.type === 'error' ? '#f87171' : (log.type === 'success' ? '#4ade80' : '#e2e8f0') } }, [
            el('span', { style: { color: '#94a3b8', marginRight: '8px' } }, `[${log.time}]`),
            log.message
          ]))),
          result.raw && el(FileInspector, {
            content: result.raw,
            label: __('Raw Diagnostic Data', 'vaptsecure'),
            testContext: control.test_logic
          })
        ]),

        // v3.5.2: Multiple Evidence Gallery Renderer (v3.13.16 Safety Fix)
        (result.screenshot_paths || (result.meta && result.meta.screenshot_paths)) &&
        el('div', { style: { marginTop: '15px' } }, el(EvidenceGallery, { screenshots: result.screenshot_paths || (result.meta ? result.meta.screenshot_paths : []) }))
      ])
    ]);
  };

  /**
   * Rate Limit Observability Monitor
   */
  const RateLimitMonitor = ({ featureKey }) => {
    const [stats, setStats] = useState(null);
    const [loading, setLoading] = useState(false);
    const [resetting, setResetting] = useState(false);
    const consecutiveFailsRef = useRef(0);

    const fetchStats = async () => {
      setLoading(true);
      try {
        const response = await fetch(`${window.vaptSecureSettings.root}vaptsecure/v1/features/${featureKey}/stats`, {
          headers: { 'X-WP-Nonce': window.vaptSecureSettings.nonce }
        });
        const data = await response.json();
        setStats(data);
        consecutiveFailsRef.current = 0;
      } catch (e) {
        vaptLog.error('Failed to fetch stats:', e);
        consecutiveFailsRef.current++;
      } finally {
        setLoading(false);
      }
    };

    const resetStats = async () => {
      if (!confirm(__('Are you sure you want to reset all active rate limit blocks for this feature?', 'vaptsecure'))) return;
      setResetting(true);
      try {
        await fetch(`${window.vaptSecureSettings.root}vaptsecure/v1/features/${featureKey}/reset`, {
          method: 'POST',
          headers: { 'X-WP-Nonce': window.vaptSecureSettings.nonce }
        });
        await fetchStats();
      } catch (e) {
        vaptLog.error('Failed to reset stats:', e);
      } finally {
        setResetting(false);
      }
    };

    useEffect(() => {
      fetchStats();

      // Listen for sync events (v3.6.24)
      const handleSync = (e) => {
        if (e.detail && (e.detail.featureKey === featureKey || e.detail.featureKey === featureKey.toLowerCase())) {
          fetchStats();
        }
      };
      window.addEventListener('vapt-refresh-stats', handleSync);

      return () => {
        window.removeEventListener('vapt-refresh-stats', handleSync);
      };
    }, [featureKey]);

    if (!stats) return null;

    return el('div', {
      className: 'vapt-rate-limit-monitor',
      style: {
        padding: '12px',
        background: '#f1f5f9',
        border: '1px solid #cbd5e1',
        borderRadius: '6px',
        marginBottom: '20px',
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center'
      }
    }, [
      el('div', { style: { display: 'flex', gap: '20px' } }, [
        el('div', null, [
          el('div', { style: { fontSize: '10px', textTransform: 'uppercase', color: '#64748b', fontWeight: '700' } }, __('Active Blocks (IPs)', 'vaptsecure')),
          el('div', { style: { fontSize: '18px', fontWeight: '800', color: stats.active_ips > 0 ? '#ef4444' : '#10b981' } }, stats.active_ips)
        ])
        // Total Attempts removed as requested (redundant with Verification results - v3.6.24)
      ]),
      el('div', null, [
        el(Button, {
          isSecondary: true,
          isSmall: true,
          isDestructive: true,
          onClick: resetStats,
          isBusy: resetting,
          disabled: resetting || stats.active_ips === 0,
          style: { height: '32px' }
        }, __('Reset Counter', 'vaptsecure'))
      ])
    ]);
  };

  const GeneratedInterface = ({ feature, onUpdate, isGuidePanel = false, hideMonitor = false, hideOpNotes = false, hideProtocol = false, hideImplementationControl = false, hideThreatPanel = false, hideBadges = false, showTechnicalTrace = false, showVerificationDetails = true, globalProtection = true, isCompact = false }) => {
    const isWorkbench = window.location.search.includes('page=vaptsecure-workbench');
    vaptLog.log('GeneratedInterface Render:', { key: feature?.key, controls: feature?.generated_schema?.controls, isGuidePanel });
    
    // [v4.1.1] Local State for instant UI feedback
    const [localData, setLocalData] = useState(() => {
      if (!feature.implementation_data) return {};
      if (typeof feature.implementation_data === 'object') return feature.implementation_data;
      try { return JSON.parse(feature.implementation_data); } catch (e) { return {}; }
    });

    // [v4.1.3] State for hover tooltip visibility
    const [showTooltip, setShowTooltip] = useState(false);
    const [tooltipPosition, setTooltipPosition] = useState({ top: 0, left: 0, width: 380, maxHeight: 360 });
    const tooltipAnchorRef = useRef(null);
    const tooltipCloseTimerRef = useRef(null);

    let schema = useMemo(() => {
      if (!feature.generated_schema) return {};
      if (typeof feature.generated_schema === 'object') return feature.generated_schema;
      try {
        return JSON.parse(feature.generated_schema);
      } catch (e) {
        vaptLog.warn('Failed to parse generated_schema:', e);
        return {};
      }
    }, [feature.generated_schema]);

    // 🛡️ Resilience: Auto-Convert Legacy "manual" type (v3.6.15)
    if (schema && schema.type === 'manual') {
      schema = {
        controls: [
          { type: 'header', label: __('Implementation Status', 'vaptsecure') },
          { type: 'toggle', label: __('Enable Feature', 'vaptsecure'), key: 'feat_enabled', default: true },
          { type: 'info', label: __('Manual Implementation Required', 'vaptsecure'), content: schema.instruction || __('Please refer to the manual verification protocol.', 'vaptsecure') }
        ],
        enforcement: { driver: 'manual', mappings: {} },
        _instructions: schema.instruction
      };
    }

    const currentData = useMemo(() => {
      if (!feature.implementation_data) return {};
      if (typeof feature.implementation_data === 'object') return feature.implementation_data;
      try {
        return JSON.parse(feature.implementation_data);
      } catch (e) {
        vaptLog.warn('Failed to parse implementation_data:', e);
        return {};
      }
    }, [feature.implementation_data]);

    // Sync localData when prop changes (from other sources)
    useEffect(() => {
      setLocalData(currentData);
    }, [currentData]);

    const verificationFeatureData = useMemo(() => {
      const merged = {
        ...localData,
        ...feature
      };

      merged.available_platforms = Array.isArray(feature.available_platforms)
        ? feature.available_platforms
        : (Array.isArray(localData.available_platforms) ? localData.available_platforms : []);
      merged.platform_implementations = feature.platform_implementations && typeof feature.platform_implementations === 'object'
        ? feature.platform_implementations
        : (localData.platform_implementations && typeof localData.platform_implementations === 'object' ? localData.platform_implementations : {});

      return merged;
    }, [feature, localData]);

    const normalizePlatformName = (value) => String(value || '')
      .toLowerCase()
      .replace(/\.php$/i, '')
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '');

    const platformMatches = (platformName, candidate) => {
      const platform = normalizePlatformName(platformName);
      const needle = normalizePlatformName(candidate);

      if (!platform || !needle) {
        return false;
      }

      return (
        platform === needle ||
        platform.includes(needle) ||
        needle.includes(platform) ||
        (needle === 'htaccess' && platform.includes('litespeed'))
      );
    };

    const normalizedControls = useMemo(() => {
      if (!schema || !Array.isArray(schema.controls)) {
        return [];
      }

      const featureBlob = [
        feature?.key,
        feature?.label,
        feature?.title,
        feature?.name,
        feature?.description,
        feature?.summary,
        feature?.remediation,
        JSON.stringify(verificationFeatureData?.platform_implementations || {}),
        JSON.stringify(verificationFeatureData?.available_platforms || [])
      ].filter(Boolean).join(' ').toLowerCase();
      const surfaceFamily = detectSurfaceFamily(verificationFeatureData, feature?.key || '', {});
      const isRateLimitSurface = surfaceFamily === 'rate_limit' || /rate limit|rate-limiting|brute force|bruteforce|php-rate-limit/.test(featureBlob);
      const isLoginErrorSurface = surfaceFamily === 'login_error' || /wp-login\.php|login_errors|invalid credentials/.test(featureBlob);
      const isRestUsersSurface = surfaceFamily === 'rest_users' || /wp-json\/wp\/v2\/users|rest api|wordpress rest api/.test(featureBlob);
      const isAuthorQuerySurface = surfaceFamily === 'author_query' || /\?author=1|author query|author archives|author enumeration/.test(featureBlob);
      const isPingbackSurface = /pingback|xmlrpc|xml-rpc/.test(featureBlob);

      const loginErrorControl = (control) => ({
        ...control,
        label: 'Login Error Consistency Check',
        key: 'verify_login_error_disclosure',
        test_logic: 'universal_probe',
        test_config: {
          method: 'POST',
          path: '/wp-login.php',
          params: {
            log: 'vaptsecure_nonexistent_user',
            pwd: 'invalid-password',
            'wp-submit': 'Log In',
            redirect_to: `${window.location.origin}/wp-admin/`,
            testcookie: '1'
          },
          expected_status: [200],
          expected_text: 'Invalid credentials. Please try again.',
          expected_enforcer: control.test_config?.expected_enforcer || control.test_config?.expected_enforcers || ''
        },
        help: 'Verifies wp-login.php returns a generic login error message.'
      });

      const rateLimitControl = (control) => ({
        ...control,
        label: 'Brute Force Resistance Check',
        key: 'verify_rate_resilience',
        test_logic: 'spam_requests',
        numTests: 5,
        test_config: {
          method: 'POST',
          path: '/wp-login.php',
          params: {
            log: 'vaptsecure_nonexistent_user',
            pwd: 'invalid-password',
            'wp-submit': 'Log In',
            redirect_to: `${window.location.origin}/wp-admin/`,
            testcookie: '1'
          },
          expected_enforcer: 'php-rate-limit'
        },
        help: 'Verifies that the login endpoint is rate limited or blocked by the active php-rate-limit policy.'
      });

      const xmlRpcControl = (control) => ({
        ...control,
        label: 'Test: XML-RPC Pingback Block',
        test_logic: 'disable_xmlrpc_pingback'
      });

      const seen = new Set();
      return schema.controls.reduce((acc, control) => {
        if (!control || typeof control !== 'object') {
          return acc;
        }

        const labelLower = String(control.label || '').toLowerCase();
        const controlPath = String(control.test_config?.path || '').toLowerCase();
        let nextControl = { ...control };

        // 🛡️ Global Label Normalization (v3.15.5) - Restore "A+" branding for existing features
        if (labelLower === 'implementation verification') {
          nextControl.label = 'A+ Adaptive Verification';
          if (nextControl.help?.includes('released implementation')) {
            nextControl.help = nextControl.help.replace('released implementation', 'A+ Adaptive implementation');
          }
        } else if (labelLower === 'platform header verification') {
          nextControl.label = 'A+ Header Verification';
          if (nextControl.help?.includes('platform-specific enforcement headers')) {
            nextControl.help = nextControl.help.replace('platform-specific enforcement headers', 'A+ Adaptive headers (x-vapt-enforced)');
          }
        }

        const label = String(nextControl.label || '').toLowerCase();

        if (isRateLimitSurface && control.type === 'test_action') {
          const shouldNormalizeRate = (
            label.includes('a+ header verification') ||
            label.includes('implementation verification') ||
            label.includes('active protection probe') ||
            label.includes('login error consistency check') ||
            control.test_logic === 'check_headers' ||
            control.test_logic === 'universal_probe' ||
            control.test_logic === 'verify_implementation' ||
            controlPath.includes('/wp-login.php') ||
            controlPath.includes('/?author=1')
          ) && !label.includes('rate');

          if (shouldNormalizeRate) {
            nextControl = rateLimitControl(control);
          }
        } else if (isLoginErrorSurface && control.type === 'test_action') {
          const shouldNormalizeLogin = (
            label.includes('a+ header verification') ||
            label.includes('rest api protection check') ||
            label.includes('author enumeration check') ||
            label.includes('rest user enumeration') ||
            control.test_logic === 'check_headers' ||
            control.test_logic === 'block_author_enumeration' ||
            control.test_logic === 'verify_rest_lockdown' ||
            controlPath.includes('/wp-json/wp/v2/users') ||
            controlPath.includes('/?author=1')
          ) && !label.includes('header verification') && !label.includes('adaptive verification');

          if (shouldNormalizeLogin) {
            nextControl = loginErrorControl(control);
          }
        } else if (isRestUsersSurface && control.type === 'test_action') {
          const shouldNormalizeRest = (
            label.includes('a+ header verification') ||
            label.includes('author enumeration check') ||
            label.includes('rest api protection check') ||
            label.includes('rest user enumeration') ||
            label.includes('username enumeration') ||
            control.test_logic === 'check_headers' ||
            control.test_logic === 'block_author_enumeration' ||
            control.test_logic === 'verify_rest_lockdown' ||
            controlPath.includes('/wp-login.php') ||
            controlPath.includes('/?author=1')
          ) && !label.includes('header verification') && !label.includes('adaptive verification');

          if (shouldNormalizeRest) {
            nextControl = {
              ...control,
              label: 'REST API Protection Check',
              key: 'verify_rest_lockdown',
              test_logic: 'universal_probe',
              test_config: {
                method: 'GET',
                path: '/wp-json/wp/v2/users',
                expected_status: [401, 403, 404, 405],
                expected_enforcer: control.test_config?.expected_enforcer || control.test_config?.expected_enforcers || ''
              },
              help: 'Verifies the WordPress Users REST endpoint is protected.'
            };
          }
        } else if (isAuthorQuerySurface && control.type === 'test_action') {
          const shouldNormalizeAuthor = (
            label.includes('a+ header verification') ||
            label.includes('rest api protection check') ||
            label.includes('author enumeration check') ||
            label.includes('rest user enumeration') ||
            control.test_logic === 'check_headers' ||
            control.test_logic === 'block_author_enumeration' ||
            control.test_logic === 'verify_rest_lockdown' ||
            controlPath.includes('/wp-login.php') ||
            controlPath.includes('/wp-json/wp/v2/users')
          ) && !label.includes('header verification') && !label.includes('adaptive verification');

          if (shouldNormalizeAuthor) {
            nextControl = {
              ...control,
              label: 'Author Enumeration Check',
              key: 'verify_author_protection',
              test_logic: 'universal_probe',
              test_config: {
                method: 'GET',
                path: '/?author=1',
                expected_status: [403, 404],
                expected_enforcer: control.test_config?.expected_enforcer || control.test_config?.expected_enforcers || ''
              },
              help: 'Verifies that author enumeration via query string is blocked.'
            };
          }
        } else if (
          isPingbackSurface &&
          control.type === 'test_action' &&
          (control.test_logic === 'check_headers' || control.test_logic === 'block_xmlrpc' || label.includes('xml-rpc')) &&
          (controlPath.includes('xmlrpc.php') || label.includes('header verification') || label.includes('xml-rpc'))
        ) {
          nextControl = xmlRpcControl(control);
        }

        const dedupeKey = `${nextControl.type || ''}:${nextControl.key || ''}:${nextControl.label || ''}:${nextControl.test_logic || ''}`.toLowerCase();
        if (nextControl.type === 'test_action' && seen.has(dedupeKey)) {
          return acc;
        }
        if (nextControl.type === 'test_action') {
          seen.add(dedupeKey);
        }
        acc.push(nextControl);
        return acc;
      }, []);
    }, [schema, feature, verificationFeatureData]);
    const [localAlert, setLocalAlert] = useState(null);
    const [statusMap, setStatusMap] = useState({});
    const [liveAudit, setLiveAudit] = useState(null);
    const statusTimersRef = useRef({});
    const pollTimersRef = useRef({});

    useEffect(() => () => {
      Object.values(statusTimersRef.current || {}).forEach(timer => {
        if (timer) {
          clearTimeout(timer);
        }
      });
      Object.values(pollTimersRef.current || {}).forEach(timer => {
        if (timer) {
          clearTimeout(timer);
        }
      });
      statusTimersRef.current = {};
      pollTimersRef.current = {};
    }, []);

    // [v4.0.x] Fetch live file audit status on mount / feature change
    useEffect(() => {
      if (!feature?.key || !apiFetch) return;
      const fetchStatus = async () => {
        try {
          const resp = await apiFetch({
            path: `vaptsecure/v1/features/${encodeURIComponent(feature.key)}/status`,
            method: 'GET'
          });
          if (resp && Array.isArray(resp.audit_summary)) {
            setLiveAudit(resp);
          }
        } catch (e) {
          vaptLog.warn('Live audit fetch failed:', e);
        }
      };
      fetchStatus();
    }, [feature?.key]);

    if (!schema || !schema.controls || !Array.isArray(schema.controls)) {
      return el('div', { style: { padding: '20px', textAlign: 'center', color: '#999', fontStyle: 'italic' } },
        __('No functional controls defined for this implementation.', 'vaptsecure')
      );
    }

    const isRemovalContext = (key, currentVal) => {
      const status = statusMap[key];
      if (!status) return false;
      return toBool(currentVal) && (status.message === __("Removing...", "vaptsecure") || status.message === __("Removed Successfully", "vaptsecure"));
    };

    const isActivelyEnforced = globalProtection ?
      ((feature.normalized_status || feature.status || 'draft').toLowerCase() === 'release' ?
        (feature.is_enforced != 0) : (feature.is_enforced == 1))
      : false;

    // Derived flags for rendering logic
    const isRateLimit = ['RISK-033', 'RISK-039'].includes(feature.key || feature.id) || !!feature.is_rate_limit;

    const handleChange = (key, val) => {
      setLocalData(prev => {
        const next = { ...prev, [key]: val };
        const boolVal = toBool(val);
        
        // Bi-directional sync for toggles (v4.1.5 Expanded)
        const isToggleKey = ['feat_enabled', 'enabled', 'prot_enabled'].includes(key);
        const riskId = (feature.key || feature.id || '').toString().toLowerCase();
        const riskSuffix = riskId.replace('risk-', '').replace(/-/g, '_');
        const autoKey = `vapt_risk_${riskSuffix}_enabled`;

        if (isToggleKey || key === autoKey) {
          next.feat_enabled = boolVal;
          next.enabled = boolVal;
          next.prot_enabled = boolVal;
          next[autoKey] = boolVal;
        }
        
        if (typeof onUpdate === 'function') {
          onUpdate(next);
        }
        return next;
      });
    };

    const renderControl = (control, index) => {
      const { type, label, key, help, options, rows, action } = control;
      const value = currentData[key] !== undefined ? currentData[key] : (control.default || '');
      const uniqueKey = key || `ctrl-${index}`;
      const isEnforced = isActivelyEnforced;
      // const conditionalTypes = ['info', 'html', 'warning', 'alert'];

      // if (conditionalTypes.includes(type) && isEnforced) return null; // Removed per user request v3.3.9

      switch (type) {
        case 'reset_action':
          return el('div', { key: uniqueKey, style: { marginBottom: '15px' } }, [
            el(Button, {
              isSecondary: true,
              isDestructive: true,
              onClick: async () => {
                try {
                  const res = await apiFetch({ path: 'vaptsecure/v1/reset-limit', method: 'POST' });
                  setLocalAlert({ message: __('Rate limits reset successfully for your IP.', 'vaptsecure'), type: 'success' });
                  // Trigger a trace log update if possible
                  window.dispatchEvent(new CustomEvent('vapt-refresh-stats', { detail: { featureKey: feature.key || feature.id } }));
                } catch (e) {
                  setLocalAlert({ message: __('Failed to reset rate limits.', 'vaptsecure'), type: 'error' });
                }
              }
            }, safeRender(label || __('Reset My Rate Limit', 'vaptsecure'))),
            help && el('p', { style: { margin: '5px 0 0', fontSize: '12px', color: '#666' } }, safeRender(help))
          ]);

        case 'number':
          return el('div', { key: uniqueKey, style: { marginBottom: '15px' } }, [
            el(TextControl, {
              label: el('strong', { style: { fontSize: '12px', color: '#334155' } }, safeRender(label)),
              type: 'number',
              value: value,
              onChange: (val) => handleChange(key, parseInt(val, 10) || 0),
              min: control.min || 1,
              max: control.max || 1000,
              help: safeRender(help)
            })
          ]);

        case 'test_action':
          return el(TestRunnerControl, { key: uniqueKey, control, featureData: verificationFeatureData, featureKey: feature.key || feature.id, globalProtection: globalProtection, showTechnicalTrace: showTechnicalTrace, showVerificationDetails: showVerificationDetails });

        case 'button':
          return el('div', { key: uniqueKey, style: { marginBottom: '15px' } }, [
            el(Button, {
              isSecondary: true,
              onClick: () => {
                if (action === 'reset_validation_logs') setLocalAlert({ message: __('Reset signal sent.', 'vaptsecure'), type: 'success' });
              }
            }, safeRender(label)),
            help && el('p', { style: { margin: '5px 0 0', fontSize: '12px', color: '#666' } }, safeRender(help))
          ]);

        case 'toggle':
          const mapping = (schema.enforcement?.mappings || {})[key] || (schema.client_deployment?.enforcement?.mappings || {})[key];
          const isDevelop = (feature.status || '').toLowerCase() === 'develop' || (feature.normalized_status || '').toLowerCase() === 'develop';
          const isSuperAdmin = window.vaptSecureSettings?.isSuper || false;

          // Enhanced Driver Detection for Tooltip Accuracy
          const activeDriver = schema.enforcement?.driver || schema.client_deployment?.enforcement?.driver || 'hook';
          const activeTarget = schema.enforcement?.target || schema.client_deployment?.enforcement?.target || 'root';

          const isEnforced = toBool(value);
          const vSettings = window.vaptSecureSettings || {};

       const getTooltipContent = () => {
         const impls = verificationFeatureData.platform_implementations || {};
         let addedCode = '';
         let targetFile = '';

         // [v4.0.x-SSoT] Removed: fail2ban ? not applicable to WordPress hosting; caddy/iis filtered upstream
         const removedPlatforms = ['fail2ban'];
         const activeDriver = schema.enforcement?.driver || schema.client_deployment?.enforcement?.driver || 'hook';
         const driverToPlatform = {
           'htaccess': 'htaccess',
           'apache': 'htaccess',
           'litespeed': 'litespeed',
           'wp_config': 'wp-config',
           'php_functions': 'php_functions',
           'hook': 'php_functions',
           'universal': 'php_functions'
         };
         const catalogEntries = Array.isArray(verificationFeatureData.available_platforms) && verificationFeatureData.available_platforms.length > 0
           ? verificationFeatureData.available_platforms
           : Object.keys(impls);
         const preferredPlatform = driverToPlatform[activeDriver] || activeDriver;
         const canonicalPlatform = catalogEntries[0] || preferredPlatform;

         const candidates = Object.entries(impls).filter(([plat]) => {
           const p = plat.toLowerCase().replace(/\s+/g, '_');
           return !removedPlatforms.some(rp => p.includes(rp));
         });

         if (catalogEntries.length > 0) {
           const canonicalMatch = candidates.find(([plat]) => platformMatches(plat, canonicalPlatform));
           if (canonicalMatch) {
             targetFile = canonicalMatch[1].target_file || canonicalMatch[0];
           }
         }

         if (!targetFile && candidates.length > 0) {
           for (const [plat, details] of candidates) {
             if (platformMatches(plat, preferredPlatform)) {
               targetFile = details.target_file || plat;
               break;
             }
           }
           if (!targetFile) {
             targetFile = candidates[0][1].target_file || candidates[0][0];
           }
         }

         if (!targetFile) {
           return null;
         }

         if (candidates.length > 0) {
           const canonicalMatch = candidates.find(([plat]) => platformMatches(plat, canonicalPlatform));
           if (canonicalMatch) {
             addedCode = canonicalMatch[1].wrapped_code || canonicalMatch[1].code;
           }
         }
         if (!addedCode && canonicalPlatform && candidates.length > 0) {
           const matched = candidates.find(([plat]) => platformMatches(plat, canonicalPlatform));
           const previewTarget = matched?.[1]?.code_ref || matched?.[1]?.target_file || matched?.[0] || targetFile;
            addedCode = sprintf(__('/* Rules are deployed to %s.\n   Code preview is not available for this platform in the current schema. */', 'vaptsecure'), previewTarget);
         }
         if (!addedCode && candidates.length > 0) {
           for (const [plat, details] of candidates) {
             if (platformMatches(plat, preferredPlatform)) {
               addedCode = details.wrapped_code || details.code;
               break;
             }
           }
         }
         if (!addedCode && candidates.length > 0) {
           addedCode = candidates[0][1].wrapped_code || candidates[0][1].code;
         }

         if (!addedCode) return null;

         const isCurrentlyEnforced = toBool(value);
         // [v4.1.4] Get actual target file from live audit, fallback to enforcement driver
         const liveTargetLabel = liveAudit?.audit_summary?.[0]?.label || '';
         const targetFilePath = liveTargetLabel || (verificationFeatureData.enforcement?.driver === 'wp_config'
           ? 'wp-config.php'
           : (verificationFeatureData.enforcement?.driver === 'htaccess' || verificationFeatureData.enforcement?.driver === 'apache'
             ? '.htaccess'
             : 'vapt-functions.php'));

         const shortPath = targetFilePath.startsWith('/') || targetFilePath.includes('\\')
            ? getShortPath(targetFilePath)
            : (targetFilePath.startsWith('./') ? targetFilePath : `./${targetFilePath}`);

         // [v4.1.4] Status based on ACTUAL live state, not just toggle
         const liveState = liveAudit?.audit_summary?.[0]?.live_state;
         const isSelfHealed = liveAudit?.was_self_healed || liveAudit?.audit_summary?.[0]?.self_healed;
         const isRemoving = statusMap[key]?.message === __('Removing...', 'vaptsecure');
         const isMissing = liveState === 'missing';
         const isPresent = liveState === 'present';
         const isCleaned = liveState === 'cleaned' || liveState === 'missing';

         // [v4.1.4] Fixed: status follows actual live state, not just toggle
         let displayStatus = 'inactive';
         let displayLabel = __('Status: Not Active', 'vaptsecure');
         let statusColor = '#991b1b';
         let statusBg = '#fef2f2';
         let statusIcon = 'no';
         let borderColor = '#94a3b8';

         if (isRemoving) {
           displayStatus = 'removing';
           displayLabel = __('Status: Removing Rules...', 'vaptsecure');
           statusColor = '#92400e';
           statusBg = '#fef3c7';
           statusIcon = 'update';
           borderColor = '#f59e0b';
         } else if (isSelfHealed) {
           displayStatus = 'recovered';
           displayLabel = __('Status: Auto-Recovered', 'vaptsecure');
           statusColor = '#065f46';
           statusBg = '#d1fae5';
           statusIcon = 'update';
           borderColor = '#10b981';
         } else if (isPresent && isCurrentlyEnforced) {
           // Toggle ON + code present = Active
           displayStatus = 'active';
           displayLabel = __('Status: Active & Injected', 'vaptsecure');
           statusColor = '#166534';
           statusBg = '#f0fdf4';
           statusIcon = 'yes';
           borderColor = '#22c55e';
         } else if (isCurrentlyEnforced && isMissing) {
           // Toggle ON but code missing = Error state
           displayStatus = 'missing';
           displayLabel = __('Status: Missing (Not Injected)', 'vaptsecure');
           statusColor = '#dc2626';
           statusBg = '#fef2f2';
           statusIcon = 'warning';
           borderColor = '#ef4444';
         } else if (!isCurrentlyEnforced && isCleaned) {
           // Toggle OFF + code cleaned = Cleaned
           displayStatus = 'cleaned';
           displayLabel = __('Status: Cleaned', 'vaptsecure');
           statusColor = '#166534';
           statusBg = '#f0fafc';
           statusIcon = 'yes';
           borderColor = '#22c55e';
         }

         const liveStateLabel = liveState || (isCurrentlyEnforced && !isMissing ? 'present' : 'missing');
         const liveStateDisplay = liveStateLabel.charAt(0).toUpperCase() + liveStateLabel.slice(1);

          return el('div', {
            style: {
              padding: '8px',
              width: `${tooltipPosition.width}px`,
              maxWidth: 'calc(100vw - 24px)',
              maxHeight: `${tooltipPosition.maxHeight}px`,
              overflowY: 'auto',
              background: '#1e293b',
              borderRadius: '8px',
              border: '1px solid #334155',
              boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05)',
              display: 'flex',
              flexDirection: 'column',
              gap: '10px',
              fontFamily: 'monospace',
              fontSize: '11px',
              color: '#f8fafc',
              whiteSpace: 'normal',
              wordBreak: 'break-word'
            }
          }, [
            el('div', {
              style: {
                fontSize: '10px',
                fontWeight: '800',
                color: '#94a3b8',
                textTransform: 'uppercase',
                letterSpacing: '0.05em',
                marginBottom: '0',
                borderBottom: '1px solid #334155',
                paddingBottom: '8px'
              }
            }, __('Technical Trace & Enforcement', 'vaptsecure')),
            el('div', {
              style: {
                background: statusBg,
                borderRadius: '6px',
                padding: '8px 12px',
                display: 'flex',
                alignItems: 'center',
                gap: '8px',
                marginBottom: '0'
              }
            }, [
              el(Icon, {
                icon: statusIcon,
                size: 14,
                style: { color: statusColor }
              }),
              el('span', {
                style: {
                  fontSize: '11px',
                  fontWeight: '800',
                  color: statusColor,
                  textTransform: 'uppercase',
                  letterSpacing: '0.025em'
                }
              }, displayLabel)
            ]),
            el('div', {
              style: {
                fontSize: '10px',
                fontWeight: '600',
                color: '#94a3b8',
                marginBottom: '0',
                display: 'flex',
                justifyContent: 'space-between'
              }
            }, [
              el('span', null, __('Live State:', 'vaptsecure')),
              el('span', { style: { color: liveState === 'present' ? '#22c55e' : (liveState === 'recovered' ? '#10b981' : (liveState === 'missing' || liveState === 'cleaned' ? '#94a3b8' : '#f59e0b')) } }, liveStateDisplay)
            ]),
            el('div', {
              title: targetFile,
              style: {
                fontSize: '12px',
                fontWeight: '700',
                color: '#38bdf8',
                marginBottom: '0',
                fontFamily: 'monospace',
                cursor: 'help'
              }
            }, shortPath),
            el('div', {
              style: {
                position: 'relative',
                background: '#0f172a',
                borderRadius: '0 4px 4px 0',
                borderLeft: `3px solid ${borderColor}`,
                overflow: 'hidden',
                padding: '8px'
              }
            }, [
              el('pre', {
                style: {
                  fontSize: '10px',
                  padding: '0',
                  margin: 0,
                  color: '#f8fafc',
                  whiteSpace: 'pre-wrap',
                  wordBreak: 'break-all',
                  fontFamily: 'monospace',
                  lineHeight: '1.4'
                }
              }, addedCode)
            ])
          ]);
       };

        const getPlainTooltipText = () => {
          const impls = verificationFeatureData.platform_implementations || {};
          const isCurrentlyEnforced = toBool(value);
          const liveState = liveAudit?.audit_summary?.[0]?.live_state || (isCurrentlyEnforced ? 'present' : 'cleaned');

          // [v4.1.4] Use actual target file path from live audit - not platform name
          const liveTargetLabel = liveAudit?.audit_summary?.[0]?.label || '';
          const targetFile = liveTargetLabel || (
            /php functions|hook/i.test(String(verificationFeatureData.enforcement?.driver))
              ? 'vapt-functions.php'
              : /wp-config/i.test(String(verificationFeatureData.enforcement?.driver))
                ? 'wp-config.php'
                : /htaccess|apache/i.test(String(verificationFeatureData.enforcement?.driver))
                  ? '.htaccess'
                  : 'vapt-functions.php'
          );

          // Simple text status - Injected / Removed
          const statusText = isCurrentlyEnforced ? 'Injected' : 'Removed';
          // Simple text Live State
          const liveStateText = liveState.charAt(0).toUpperCase() + liveState.slice(1);

          // [v4.1.4] Get code from resolved schema (platform_implementations) - not stale DB data
          // Note: impls already declared on line 3094
          let snippetSource = '';
          // Get code from first platform implementation that has it
          for (const [platform, impl] of Object.entries(impls)) {
            if (impl?.wrapped_code) {
              snippetSource = impl.wrapped_code;
              break;
            }
            if (impl?.code) {
              snippetSource = impl.code;
              break;
            }
          }
          // Fallback to localData
          if (!snippetSource) {
            snippetSource = localData?.wrapped_code || localData?.code || '';
          }
          const snippetPreview = String(snippetSource).trim();

          // [v4.1.4] Use enforcement driver as implementation label
          const implLabel = verificationFeatureData.enforcement?.driver || 'PHP Functions';
          return `Implementation: ${implLabel}\nTarget File: ${targetFile}\nStatus: ${statusText}\nLive State: ${liveStateText}\n\nCode Preview:\n${snippetPreview || '(no snippet available)'}`;
        };

       const tooltipContent = getTooltipContent();
       const tooltipText = getPlainTooltipText(); // Used for clipboard copy
        const updateTooltipPosition = () => {
          const anchor = tooltipAnchorRef.current;
          if (!anchor || typeof window === 'undefined') {
            return;
          }

          const rect = anchor.getBoundingClientRect();
          const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
          const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
          const margin = 12;
          const tooltipLines = String(tooltipText || '').split('\n');
          const textLines = tooltipLines.length;
          const longestLineLength = tooltipLines.reduce((max, line) => Math.max(max, String(line).length), 0);
          const contentDrivenWidth = Math.round(longestLineLength * 5.8 + 58);
          const viewportDrivenWidth = Math.round(viewportWidth * 0.22);
          const desiredWidth = Math.max(
            260,
            Math.min(
              340,
              Math.max(280, contentDrivenWidth, viewportDrivenWidth)
            )
          );
          const desiredHeight = Math.min(
            Math.max(240, textLines * 18 + 112),
            Math.max(240, viewportHeight - (margin * 2))
          );

          const availableRight = Math.max(0, viewportWidth - rect.right - margin);
          const availableLeft = Math.max(0, rect.left - margin);
          const availableBelow = Math.max(0, viewportHeight - rect.bottom - margin);
          const availableAbove = Math.max(0, rect.top - margin);

          let width = desiredWidth;
          let left = rect.right + margin;

          if (availableRight >= desiredWidth) {
            width = desiredWidth;
            left = rect.right + margin;
          } else if (availableLeft >= desiredWidth) {
            width = desiredWidth;
            left = rect.left - desiredWidth - margin;
          } else if (availableRight >= availableLeft && availableRight > 0) {
            width = Math.max(280, Math.min(desiredWidth, availableRight));
            left = Math.min(Math.max(margin, rect.right + margin), viewportWidth - width - margin);
          } else if (availableLeft > 0) {
            width = Math.max(280, Math.min(desiredWidth, availableLeft));
            left = Math.max(margin, rect.left - width - margin);
          } else {
            width = Math.max(280, Math.min(desiredWidth, viewportWidth - (margin * 2)));
            left = margin;
          }

          const maxHeight = Math.max(240, Math.min(desiredHeight, viewportHeight - (margin * 2)));
          let top = rect.bottom + margin;

          if (availableBelow >= maxHeight) {
            top = rect.bottom + margin;
          } else if (availableAbove >= maxHeight) {
            top = rect.top - maxHeight - margin;
          } else {
            top = Math.max(margin, Math.min(rect.bottom + margin, viewportHeight - maxHeight - margin));
          }

          left = Math.max(margin, Math.min(left, viewportWidth - width - margin));
          top = Math.max(margin, Math.min(top, viewportHeight - maxHeight - margin));

          setTooltipPosition({ top, left, width, maxHeight });
        };

        const openTooltip = () => {
          if (!tooltipText) return;
          if (tooltipCloseTimerRef.current) {
            clearTimeout(tooltipCloseTimerRef.current);
            tooltipCloseTimerRef.current = null;
          }
          updateTooltipPosition();
          setShowTooltip(true);
        };

        const scheduleCloseTooltip = () => {
          if (tooltipCloseTimerRef.current) {
            clearTimeout(tooltipCloseTimerRef.current);
          }
          tooltipCloseTimerRef.current = setTimeout(() => {
            setShowTooltip(false);
            tooltipCloseTimerRef.current = null;
          }, 140);
        };

        const cancelCloseTooltip = () => {
          if (tooltipCloseTimerRef.current) {
            clearTimeout(tooltipCloseTimerRef.current);
            tooltipCloseTimerRef.current = null;
          }
        };

        useEffect(() => () => {
          cancelCloseTooltip();
        }, []);

        useEffect(() => {
          if (!showTooltip) {
            return undefined;
          }

          const reposition = () => updateTooltipPosition();
          reposition();
          window.addEventListener('resize', reposition);
          window.addEventListener('scroll', reposition, true);

          return () => {
            window.removeEventListener('resize', reposition);
            window.removeEventListener('scroll', reposition, true);
          };
        }, [showTooltip, tooltipText]);

          const statusHeader = isEnforced ?
            el('div', { style: { color: '#475569', background: '#f8fafc', padding: '6px 10px', borderRadius: '4px', fontWeight: '800', marginBottom: '10px', fontSize: '11px', display: 'flex', flexDirection: 'column', alignItems: 'flex-start', border: '1px solid #cbd5e1' } }, [
              el('span', null, __('SNIPPET PREVIEW', 'vaptsecure')),
              el('span', { style: { fontSize: '10px', fontWeight: '600', color: '#64748b' } }, __('This is the generated code shape. Verification uses a fresh file audit.', 'vaptsecure'))
            ]) :
            el('div', { style: { color: '#475569', background: '#f8fafc', padding: '6px 10px', borderRadius: '4px', fontWeight: '800', marginBottom: '10px', fontSize: '11px', display: 'flex', flexDirection: 'column', alignItems: 'flex-start', border: '1px solid #cbd5e1' } }, [
              el('span', null, __('SNIPPET PREVIEW', 'vaptsecure')),
              el('span', { style: { fontSize: '10px', fontWeight: '600', color: '#64748b' } }, __('This is not file status. Verification uses a fresh file audit.', 'vaptsecure'))
            ]);
return el('div', { id: control.id, key: uniqueKey, style: { marginBottom: isCompact ? '0' : '0' } }, [
  el(ToggleControl, {
    disabled: globalProtection === false,
    label: isCompact ? '' : el('div', { style: { display: 'flex', alignItems: 'center', gap: '6px' } }, [
      el('strong', { style: { fontSize: '12px', color: '#334155' } }, safeRender(label)),
      // [v4.1.3] Clickable copyable tooltip - stopPropagation to prevent toggle trigger
      tooltipText && el('div', {
        ref: tooltipAnchorRef,
        style: {
          position: 'relative',
          marginLeft: '6px',
          display: 'inline-flex',
          alignItems: 'center',
          overflow: 'visible'
        },
        onMouseEnter: openTooltip,
        onMouseLeave: scheduleCloseTooltip,
        onFocus: openTooltip,
        onBlur: scheduleCloseTooltip
      }, [
        el('span', {
          onClick: (e) => {
            e.stopPropagation();
            if (showTooltip) {
              setShowTooltip(false);
              return;
            }
            openTooltip();
          },
          style: {
            display: 'inline-flex',
            alignItems: 'center',
            lineHeight: 0,
            cursor: 'help',
            padding: '4px'
          }
        }, el(Icon, { icon: 'info-outline', size: 14, style: { color: '#94a3b8' } })),
        showTooltip && createPortal(
           el('div', {
             onMouseEnter: cancelCloseTooltip,
             onMouseLeave: scheduleCloseTooltip,
             onClick: (e) => e.stopPropagation(),
             role: 'tooltip',
             style: {
               position: 'fixed',
               top: `${tooltipPosition.top}px`,
               left: `${tooltipPosition.left}px`,
               zIndex: 100000,
               pointerEvents: 'auto',
               boxShadow: '0 10px 25px rgba(0,0,0,0.3)'
             }
           }, tooltipContent),
           document.body
         )
      ])
    ]),
    help: safeRender(control.description || help),
    checked: toBool(value),
    onChange: (val) => {
      const isRemoval = toBool(value) && !val;
      const progressMsg = isRemoval ? __('Removing...', 'vaptsecure') : __('Applying...', 'vaptsecure');
      const successMsg = isRemoval ? __('Protection Disabled', 'vaptsecure') : __('Protection Enabled', 'vaptsecure');
      if (statusTimersRef.current[key]) {
        clearTimeout(statusTimersRef.current[key]);
        delete statusTimersRef.current[key];
      }

      setStatusMap(prev => ({
        ...prev,
        [key]: {
          ...(prev[key] || {}),
          message: progressMsg,
          type: 'info'
        }
      }));

      Promise.resolve(handleChange(key, val))
        .then((response) => {
          const auditSummary = Array.isArray(response?.audit_summary)
            ? response.audit_summary
            : Array.isArray(response?.cleanup_summary)
              ? response.cleanup_summary
              : [];

          setStatusMap(prev => ({
            ...prev,
            [key]: {
              message: successMsg,
              type: 'success',
              auditSummary
            }
          }));

          const refreshLiveAudit = async () => {
            if (!apiFetch || !feature?.key) {
              return null;
            }

            try {
              const resp = await apiFetch({
                path: `vaptsecure/v1/features/${encodeURIComponent(feature.key)}/status`,
                method: 'GET'
              });

              if (resp && Array.isArray(resp.audit_summary)) {
                setLiveAudit(resp);
              }

              return resp;
            } catch (e) {
              vaptLog.warn('Live audit refresh failed:', e);
              return null;
            }
          };

          if (isRemoval && apiFetch && feature?.key) {
            const doPoll = async (attempt = 0) => {
              if (attempt > 10) return;
              try {
                const resp = await apiFetch({
                  path: `vaptsecure/v1/features/${encodeURIComponent(feature.key)}/status`,
                  method: 'GET'
                });
                if (resp && Array.isArray(resp.audit_summary)) {
                  setLiveAudit(resp);
                  const first = resp.audit_summary[0];
                  if (first && (first.live_state === 'cleaned' || first.live_state === 'missing' || first.status === 'removed')) {
                    setStatusMap(prev => ({
                      ...prev,
                      [key]: {
                        message: __('Removed Successfully', 'vaptsecure'),
                        type: 'success'
                      }
                    }));
                    statusTimersRef.current[key] = setTimeout(() => {
                      setStatusMap(prev => {
                        const next = { ...prev };
                        delete next[key];
                        return next;
                      });
                      delete statusTimersRef.current[key];
                    }, 3000);
                    return;
                  }
                }
              } catch (e) {
                vaptLog.warn('Removal poll failed:', e);
              }
              pollTimersRef.current[key] = setTimeout(() => doPoll(attempt + 1), 2000);
            };
            doPoll(0);
          } else {
            refreshLiveAudit();
            statusTimersRef.current[key] = setTimeout(() => {
              setStatusMap(prev => {
                const next = { ...prev };
                delete next[key];
                return next;
              });
              delete statusTimersRef.current[key];
            }, 3000);
          }
        })
        .catch((error) => {
          const errMsg = error?.message || error?.data?.message || __('Save Failed', 'vaptsecure');
          setStatusMap(prev => ({
            ...prev,
            [key]: {
              message: errMsg,
              type: 'error',
              auditSummary: []
            }
          }));
          statusTimersRef.current[key] = setTimeout(() => {
            setStatusMap(prev => {
              const next = { ...prev };
              delete next[key];
              return next;
            });
            delete statusTimersRef.current[key];
          }, 3000);
        });
    }
  }),
  statusMap[key] && el('div', {
    style: {
      marginTop: '-4px',
      marginBottom: '4px',
      marginLeft: '35px',
      display: 'flex'
    }
  }, el('span', {
    style: {
      fontSize: '10px',
      fontWeight: '600',
      padding: '1px 6px',
      borderRadius: '4px',
      background: statusMap[key].type === 'success' ? '#ecfdf5' : (isRemovalContext(key, value) ? '#fef2f2' : '#f0f9ff'),
      color: statusMap[key].type === 'success' ? '#059669' : (isRemovalContext(key, value) ? '#b91c1c' : '#0369a1'),
      border: `1px solid ${statusMap[key].type === 'success' ? '#10b981' : (isRemovalContext(key, value) ? '#f87171' : '#0ea5e9')}`,
      display: 'flex',
      alignItems: 'center',
      gap: '4px'
    }
  }, [
    el(Icon, { icon: statusMap[key].type === 'success' ? 'yes' : 'update', size: 10 }),
    statusMap[key].message
  ])),
  null
]);

        case 'textarea':
        case 'code':
          return el('div', { id: control.id || `vapt-text-wrapper-${uniqueKey}`, key: uniqueKey, style: { marginBottom: '10px' } }, [
            el('div', { style: { display: 'flex', alignItems: 'center', gap: '6px', marginBottom: '4px' } }, [
              el('label', { style: { fontSize: '12px', fontWeight: '600', color: '#334155' } }, safeRender(label)),
              help && el(Tooltip, { text: safeRender(help) }, el(Icon, { icon: 'info-outline', size: 14, style: { color: '#94a3b8', cursor: 'help' } }))
            ]),
            el(TextareaControl, {
              value: value,
              rows: rows || (type === 'code' ? 4 : 3),
              onChange: (val) => handleChange(key, val),
              placeholder: value ? '' : __('No data available.', 'vaptsecure'),
              __nextHasNoMarginBottom: true,
              style: type === 'code' ? { fontFamily: 'monospace', fontSize: '11px', background: '#f8fafc' } : { fontSize: '12px' }
            })
          ]);

        case 'header':
          return el('h3', { id: control.id || `vapt-header-${uniqueKey}`, key: uniqueKey, style: { fontSize: '14px', fontWeight: '700', borderBottom: '1px solid #e2e8f0', paddingBottom: '6px', marginTop: '8px', marginBottom: '8px', color: '#1e293b' } }, safeRender(label));

        case 'section':
          return el('h4', { id: control.id || `vapt-section-${uniqueKey}`, key: uniqueKey, style: { fontSize: '11px', fontWeight: '700', textTransform: 'uppercase', color: '#64748b', marginTop: '12px', marginBottom: '6px', letterSpacing: '0.025em' } }, safeRender(label));

        case 'risk_indicators':
          return el('div', { id: control.id || `vapt-risks-${uniqueKey}`, key: uniqueKey, style: { padding: '10px 0' } }, [
            label && el('strong', { style: { display: 'block', fontSize: '11px', color: '#991b1b', marginBottom: '5px', textTransform: 'uppercase' } }, safeRender(label)),
            el('ul', { style: { margin: 0, paddingLeft: '18px', color: '#b91c1c', fontSize: '12px', listStyleType: 'disc' } },
              (control.risks || control.items || []).map((r, i) => el('li', { key: i, style: { marginBottom: '4px' } }, safeRender(r))))
          ]);

        case 'assurance_badges':
          return el('div', { id: control.id || `vapt-badges-${uniqueKey}`, key: uniqueKey, style: { display: 'flex', gap: '8px', flexWrap: 'wrap', padding: '10px 0', marginTop: '10px', borderTop: '1px solid #fed7aa' } },
            (control.badges || control.items || []).map((b, i) => el('span', { key: i, style: { display: 'flex', alignItems: 'center', background: '#ffffff', color: '#166534', padding: '4px 10px', borderRadius: '15px', fontSize: '12px', border: '1px solid #bbf7d0', fontWeight: '600', boxShadow: '0 1px 2px rgba(0,0,0,0.05)' } }, [
              el('span', { style: { marginRight: '6px', fontSize: '14px' } }, '🛡️'),
              safeRender(b)
            ]))
          );

        case 'test_checklist':
        case 'evidence_list':
          return el('div', { id: control.id || `vapt-list-wrapper-${uniqueKey}`, key: uniqueKey, style: { marginBottom: '10px' } }, [
            label && el('strong', { style: { display: 'block', fontSize: '12px', color: '#334155', marginBottom: '6px' } }, safeRender(label)),
            el('ol', { style: { margin: 0, paddingLeft: '20px', color: '#475569', fontSize: '12px' } },
              (control.items || control.tests || control.checklist || control.evidence || []).map((item, i) => el('li', { key: i, style: { marginBottom: '4px' } }, safeRender(item))))
          ]);

        case 'info':
        case 'html':
          const isSecurityInsight = (control.content || control.html || label || '').includes('SECURITY INSIGHTS');
          return el('div', {
            id: control.id || `vapt-info-${uniqueKey}`,
            key: uniqueKey,
            style: {
              padding: '0',
              background: 'transparent',
              border: 'none',
              boxShadow: 'none',
              fontSize: '12.5px',
              color: '#334155',
              marginBottom: '10px',
              lineHeight: '1.5',
              marginTop: isSecurityInsight ? '0' : '0', 
              display: 'block'
            },
            dangerouslySetInnerHTML: { __html: control.content || control.html || label }
          });

        case 'warning':
        case 'alert':
          const alertType = (label || 'info').toLowerCase();
          const alertMap = {
            success: { icon: 'yes', color: '#166534', bg: '#f0fdf4', border: '#bbf7d0' },
            warning: { icon: 'warning', color: '#9a3412', bg: '#fff7ed', border: '#fed7aa' },
            error: { icon: 'no', color: '#991b1b', bg: '#fef2f2', border: '#fecaca' },
            info: { icon: 'info', color: '#0c4a6e', bg: '#f0f9ff', border: '#bae6fd' },
            tip: { icon: 'lightbulb', color: '#3f6212', bg: '#f7fee7', border: '#d9f99d' },
            alert: { icon: 'warning', color: '#9a3412', bg: '#fff7ed', border: '#fed7aa' }
          };
          const style = alertMap[alertType] || alertMap.info;
          return el('div', {
            id: control.id || `vapt-alert-${uniqueKey}`,
            key: uniqueKey,
            style: {
              display: 'flex',
              gap: '10px',
              padding: '12px',
              background: style.bg,
              borderLeft: `4px solid ${style.color}`,
              borderTop: `1px solid ${style.border}`,
              borderRight: `1px solid ${style.border}`,
              borderBottom: `1px solid ${style.border}`,
              borderRadius: '4px',
              fontSize: '13px',
              color: style.color,
              marginBottom: '15px',
              alignItems: 'center'
            }
          }, [
            el(Icon, { icon: style.icon, size: 20, style: { flexShrink: 0 } }),
            el('div', {
              style: { lineHeight: '1.5' },
              dangerouslySetInnerHTML: { __html: control.message || control.content || label }
            })
          ]);

        case 'remediation_steps':
        case 'evidence_uploader':
          return null;

        default:
          return null;
      }
    };

    const verificationTypes = ['verification_action', 'automated_test', 'test_action', 'risk_indicators', 'assurance_badges'];
    const guideTypes = ['test_checklist', 'evidence_list', 'remediation_steps', 'evidence_uploader'];

    const mainControlsRaw = normalizedControls.filter(c => {
      const isVerification = verificationTypes.includes(c.type);
      const isGuide = guideTypes.includes(c.type);

      // 🧹 Visibility Logic (Schema-Driven)
      if (c.visibility && c.visibility.condition === 'has_content') {
        const val = currentData[c.key];
        const hasContent = val && val.toString().trim().length > 0;
        if (!hasContent && c.visibility.fallback === 'hide') return false;
      }

      // 🧹 Legacy / Key-Based Suppression (v3.3.40 fallback)
      if (['textarea', 'code', 'input', 'html', 'info'].includes(c.type)) {
        const val = currentData[c.key];
        const hasContent = val && val.toString().trim().length > 0;

        // Legacy: Always hide these specific keys if empty
        const legacyKeys = ['operational_notes', 'manual_protocol', 'implementation_notes'];
        if (!hasContent && (legacyKeys.includes(c.key) || c.key?.includes('note') || c.key?.includes('protocol'))) {
          return false;
        }
      }

      // Hide empty Guide items
      if (isGuide) {
        if (['test_checklist', 'evidence_list'].includes(c.type)) {
          const items = c.items || c.tests || c.checklist || c.evidence || [];
          if (items.length === 0) return false;
        }
      }

      if (isGuidePanel) {
        return isGuide;
      } else {
        if (isVerification || isGuide) return false;

        if (c.type === 'section') {
          const label = (c.label || '').toLowerCase();
          const redundantLabels = [
            'verification',
            'automated verification',
            'functional verification',
            'manual verification guidelines',
            'threat coverage',
            'verification & assurance'
          ];
          if (redundantLabels.some(rl => label.includes(rl))) return false;
        }
        return true;
      }
    });

    // Orphan Logic (v3.3.10) - Remove headers/sections if they contain zero functional children
    const mainControls = mainControlsRaw.filter((c, i) => {
      if (['header', 'section'].includes(c.type)) {
        const nextContent = mainControlsRaw.slice(i + 1).find(nc => !['header', 'section', 'divider', 'group'].includes(nc.type));
        return !!nextContent;
      }
      return true;
    });

    const riskControls = normalizedControls.filter(c => c.type === 'risk_indicators');
    const badgeControls = normalizedControls.filter(c => c.type === 'assurance_badges');
    const otherVerificationControls = normalizedControls.filter(c => {
      const isVerification = verificationTypes.includes(c.type);
      if (!isVerification) return false;
      
      if (c.type === 'risk_indicators' || c.type === 'assurance_badges' || c.type === 'verification_action' || c.type === 'automated_test') {
        return false;
      }
      
      // 🛡️ Hide "Active Protection Probe" from Client Dashboard (v2.5.21)
      if (!isWorkbench && c.key === 'verify_active_protection') return false;
      
      return true;
    });

    const getBadgeIcon = (text) => {
      const t = (text || '').toString().toLowerCase();
      if (t.includes('prevent') || t.includes('block')) return '🛡️';
      if (t.includes('detect') || t.includes('log')) return '👁️';
      if (t.includes('limit') || t.includes('rate')) return '⚡';
      if (t.includes('secure') || t.includes('safe')) return '🔒';
      if (t.includes('complian') || t.includes('audit')) return '📋';
      return '✅';
    };

    // v3.14.9: Business Impact Visibility Logic
    // If all controls are hidden AND hideOpNotes is true, return null
    const hasOpNotes = !!(feature.operational_notes || schema.operational_notes);
    const hasProtocol = !!(feature.manual_protocol || schema.manual_protocol || (schema.manual_protocol && (schema.manual_protocol.steps || schema.manual_protocol.length > 0)));

    if (mainControls.length === 0 && riskControls.length === 0 && badgeControls.length === 0 && otherVerificationControls.length === 0) {
      // If we are hiding op notes (or they don't exist) AND we are hiding protocol (or it doesn't exist)
      if ((hideOpNotes || !hasOpNotes) && (hideProtocol || !hasProtocol)) {
        // Still show monitor if explicitly asked and present
        if (isRateLimit && !hideMonitor) {
          return el('div', { className: 'vapt-generated-interface' }, el(RateLimitMonitor, { featureKey: feature.key || feature.id }));
        }
        return null;
      }
    }

    const metadata = schema.metadata || {};

    let opNotes = feature.operational_notes || schema.operational_notes;

    // 🛡️ Logic to handle "Implementation Details" visibility and styling (v2.5.19)
    let processedNotes = opNotes;
    let detailElement = null;
    
    if (typeof opNotes === 'string') {
        const marker = 'Implementation Details:';
        const markerIndex = opNotes.indexOf(marker);
        
        if (markerIndex !== -1) {
            // Found implementation details
            if (!isWorkbench) {
                // Client Dashboard: Strip them out
                processedNotes = opNotes.substring(0, markerIndex).trim().replace(/\n+$/, '');
            } else {
                // Workbench: Extract for styling and keep the rest as processedNotes
                processedNotes = opNotes.substring(0, markerIndex).trim().replace(/\n+$/, '');
                const detailContent = opNotes.substring(markerIndex + marker.length).trim();
                
                detailElement = el('div', {
                    style: {
                        marginTop: '14px',
                        fontSize: '12px'
                    }
                }, [
                    el('strong', {
                        style: {
                            fontWeight: '800',
                            color: '#1e293b',
                            textTransform: 'uppercase',
                            fontSize: '10px',
                            letterSpacing: '0.05em',
                            display: 'block',
                            marginBottom: '6px'
                        }
                    }, __('Implementation Details:', 'vaptsecure')),
                    el('div', { style: { color: '#4b5563' } }, detailContent)
                ]);
            }
        }
    }

    const protocolData = feature.manual_protocol || schema.manual_protocol;

    // 🛡️ Robust Protocol Parsing (v3.12.19)
    let protocolSteps = null;
    if (protocolData) {
      const parsed = (typeof protocolData === 'string' ? JSON.parse(protocolData) : protocolData);
      if (Array.isArray(parsed)) {
        protocolSteps = parsed;
      } else if (parsed && Array.isArray(parsed.steps)) {
        protocolSteps = parsed.steps;
      } else if (parsed) {
        protocolSteps = [parsed];
      }
    }

    // 🛡️ Helper: Convert URLs to clickable links (v3.12.21)
    const linkify = (text) => {
      if (!text || typeof text !== 'string') return text;

      // 1. Handle Markdown Links first: [label](url) (v3.13.15)
      const mdRegex = /\[([^\]]+)\]\((https?:\/\/[^\s#?)]+[^)]*)\)/g;
      let parts = [];
      let lastIndex = 0;
      let match;

      while ((match = mdRegex.exec(text)) !== null) {
        if (match.index > lastIndex) {
          parts.push(text.substring(lastIndex, match.index));
        }
        parts.push(el('a', {
          key: 'md-' + match.index,
          href: match[2],
          target: '_blank',
          rel: 'noopener noreferrer',
          style: { color: '#2563eb', textDecoration: 'underline' }
        }, match[1]));
        lastIndex = mdRegex.lastIndex;
      }

      if (lastIndex < text.length) {
        const remaining = text.substring(lastIndex);
        // 2. Handle raw URLs in the remaining text (excluding what was already matched)
        const urlRegex = /(https?:\/\/[^\s]+)/g;
        const rawParts = remaining.split(urlRegex);
        rawParts.forEach((part, i) => {
          if (part.match(urlRegex)) {
            parts.push(el('a', {
              key: 'raw-' + i,
              href: part,
              target: '_blank',
              rel: 'noopener noreferrer',
              style: { color: '#2563eb', textDecoration: 'underline' }
            }, part));
          } else {
            parts.push(part);
          }
        });
      }

      return parts.length > 0 ? parts : text;
    };

    return el('div', { className: 'vapt-generated-interface', style: { display: 'flex', flexDirection: 'column', gap: '20px' } }, [

      // 🛡️ Operational Notes (v3.12.18) - Card UI Transition (v2.4.3)
      !hideOpNotes && opNotes && el('div', {
        className: 'vapt-op-notes-refactored',
        id: 'vapt-op-notes-container',
        style: {
          padding: '0',
          background: 'transparent',
          border: 'none',
          boxShadow: 'none',
          fontSize: '12.5px',
          color: '#334155',
          lineHeight: '1.5',
          marginBottom: '5px', 
          marginTop: '0'
        }
      }, [
        el('div', { style: { display: 'flex', alignItems: 'center', gap: '6px', color: '#0d9488', fontWeight: '700', fontSize: '11px', textTransform: 'uppercase', letterSpacing: '0.05em', height: '16px', marginBottom: '10px', lineHeight: '1' } }, [
          el(Icon, { icon: 'info', size: 14 }),
          __('Business Impact & Security Benefit', 'vaptsecure')
        ]),
        el('div', { style: { color: '#475569', paddingLeft: '20px' } }, [
          typeof processedNotes === 'string' ? linkify(processedNotes) : JSON.stringify(processedNotes),
          detailElement
        ])
      ]),

      // Functional Controls Panel

      // Functional Controls Panel

      // Live Rate Limit Monitor
      mainControls.length > 0 && el('div', { className: 'vapt-functional-panel', style: { background: '#fff', borderRadius: '8px', padding: '0' } }, [
        el('div', { style: { display: 'flex', flexDirection: 'column', gap: '15px' } }, mainControls.reduce((acc, c, i) => {
          acc.push(renderControl(c, i));
          return acc;
        }, [])),
      ]),

      // Live Rate Limit Monitor (Moved below controls v3.3.45)
      isRateLimit && !hideMonitor && el(RateLimitMonitor, { featureKey: feature.key || feature.id }),

      // 🛡️ Wrap Threat Panel with suppression check
      !hideThreatPanel && (feature.include_verification_guidance == 1 || feature.include_verification_guidance === true || feature.include_verification_guidance === undefined) && (riskControls.length > 0 || otherVerificationControls.length > 0) && el('div', {
        className: 'vapt-threat-panel',
        style: {
          background: '#fff7ed',
          border: '1px solid #fed7aa',
          borderRadius: '8px',
          padding: '15px'
        }
      }, [
        el('h4', { style: { margin: '0 0 10px 0', fontSize: '12px', fontWeight: '700', textTransform: 'uppercase', color: '#9a3412' } }, __('Threat Coverage', 'vaptsecure')),
        riskControls.map(renderControl),
        otherVerificationControls.map(renderControl)
      ]),

      // 🛡️ Wrap Badges with suppression check
      !hideBadges && (feature.include_verification_guidance == 1 || feature.include_verification_guidance === true || feature.include_verification_guidance === undefined) && badgeControls.length > 0 && el('div', {
        className: 'vapt-badges-row',
        style: { display: 'flex', flexWrap: 'wrap', gap: '10px' }
      },
        badgeControls.map(c =>
          (c.badges || c.items || []).map((b, i) => {
            const label = typeof b === 'object' ? (b.label || JSON.stringify(b)) : b;
            return el('span', { key: i, style: { display: 'flex', alignItems: 'center', background: '#ffffff', color: '#166534', padding: '6px 12px', borderRadius: '20px', fontSize: '12px', border: '1px solid #bbf7d0', fontWeight: '600', boxShadow: '0 1px 2px rgba(0,0,0,0.05)' } }, [
              el('span', { style: { marginRight: '6px', fontSize: '14px' } }, getBadgeIcon(label)),
              label
            ]);
          }))
      ),

      // 🛡️ Manual Verification Protocol (v3.12.18) - Collapsible (v3.12.20)
      !hideProtocol && protocolSteps && el('div', {
        className: 'vapt-protocol-panel-refactored',
        style: {
          background: 'transparent',
          border: 'none',
          borderRadius: 0,
          padding: '0'
        }
      }, [
        el('div', { style: { fontSize: '11px', fontWeight: '700', textTransform: 'uppercase', color: '#334155', letterSpacing: '0.05em', marginBottom: '8px', display: 'flex', alignItems: 'center', gap: '6px' } }, [
          el(Icon, { icon: 'excerpt-view', size: 14 }),
          __('Manual Verification Protocol', 'vaptsecure')
        ]),
        el('ol', { style: { margin: '0', paddingLeft: '25px', fontSize: '12px', color: '#475569' } },
          (Array.isArray(protocolSteps) ? protocolSteps : [protocolSteps]).map((s, i) => {
            let stepText = typeof s === 'object' ? (s.action || s.description || s.step || JSON.stringify(s)) : s;
            // 🛡️ Enhanced Numbering Cleanup (v3.13.14): Handles "1. ", "Step 1: ", "1) ", etc.
            stepText = stepText.replace(/^(Step\s*\d+[:\s]*|\d+[\.\)]\s*)+/i, '');
            return el('li', { key: i, style: { marginBottom: '4px' } }, linkify(stepText));
          })
        )
      ]),

      // 🛡️ "No Manual Verification" fallback (v3.14.10)
      !hideProtocol && !protocolSteps && el('div', {
        style: {
          padding: '0',
          background: 'transparent',
          fontSize: '12px',
          color: '#64748b',
          fontStyle: 'italic'
        }
      }, __('No manual verification required.', 'vaptsecure')),

      localAlert && el(Modal, {
        title: localAlert.type === 'error' ? __('Error', 'vaptsecure') : __('Notice', 'vaptsecure'),
        onRequestClose: () => setLocalAlert(null),
        style: { maxWidth: '400px' }
      }, [
        el('div', { style: { display: 'flex', gap: '10px', alignItems: 'center', marginBottom: '15px' } }, [
          localAlert.type === 'success' && el(Icon, { icon: 'yes', size: 24, style: { color: 'green', background: '#dcfce7', borderRadius: '50%', padding: '4px' } }),
          el('p', { style: { fontSize: '14px', color: '#1f2937', margin: 0 } }, safeRender(localAlert.message))
        ]),
        el('div', { style: { textAlign: 'right' } },
          el(Button, { isPrimary: true, onClick: () => setLocalAlert(null) }, __('OK', 'vaptsecure'))
        )
      ])

    ]);
  };
  GeneratedInterface.DynamicConfigSection = DynamicConfigSection;

  window.VAPTSECURE_GeneratedInterface = GeneratedInterface;
  window.VAPTSECURE_DynamicConfigSection = DynamicConfigSection;
  window.VAPTSECURE_PLACEHOLDER_METADATA = PLACEHOLDER_METADATA;
})();

// Superadmin Workbench Entry Point
// Phase 6 Implementation - IDE Workbench Redesign

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
  vaptLog.log('workbench.js loaded');
  if (typeof wp === 'undefined') return;

  const { render, useState, useEffect, useMemo, Fragment, createElement: el } = wp.element || {};
  const { Button, ToggleControl, Spinner, Notice, Card, CardBody, CardHeader, CardFooter, Icon, Tooltip, Modal } = wp.components || {};
  const settings = window.vaptSecureSettings || {};
  const isSuper = settings.isSuper || false;

  const apiFetch = wp.apiFetch;
  const { __, sprintf } = wp.i18n || {};

  // Settings moved to top

  const GeneratedInterface = window.VAPTSECURE_GeneratedInterface || window.vapt_GeneratedInterface;
  const DynamicConfigSection = window.VAPTSECURE_DynamicConfigSection || (GeneratedInterface ? GeneratedInterface.DynamicConfigSection : null);

  vaptLog.info('Workbench Initialization:', { 
    hasGeneratedInterface: !!GeneratedInterface, 
    hasDynamicConfigSection: !!DynamicConfigSection 
  });

  const STATUS_LABELS = {
    'All': __('All Lifecycle', 'vaptsecure'),
    'Develop': __('Develop', 'vaptsecure'),
    'Release': __('Release', 'vaptsecure')
  };

  const ClientDashboard = () => {
    const [features, setFeatures] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // [v4.1.4] Persistence: Initialize state from LocalStorage only (URL params hidden for privacy)
    const [activeStatus, setActiveStatus] = useState(() => {
      const saved = localStorage.getItem('vaptsecure_workbench_active_status');
      return saved ? saved : 'Develop';
    });
    const [activeCategory, setActiveCategory] = useState(() => {
      const saved = localStorage.getItem('vaptsecure_workbench_active_category');
      return saved ? saved : 'all';
    });
    const [activeFeatureKey, setActiveFeatureKey] = useState(() => {
      const saved = localStorage.getItem('vaptsecure_workbench_active_feature');
      return saved ? saved : null;
    });

    const [saveStatus, setSaveStatus] = useState(null);
    const [verifFeature, setVerifFeature] = useState(null);
    const [viewportWidth, setViewportWidth] = useState(() => typeof window !== 'undefined' ? window.innerWidth : 1440);
    const [isNavigatorOpen, setIsNavigatorOpen] = useState(true);
    const [hasInitializedCategory, setHasInitializedCategory] = useState(false);

    // [v4.1.4] Persistence: Sync state to LocalStorage only (URL params hidden for privacy)
    useEffect(() => {
      localStorage.setItem('vaptsecure_workbench_active_status', activeStatus);
      localStorage.setItem('vaptsecure_workbench_active_category', activeCategory);
      if (activeFeatureKey) {
        localStorage.setItem('vaptsecure_workbench_active_feature', activeFeatureKey);
      } else {
        localStorage.removeItem('vaptsecure_workbench_active_feature');
      }
    }, [activeStatus, activeCategory, activeFeatureKey]);

    const isCompactViewport = viewportWidth < 1100;
    const navigatorWidth = isCompactViewport ? Math.min(340, Math.max(280, viewportWidth - 48)) : 360;

    // Auto-dismiss Success Toasts
    useEffect(() => {
      if (saveStatus && saveStatus.type === 'success') {
        const timer = setTimeout(() => {
          vaptLog.info('Auto-clearing success toast');
          setSaveStatus(null);
        }, 3000); // Increased to 3s for better visibility
        return () => clearTimeout(timer);
      }
    }, [saveStatus]);

    const fetchData = (refresh = false) => {
      setLoading(true);

      // Workbench always fetches all features (Superadmin-only, unscoped)
      const path = 'vaptsecure/v1/features';
      apiFetch({ path })
        .then(data => {
          // Dedup features by key to prevent list inflation
          const uniqueFeatures = Array.from(new Map((data.features || []).map(item => [item.key, item])).values());
          vaptLog.log('Features fetched:', uniqueFeatures.length);
          setFeatures(uniqueFeatures);
          setLoading(false);
        })
        .catch(err => {
          vaptLog.error('Fetch failed:', err);
          setError(err.message || 'Failed to load features');
          setLoading(false);
        });
    };

    useEffect(() => {
      fetchData();
    }, []);

    const updateFeature = (key, data, successMsg, silent = false) => {
      vaptLog.info(`AJAX Update Feature: ${key}`, data);
      setFeatures(prev => prev.map(f => f.key === key ? { ...f, ...data } : f));
      
      if (!silent) {
        setSaveStatus({ message: __('Saving...', 'vaptsecure'), type: 'info' });
      }

      return apiFetch({
        path: 'vaptsecure/v1/features/update',
        method: 'POST',
        data: { key, ...data }
      })
        .then((res) => {
          vaptLog.info(`AJAX Success for ${key}`, res);
          if (!silent) {
            setSaveStatus({ message: successMsg || __('Saved', 'vaptsecure'), type: 'success' });
          }
          return res;
        })
        .catch(err => {
          vaptLog.error(`AJAX Error for ${key}:`, err);
          if (!silent) {
            setSaveStatus({ message: __('Save Failed', 'vaptsecure'), type: 'error' });
          }
          throw err;
        });
    };

    const buildImplementationUpdate = (data) => {
      const payload = { implementation_data: data };
      let toggleValue = null;
      if (data && Object.prototype.hasOwnProperty.call(data, 'feat_enabled')) {
        toggleValue = data.feat_enabled;
      } else if (data && Object.prototype.hasOwnProperty.call(data, 'prot_enabled')) {
        toggleValue = data.prot_enabled;
      } else if (data && Object.prototype.hasOwnProperty.call(data, 'enabled')) {
        toggleValue = data.enabled;
      }

      if (toggleValue !== null) {
        payload.is_enabled = toggleValue ? 1 : 0;
        payload.is_enforced = toggleValue ? 1 : 0;
      }

      return payload;
    };

    const availableStatuses = useMemo(() => isSuper ? ['All', 'Develop', 'Release'] : ['All', 'Develop', 'Release'], [isSuper]);

    const statusFeatures = useMemo(() => {
        return features.filter(f => {
            // In generated builds (Locked Mode), we trust the list returned by the scoped API
            // which already filters by vaptsecure_is_feature_allowed().
            // We still check for generated_schema to ensure the UI can render.
            if (!f.generated_schema) return false;

            const s = f.normalized_status || (f.status ? f.status.toLowerCase() : '');
            
            // For Locked Builds (Client Side), we typically want to see "Release" features.
            // However, the user might want to see what's implemented regardless of status 
            // if it's explicitly included in the build.
            const active = activeStatus.toLowerCase();

            if (active === 'all') {
                return true; // Trust the API's scoping
            }

            if (active === 'develop') return ['develop', 'in_progress'].includes(s);
            if (active === 'release') return ['release', 'implemented'].includes(s);
            return s === active;
        });
    }, [features, activeStatus]);

    const categories = useMemo(() => {
      const cats = [...new Set(statusFeatures.map(f => f.category || 'Uncategorized'))].sort();
      return cats;
    }, [statusFeatures]);

    useEffect(() => {
      if (categories.length > 0) {
        if (!hasInitializedCategory) {
          // [v4.1.4] Only set default if URL/Storage didn't provide one
          if (!activeCategory || activeCategory === 'all') {
            setActiveCategory('all');
          }
          setHasInitializedCategory(true);
        } else if (activeCategory && activeCategory !== 'all' && !categories.includes(activeCategory)) {
          // If the specific category is no longer valid, fallback to all
          setActiveCategory('all');
        }
      }
    }, [categories, activeCategory, hasInitializedCategory]);

    const displayFeatures = useMemo(() => {
      if (!activeCategory) return [];
      if (activeCategory === 'all') return statusFeatures;
      return statusFeatures.filter(f => (f.category || 'Uncategorized') === activeCategory);
    }, [statusFeatures, activeCategory]);

    useEffect(() => {
      if (activeCategory === null) return;

      if (displayFeatures.length > 0) {
        const currentInList = displayFeatures.find(f => f.key === activeFeatureKey);
        if (!currentInList) {
          setActiveFeatureKey(displayFeatures[0].key);
        }
      } else {
        setActiveFeatureKey(null);
      }
    }, [displayFeatures, activeFeatureKey, activeCategory]);

    const selectFeature = (featureKey, category) => {
      if (activeCategory !== category) {
        setActiveCategory(category);
      }
      setActiveFeatureKey(featureKey);
    };

    const toggleCategory = (category) => {
      setActiveCategory(prev => prev === category ? null : category);
    };

    // Helper to render a single feature card
    const renderFeatureCard = (f, setVerifFeature) => {
      const schema = typeof f.generated_schema === 'string' ? JSON.parse(f.generated_schema) : (f.generated_schema || { controls: [] });
      vaptLog.log(`Rendering Feature ${f.key}:`, schema);
      const isVerifEngine = f.include_verification_engine;
      const featureBlob = [
        f.key,
        f.label,
        f.title,
        f.name,
        f.description,
        f.summary,
        f.remediation
      ].filter(Boolean).join(' ').toLowerCase();

      // 🛡️ Resilience: Auto-Inject Verification Controls (Moved from GeneratedInterface v3.6.19)
      // This ensures they are correctly filtered into 'automControls' and don't appear in 'implControls'
      if (schema && Array.isArray(schema.controls)) {
        const hasTests = schema.controls.some(c => c.type === 'test_action');
        const featureKey = f.key || '';

        if (!hasTests) {
          // 1. Rate Limiting / Brute Force
          if (['limit-login-attempts', 'rate-limiting', 'login-protection'].some(k => featureBlob.includes(k))) {
            schema.controls.push({
              type: 'test_action',
              label: featureBlob.includes('xmlrpc') ? 'Test: XML-RPC Block' : 'Test: Rate Limit (Spike)',
              key: 'auto_verify_resilience',
              test_logic: featureBlob.includes('xmlrpc') ? 'block_xmlrpc' : 'spam_requests',
              help: __('Auto-injected verification test.', 'vaptsecure')
            });
          }
          // 1b. Core file blocking: WP-Cron
          else if (featureBlob.includes('cron') || featureBlob.includes('wp-cron')) {
            schema.controls.push({
              type: 'test_action',
              label: 'Test: WP-Cron Block',
              key: 'auto_verify_cron',
              test_logic: 'check_headers',
              test_config: {
                path: '/wp-cron.php',
                expected_headers: {
                  'x-vapt-enforced': 'php-cron'
                }
              },
              help: __('Auto-injected verification test.', 'vaptsecure')
            });
          }
          // 1c. REST user enumeration / XML-RPC / pingback / username enumeration
          else if (featureBlob.includes('xmlrpc') || featureBlob.includes('pingback') || featureBlob.includes('rest api') || featureBlob.includes('username enumeration') || featureBlob.includes('user enumeration') || featureBlob.includes('author enumeration')) {
            if (featureBlob.includes('wp-login.php') || featureBlob.includes('login_errors') || featureBlob.includes('invalid credentials')) {
              schema.controls.push({
                type: 'test_action',
                label: 'Test: Login Error Consistency',
                key: 'auto_verify_login_error',
                test_logic: 'universal_probe',
                test_config: {
                  method: 'POST',
                  path: '/wp-login.php',
                  params: {
                    log: 'vaptsecure_nonexistent_user',
                    pwd: 'invalid-password',
                    'wp-submit': 'Log In',
                    redirect_to: window.location.origin + '/wp-admin/',
                    testcookie: '1'
                  },
                  expected_status: [200],
                  expected_text: 'Invalid credentials. Please try again.'
                },
                help: __('Auto-injected verification test.', 'vaptsecure')
              });
            }
            else {
              schema.controls.push({
                type: 'test_action',
                label: featureBlob.includes('pingback') ? 'Test: XML-RPC Pingback Block' : (featureBlob.includes('xmlrpc') ? 'Test: XML-RPC Block' : 'Test: REST User Enumeration Block'),
                key: 'auto_verify_access_control',
                test_logic: featureBlob.includes('pingback') ? 'disable_xmlrpc_pingback' : (featureBlob.includes('xmlrpc') ? 'block_xmlrpc' : 'block_author_enumeration'),
                help: __('Auto-injected verification test.', 'vaptsecure')
              });
            }
          }
          // 2. Generic Fallback
          else if (f.include_verification_engine || (f.generated_schema && f.generated_schema.include_verification_engine)) {
            schema.controls.push({
              type: 'test_action',
              label: 'Test: Basic Verification',
              key: 'auto_verify_generic',
              test_logic: 'default',
              help: __('Basic availability check.', 'vaptsecure')
            });
          }
        }
      }

      const effectiveSchema = schema && Array.isArray(schema.controls)
        ? (() => {
            const isWpLoginLoginErrorFeature = /\/wp-login\.php|login_errors|invalid credentials/.test(featureBlob);
            const isRestUsersFeature = /\/wp-json\/wp\/v2\/users|rest api|wordpress rest api/.test(featureBlob);
            const isAuthorQueryFeature = /\?author=1|author query|author archives|author enumeration/.test(featureBlob);
            const controls = schema.controls.map((control) => {
              const controlLabel = String(control.label || '').toLowerCase();
              const controlPath = String(control.test_config?.path || '').toLowerCase();
              if (isWpLoginLoginErrorFeature && control.type === 'test_action' && (
                controlLabel.includes('a+ header verification') ||
                controlLabel.includes('rest api protection check') ||
                controlLabel.includes('author enumeration check') ||
                controlLabel.includes('rest user enumeration') ||
                control.test_logic === 'check_headers' ||
                control.test_logic === 'block_author_enumeration' ||
                control.test_logic === 'verify_rest_lockdown' ||
                controlPath.includes('/wp-json/wp/v2/users') ||
                controlPath.includes('/?author=1')
              )) {
                return {
                  ...control,
                  label: 'Test: Login Error Consistency',
                  key: 'auto_verify_login_error',
                  test_logic: 'universal_probe',
                  test_config: {
                    method: 'POST',
                    path: '/wp-login.php',
                    params: {
                      log: 'vaptsecure_nonexistent_user',
                      pwd: 'invalid-password',
                      'wp-submit': 'Log In',
                      redirect_to: window.location.origin + '/wp-admin/',
                      testcookie: '1'
                    },
                    expected_status: [200],
                    expected_text: 'Invalid credentials. Please try again.'
                  },
                  help: __('Auto-injected verification test.', 'vaptsecure')
                };
              }
              if (isRestUsersFeature && control.type === 'test_action' && (
                controlLabel.includes('a+ header verification') ||
                controlLabel.includes('rest api protection check') ||
                controlLabel.includes('author enumeration check') ||
                controlLabel.includes('rest user enumeration') ||
                controlLabel.includes('username enumeration') ||
                control.test_logic === 'check_headers' ||
                control.test_logic === 'block_author_enumeration' ||
                control.test_logic === 'verify_rest_lockdown' ||
                controlPath.includes('/wp-login.php') ||
                controlPath.includes('/?author=1')
              )) {
                return {
                  ...control,
                  label: 'Test: REST User Enumeration Block',
                  key: 'auto_verify_access_control',
                  test_logic: 'block_author_enumeration',
                  help: __('Auto-injected verification test.', 'vaptsecure')
                };
              }
              if (isAuthorQueryFeature && control.type === 'test_action' && (
                controlLabel.includes('a+ header verification') ||
                controlLabel.includes('rest api protection check') ||
                controlLabel.includes('author enumeration check') ||
                controlLabel.includes('rest user enumeration') ||
                control.test_logic === 'check_headers' ||
                control.test_logic === 'block_author_enumeration' ||
                control.test_logic === 'verify_rest_lockdown' ||
                controlPath.includes('/wp-login.php') ||
                controlPath.includes('/wp-json/wp/v2/users')
              )) {
                return {
                  ...control,
                  label: 'Test: Author Enumeration Block',
                  key: 'auto_verify_author',
                  test_logic: 'block_author_enumeration',
                  help: __('Auto-injected verification test.', 'vaptsecure')
                };
              }
              const isPingbackControl =
                featureBlob.includes('pingback') &&
                control.type === 'test_action' &&
                (control.test_logic === 'check_headers' || control.test_logic === 'block_xmlrpc' || controlLabel.includes('xml-rpc')) &&
                (controlPath.includes('xmlrpc.php') || controlLabel.includes('header verification') || controlLabel.includes('xml-rpc'));

              if (!isPingbackControl) return control;

              return {
                ...control,
                label: 'Test: XML-RPC Pingback Block',
                test_logic: 'disable_xmlrpc_pingback'
              };
            }).filter((control, index, mappedControls) => {
              if (control.type !== 'test_action' || control.test_logic !== 'disable_xmlrpc_pingback') {
                return true;
              }

              return mappedControls.findIndex(candidate =>
                candidate.type === 'test_action' &&
                candidate.test_logic === 'disable_xmlrpc_pingback'
              ) === index;
            });

            const hasActiveProbe = controls.some(control => control.type === 'test_action' && control.key === 'verify_active_protection');
            const hasCanonicalEnumerationProbe = controls.some(control => {
              if (control.type !== 'test_action') return false;
              const label = String(control.label || '').toLowerCase();
              const path = String(control.test_config?.path || '').toLowerCase();
              return label.includes('rest api protection check') ||
                label.includes('author enumeration check') ||
                label.includes('login error consistency') ||
                path.includes('/wp-json/wp/v2/users') ||
                path.includes('/?author=1') ||
                path.includes('/wp-login.php');
            });
            if (!hasActiveProbe && controls.some(control => control.type === 'test_action') && !hasCanonicalEnumerationProbe) {
              const activeProbePath = featureBlob.includes('/wp-json/wp/v2/users') || featureBlob.includes('rest api') || featureBlob.includes('wordpress rest api')
                ? '/wp-json/wp/v2/users'
                : (featureBlob.includes('/?author=1') || featureBlob.includes('author query') || featureBlob.includes('author archives') || featureBlob.includes('author enumeration')
                  ? '/?author=1'
                  : (featureBlob.includes('xmlrpc') || featureBlob.includes('xml-rpc') || featureBlob.includes('pingback')
                    ? '/xmlrpc.php'
                    : (featureBlob.includes('cron') || featureBlob.includes('wp-cron')
                      ? '/wp-cron.php'
                      : '/')));

              controls.push({
                type: 'test_action',
                label: 'Active Protection Probe',
                key: 'verify_active_protection',
                test_logic: 'universal_probe',
                test_config: {
                  path: activeProbePath,
                  expected_status: activeProbePath === '/' ? [200] : [401, 403, 404, 405]
                },
                help: __('Runs a runtime endpoint probe to confirm the protection response.', 'vaptsecure')
              });
            }

            const testPriority = (control) => {
              if (control.type !== 'test_action') return 0;

              const label = String(control.label || '').toLowerCase();
              if (control.key === 'verify_integrity' || label.includes('site integrity')) return 30;
              if (control.key === 'verify_active_protection' || label.includes('active protection probe')) return 20;
              return 10;
            };

            controls.sort((a, b) => testPriority(a) - testPriority(b));

            return { ...schema, controls };
          })()
        : schema;

      // Filter controls
      // 1. Implementation Controls (Left Column)
      const implControls = effectiveSchema.controls ? effectiveSchema.controls.filter(c =>
        !['test_action', 'risk_indicators', 'assurance_badges', 'test_checklist', 'evidence_list', 'header', 'html', 'info', 'warning', 'alert'].includes(c.type) &&
        !['feat_enabled', 'is_enabled', 'is_enforced'].includes(c.key) &&
        !c.label?.toLowerCase().includes('notes') &&
        !c.label?.toLowerCase().includes('enable protection') &&
        !c.label?.toLowerCase().includes('enable feature')
      ) : [];

      // 1.1. Security Insights / HTML blocks for Row 1 Right Column
      const insightControls = effectiveSchema.controls ? effectiveSchema.controls.filter(c => (c.type === 'html' || c.type === 'info' || c.type === 'warning' || c.type === 'alert') && !c.label?.toLowerCase().includes('enable protection') && !c.label?.toLowerCase().includes('enable feature')) : [];

      // 1.2. Master Toggle Control (The one with the tooltip)
      const masterToggleControl = effectiveSchema.controls ? effectiveSchema.controls.find(c => c.type === 'toggle' && (c.key === 'feat_enabled' || c.label?.toLowerCase().includes('enable protection') || c.label?.toLowerCase().includes('enable feature'))) : null;

      // 2. Automated Controls (Right Column)
      const automControls = effectiveSchema.controls ? effectiveSchema.controls.filter(c => c.type === 'test_action') : [];
      const noteControls = (effectiveSchema.controls || []).filter(c => {
        const isNote = c.label?.toLowerCase().includes('notes') || c.key?.includes('notes');
        if (!isNote) return false;

        // Content Check
        const implData = f.implementation_data ? (typeof f.implementation_data === 'string' ? JSON.parse(f.implementation_data) : f.implementation_data) : {};
        const val = implData[c.key];
        return val && val.toString().trim().length > 0;
      });

      const automatedVerificationPanel = el('div', { className: 'vapt-automation-panel', style: { padding: '15px', background: '#f8fafc', borderRadius: '8px', border: '1px solid #e2e8f0', display: 'flex', flexDirection: 'column', minWidth: 0, overflow: 'hidden' } }, [
        el('h4', { style: { margin: '0 0 12px 0', fontSize: '11px', fontWeight: 700, color: '#0f766e', textTransform: 'uppercase', letterSpacing: '0.05em', display: 'flex', alignItems: 'center', gap: '6px' } }, [
          el(Icon, { icon: 'yes-alt', size: 14 }),
          __('Automated Verification Engine', 'vaptsecure')
        ]),
        el('div', { style: { flex: 1, minWidth: 0, overflow: 'hidden' } }, [
          automControls.length > 0 ? el(GeneratedInterface, {
            feature: { ...f, generated_schema: { ...schema, controls: automControls } },
            onUpdate: (data) => updateFeature(f.key, buildImplementationUpdate(data)),
            hideMonitor: true,
            hideOpNotes: true, // Hide Business Impact here
            hideProtocol: true, // Manual protocol is in its own panel
            showTechnicalTrace: true,
            showVerificationDetails: false
          }) : el('p', { style: { fontSize: '12px', color: '#64748b', fontStyle: 'italic', margin: 0 } }, __('No automated tests defined.', 'vaptsecure'))
        ])
      ]);

      const manualVerificationPanel = el('div', { className: 'vapt-protocol-panel', style: { padding: '15px', background: '#fff', borderRadius: '8px', border: '1px solid #e2e8f0', display: 'flex', flexDirection: 'column', minWidth: 0, overflow: 'hidden' } }, [
        el('div', { style: { flex: 1, minWidth: 0, overflow: 'hidden' } }, [
          // v3.14.11: CLEANEST RENDERING - only show protocol box
          el(GeneratedInterface, {
            // 🛡️ v3.14.12: Pass a schema with ZERO controls to ensure NO toggles/inputs leak in
            feature: { ...f, generated_schema: { ...schema, controls: [] } },
            onUpdate: (data) => updateFeature(f.key, buildImplementationUpdate(data)),
            hideOpNotes: true,
            hideMonitor: true,
            hideImplementationControl: true,
            hideThreatPanel: true,
            hideBadges: true,
            hideProtocol: false
          })
        ])
      ]);

      return el(Card, { key: f.key, id: `feature-${f.key}`, style: { borderRadius: '12px', border: '1px solid #e5e7eb', boxShadow: 'none' } }, [
        el(CardHeader, { style: { borderBottom: '1px solid #f3f4f6', padding: '12px 24px' } }, [
          el('div', { style: { display: 'grid', gridTemplateColumns: '1fr auto', alignItems: 'center', gap: '20px', width: '100%' } }, [
            el('div', null, [
              el('div', { style: { display: 'flex', flexDirection: 'column', gap: '4px' } }, [
                el('h3', { style: { margin: 0, fontSize: '16px', fontWeight: 700, color: '#111827', display: 'flex', alignItems: 'center' } }, [
                  f.label,
                  f.severity && el('span', {
                    style: {
                      marginLeft: '15px',
                      fontSize: '11px',
                      fontWeight: '700',
                      padding: '2px 8px',
                      borderRadius: '4px',
                      textTransform: 'uppercase',
                      color: '#fff',
                      background: (() => {
                        const s = f.severity.toLowerCase();
                        if (s === 'critical') return '#dc2626'; // Red
                        if (s === 'high') return '#ea580c';     // Orange
                        if (s === 'medium') return '#2271b1';   // Blue
                        return '#64748b';                        // Slate (Low/Info)
                      })(),
                      boxShadow: '0 1px 2px rgba(0,0,0,0.1)'
                    }
                  }, f.severity)
                ]),
                f.description && el('p', { style: { margin: 0, fontSize: '12px', color: '#64748b', lineHeight: '1.4' } }, f.description)
              ])
            ]),
            el('div', { style: { display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '10px' } }, [
              el('span', {
                className: `vapt-status-badge status-${f.status.toLowerCase()}`,
                style: {
                  fontSize: '10px',
                  fontWeight: 700,
                  padding: '2px 8px',
                  borderRadius: '4px',
                  textTransform: 'uppercase',
                  color: '#fff',
                  background: (f.status === 'Develop' || f.status === 'develop') ? '#10b981' :
                    (f.status === 'Release' || f.status === 'release' || f.status === 'implemented') ? '#f97316' : '#94a3b8',
                  boxShadow: '0 1px 2px rgba(0,0,0,0.1)'
                }
              }, f.status),
            ])
          ])
        ]),
      el(CardBody, { style: { padding: isCompactViewport ? '16px' : '24px' } }, [
        el('div', { style: { display: 'flex', flexDirection: 'column', gap: '25px' } }, [
          // Row 1: Functional Implementation + Implementation Control
          el('div', { style: { display: 'grid', gridTemplateColumns: isCompactViewport ? '1fr' : '1fr 1fr', gap: '15px', alignItems: 'start' } }, [
            el('div', { style: { display: 'flex', flexDirection: 'column', gap: '15px', minWidth: 0 } }, [
              el('div', { className: 'vapt-implementation-panel', style: { padding: '15px', background: '#fff', borderRadius: '8px', border: '1px solid #e2e8f0', boxShadow: '0 1px 2px rgba(0,0,0,0.05)', display: 'flex', flexDirection: 'column', minWidth: 0, overflow: 'hidden' } }, [
                el('h4', { style: { margin: '0 0 15px 0', fontSize: '13px', fontWeight: 700, color: '#111827', display: 'flex', justifyContent: 'space-between', alignItems: 'center', borderBottom: '1px solid #f1f5f9', paddingBottom: '8px', minHeight: '32px' } }, [
                  el('span', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, [
                    el(Icon, { icon: 'admin-settings', size: 16 }),
                    __('Functional Implementation', 'vaptsecure')
                  ])
                ]),
                el('div', { style: { flex: 1, minWidth: 0, overflow: 'hidden' } }, [
                  f.generated_schema && GeneratedInterface
                    ? el(GeneratedInterface, {
                        feature: { ...f, generated_schema: { ...effectiveSchema, controls: implControls } },
                        onUpdate: (data) => updateFeature(f.key, buildImplementationUpdate(data)),
                        hideProtocol: true, // 🛡️ v3.14.14: Explicitly hide protocol from left panel
                        hideImplementationControl: true,
                        hideOpNotes: false, // Keep Business Impact here
                        hideThreatPanel: true // Prevent leaking Security Insights/HTML to left panel
                      })
                    : el('div', { style: { padding: '30px', background: '#f9fafb', border: '1px dashed #d1d5db', borderRadius: '8px', textAlign: 'center', color: '#9ca3af', fontSize: '13px' } },
                      __('No configurable controls.', 'vaptsecure'))
                ])
              ]),

              el('div', { className: 'vapt-control-panel', style: { padding: '15px', background: '#fff', borderRadius: '8px', border: '1px solid #e2e8f0', boxShadow: '0 1px 2px rgba(0,0,0,0.05)', display: 'flex', flexDirection: 'column', minWidth: 0, overflow: 'hidden' } }, [
                el('h4', { style: { margin: '0 0 15px 0', fontSize: '13px', fontWeight: 700, color: '#111827', display: 'flex', alignItems: 'center', justifyContent: 'space-between', borderBottom: '1px solid #f1f5f9', paddingBottom: '8px', minHeight: '32px' } }, [
                  el('span', { style: { display: 'flex', alignItems: 'center', gap: '8px' } }, [
                    el(Icon, { icon: 'shield', size: 16 }),
                    __('Implementation Control', 'vaptsecure')
                  ]),
                  // 🛡️ Enable Protection Toggle next to title
                  masterToggleControl ? el('div', { className: 'vapt-inline-master-toggle', style: { transform: 'scale(0.85)', marginRight: '-10px', display: 'flex', alignItems: 'center' } }, [
                    el(GeneratedInterface, {
                      feature: { ...f, generated_schema: { ...effectiveSchema, controls: [masterToggleControl] } },
                      onUpdate: (data) => updateFeature(f.key, buildImplementationUpdate(data)),
                      hideOpNotes: true,
                      hideProtocol: true,
                      hideMonitor: true,
                      globalProtection: true,
                      isCompact: false // Restored label
                    })
                  ]) : el('div', { className: 'vapt-inline-master-toggle', style: { transform: 'scale(0.85)', marginRight: '-10px', display: 'flex', alignItems: 'center' } }, [
                    el(ToggleControl, {
                      label: __('Enable Protection', 'vaptsecure'),
                      checked: !!(f.is_enabled || f.is_enforced),
                      onChange: (val) => updateFeature(f.key, { is_enabled: val ? 1 : 0, is_enforced: val ? 1 : 0 }, __('Saved', 'vaptsecure')),
                      __nextHasNoMarginBottom: true
                    })
                  ])
                ]),
                // [v4.1.2] Dynamic Configuration (Moved above Security Insight)
                DynamicConfigSection && el(DynamicConfigSection, {
                  feature: f,
                  verificationFeatureData: { 
                    ...f, 
                    platform_implementations: f.platform_implementations || {} 
                  },
                  currentData: (() => {
                    if (!f.implementation_data) return {};
                    if (typeof f.implementation_data === 'object') return f.implementation_data;
                    try { return JSON.parse(f.implementation_data); } catch(e) { return {}; }
                  })(),
                  handleChange: (key, val) => {
                    const current = (() => {
                      if (!f.implementation_data) return {};
                      if (typeof f.implementation_data === 'object') return f.implementation_data;
                      try { return JSON.parse(f.implementation_data); } catch(e) { return {}; }
                    })();
                    const next = { ...current, [key]: val };
                    updateFeature(f.key, buildImplementationUpdate(next), __('Configuration Updated', 'vaptsecure'));
                  }
                }),
                insightControls.length > 0 && el('div', { style: { padding: '10px 0 0' } }, [
                  el(GeneratedInterface, {
                    feature: { ...f, generated_schema: { ...effectiveSchema, controls: insightControls } },
                    onUpdate: (data) => updateFeature(f.key, buildImplementationUpdate(data)),
                    hideOpNotes: true,
                    hideProtocol: true,
                    hideMonitor: true
                  })
                ])
              ]),

              manualVerificationPanel
            ]),
            automatedVerificationPanel
          ]),

          // Operational Notes (Full Width, Below Grid)
          !!f.include_operational_notes && noteControls.length > 0 && el('div', { style: { marginTop: '25px', padding: '15px', background: '#fff', borderRadius: '8px', border: '1px solid #e2e8f0' } }, [
            el('h4', { style: { margin: '0 0 10px 0', fontSize: '12px', fontWeight: 700, color: '#475569', textTransform: 'uppercase', display: 'flex', alignItems: 'center', gap: '8px' } }, [
              el(Icon, { icon: 'editor-help', size: 18 }),
              __('Operational Notes', 'vaptsecure')
            ]),
            el(GeneratedInterface, {
              feature: { ...f, generated_schema: { ...schema, controls: noteControls } },
              onUpdate: (data) => updateFeature(f.key, buildImplementationUpdate(data)),
              hideMonitor: true
            })
          ])
        ])
      ]),
      el(CardFooter, { style: { borderTop: '1px solid #f3f4f6', padding: '12px 24px', background: '#fafafa' } }, [
        el('span', { style: { fontSize: '11px', color: '#9ca3af' } }, sprintf(__('Feature Reference: %s', 'vaptsecure'), f.key))
      ])
    ]);
  };

    if (loading) return el('div', { className: 'vapt-loading' }, [el(Spinner), el('p', null, __('Loading Workbench...', 'vaptsecure'))]);
    if (error) return el(Notice, { status: 'error', isDismissible: false }, error);

    return el('div', { className: 'vapt-workbench-root', style: { display: 'flex', flexDirection: 'column', minHeight: 'calc(100vh - 120px)', background: '#f9fafb', position: 'relative', paddingBottom: '40px' } }, [

      // Toast Notification
      saveStatus && el('div', {
        style: {
          position: 'fixed', top: '40px', left: '50%', transform: 'translateX(-50%)',
          background: saveStatus.type === 'error' ? '#fde8e8' : (saveStatus.type === 'success' ? '#def7ec' : '#e0f2fe'),
          color: saveStatus.type === 'error' ? '#9b1c1c' : (saveStatus.type === 'success' ? '#03543f' : '#0369a1'),
          padding: '10px 20px', borderRadius: '30px', boxShadow: '0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05)',
          zIndex: 99999, fontWeight: '600', fontSize: '13px', display: 'flex', alignItems: 'center', gap: '8px',
          border: '1px solid rgba(0,0,0,0.1)'
        }
      }, [
        el(Icon, { icon: saveStatus.type === 'error' ? 'warning' : (saveStatus.type === 'success' ? 'yes' : 'update'), size: 16 }),
        saveStatus.message
      ]),

      // Top Navigation
      el('header', { style: { padding: '15px 30px', background: '#fff', borderBottom: '1px solid #e5e7eb', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }, [
        el('div', { style: { display: 'flex', alignItems: 'center', gap: '15px' } }, [
          el('h2', { style: { margin: 0, fontSize: '18px', fontWeight: 700, color: '#111827', display: 'flex', alignItems: 'baseline', gap: '8px' } }, [
            __('VAPT Implementation Dashboard'),
            el('span', { style: { fontSize: '11px', color: '#9ca3af', fontWeight: '400' } }, `v${settings.pluginVersion}`)
          ])
        ]),
        el('div', { style: { display: 'flex', gap: '5px', background: '#f3f4f6', padding: '4px', borderRadius: '8px' } },
          availableStatuses.map(s => el(Button, {
            key: s,
            onClick: () => setActiveStatus(s),
            style: {
              background: activeStatus === s ? '#fff' : 'transparent',
              color: activeStatus === s ? '#111827' : '#6b7280',
              border: 'none', borderRadius: '6px', padding: '8px 16px', fontWeight: 600, fontSize: '13px',
              boxShadow: activeStatus === s ? '0 1px 3px rgba(0,0,0,0.1)' : 'none'
            }
          }, STATUS_LABELS[s]))
        )
      ]),

      // Main Content Area
      el('div', { className: 'vapt-workbench-shell', style: { display: 'flex', flexGrow: 1, overflow: 'hidden', position: 'relative' } }, [
        isCompactViewport && el(Button, {
          className: 'vapt-workbench-nav-toggle',
          onClick: () => setIsNavigatorOpen(prev => !prev),
          style: {
            position: 'absolute',
            top: '14px',
            left: '16px',
            zIndex: 45,
            background: '#111827',
            color: '#fff',
            border: 'none',
            borderRadius: '999px',
            padding: '7px 12px',
            fontSize: '12px',
            fontWeight: 700,
            boxShadow: '0 8px 18px rgba(15, 23, 42, 0.18)'
          }
        }, isNavigatorOpen ? __('Hide Menu', 'vaptsecure') : __('Menu', 'vaptsecure')),
        isCompactViewport && isNavigatorOpen && el('button', {
          className: 'vapt-workbench-nav-backdrop',
          'aria-label': __('Close feature navigator', 'vaptsecure'),
          onClick: () => setIsNavigatorOpen(false),
          style: {
            position: 'absolute',
            inset: 0,
            zIndex: 35,
            border: 'none',
            background: 'rgba(15, 23, 42, 0.28)',
            cursor: 'pointer'
          }
        }),
        // Pane 1: Collapsible Feature Navigator
        el('aside', {
          className: 'vapt-workbench-sidebar' + (isCompactViewport ? ' is-offcanvas' : '') + (isNavigatorOpen ? ' is-open' : ''),
          style: {
            width: `${navigatorWidth}px`,
            borderRight: '1px solid #e5e7eb',
            background: '#fff',
            overflowY: 'auto',
            maxHeight: '100%',
            padding: isCompactViewport ? '58px 0 18px' : '18px 0',
            flexShrink: 0,
            position: isCompactViewport ? 'absolute' : 'relative',
            top: 0,
            bottom: 0,
            left: 0,
            zIndex: isCompactViewport ? 40 : 'auto',
            transform: isCompactViewport && !isNavigatorOpen ? 'translateX(-105%)' : 'translateX(0)',
            transition: 'transform 180ms ease, box-shadow 180ms ease',
            boxShadow: isCompactViewport && isNavigatorOpen ? '18px 0 35px rgba(15, 23, 42, 0.18)' : 'none'
          }
        }, [
          el('div', { style: { padding: '0 20px 14px', borderBottom: '1px solid #eef2f7' } }, [
            el('div', { style: { fontSize: '11px', fontWeight: 800, color: '#9ca3af', textTransform: 'uppercase', letterSpacing: '0.06em' } }, __('Feature Navigator', 'vaptsecure')),
            el('div', { style: { marginTop: '4px', fontSize: '12px', color: '#64748b' } }, sprintf(__('%d features across %d categories', 'vaptsecure'), statusFeatures.length, categories.length))
          ]),
          categories.length > 0 && el(Fragment, null, [
            (() => {
              const isExpanded = activeCategory === 'all';
              return el('div', {
                key: 'all',
                className: 'vapt-nav-accordion' + (isExpanded ? ' is-expanded' : ''),
                style: { borderBottom: '1px solid #f1f5f9' }
              }, [
                el('button', {
                  onClick: () => toggleCategory('all'),
                  className: 'vapt-sidebar-link' + (isExpanded ? ' is-active' : ''),
                  'aria-expanded': isExpanded,
                  style: {
                    width: '100%', border: 'none', background: isExpanded ? '#eff6ff' : 'transparent',
                    color: isExpanded ? '#1d4ed8' : '#334155',
                    padding: '13px 18px', textAlign: 'left', cursor: 'pointer',
                    display: 'grid', gridTemplateColumns: '18px 1fr auto', alignItems: 'center', gap: '9px',
                    borderRight: isExpanded ? '3px solid #1d4ed8' : '3px solid transparent',
                    fontWeight: isExpanded ? 700 : 600,
                    fontSize: '13px'
                  }
                }, [
                  el('span', { style: { color: isExpanded ? '#1d4ed8' : '#94a3b8', fontSize: '13px', fontWeight: 800 } }, isExpanded ? '-' : '+'),
                  el('span', null, __('All Categories', 'vaptsecure')),
                  el('span', { style: { fontSize: '11px', background: isExpanded ? '#dbeafe' : '#f3f4f6', color: isExpanded ? '#1d4ed8' : '#64748b', padding: '2px 7px', borderRadius: '999px' } }, statusFeatures.length)
                ]),
                isExpanded && el('div', {
                  className: 'vapt-nav-submenu',
                  style: { padding: '2px 0 4px', background: '#f8fafc' }
                }, statusFeatures.map((f, index) => {
                  const isActive = activeFeatureKey === f.key;
                  const multiFileClass = f.exists_in_multiple_files ? ' vapt-feature-multi-file' : (f.is_from_active_file === false ? ' vapt-feature-inactive-only' : '');
                  return el('button', {
                    key: f.key,
                    title: f.label,
                    onClick: () => selectFeature(f.key, 'all'),
                    className: 'vapt-feature-item' + (isActive ? ' is-active' : '') + multiFileClass,
                    style: {
                      width: '100%', border: 'none',
                      background: isActive ? '#e0ecff' : 'transparent',
                      color: isActive ? '#1d4ed8' : '#475569',
                      padding: '6px 18px 6px 58px', textAlign: 'left', cursor: 'pointer',
                      display: 'grid',
                      gridTemplateColumns: '28px 1fr',
                      alignItems: 'center',
                      gap: '8px',
                      borderRight: isActive ? '3px solid #1d4ed8' : '3px solid transparent',
                      fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
                      fontWeight: isActive ? 700 : 500,
                      fontSize: '12px',
                      lineHeight: 1.25,
                      letterSpacing: '-0.01em',
                      transition: 'all 0.15s ease'
                    }
                  }, [
                    el('span', {
                      style: {
                        color: isActive ? '#1d4ed8' : '#94a3b8',
                        fontWeight: 700,
                        textAlign: 'right',
                        fontVariantNumeric: 'tabular-nums'
                      }
                    }, String(index + 1).padStart(2, '0') + '.'),
                    el('span', {
                      style: {
                        display: 'block',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap'
                      }
                    }, f.label)
                  ]);
                }))
              ]);
            })(),
            categories.map(cat => {
              const catFeatures = statusFeatures.filter(f => (f.category || 'Uncategorized') === cat);
              const isExpanded = activeCategory === cat;
              return el('div', {
                key: cat,
                className: 'vapt-nav-accordion' + (isExpanded ? ' is-expanded' : ''),
                style: { borderBottom: '1px solid #f1f5f9' }
              }, [
                el('button', {
                  onClick: () => toggleCategory(cat),
                  className: 'vapt-sidebar-link' + (isExpanded ? ' is-active' : ''),
                  'aria-expanded': isExpanded,
                  style: {
                    width: '100%', border: 'none', background: isExpanded ? '#eff6ff' : 'transparent',
                    color: isExpanded ? '#1d4ed8' : '#334155',
                    padding: '13px 18px', textAlign: 'left', cursor: 'pointer',
                    display: 'grid', gridTemplateColumns: '18px 1fr auto', alignItems: 'center', gap: '9px',
                    borderRight: isExpanded ? '3px solid #1d4ed8' : '3px solid transparent',
                    fontWeight: isExpanded ? 700 : 600,
                    fontSize: '13px'
                  }
                }, [
                  el('span', { style: { color: isExpanded ? '#1d4ed8' : '#94a3b8', fontSize: '13px', fontWeight: 800 } }, isExpanded ? '-' : '+'),
                  el('span', null, cat),
                  el('span', { style: { fontSize: '11px', background: isExpanded ? '#dbeafe' : '#f3f4f6', color: isExpanded ? '#1d4ed8' : '#64748b', padding: '2px 7px', borderRadius: '999px' } }, catFeatures.length)
                ]),
                isExpanded && el('div', {
                  className: 'vapt-nav-submenu',
                  style: { padding: '2px 0 4px', background: '#f8fafc' }
                }, catFeatures.map((f, index) => {
                  const isActive = activeFeatureKey === f.key;
                  const multiFileClass = f.exists_in_multiple_files ? ' vapt-feature-multi-file' : (f.is_from_active_file === false ? ' vapt-feature-inactive-only' : '');
                  return el('button', {
                    key: f.key,
                    title: f.label,
                    onClick: () => selectFeature(f.key, cat),
                    className: 'vapt-feature-item' + (isActive ? ' is-active' : '') + multiFileClass,
                    style: {
                      width: '100%', border: 'none',
                      background: isActive ? '#e0ecff' : 'transparent',
                      color: isActive ? '#1d4ed8' : '#475569',
                      padding: '6px 18px 6px 58px', textAlign: 'left', cursor: 'pointer',
                      display: 'grid',
                      gridTemplateColumns: '28px 1fr',
                      alignItems: 'center',
                      gap: '8px',
                      borderRight: isActive ? '3px solid #1d4ed8' : '3px solid transparent',
                      fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
                      fontWeight: isActive ? 700 : 500,
                      fontSize: '12px',
                      lineHeight: 1.25,
                      letterSpacing: '-0.01em',
                      transition: 'all 0.15s ease'
                    }
                  }, [
                    el('span', {
                      style: {
                        color: isActive ? '#1d4ed8' : '#94a3b8',
                        fontWeight: 700,
                        textAlign: 'right',
                        fontVariantNumeric: 'tabular-nums'
                      }
                    }, String(index + 1).padStart(2, '0') + '.'),
                    el('span', {
                      style: {
                        display: 'block',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                        whiteSpace: 'nowrap'
                      }
                    }, f.label)
                  ]);
                }))
              ]);
            })
          ]),
          categories.length === 0 && el('p', { style: { padding: '20px', color: '#9ca3af', fontSize: '13px' } }, __('No active categories', 'vaptsecure'))
        ]),

        // Pane 2: Feature Interface (Right)
        el('main', {
          className: 'vapt-workbench-main',
          style: {
            flexGrow: 1,
            minWidth: 0,
            width: isCompactViewport ? '100%' : 'auto',
            padding: isCompactViewport ? '62px 16px 24px' : '30px',
            overflowY: 'auto',
            background: '#f9fafb'
          }
        }, [
          !activeFeatureKey ? el('div', { style: { textAlign: 'center', padding: '100px', color: '#9ca3af' } }, __('Select a feature from the list to view implementation controls.', 'vaptsecure')) :
            el('div', { style: { maxWidth: isCompactViewport ? '100%' : '1200px', margin: '0 auto', display: 'flex', flexDirection: 'column', gap: '20px', minWidth: 0 } }, [
              // Breadcrumb Removed (v3.6.19 Request)
              renderFeatureCard(features.find(f => f.key === activeFeatureKey), setVerifFeature)
            ])
        ])
      ]),

      // Functional Verification Modal (Simplified)
      verifFeature && el(Modal, {
        title: sprintf(__('Manual Verification: %s', 'vaptsecure'), verifFeature.label),
        onRequestClose: () => setVerifFeature(null),
        style: { width: '700px', maxWidth: '98%' }
      }, (() => {
        const f = verifFeature;
        const schema = typeof f.generated_schema === 'string' ? JSON.parse(f.generated_schema) : (f.generated_schema || { controls: [] });

        // Extracted Manual Steps Only
        const protocol = f.test_method || '';
        const checklist = typeof f.verification_steps === 'string' ? JSON.parse(f.verification_steps) : (f.verification_steps || []);
        const guideItems = schema.controls ? schema.controls.filter(c => ['test_checklist', 'evidence_list'].includes(c.type)) : [];
        const support = schema.controls ? schema.controls.filter(c => ['risk_indicators', 'assurance_badges'].includes(c.type)) : [];

        const boxStyle = { padding: '15px', background: '#fff', borderRadius: '8px', border: '1px solid #e2e8f0', boxShadow: '0 1px 2px rgba(0,0,0,0.05)' };

        return el('div', { style: { display: 'flex', flexDirection: 'column', gap: '20px', padding: '10px' } }, [
          // Manual Protocol & Evidence
          (protocol || checklist.length > 0 || guideItems.length > 0) ? el('div', { style: { ...boxStyle, background: '#f8fafc' } }, [
            el('h4', { style: { margin: '0 0 15px 0', fontSize: '12px', fontWeight: 700, color: '#475569', textTransform: 'uppercase', letterSpacing: '0.05em' } }, __('Manual Verification Protocol', 'vaptsecure')),

            protocol && el('div', { style: { marginBottom: '20px' } }, [
              el('label', { style: { display: 'block', fontSize: '11px', fontWeight: 700, color: '#92400e', marginBottom: '8px', textTransform: 'uppercase' } }, __('Test Protocol')),
              el('ol', { style: { margin: 0, paddingLeft: '20px', fontSize: '12px', color: '#4b5563', lineHeight: '1.6' } },
                protocol.split('\n').filter(l => l.trim()).map((l, i) => el('li', { key: i, style: { marginBottom: '4px' } }, l.replace(/^\d+\.\s*/, '')))
              )
            ]),

            checklist.length > 0 && el('div', { style: { marginBottom: '20px' } }, [
              el('label', { style: { display: 'block', fontSize: '11px', fontWeight: 700, color: '#0369a1', marginBottom: '8px', textTransform: 'uppercase' } }, __('Evidence Checklist')),
              el('ol', { style: { margin: 0, padding: 0, listStyle: 'none' } },
                checklist.map((step, i) => el('li', { key: i, style: { fontSize: '12px', color: '#4b5563', display: 'flex', gap: '10px', alignItems: 'flex-start', marginBottom: '8px' } }, [
                  el('input', { type: 'checkbox', style: { margin: '3px 0 0 0', width: '14px', height: '14px' } }),
                  el('span', null, step)
                ]))
              )
            ]),

            guideItems.length > 0 && el(GeneratedInterface, {
              feature: { ...f, generated_schema: { ...schema, controls: guideItems } },
              onUpdate: (data) => updateFeature(f.key, buildImplementationUpdate(data)),
              isGuidePanel: true
            })
          ]) : el('div', { style: { padding: '20px', textAlign: 'center', color: '#9ca3af', fontStyle: 'italic' } }, __('No manual verification steps defined.', 'vaptsecure')),

          // Assurance Badges
          support.length > 0 && el('div', { style: { ...boxStyle, background: '#f0fdf4', border: '1px solid #bbf7d0' } }, [
            el('h4', { style: { margin: '0 0 12px 0', fontSize: '12px', fontWeight: 700, color: '#166534', textTransform: 'uppercase', letterSpacing: '0.05em' } }, __('Verification & Assurance')),
            el(GeneratedInterface, { feature: { ...f, generated_schema: { ...schema, controls: support } }, onUpdate: (data) => updateFeature(f.key, buildImplementationUpdate(data)) })
          ])
        ]);
      })())
    ]);
  };

  // Robust DOM-ready: handles 'loading', 'interactive', and 'complete' states
  const init = () => {
    const container = document.getElementById('vapt-workbench-root');
    if (container) render(el(ClientDashboard), container);
    else vaptLog.error('#vapt-workbench-root not found!');
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    // readyState is 'interactive' or 'complete' — DOM is already ready
    init();
  }
})();

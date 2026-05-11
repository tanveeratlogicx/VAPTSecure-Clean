// VAPTSecure Clean - A+ Adaptive Schema Generator v3.5.1
// Implementation of rules/vapt-client-multienv-v3.2.agrules

(function () {
  const APlusGenerator = {
    version: "3.5.1",

    /**
     * Generates a v3.2 A+ Adaptive Schema from feature context.
     * @param {object} feature The feature raw data.
     * @param {string} customInstruction Optional user-provided context.
     * @returns {object} Full A+ Adaptive Interface Schema.
     */
    generate: function (feature, customInstruction = '') {
      const timestamp = new Date().toISOString();
      const riskId = feature.id || feature.risk_id || 'vapt-risk-' + Math.random().toString(36).substr(2, 9);
      const title = feature.label || feature.title || feature.name || 'Untitled Protection';

      const includeProtocol = feature.include_manual_protocol !== false && feature.include_manual_protocol !== 0 && feature.include_manual_protocol !== '0';
      const includeNotes = feature.include_operational_notes !== false && feature.include_operational_notes !== 0 && feature.include_operational_notes !== '0';

      const schema = {
        metadata: {
          name: "VAPT Client-Ready Multi-Environment Generator",
          version: this.version,
          purpose: "Client deployment - any environment",
          target_grade: "A+",
          timestamp: timestamp,
          client_ready: true,
          universal_deployment: true,
          schema_grade: "A+",
          risk_id: riskId,
          title: title
        },
        global_config: {
          runtime_environment_detection: {
            version: this.version,
            execution_phase: "plugin_init",
            cache_duration_minutes: 60,
            detection_cascade: [
              { name: "server_software_header", priority: 1, method: "inspect_server_variable", variable: "SERVER_SOFTWARE", confidence: "high", timeout_ms: 100 },
              { name: "php_sapi_detection", priority: 2, method: "php_function", function: "php_sapi_name", confidence: "medium" },
              {
                name: "filesystem_probe", priority: 3, method: "file_exists", probes: {
                  apache: [".htaccess"],
                  nginx: ["/etc/nginx/nginx.conf"],
                  iis: ["web.config"]
                }, confidence: "high"
              }
            ],
            capability_matrix: {
              apache_with_htaccess: { capabilities: { rewrite_rules: true, header_injection: true, file_blocking: true, performance: "excellent" } },
              nginx_with_config: { capabilities: { rewrite_rules: true, header_injection: true, file_blocking: true, performance: "excellent" } },
              limited_php_only: { capabilities: { wordpress_hooks: true, runtime_blocking: true, file_blocking: false, performance: "good" } }
            }
          },
          runtime_platform_selection: {
            strategy: "maximize_protection_capability",
            decision_tree: [
              { if: "environment.apache_with_htaccess.available", then: { select: "apache_htaccess", reason: "Server-level blocking with .htaccess" } },
              { if: "environment.nginx_with_config.available", then: { select: "nginx_config", reason: "Server-level blocking with Nginx config" } },
              { else: { select: "php_functions", reason: "Application-level blocking via WordPress hooks" } }
            ]
          }
        },
        // 🛡️ Global Platform Matrix (Satisfies Orchestrator v4.0.0)
        platform_matrix: {
          apache_htaccess: { applicable: true, config_format: "apache_rewrite", rules: this.suggestApacheRules(feature), target: "root" },
          nginx_config: { applicable: true, config_format: "nginx_rewrite", rules: this.suggestNginxRules(feature) },
          php_functions: { applicable: true, universal: true, implementation: { type: "wordpress_plugin", method: "hook" } }
        },
        risk_interfaces: [
          {
            risk_id: riskId,
            title: title,
            category: feature.category || "General",
            severity: feature.severity || "Medium",
            protection_definition: {
              threat: { name: title, vector: feature.summary || feature.description || '', cwe: feature.owasp?.cwe || feature.cwe || '' },
              blocking_behavior: {
                trigger_condition: "detection_logic",
                action: "block_with_403",
                response_headers: { "X-VAPT-Protection": "active", "X-VAPT-Risk-ID": riskId }
              },
              implementations: {
                apache_htaccess: { applicable: true, config_format: "apache_rewrite", rules: this.suggestApacheRules(feature) },
                nginx_config: { applicable: true, config_format: "nginx_rewrite", rules: this.suggestNginxRules(feature) },
                php_functions: {
                  applicable: true,
                  universal: true,
                  implementation: {
                    type: "wordpress_plugin",
                    method: "hook",
                    hooks: [
                      { name: "init", priority: 1, action: "block_request" },
                      { name: "rest_api_init", priority: 10, action: "disable_endpoint" }
                    ]
                  }
                }
              }
            },
            client_verification: {
              tests: [
                { name: "Protection Active", description: "Verify protection is blocking threats", type: "test_action", key: "verify_protection" },
                { name: "Legitimate Access", description: "Verify normal website use still works", type: "http_probe", expect: "200 OK" }
              ]
            }
          }
        ],
        ...(includeProtocol ? {
          manual_protocol: {
            steps: Array.isArray(feature.verification_steps) && feature.verification_steps.length > 0
              ? feature.verification_steps
              : (feature.verification_steps
                ? [feature.verification_steps]
                : [
                  "To manually verify this protection:",
                  "1. Ensure the 'Enable Protection' toggle is active and deployed.",
                  "2. Follow the standard automated HTTP probe tests beneath the toggle.",
                  "3. Validate the protection: " + (feature.summary || feature.description || "Monitor server headers for the expected block."),
                  "4. If automated tests report a failure, check environment compatibility with the active enforcer."
                ]
              )
          }
        } : {}),
        ...(includeNotes ? {
          operational_notes: feature.operational_notes || (() => {
            const summaryText = feature.summary || feature.description || "This feature applies security enhancements.";
            const platformTargetList = [];

            if (feature.platform_implementations) {
              const activeEnforcer = feature.active_enforcer;
              const envProfile = window.vaptEnvironmentProfile;

              // Normalize enforcer name for flexible matching
              const normalizeEnforcerName = (name) => {
                if (!name) return '';
                const n = name.toLowerCase().replace(/[^a-z0-9]/g, '');
                // Map variations to canonical names
                if (n === 'htaccess' || n === 'apachehtaccess' || n === 'apache') return 'htaccess';
                if (n === 'nginx' || n === 'nginxconfig') return 'nginx';
                if (n === 'wpconfig' || n === 'wpconfigphp' || n === 'config') return 'wp_config';
                if (n === 'phpfunctions' || n === 'phpheaders' || n === 'hook' || n === 'wordpress' || n === 'wordpresscore' || n === 'wordpress-core') return 'php_functions';
                if (n === 'servercron' || n === 'server-cron' || n === 'phpcron' || n === 'php-cron') return 'php_cron';
                if (n === 'iis' || n === 'webconfig') return 'iis';
                if (n === 'cloudflare') return 'cloudflare';
                return n;
              };

              // Check if enforcer is compatible with environment capabilities
              const isEnforcerCompatible = (key, capabilities) => {
                if (!capabilities) return true; // No capabilities info, allow all
                const normalizedKey = normalizeEnforcerName(key);
                if (normalizedKey === 'htaccess' && capabilities.apache_with_htaccess) return true;
                if (normalizedKey === 'nginx' && capabilities.nginx_with_config) return true;
                if (normalizedKey === 'iis' && !capabilities.iis) return false;
                if (normalizedKey === 'cloudflare' && !capabilities.cloudflare) return false;
                // php_functions is universal (WordPress hooks)
                if (normalizedKey === 'php_functions') return true;
                return true; // Unknown enforcers allowed by default
              };

              for (const [key, details] of Object.entries(feature.platform_implementations)) {
                let isRelevant = true;

                if (activeEnforcer) {
                  // Active enforcer set: ONLY show that specific enforcer
                  const normalizedKey = normalizeEnforcerName(key);
                  const normalizedActive = normalizeEnforcerName(activeEnforcer);
                  isRelevant = (normalizedKey === normalizedActive);
                } else if (envProfile && envProfile.capabilities) {
                  // No active enforcer: filter by environment compatibility
                  isRelevant = isEnforcerCompatible(key, envProfile.capabilities);
                }

                if (isRelevant) {
                  if (details.target_file) {
                    platformTargetList.push(`${key} (targets ${details.target_file})`);
                  } else if (details.lib_key) {
                    platformTargetList.push(`${key} (via ${details.lib_key})`);
                  } else {
                    platformTargetList.push(key);
                  }
                }
              }
            }

            const targetedSystems = platformTargetList.length > 0
              ? `It modifies the following systems: ${platformTargetList.join(', ')}.`
              : "It leverages dynamic capabilities based on your runtime environment.";

            // Only show Implementation Details for superadmins (debugging purpose)
            const isSuperAdmin = window.vaptSecureSettings && window.vaptSecureSettings.isSuper;
            const implementationDetails = isSuperAdmin 
              ? `\n\nImplementation Details: ${targetedSystems}` 
              : '';

            return `${summaryText}${implementationDetails}`;
          })()
        } : {}),
        controls: [
          { type: 'header', id: `vapt-header-impl-${riskId}`, label: 'Implementation Control' },
          { type: 'toggle', id: `vapt-toggle-enable-${riskId}`, label: 'Enable Protection', key: 'feat_enabled', default: true },
          {
            type: 'html',
            id: `vapt-description-summary-${riskId}`,
            html: (() => {
              const baseDesc = feature.summary || feature.description || `protection against ${title} based on your primary environment configuration`;
              const cleanedDesc = baseDesc.replace(/\.$/, '') + '.'; // Ensure it ends with exactly one period

              let codePreview = '';
              if (feature.platform_implementations) {
                const implEntries = Object.entries(feature.platform_implementations);
                if (implEntries.length > 0) {
                  const [implTarget, implDetails] = implEntries[0];
                  if (implDetails && (implDetails.code || implDetails.code_ref)) {
                    let previewTarget = implDetails.target_file || implTarget;
                    let previewCode = implDetails.code || 'Code snippet reference is loading...';

                    // Technical preview now strictly shows the exact target and code.

                    codePreview = `
                      <div style="margin-top: 12px; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12px; overflow-x: auto;">
                        <div style="font-weight: 600; color: #334155; margin-bottom: 6px;">Injecting into: <span style="font-family: monospace; color: #0ea5e9;">${previewTarget}</span></div>
                        <pre style="margin: 0; padding: 0; color: #475569; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; white-space: pre-wrap;">${previewCode.replace(/</g, '&lt;').replace(/>/g, '&gt;')}</pre>
                      </div>
                    `;
                  }
                }
              }

              return `
                <div style="display: flex; flex-direction: column; gap: 8px;">
                  <div style="display: flex; alignItems: center; gap: 8px; color: #0ea5e9; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em;">
                    <span style="font-size: 14px;">🛡️</span> Security Insights
                  </div>
                  <div style="color: #475569; margin-top: 4px;">
                    <strong>Protection Applied:</strong> Activating this control mitigates the following security risk: <em>"${cleanedDesc}"</em>. This ensures that the system is properly hardened and enforces verified security protocols.
                  </div>
                </div>
              `;
            })()
          },
          { type: 'header', id: `vapt-header-verify-${riskId}`, label: 'Automated Verification' },
          ...this.suggestVerificationTests(feature, riskId)
        ],
        client_deployment: {
          profiles: {
            auto_detect: { name: "Automatic Environment Detection", strategy: "maximize_protection_capability", fallback: "php_functions" },
            maximum_protection: { name: "Maximum Protection (All Layers)", strategy: "defense_in_depth" },
            conservative: { name: "Conservative (Shared Hosting Safe)", allowed_platforms: ["php_functions", "apache_htaccess"] }
          },
          enforcement: (() => {
            const apacheRules = this.suggestApacheRules(feature);
            const isConfigBased = feature.key === 'RISK-001' || (feature.platform_implementations && (feature.platform_implementations['wp-config.php'] || feature.platform_implementations['wp_config']));

            if (apacheRules && apacheRules.length > 10 && !isConfigBased) {
              return {
                driver: "htaccess",
                target: "root",
                is_adaptive: true,
                mappings: { feat_enabled: apacheRules }
              };
            }

            // If it's RISK-001 or has wp-config implementation, use 'config' driver
            if (isConfigBased) {
              return {
                driver: "config",
                is_adaptive: true,
                mappings: { feat_enabled: feature.platform_implementations?.['wp-config.php']?.code || "define('DISABLE_WP_CRON', true);" }
              };
            }

            return {
              driver: "hook",
              is_adaptive: true,
              mappings: { feat_enabled: "/* Managed via PHP hooks */" }
            };
          })()
        },
        _instructions: customInstruction || "Generated via A+ Adaptive Workbench"
      };

      return schema;
    },

    suggestApacheRules: function (feature) {
      const title = feature.label || feature.title || 'Feature';

      // Extract the actual protection logic from multiple possible fields
      let ruleCode = '';

      // Priority 1: Check remediation field (legacy/direct)
      if (feature.remediation) {
        ruleCode = feature.remediation
          .replace(/#\s*BEGIN\s+VAPT.*?\n/gi, '')
          .replace(/#\s*END\s+VAPT.*?\n/gi, '')
          .replace(/^#.*$/gm, '')
          .trim();
      }

      // Priority 2: Check platform_implementations for .htaccess (interface_schema v2.0)
      if (!ruleCode && feature.platform_implementations) {
        const htaccessImpl = feature.platform_implementations['.htaccess'] ||
          feature.platform_implementations['htaccess'] ||
          feature.platform_implementations['apache_htaccess'];
        if (htaccessImpl && htaccessImpl.code) {
          ruleCode = htaccessImpl.code;
        }
      }

      // Priority 3: Check enforcement.mappings.feat_enabled (generated schema from workbench)
      if (!ruleCode && feature.enforcement && feature.enforcement.mappings) {
        const mappingCode = feature.enforcement.mappings.feat_enabled ||
          feature.enforcement.mappings.rules ||
          feature.enforcement.mappings.code;
        if (mappingCode && typeof mappingCode === 'string' && mappingCode.includes('RewriteRule')) {
          ruleCode = mappingCode;
        }
      }

      // If no valid rule code found after all checks, use placeholder
      if (!ruleCode || ruleCode.length < 10) {
        // Fallback: Try to construct from common patterns
        if (feature.key && feature.key.includes('RISK-003')) {
          ruleCode = '<IfModule mod_rewrite.c>\n    RewriteEngine On\n    RewriteBase /\n    RewriteCond %{REQUEST_URI} !/wp-json/wp/v2/users/me [NC]\n    RewriteRule ^wp-json/wp/v2/users - [F,L]\n</IfModule>';
        } else if (feature.key && (feature.key.includes('xmlrpc') || feature.key.includes('xml-rpc'))) {
          ruleCode = '<Files "xmlrpc.php">\n    Order Deny,Allow\n    Deny from all\n</Files>';
        }
      }

      // Final fallback
      if (!ruleCode || ruleCode.length < 10) {
        ruleCode = '';
      }

      return ruleCode;
    },

    suggestNginxRules: function (feature) {
      const title = feature.label || feature.title || 'Feature';
      const ruleCode = (feature.remediation && feature.remediation.includes('return 403'))
        ? feature.remediation
        : (feature.remediation || '');

      if (!ruleCode || ruleCode.length < 10) return '';

      return `# VAPT Nginx Protection: ${title}\nif ($vapt_whitelist = 1) { set $vapt_block 0; }\n${ruleCode.trim()}\nif ($vapt_block = 1) {\n    return 403;\n}`;
    },

    suggestVerificationTests: function (feature, riskId) {
      const inferProbePath = (featureData, featureKey = '') => {
        const parts = [
          featureKey,
          featureData && (featureData.label || featureData.title || featureData.name || ''),
          featureData && (featureData.summary || featureData.description || featureData.remediation || ''),
          featureData && Array.isArray(featureData.wp_paths) ? featureData.wp_paths.join(' ') : '',
          featureData && featureData.context && Array.isArray(featureData.context.wp_paths) ? featureData.context.wp_paths.join(' ') : '',
          featureData && Array.isArray(featureData.available_platforms) ? featureData.available_platforms.join(' ') : ''
        ];

        if (featureData && featureData.platform_implementations && typeof featureData.platform_implementations === 'object') {
          Object.values(featureData.platform_implementations).forEach(impl => {
            if (!impl || typeof impl !== 'object') return;
            if (impl.target_file) parts.push(impl.target_file);
            if (impl.path) parts.push(impl.path);
            if (impl.request_path) parts.push(impl.request_path);
            if (impl.operation) parts.push(impl.operation);
            if (impl.implementation_type) parts.push(impl.implementation_type);
          });
        }

        const text = parts.filter(Boolean).join(' ').toLowerCase();
        const has = (...terms) => terms.some(term => text.includes(term));

        if (text.includes('/wp-json/wp/v2/users') || has('rest api', 'endpoint disclosure', 'rest')) return '/wp-json/wp/v2/users';
        if (text.includes('/?author=1') || has('author query', 'author archives', 'author enumeration')) return '/?author=1';
        if (has('login', 'brute', 'password reset', 'lost password', 'auth')) return '/wp-login.php';
        if (has('cron')) return '/wp-cron.php?doing_wp_cron=1';
        if (has('xmlrpc', 'xml-rpc')) return '/xmlrpc.php';
        if (has('directory', 'indexing', 'uploads')) return '/wp-content/uploads/';
        if (has('wp-admin', 'admin')) return '/wp-admin/';

        return '/';
      };

      const normalizePlatformName = (value) => {
        let normalized = String(value || '')
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, '-')
          .replace(/^-+|-+$/g, '');

        if (normalized === 'apache' || normalized === 'apache-htaccess' || normalized === 'htaccess') return 'htaccess';
        if (normalized === 'wp-config' || normalized === 'wp-config-php' || normalized === 'wpconfig' || normalized === 'config') return 'wp-config';
        if (normalized === 'php-functions' || normalized === 'hook' || normalized === 'wordpress' || normalized === 'wordpresscore' || normalized === 'wordpress-core') return 'php-functions';
        if (normalized === 'server-cron' || normalized === 'servercron' || normalized === 'php-cron' || normalized === 'phpcron') return 'php-cron';
        if (normalized === 'web-config' || normalized === 'webconfig') return 'iis';
        return normalized;
      };

      const detectSurfaceFamily = (feature = {}, featureKey = '') => {
        const blob = [
          featureKey,
          feature?.risk_id || '',
          feature?.label || feature?.title || feature?.name || '',
          feature?.summary || feature?.description || feature?.remediation || '',
          feature?.platform_implementations ? JSON.stringify(feature.platform_implementations) : '',
          feature?.available_platforms ? JSON.stringify(feature.available_platforms) : ''
        ].filter(Boolean).join(' ').toLowerCase();

        if (blob.includes('/wp-json/wp/v2/users') || blob.includes('wordpress rest api') || blob.includes('rest api')) return 'rest_users';
        if (blob.includes('/?author=1') || blob.includes('author query') || blob.includes('author archives') || blob.includes('author enumeration')) return 'author_query';
        if (blob.includes('/wp-login.php') || blob.includes('login_errors') || blob.includes('invalid credentials')) return 'login_error';
        if (blob.includes('pingback') || blob.includes('xmlrpc') || blob.includes('xml-rpc')) return 'xmlrpc';
        if (blob.includes('cron') || blob.includes('wp-cron')) return 'cron';
        return '';
      };

      const collectPlatformHints = (featureData = {}) => {
        const hints = new Set();
        const add = (value) => {
          const normalized = normalizePlatformName(value);
          if (normalized) hints.add(normalized);
        };

        if (Array.isArray(featureData.available_platforms)) {
          featureData.available_platforms.forEach(add);
        }

        if (featureData.platform_implementations && typeof featureData.platform_implementations === 'object') {
          Object.entries(featureData.platform_implementations).forEach(([platformName, impl]) => {
            add(platformName);
            if (impl && typeof impl === 'object') {
              add(impl.lib_key);
              add(impl.target_file);
              add(impl.request_path);
              add(impl.implementation_type);
              add(impl.operation);
            }
          });
        }

        return hints;
      };

      const resolvePrimaryPlatform = (featureData = {}) => {
        const hints = collectPlatformHints(featureData);
        const priority = ['htaccess', 'apache', 'nginx', 'caddy', 'iis', 'cloudflare', 'php-headers', 'php-functions', 'php-cron', 'wp-config', 'wpconfig', 'server-cron', 'fail2ban'];
        for (const candidate of priority) {
          if (hints.has(normalizePlatformName(candidate))) {
            return candidate;
          }
        }
        return '';
      };

      const resolveExpectedEnforcer = (primaryPlatform, operation = '') => {
        const platform = normalizePlatformName(primaryPlatform);
        const op = normalizePlatformName(operation);

        if (platform === 'htaccess' || platform === 'apache') return 'htaccess';
        if (platform === 'nginx') return 'nginx';
        if (platform === 'caddy') return 'caddy';
        if (platform === 'iis') return 'iis';
        if (platform === 'cloudflare') return 'cloudflare';
        if (platform === 'fail2ban' || op.includes('jail')) return 'fail2ban';
        if (platform === 'wp-config' || platform === 'wpconfig' || op.includes('constant') || op.includes('config')) return 'wp-config';
        if (platform === 'php-functions' || platform === 'php-headers' || op.includes('hook') || op.includes('wordpress')) return 'php-headers';
        if (platform === 'php-cron' || platform === 'server-cron' || op.includes('cron')) return (platform === 'server-cron') ? 'server-cron' : 'php-cron';
        return platform || 'php-headers';
      };

      const resolvePrimaryImplementation = (featureData = {}, primaryPlatform = '') => {
        if (!featureData.platform_implementations || typeof featureData.platform_implementations !== 'object') {
          return null;
        }

        const normalizedTarget = normalizePlatformName(primaryPlatform);
        const entries = Object.entries(featureData.platform_implementations);
        for (const [platformName, impl] of entries) {
          const candidates = [platformName];
          if (impl && typeof impl === 'object') {
            candidates.push(impl.lib_key, impl.target_file, impl.request_path, impl.implementation_type, impl.operation);
          }
          if (candidates.some(candidate => normalizePlatformName(candidate) === normalizedTarget)) {
            return impl || null;
          }
        }

        return entries.length ? entries[0][1] : null;
      };

      const featureKey = feature.key || feature.id || '';
      const inferredPath = inferProbePath(feature, featureKey);
      const headerProbePath = `${inferredPath}?vapt_header_check=1`;
      const activeProbePath = inferredPath === '/' ? '/index.php' : inferredPath;
      const primaryPlatform = resolvePrimaryPlatform(feature);
      const primaryImplementation = resolvePrimaryImplementation(feature, primaryPlatform);
      const primaryOperation = String(
        primaryImplementation?.operation ||
        primaryImplementation?.implementation_type ||
        primaryImplementation?.target_file ||
        primaryPlatform ||
        ''
      ).toLowerCase();
      const expectedEnforcer = resolveExpectedEnforcer(primaryPlatform, primaryOperation);
      const isRateLimitFlow = primaryPlatform === 'fail2ban' || primaryOperation.includes('jail') || /rate limit|brute|login/i.test(featureKey + ' ' + (feature.label || feature.title || feature.name || '') + ' ' + (feature.summary || feature.description || ''));
      const isHeaderFlow = /header/.test(primaryOperation) || (['htaccess', 'apache', 'nginx', 'caddy', 'iis', 'cloudflare'].includes(normalizePlatformName(primaryPlatform)) && !/rewrite|block|respond|transform/.test(primaryOperation));
      const isConfigFlow = primaryPlatform === 'wp-config' || primaryPlatform === 'wpconfig' || /constant|config/.test(primaryOperation);
      const isRewriteFlow = /rewrite|block|respond|transform|url_rewrite|web_config|webconfig/.test(primaryOperation);
      const surfaceFamily = (() => {
        const blob = `${featureKey} ${(feature.label || feature.title || feature.name || '')} ${(feature.summary || feature.description || feature.remediation || '')} ${JSON.stringify(feature.platform_implementations || {})}`.toLowerCase();
        if (blob.includes('/wp-json/wp/v2/users') || blob.includes('rest api') || blob.includes('wordpress rest api')) return 'rest_users';
        if (blob.includes('/?author=1') || blob.includes('author query') || blob.includes('author archives') || blob.includes('author enumeration')) return 'author_query';
        if (blob.includes('/wp-login.php') || blob.includes('login_errors') || blob.includes('invalid credentials')) return 'login_error';
        if (blob.includes('pingback') || blob.includes('xmlrpc') || blob.includes('xml-rpc')) return 'xmlrpc';
        if (blob.includes('cron') || blob.includes('wp-cron')) return 'cron';
        return '';
      })();
      const tests = [];

      if (isRateLimitFlow) {
        tests.push({
          type: 'test_action',
          id: `vapt-test-rate-${riskId}`,
          label: 'Brute Force Resistance Check',
          key: 'verify_rate_resilience',
          test_logic: 'spam_requests',
          numTests: 5,
test_config: {
             enforcement_mode: 'external',
             path: inferredPath,
             expected_enforcer: expectedEnforcer
           },
          help: `Verifies that the login endpoint is rate limited or blocked by the active ${expectedEnforcer || 'platform'} policy for ${inferredPath}.`
        });
      } else if (isConfigFlow) {
        tests.push({
          type: 'test_action',
          id: `vapt-test-implementation-${riskId}`,
          label: 'A+ Adaptive Verification',
          key: 'verify_implementation',
          test_logic: 'verify_implementation',
          test_config: {
            expected_enforcer: expectedEnforcer
          },
          help: `Verifies that the A+ Adaptive implementation is present for ${inferredPath} using the client verification endpoint.`
        });
      } else if (isHeaderFlow) {
        tests.push({
          type: 'test_action',
          id: `vapt-test-headers-${riskId}`,
          label: 'A+ Header Verification',
          key: 'verify_aplus_headers',
          test_logic: 'check_headers',
          test_config: {
            path: headerProbePath,
            expected_enforcer: expectedEnforcer,
            expected_headers: {
              'x-vapt-enforced': expectedEnforcer
            }
          },
          help: `Verifies that the A+ Adaptive headers (x-vapt-enforced) are correctly injected by the active enforcer.`
        });
      } else if (isRewriteFlow) {
        tests.push({
          type: 'test_action',
          id: `vapt-test-active-${riskId}`,
          label: 'Platform Protection Probe',
          key: 'verify_active_protection',
          test_logic: 'universal_probe',
          test_config: {
            path: activeProbePath,
            params: { vapt_test: 'active' },
            expected_status: [403, 404, 400, 401, 405, 429],
            expected_enforcer: expectedEnforcer
          },
          help: `Runs a platform-specific request probe to verify the released protection for ${inferredPath}.`
        });
      } else {
        tests.push({
          type: 'test_action',
          id: `vapt-test-implementation-${riskId}`,
          label: 'A+ Adaptive Verification',
          key: 'verify_implementation',
          test_logic: 'verify_implementation',
          test_config: {
            expected_enforcer: expectedEnforcer
          },
          help: `Verifies that the A+ Adaptive implementation is present for ${inferredPath} using the client verification endpoint.`
        });
      }

      // 2. Specific Functional Probes
      const title = (feature.label || feature.title || feature.name || '').toLowerCase();
      const platformHintsForFunctional = collectPlatformHints(feature);
      if (surfaceFamily === 'login_error') {
        tests.push({
          type: 'test_action',
          id: `vapt-test-login-error-${riskId}`,
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
            expected_enforcer: expectedEnforcer
          },
          help: 'Verifies wp-login.php returns a generic login error message.'
        });
      } else if (surfaceFamily === 'rest_users') {
        tests.push({
          type: 'test_action',
          id: `vapt-test-rest-${riskId}`,
          label: 'REST API Protection Check',
          key: 'verify_rest_lockdown',
          test_logic: 'universal_probe',
          test_config: {
            path: '/wp-json/wp/v2/users',
            expected_status: [401, 403, 404],
            expected_enforcer: expectedEnforcer
          },
          help: 'Verifies that the WordPress Users REST endpoint is protected.'
        });
      } else if (surfaceFamily === 'author_query') {
        tests.push({
          type: 'test_action',
          id: `vapt-test-author-${riskId}`,
          label: 'Author Enumeration Check',
          key: 'verify_author_protection',
          test_logic: 'universal_probe',
          test_config: {
            path: '/?author=1',
            expected_status: [403, 404],
            expected_enforcer: expectedEnforcer
          },
          help: 'Verifies that author enumeration via query string is blocked.'
        });
      } else if (featureKey.includes('xmlrpc') || title.includes('xmlrpc') || title.includes('xml-rpc')) {
        tests.push({
          type: 'test_action',
          id: `vapt-test-xmlrpc-${riskId}`,
          label: 'XML-RPC Lockdown Check',
          key: 'verify_xmlrpc_block',
          test_logic: 'block_xmlrpc',
          test_config: {
            expected_enforcer: expectedEnforcer
          },
          help: 'Triggers a POST request to xmlrpc.php to verify the block.'
        });
      } else if (featureKey.includes('directory') || featureKey.includes('indexing') || title.includes('directory') || title.includes('indexing')) {
        tests.push({
          type: 'test_action',
          id: `vapt-test-dir-${riskId}`,
          label: 'Directory Indexing Check',
          key: 'verify_dir_block',
          test_logic: 'disable_directory_browsing',
          test_config: {
            expected_enforcer: expectedEnforcer
          },
          help: 'Attempts to list the /wp-content/uploads/ directory.'
        });
      } else {
        if (primaryPlatform === 'fail2ban') {
          tests.push({
            type: 'test_action',
            id: `vapt-test-active-${riskId}`,
            label: 'Active Protection Probe',
            key: 'verify_active_protection',
            test_logic: 'spam_requests',
            numTests: 5,
            test_config: {
              enforcement_mode: 'external',
              path: '/wp-login.php',
              expected_enforcer: expectedEnforcer
            },
            help: 'Runs a brute-force probe to verify the fail2ban-backed login protection.'
          });
        } else if (isHeaderFlow) {
          tests.push({
            type: 'test_action',
            id: `vapt-test-active-${riskId}`,
            label: 'Active Protection Probe',
            key: 'verify_active_protection',
            test_logic: 'universal_probe',
            test_config: {
              path: surfaceFamily === 'rest_users'
                ? '/wp-json/wp/v2/users'
                : (surfaceFamily === 'author_query'
                  ? '/?author=1'
                  : activeProbePath),
              params: { vapt_test: 'active' },
              expected_headers: { 'x-vapt-enforced': expectedEnforcer },
              expected_enforcer: expectedEnforcer
            },
            help: 'Runs a platform-specific probe to verify the active header enforcement.'
          });
        } else {
          tests.push({
            type: 'test_action',
            id: `vapt-test-active-${riskId}`,
            label: 'Implementation Verification',
            key: 'verify_implementation',
            test_logic: 'verify_implementation',
            test_config: {
              expected_enforcer: expectedEnforcer
            },
            help: 'Runs the client verification endpoint to confirm the released implementation.'
          });
        }
      }

      // 3. Site Integrity Check
      tests.push({
        type: 'test_action',
        id: `vapt-test-integrity-${riskId}`,
        label: 'Site Integrity Check',
        key: 'verify_integrity',
        test_logic: 'default',
        help: 'Ensures the website remains accessible after protection is applied.'
      });

      return tests;
    }
  };

  window.VAPTSECURE_APlusGenerator = APlusGenerator;
})();

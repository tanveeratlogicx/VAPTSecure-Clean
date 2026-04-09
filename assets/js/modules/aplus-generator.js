// VAPT Secure - A+ Adaptive Schema Generator v3.3.0
// Implementation of rules/vapt-client-multienv-v3.2.agrules

(function () {
  const APlusGenerator = {
    version: "3.3.0",

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
                if (n === 'phpfunctions' || n === 'phpheaders' || n === 'hook' || n === 'wordpress') return 'php_functions';
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
      const tests = [];
      const featureKey = feature.key || feature.id || '';

      // 1. A+ Header Check - Verify VAPT enforcement headers
      // [FIX v2.4.25] Only check x-vapt-enforced - no enforcer emits x-vapt-risk-id
      tests.push({
        type: 'test_action',
        id: `vapt-test-headers-${riskId}`,
        label: 'A+ Header Verification',
        key: 'verify_aplus_headers',
        test_logic: 'check_headers',
        test_config: {
          path: '/?vapt_header_check=1',
          expected_headers: {
            'x-vapt-enforced': 'htaccess|nginx|php-headers'
          }
        },
        help: 'Verifies that A+ Adaptive headers (x-vapt-enforced) are correctly injected by the active enforcer.'
      });

      // 2. Specific Functional Probes
      const title = (feature.label || feature.title || feature.name || '').toLowerCase();

      if (featureKey.includes('user-enumeration') || featureKey.includes('users') || title.includes('user enumeration') || title.includes('users') || title.includes('username enumeration')) {
        tests.push({
          type: 'test_action',
          id: `vapt-test-rest-${riskId}`,
          label: 'REST API Protection Check',
          key: 'verify_rest_lockdown',
          test_logic: 'universal_probe',
          test_config: {
            path: '/wp-json/wp/v2/users',
            expected_status: [401, 403, 404]
          },
          help: 'Verifies that the WordPress Users REST endpoint is protected.'
        });

        tests.push({
          type: 'test_action',
          id: `vapt-test-author-${riskId}`,
          label: 'Author Enumeration Check',
          key: 'verify_author_protection',
          test_logic: 'universal_probe',
          test_config: {
            path: '/?author=1',
            expected_status: [403, 404]
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
          help: 'Triggers a POST request to xmlrpc.php to verify the block.'
        });
      } else if (featureKey.includes('directory') || featureKey.includes('indexing') || title.includes('directory') || title.includes('indexing')) {
        tests.push({
          type: 'test_action',
          id: `vapt-test-dir-${riskId}`,
          label: 'Directory Indexing Check',
          key: 'verify_dir_block',
          test_logic: 'disable_directory_browsing',
          help: 'Attempts to list the /wp-content/uploads/ directory.'
        });
      } else {
        // Generic active probe
        tests.push({
          type: 'test_action',
          id: `vapt-test-active-${riskId}`,
          label: 'Active Protection Probe',
          key: 'verify_active_protection',
          test_logic: 'universal_probe',
          test_config: {
            path: '/index.php',
            params: { vapt_test: 'active' },
            expected_headers: { 'x-vapt-enforced': 'htaccess|nginx|php-headers' }
          },
          help: 'Runs a generic probe to verify server-level enforcement.'
        });
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

(function () {
  if (typeof wp === 'undefined' || !wp.element || !wp.components || !wp.i18n || !wp.apiFetch) return;

  const vaptLog = window.vaptLog || {
    log: () => { },
    warn: () => { },
    error: (...args) => console.error('[VAPT]', ...args),
    debug: () => { },
    info: () => { }
  };

  const { useState, useEffect, Fragment, createElement: el } = wp.element;
  const { __, sprintf } = wp.i18n || {};
  const components = wp.components || {};

  const {
    Button,
    Icon,
    Modal,
    Spinner,
    ToggleControl
  } = {
    Button: components.Button,
    Icon: components.Icon,
    Modal: components.Modal,
    Spinner: components.Spinner,
    ToggleControl: components.ToggleControl,
  };

  const apiFetch = wp.apiFetch;
  const settings = window.vaptSecureSettings || {};

  const VAPTSECURE_AlertModal = window.VAPTSECURE_AlertModal;
  const VAPTSECURE_ConfirmModal = window.VAPTSECURE_ConfirmModal;

  if (!useState || !useEffect || !el || !__ || !sprintf || !apiFetch || !Button || !Icon || !Modal || !Spinner || !ToggleControl) return;
  if (!VAPTSECURE_AlertModal || !VAPTSECURE_ConfirmModal) return;

  const getMappedContent = window.VAPTSECURE_getMappedContent || ((obj, _mappingKey, fallbackKey) => obj ? obj[fallbackKey] : undefined);

  const HistoryModal = ({ feature, updateFeature, onClose }) => {
    const [history, setHistory] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
      apiFetch({ path: `vaptsecure/v1/features/${feature.key || feature.id}/history` })
        .then(res => {
          setHistory(res);
          setLoading(false);
        })
        .catch(() => setLoading(false));
    }, [feature.key || feature.id]);

    const [confirmState, setConfirmState] = useState(null);

    const resetHistory = () => {
      setConfirmState({
        message: sprintf(__('Are you sure you want to reset history for "%s"?\n\nThis will:\n1. Clear all history records.\n2. Reset status to "Draft".', 'vaptsecure'), feature.label),
        isDestructive: true,
        onConfirm: () => {
          setConfirmState(null);
          setLoading(true);
          updateFeature(feature.key || feature.id, {
            status: 'Draft',
            reset_history: true,
            has_history: false,
            history_note: 'History Reset by User',
            generated_schema: null,
            implementation_data: null,
            wireframe_url: '',
            include_verification_engine: 0,
            include_verification_guidance: 0
          }).then(() => {
            setLoading(false);
            onClose();
          });
        }
      });
    };

    return el(Modal, {
      id: 'vapt-history-modal',
      title: sprintf(__('History: %s', 'vaptsecure'), feature.name || feature.label),
      onRequestClose: onClose,
      className: 'vapt-history-modal'
    }, [
      el('div', { id: 'vapt-history-modal-actions', className: 'vapt-flex-between', style: { marginBottom: '10px' } }, [
        el('div', null),
        el(Button, {
          id: 'vapt-btn-reset-history',
          isDestructive: true,
          isSmall: true,
          icon: 'trash',
          onClick: resetHistory,
          disabled: loading || history.length === 0
        }, __('Reset History & Status', 'vaptsecure'))
      ]),
      loading ? el(Spinner) : el('div', { id: 'vapt-history-modal-table-wrap' }, [
        history.length === 0 ? el('p', null, __('No history recorded yet.', 'vaptsecure')) :
          el('table', { className: 'wp-list-table widefat fixed striped' }, [
            el('thead', null, el('tr', null, [
              el('th', { style: { width: '120px' } }, __('Date', 'vaptsecure')),
              el('th', { style: { width: '100px' } }, __('From', 'vaptsecure')),
              el('th', { style: { width: '100px' } }, __('To', 'vaptsecure')),
              el('th', { style: { width: '120px' } }, __('User', 'vaptsecure')),
              el('th', null, __('Note', 'vaptsecure')),
            ])),
            el('tbody', null, history.map((h, i) => el('tr', { key: i }, [
              el('td', null, new Date(h.created_at).toLocaleString()),
              el('td', null, el('span', { className: `vapt-status-badge status-${h.old_status}` }, h.old_status)),
              el('td', null, el('span', { className: `vapt-status-badge status-${h.new_status}` }, h.new_status)),
              el('td', null, h.user_name || __('System', 'vaptsecure')),
              el('td', null, h.note || '-')
            ])))
          ])
      ]),
      el('div', { style: { marginTop: '20px', textAlign: 'right' } }, [
        el(Button, { isPrimary: true, onClick: onClose }, __('Close', 'vaptsecure'))
      ]),
      confirmState && el(VAPTSECURE_ConfirmModal, {
        isOpen: true,
        message: confirmState.message,
        isDestructive: confirmState.isDestructive,
        onConfirm: confirmState.onConfirm,
        onCancel: () => setConfirmState(null)
      })
    ]);
  };

  const DesignModal = ({ feature, onClose, updateFeature, designPromptConfig, selectedFile, fieldMapping, rootAiInstructions, rootGlobalSettings }) => {
    const MEANINGFUL_DEFAULT = {
      controls: [
        { type: 'header', label: 'Feature Configuration' },
        { type: 'toggle', label: 'Enable Feature', key: 'feat_enabled', default: true }
      ],
      enforcement: { driver: 'hook', mappings: { feat_enabled: 'your_backend_hook_here' } },
      _instructions: 'Paste the AI-generated JSON here to replace this default.'
    };

    const getInitialSchema = () => {
      if (!feature.generated_schema) return MEANINGFUL_DEFAULT;
      if (typeof feature.generated_schema === 'string') {
        try {
          const parsed = JSON.parse(feature.generated_schema);
          if (!parsed || (Array.isArray(parsed) && parsed.length === 0) || (typeof parsed === 'object' && Object.keys(parsed).length === 0)) {
            return MEANINGFUL_DEFAULT;
          }
          if (typeof parsed === 'string') {
            const doubleParsed = JSON.parse(parsed);
            if (!doubleParsed || (Array.isArray(doubleParsed) && doubleParsed.length === 0)) return MEANINGFUL_DEFAULT;
            return doubleParsed;
          }
          return parsed;
        } catch (e) {
          return MEANINGFUL_DEFAULT;
        }
      }
      if (Array.isArray(feature.generated_schema) && feature.generated_schema.length === 0) return MEANINGFUL_DEFAULT;
      if (typeof feature.generated_schema === 'object' && Object.keys(feature.generated_schema).length === 0) return MEANINGFUL_DEFAULT;
      return feature.generated_schema;
    };

    const initialParsed = getInitialSchema();
    const defaultValue = JSON.stringify(initialParsed, null, 2);

    const [schemaText, setSchemaText] = useState(defaultValue);
    const [parsedSchema, setParsedSchema] = useState(initialParsed);
    const [localImplData, setLocalImplData] = useState(
      feature.implementation_data ? (typeof feature.implementation_data === 'string' ? JSON.parse(feature.implementation_data) : feature.implementation_data) : {}
    );
    const [customizationText, setCustomizationText] = useState(feature.dev_instruct || '');
    const [isSaving, setIsSaving] = useState(false);
    const [saveStatus, setSaveStatus] = useState(null);

    const [includeProtocol, setIncludeProtocol] = useState((feature.include_manual_protocol === undefined || feature.include_manual_protocol === null) ? true : feature.include_manual_protocol == 1);
    const [includeNotes, setIncludeNotes] = useState((feature.include_operational_notes === undefined || feature.include_operational_notes === null) ? true : feature.include_operational_notes == 1);

    const [isMultiEnv, setIsMultiEnv] = useState(false);
    const [isAdaptiveDeployment, setIsAdaptiveDeployment] = useState(feature.is_adaptive_deployment == 1);

    const [isHoveringSchema, setIsHoveringSchema] = useState(false);

    useEffect(() => {
      const handleGlobalPaste = (e) => {
        if (isHoveringSchema) {
          e.preventDefault();
          const text = (e.clipboardData || window.clipboardData).getData('text');
          if (text) {
            onJsonChange(text);
            setSaveStatus({ message: __('Content Replaced from Clipboard!', 'vaptsecure'), type: 'success' });
            setTimeout(() => setSaveStatus(null), 2000);
          }
        }
      };
      window.addEventListener('paste', handleGlobalPaste);
      return () => window.removeEventListener('paste', handleGlobalPaste);
    }, [isHoveringSchema]);

    useEffect(() => {
      const originalOverflow = document.body.style.overflow;
      document.body.style.overflow = 'hidden';
      return () => {
        document.body.style.overflow = originalOverflow;
      };
    }, []);

    const [alertState, setAlertState] = useState(null);
    const [confirmState, setConfirmState] = useState(null);
    const [isRemoveConfirmOpen, setIsRemoveConfirmOpen] = useState(false);

    const onJsonChange = (val) => {
      setSchemaText(val);
      try {
        const parsed = JSON.parse(val);
        if (parsed && parsed.controls) setParsedSchema(parsed);
      } catch (e) { }
    };

    const handleSave = () => {
      try {
        let cleanText = schemaText.trim();
        if (cleanText.startsWith('```')) {
          cleanText = cleanText.replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/, '');
        }
        cleanText = cleanText.replace(/\u00A0/g, ' ');
        cleanText = cleanText.replace(/[\u200B\u200C\u200D\uFEFF]/g, '');

        const parsed = JSON.parse(cleanText);
        const controls = Array.isArray(parsed.controls) ? parsed.controls : [];
        const hasTestActions = controls.some(c => c.type === 'test_action');

        setIsSaving(true);
        const payload = {
          generated_schema: JSON.stringify(parsed),
          implementation_data: JSON.stringify(localImplData),
          is_enforced: 1,
          is_adaptive_deployment: isAdaptiveDeployment ? 1 : 0,
          include_verification_engine: hasTestActions ? 1 : 0,
          include_verification_guidance: 1,
          include_manual_protocol: includeProtocol ? 1 : 0,
          include_operational_notes: includeNotes ? 1 : 0,
        };

        if (feature.normalized_status === 'draft') {
          payload.status = 'Develop';
        }

        updateFeature(feature.key || feature.id, {
          ...payload,
          dev_instruct: customizationText
        })
          .then(() => {
            setIsSaving(false);
            onClose();
          })
          .catch(() => setIsSaving(false));
      } catch (e) {
        vaptLog.error('Design Save Error:', e);
        if (e instanceof SyntaxError) {
          setAlertState({ message: sprintf(__('Invalid JSON format: %s. Check for hidden characters or syntax errors.', 'vaptsecure'), e.message) });
        } else {
          setAlertState({ message: sprintf(__('Execution Error: %s. Please report this to support.', 'vaptsecure'), e.message) });
        }
      }
    };

    const handleRemoveConfirm = () => {
      setIsSaving(true);
      updateFeature(feature.key || feature.id, {
        status: 'Draft',
        generated_schema: null,
        implementation_data: null,
        is_enforced: 0,
        include_verification_engine: 0,
        include_verification_guidance: 0,
        reset_history: true,
        has_history: false
      })
        .then(() => {
          setIsSaving(false);
          setIsRemoveConfirmOpen(false);
          onClose();
        })
        .catch(() => {
          setIsSaving(false);
          setIsRemoveConfirmOpen(false);
          setAlertState({ message: __('Failed to remove implementation.', 'vaptsecure') });
        });
    };

    const copyContext = () => {
      let contextJson = `
{
  "site_context": {
    "home_url": "${settings.homeUrl || ''}",
    "plugin_name": "${settings.pluginName || 'VAPT Secure'}",
    "environment": "production",
    "mandate": "All URLs generated in the final JSON schema MUST be absolute URLs, using the provided home_url as the base."
  },
  "feature_blueprint": {
    "id": "feature_id",
    "title": "feature_title",
    "description": "feature_description",
    "severity": "feature_severity",
    "category": "feature_category",
    "compliance_references": "feature_owasp",
    "cwe_reference": "feature_cwe",
    "remediation_strategy": "feature_remediation",
    "evidence_requirements": "feature_evidence_requirements",
    "verification_steps": "feature_verification_steps",
    "test_method": "feature_test_method",
    "ui_components": {
      "primary_card": "automation_prompts.ai_ui",
      "test_checklist": "tests",
      "risk_indicators": "risks",
      "assurance_badges": "assurance",
      "evidence_list": "evidence"
    },
    "interface_layout": {
      "grid_structure": "Two-Column (Controls Left, Status Right)",
      "functional_blocks": [
        "Implementation Notes (Contextual Textarea)",
        "Manual Verification (Full-Width Protocol & Evidence Checklist)",
        "Automated Verification (Trigger Actions & Live Status)"
      ],
      "styling": "Standardized cards with subtle shadows and clear hierarchy."
    },
    "automation_context": {
      "ai_check_prompt": "automation_prompts.ai_check",
      "ai_schema_fields": "automation_prompts.ai_schema",
      "ai_agent_instructions": "ai_agent_instructions",
      "global_settings": "global_settings"
    },
    "risk_properties": {
      "cvss_score": "cvss_score",
      "cvss_vector": "cvss_vector",
      "affected_components": "affected_components",
      "performance_impact": "performance_impact"
    },
    "protection_details": "protection_details",
    "testing_specs": "testing_specs",
    "verification_engine": "verification_engine",
    "relationships": "relationships",
    "reporting": "reporting",
    "references": "references",
    "implementation_strategy": {
      "execution_driver": "Prioritize: prioritizedDriver",
      "enforcement_mechanism": "Intelligent automated selection based on active datasource.",
      "decision_matrix": {
        "driver: htaccess": "Use for physical files, server-wide blocking, or headers. Requires 'target': 'root'.",
        "driver: wp-config": "Use for wp-config.php constants (defines).",
        "driver: hook": "Use for dynamic PHP logic, headers, request interceptions (wp_head, init).",
        "driver: manual": "Use for directives that require manual server configuration (e.g. Nginx, System Services)."
      },
      "available_methods": [
        "block_xmlrpc",
        "add_security_headers",
        "hide_wp_version",
        "block_user_enumeration",
        "disable_file_editors",
        "block_debug_exposure",
        "limit_login_attempts",
        "block_wp_cron",
        "block_rest_api"
      ],
      "data_binding": "Controls must use 'key' to bind to enforcer logic."
    },
    "verification_protocol": {
      "automated_verification": "Interactive test actions (universal_probe) for real-time proof"
    },
    "ui_blueprint": "ui_configuration",
    "implementation_logic": {
      "automated_steps": "automated_steps",
      "manual_steps": "manual_steps"
    },
    "raw_feature_context": "raw_json",
    "previous_implementation": "previous_schema"
  }
}
`;

      let prioritizedDriver = 'hook';

      const selection = feature.active_enforcer;
      const targets = feature.protection?.automated_protection?.implementation_targets || feature.available_platforms || [];

      if (selection) {
        const selLower = selection.toLowerCase();
        if (selLower.includes('htaccess') || selLower === 'apache' || selLower === 'litespeed') prioritizedDriver = 'htaccess';
        else if (selLower.includes('functions') || selLower.includes('hook') || selLower === 'wordpress' || selLower === 'php') prioritizedDriver = 'hook';
        else if (selLower.includes('wp-config')) prioritizedDriver = 'wp-config';
        else if (selLower === 'fail2ban') prioritizedDriver = 'fail2ban';
        else if (selLower === 'nginx') prioritizedDriver = 'nginx';
        else if (selLower === 'cloudflare') prioritizedDriver = 'cloudflare';
        else if (selLower === 'iis') prioritizedDriver = 'iis';
        else if (selLower === 'caddy') prioritizedDriver = 'caddy';
      } else if (Array.isArray(targets) && targets.length > 0) {
        if (targets.includes('.htaccess')) prioritizedDriver = 'htaccess';
        else if (targets.includes('PHP Hook') || targets.includes('WordPress') || targets.includes('PHP Functions') || targets.includes('WordPress Core')) prioritizedDriver = 'hook';
        else if (targets.includes('wp-config.php')) prioritizedDriver = 'wp-config';
        else if (targets.includes('fail2ban')) prioritizedDriver = 'fail2ban';
        else if (targets.includes('Nginx')) prioritizedDriver = 'nginx';
        else if (targets.includes('Cloudflare')) prioritizedDriver = 'cloudflare';
        else if (targets.includes('IIS')) prioritizedDriver = 'iis';
        else if (targets.includes('Caddy')) prioritizedDriver = 'caddy';
        else if (targets.includes('Litespeed')) prioritizedDriver = 'htaccess';
      } else {
        const dsLower = (selectedFile || '').toLowerCase();
        if (dsLower.includes('htaccess')) prioritizedDriver = 'htaccess';
        else if (dsLower.includes('hook') || dsLower.includes('php')) prioritizedDriver = 'hook';
        else if (dsLower.includes('wp-config')) prioritizedDriver = 'wp-config';
        else if (dsLower.includes('nginx')) prioritizedDriver = 'nginx';
        else if (dsLower.includes('fail2ban')) prioritizedDriver = 'fail2ban';
      }

      if (designPromptConfig) {
        contextJson = typeof designPromptConfig === 'string'
          ? designPromptConfig
          : JSON.stringify(designPromptConfig, null, 2);
      } else {
        const defaultTemplate = {
          design_prompt: {
            interface_version: isMultiEnv ? '3.2.0' : '2.0',
            schema_grade: isMultiEnv ? 'A+' : 'Standard',
            interface_type: 'Interactive VAPT Functional Workbench',
            schema_definition: isMultiEnv ? 'VAPT A+ Client-Ready Multi-Environment Interface Schema v3.2' : 'WordPress VAPT schema with standardized control fields',
            id: '{{id}}',
            title: '{{title}}',
            description: '{{description}}',
            severity: '{{severity}}',
            category: '{{category}}',
            compliance_references: '{{owasp}}',
            cwe_reference: '{{cwe}}',
            remediation_strategy: '{{remediation}}',
            evidence_requirements: '{{evidence_requirements}}',
            verification_steps: '{{verification_steps}}',
            test_method: '{{test_method}}',
            visual_indicator: 'shield',
            ui_components: {
              primary_card: '{{automation_prompts.ai_ui}}',
              test_checklist: '{{tests}}',
              risk_indicators: '{{risks}}',
              assurance_badges: '{{assurance}}',
              evidence_list: '{{evidence}}'
            },
            interface_layout: {
              grid_structure: 'Two-Column (Controls Left, Status Right)',
              functional_blocks: [
                'Implementation Notes (Contextual Textarea)',
                'Manual Verification (Full-Width Protocol & Evidence Checklist)',
                'Automated Verification (Trigger Actions & Live Status)'
              ],
              styling: 'Standardized cards with subtle shadows and clear hierarchy.'
            },
            automation_context: {
              ai_check_prompt: '{{automation_prompts.ai_check}}',
              ai_schema_fields: '{{automation_prompts.ai_schema}}',
              ai_agent_instructions: '{{ai_agent_instructions}}',
              global_settings: '{{global_settings}}',
              telemetry: { log_events: true, audit_trail: true }
            },
            risk_properties: {
              cvss_score: '{{cvss_score}}',
              cvss_vector: '{{cvss_vector}}',
              affected_components: '{{affected_components}}',
              performance_impact: '{{performance_impact}}'
            },
            protection_details: '{{protection_details}}',
            testing_specs: '{{testing_specs}}',
            verification_engine: '{{verification_engine}}',
            relationships: '{{relationships}}',
            reporting: '{{reporting}}',
            references: '{{references}}',
            multi_environment: isMultiEnv ? {
              mode: 'runtime_detection',
              supported_platforms: ['apache_htaccess', 'nginx_config', 'iis_config', 'caddy_config', 'cloudflare_edge', 'php_functions'],
              fallback_strategy: 'cascade',
              runtime_selection: 'maximize_protection_capability'
            } : null
          }
        };
        contextJson = JSON.stringify(defaultTemplate, null, 2);
      }

      let displayInstruct = feature.dev_instruct || feature.devInstruct || feature.ai_agent_instructions || '';
      if (!displayInstruct && feature.generated_schema) {
        try {
          const schema = typeof feature.generated_schema === 'string' ? JSON.parse(feature.generated_schema) : feature.generated_schema;
          if (schema && schema.instruction) displayInstruct = schema.instruction;
        } catch (e) { }
      }
      if (!displayInstruct) displayInstruct = 'No specific guidelines provided.';

      let referenceCode = '';
      if (feature.code_examples && Array.isArray(feature.code_examples)) {
        referenceCode = feature.code_examples.map(ex => {
          return `Language: ${ex.language || 'PHP'}\nDescription: ${ex.description || 'Implementation Logic'}\nCode:\n${ex.code}`;
        }).join('\n\n');
      }

      const replaceAll = (str, key, val) => {
        const value = Array.isArray(val) ? val.join(', ') : (val || '');
        return str.split(`{{${key}}}`).join(value).split(`{${key}}`).join(value);
      };

      const rawDesc = getMappedContent(feature, 'description', 'description', fieldMapping);
      const rawSev = getMappedContent(feature, 'severity', 'severity', fieldMapping);
      const rawMethod = getMappedContent(feature, 'test_method', 'test_method', fieldMapping);
      const rawVerif = getMappedContent(feature, 'verification_steps', 'verification_steps', fieldMapping);
      const rawOwasp = getMappedContent(feature, 'owasp', 'owasp', fieldMapping) || getMappedContent(feature, 'compliance', 'owasp_mapping', fieldMapping) || feature.owasp || '';
      const rawRemediation = getMappedContent(feature, 'remediation', 'remediation', fieldMapping);
      const rawScenario = getMappedContent(feature, 'attack_scenario', 'attack_scenario', fieldMapping);
      const rawCvssScore = getMappedContent(feature, 'cvss_score', 'cvss_score', fieldMapping) || feature.cvss_score || '';
      const rawPlatforms = getMappedContent(feature, 'available_platforms', 'available_platforms', fieldMapping) || feature.available_platforms || [];
      const rawPlatformImpl = getMappedContent(feature, 'platform_implementations', 'platform_implementations', fieldMapping) || feature.platform_implementations || {};
      const rawUiLayout = getMappedContent(feature, 'ui_layout', 'ui_layout', fieldMapping) || feature.ui_layout || {};
      const rawComponents = getMappedContent(feature, 'components', 'components', fieldMapping) || feature.components || [];
      const rawActions = getMappedContent(feature, 'actions', 'actions', fieldMapping) || feature.actions || [];

      const formatValue = (val) => {
        if (Array.isArray(val)) return val.join('\n');
        if (typeof val === 'object' && val !== null) return JSON.stringify(val, null, 2);
        return val || '';
      };

      contextJson = replaceAll(contextJson, 'id', feature.id || 'N/A');
      contextJson = replaceAll(contextJson, 'title', feature.name || feature.label || feature.title || '');
      contextJson = replaceAll(contextJson, 'category', feature.category || 'General');
      contextJson = replaceAll(contextJson, 'description', formatValue(rawDesc) || 'None provided');
      contextJson = replaceAll(contextJson, 'severity', (typeof rawSev === 'object' ? rawSev.level : rawSev) || 'Medium');
      contextJson = replaceAll(contextJson, 'remediation', formatValue(rawRemediation));
      contextJson = replaceAll(contextJson, 'owasp', formatValue(rawOwasp));
      contextJson = replaceAll(contextJson, 'cwe', feature.cwe || '');
      contextJson = replaceAll(contextJson, 'risks', Array.isArray(feature.risks) ? feature.risks.join(', ') : (feature.risks || ''));
      contextJson = replaceAll(contextJson, 'verification_steps', formatValue(rawVerif));

      const testingSpecs = {
        payloads: feature.testing?.test_payloads || [],
        tools: feature.testing?.tools_required || []
      };
      contextJson = replaceAll(contextJson, 'testing_specs', JSON.stringify(testingSpecs, null, 2));

      const aiInstructions = { ...rootAiInstructions, ...(feature.root_ai_agent_instructions || {}), ...(feature.ai_agent_instructions || {}) };
      const globalSettings = { ...rootGlobalSettings, ...(feature.root_global_settings || {}), ...(feature.global_settings || {}) };

      const testing = feature.testing || {};
      const verifEngine = feature.verification_engine || {};
      const relationships = feature.relationships || {};
      const perfImpact = feature.performance_impact || {};

      contextJson = replaceAll(contextJson, 'ai_agent_instructions', JSON.stringify(aiInstructions, null, 2));
      contextJson = replaceAll(contextJson, 'global_settings', JSON.stringify(globalSettings, null, 2));

      const cvssScore = rawCvssScore || (typeof rawSev === 'object' ? rawSev.cvss_score : '') || '';
      const cvssVector = (typeof rawSev === 'object' ? rawSev.cvss_vector : '') || '';
      const affectedComponents = (typeof rawDesc === 'object' ? rawDesc.affected_components : '') || '';

      contextJson = replaceAll(contextJson, 'cvss_score', cvssScore);
      contextJson = replaceAll(contextJson, 'cvss_vector', cvssVector);
      contextJson = replaceAll(contextJson, 'affected_components', Array.isArray(affectedComponents) ? affectedComponents.join(', ') : (affectedComponents || ''));

      const protection = feature.protection || {};
      const protectionDetails = {
        available_platforms: Array.isArray(rawPlatforms) ? rawPlatforms : (protection.plugin_dependencies || []),
        platform_implementations: rawPlatformImpl
      };
      contextJson = replaceAll(contextJson, 'protection_details', JSON.stringify(protectionDetails, null, 2));

      contextJson = replaceAll(contextJson, 'ui_layout', JSON.stringify(rawUiLayout, null, 2));
      contextJson = replaceAll(contextJson, 'components', JSON.stringify(rawComponents, null, 2));
      contextJson = replaceAll(contextJson, 'actions', JSON.stringify(rawActions, null, 2));

      const testingSpecsFull = {
        payloads: testing.test_payloads || [],
        difficulty: testing.difficulty || 'Medium',
        tools: testing.tools_required || []
      };
      contextJson = replaceAll(contextJson, 'testing_specs', JSON.stringify(testingSpecsFull, null, 2));

      contextJson = replaceAll(contextJson, 'verification_engine', JSON.stringify(verifEngine, null, 2));
      contextJson = replaceAll(contextJson, 'relationships', JSON.stringify(relationships, null, 2));
      contextJson = replaceAll(contextJson, 'performance_impact', JSON.stringify(perfImpact, null, 2));

      contextJson = replaceAll(contextJson, 'reporting', JSON.stringify(feature.reporting || {}, null, 2));
      contextJson = replaceAll(contextJson, 'references', JSON.stringify(feature.references || [], null, 2));

      const rawContext = { ...feature };
      delete rawContext.generated_schema;
      delete rawContext.implementation_data;
      contextJson = replaceAll(contextJson, 'raw_json', JSON.stringify(rawContext, null, 2));
      contextJson = replaceAll(contextJson, 'previous_schema', feature.generated_schema || 'None');

      const prompts = feature.automation_prompts || {};
      contextJson = replaceAll(contextJson, 'automation_prompts.ai_ui', prompts.ai_ui || 'Interactive JSON Schema for VAPT Workbench.');
      contextJson = replaceAll(contextJson, 'automation_prompts.ai_check', prompts.ai_check || `PHP verification logic for ${feature.label || 'this feature'}.`);
      contextJson = replaceAll(contextJson, 'automation_prompts.ai_schema', prompts.ai_schema || `Essential schema fields for ${feature.label || 'this feature'}.`);

      const featureSeverity = typeof rawSev === 'object' ? rawSev : { level: rawSev };
      const businessImpact = featureSeverity.business_impact || '';

      const featureDesc = typeof rawDesc === 'object' ? rawDesc : { summary: rawDesc };
      const detailedDesc = featureDesc.detailed || featureDesc.summary || '';
      const attackScenario = rawScenario || featureDesc.attack_scenario || '';

      const securityObjective = `
        - **PRIMARY GOAL**: Remediate the vulnerability identified as **${feature.id || 'N/A'}** (${feature.label || 'Unnamed Feature'}).
        - **SECURITY MANDATE**: You MUST ensure the implementation provides robust protection against **${Array.isArray(feature.risks) ? feature.risks.join(' and ') : (feature.risks || 'identified risks')}**.
        - **VULNERABILITY CONTEXT**: ${detailedDesc || 'No detailed description provided.'}
        - **ATTACK VECTOR RELEVANCE**: This control specifically defeats the scenario where ${attackScenario || 'an attacker attempts to exploit this weakness'}.
      `.trim();

      const operationalContext = `
        - **Business/Operational Risk**: ${businessImpact || 'N/A'}
        - **Global Compliance Anchor**: This feature maps to **${formatValue(rawOwasp) || 'General Security Best Practices'}**.
        - **Performance Constraint**: ${formatValue(perfImpact) || 'Standard implementation.'}
      `.trim();

      const protocolContext = `
        - **Manual Verification Steps**: ${formatValue(rawVerif) || 'N/A'}
        - **Remediation Effort**: ${feature.protection?.remediation_effort || 'N/A'}
        - **Testing Protocol**: ${formatValue(rawMethod) || 'N/A'}
      `.trim();

      const homeUrl = (settings.homeUrl || '').replace(/\/$/, '');
      const currentDomain = (settings.currentDomain || window.location.hostname || 'hermasnet.local').split(':')[0];
      const includeProtocolLocal = feature.include_manual_protocol !== false;
      const includeNotesLocal = feature.include_operational_notes !== false;

      const strategyLine = isMultiEnv
        ? '**Multi-Platform Parallel Strategy**: You MUST generate a "platform_matrix" including implementations for Apache (.htaccess), Nginx, IIS, Caddy, Cloudflare, and PHP Fallback.'
        : '**Single Enforcer Strategy**: Target ONLY the **' + prioritizedDriver + '** driver. Valid: hook, htaccess, wp-config, nginx, fail2ban, cloudflare, iis, caddy.';

      const finalPrompt = `
      --- ROLE & OBJECTIVE ---
      You are the **VAPT Security Expert Agent**. Your mandate is to generate production-ready Interface Schema JSONs for the **${settings.pluginName || 'VAPT Secure'}** workbench. You MUST achieve \u226590% accuracy by following the deterministic instructions below.

      --- THE FOUR PILLARS OF ACCURACY ---
      1. **Schema-First Generation**: ALWAYS use the provided context as ground truth. Never infer component types, default values, or sections.
      2. **Pattern Library Lookup**: Use the provided 'platform_implementations' for enforcement code. Never hallucinate security rules.
      3. **Enforcer Validation**: Verify the platform exists in 'available_platforms' before outputting.
      4. **Self-Check Rubric**: You MUST score your own output against the rubric below. Only output if score \u2265 13/15.

      --- DESIGN CONTEXT (JSON) ---
      ${contextJson}

      --- SECURITY OBJECTIVE ---
      ${securityObjective}

      --- OPERATIONAL & GLOBAL CONTEXT ---
      ${operationalContext}

      --- MANUAL PROTOCOL CONTEXT ---
      ${protocolContext}

      --- AI AGENT INSTRUCTIONS ---
      ${displayInstruct}

      --- REFERENCE CODE ---
      ${referenceCode || 'No specific reference code provided.'}

      --- INSTRUCTIONS & CRITICAL RULES ---
      1. **Output Format**: Provide ONLY a JSON block. No preamble. No conversational filler.
      2. **Fully Qualified URLs**: Use **site_context.home_url** (${homeUrl}) for ALL URLs and endpoints (e.g. ${homeUrl}/wp-cron.php). Every "url" property MUST be an absolute link. No relative paths.
      3. ${strategyLine}
      4. **Naming Conventions**:
         - Component: Risk{NNN}{TitleCamelCase} (e.g. Risk001WpCronProtection)
         - Handlers: handleRISK{NNN}{EventType}Change (e.g. handleRISK001ToggleChange)
      5. **Absolute Links**: Description fields MUST provide URLs as clean, clickable Markdown links [label](url).
      6. **Key Enforcement**: EVERY control MUST have a unique "key" field.
      7. **Resiliency**: Include "retry_on_failure: true" and failure logic in "test_action" configurations.
      8. **Safety & Compliance**:
         - Mandate **Rollback Verification** steps to ensure site stability.
         - Include **Dependency Checks** (verifying required server modules).
         - Implement **Rate Limiting** logic for probes to protect high-availability environments.

      ${isMultiEnv ? `--- A+ CLIENT-READY REQUIREMENTS (v3.2) ---
      1. **Versioning**: Schema MUST include \`"schema_version": "3.2.0"\` and \`"schema_grade": "A+"\`.
      2. **Runtime Detection**: Include \`runtime_environment_detection\` cascade (header, php, filesystem, function).
      3. **Platform Matrix**: Implement \`implementations\` for: apache_htaccess, nginx_config, iis_config, caddy_config, cloudflare_edge, php_functions.
      4. **Deployment Profiles**: Define \`client_deployment.profiles\` for: Auto-Detect, Maximum, Conservative, Enterprise.
      5. **Unified Test Suite**: Create a single suite that validates protection across ALL active platforms.
      6. **Client Verification**: Include \`client_verification\` with http-probes and user-friendly messaging.` : `--- ADVANCED CHECKPOINTS (v2.0) ---
      1. **Versioning**: Schema MUST include \`"interface_version": "2.0"\`.
      2. **Test Logic**: \`test_action\` MUST include timeout and retry parameters.
      3. **Conditional Logic**: Controls MUST specify \`prerequisites\` or conflicts where applicable.
      4. **Multi-Environment**: Enforcement MUST define a \`fallback_driver\`.
      5. **Audit Trail**: Include telemetry configuration for implementation events.
      6. **UX Visuals**: Add visual indicators and help resource links to the schema.`}

      --- FULL SELF-CHECK RUBRIC (Score 1-19) ---
      You MUST score exactly 19/19 to deliver.
      ${isMultiEnv ? `1. [x] Schema Version is 3.2.0?
      2. [x] Schema Grade is A+?
      3. [x] platform_matrix.implementations contains >= 6 platforms?
      4. [x] Runtime detection cascade defined for client environments?
      5. [x] Unified test suite includes environment-agnostic validation?
      6. [x] Client deployment profiles (Auto-Detect/Enterprise) defined?
      7. [x] Multi-platform UI badges/indicators enabled?
      8. [x] Enrollment is automatic via cascade strategy?
      9. [x] php_functions defined as the last universal fallback?
      10. [x] Rollback verification included for ALL platforms?
      11. [x] Every URL is FULLY QUALIFIED (absolute link)?
      12. [x] VAPT block markers present in all implementation code?
      13. [x] Retry logic included in test_action?
      14. [x] Rate limiting probes defined?
      15. [x] Timeout parameters set for all probe actions?
      16. [x] Prerequisites defined for complex enforcers?
      17. [x] Component names follow PascalCase?
      18. [x] Telemetry/Audit trail configured?
      19. [x] JSON syntax validated?` : `1. [x] Component IDs match schema exactly?
      2. [x] Enforcement code sourced from library?
      3. [x] Severity colors match global config?
      4. [x] Handler names follow PascalCase conventions?
      5. [x] Target platform listed in available_platforms?
      6. [x] VAPT block markers present in output?
      7. [x] Double-Qualification Guard: No redundant domain prepending?
      8. [x] Every URL in test_action is FULLY QUALIFIED (absolute link)?
      9. [x] Descriptions contain functional Markdown Links for URLs?
      10. [x] No forbidden .htaccess directives used?
      11. [x] RewriteRules placed BEFORE # BEGIN WordPress?
      12. [x] RewriteRules wrapped in <IfModule>?
      13. [x] Version 2.0 marker present?
      14. [x] Fallback driver defined in enforcement?
      15. [x] Retry logic included in test_action?
      16. [x] Prerequisites defined for complex controls?
      17. [x] Telemetry/Audit trail configured?
      18. [x] Visual indicators (shield/icon) included?
      19. [x] JSON syntax validated before output?`}

      --- JSON SKELETON ---
      \`\`\`json
      {
        "interface_version": "${isMultiEnv ? '3.2.0' : '2.0'}",
        "metadata": {
          "risk_id": "${feature.id || 'N/A'}",
          "schema_grade": "${isMultiEnv ? 'A+' : 'Standard'}",
          "severity": "${(typeof feature.severity === 'object' ? feature.severity.level : feature.severity) || 'High'}"
        },
        ${includeProtocolLocal ? '"manual_protocol": { "steps": ["Step 1...", "Step 2..."] },' : ''}
        ${includeNotesLocal ? '"operational_notes": "Summary of risks and benefits...",' : ''}
        "controls": [
          {
            "type": "toggle", "label": "Enable Protection", "key": "prot_enabled", "default": false, "visual_indicator": "shield"
          },
          {
            "type": "test_action", "label": "Verify Configuration", "key": "verify_prot",
            "test_config": { "url": "${homeUrl}/...", "expected_status": 403, "retry_on_failure": true }
          }
        ],
        ${isMultiEnv ? `"platform_matrix": {
          "runtime_detection": { "detection_cascade": ["header", "php", "filesystem"] },
          "implementations": {
            "apache_htaccess": { "lib_key": "htaccess", "rollback": { "automatic": true } },
            "nginx_config": { "lib_key": "nginx", "rollback": { "automatic": true } },
            "php_functions": { "lib_key": "php_functions", "universal_fallback": true }
          }
        },
        "client_deployment": { "profiles": { "auto_detect": { "deploy_order": ["apache_htaccess", "php_functions"] } } },` : `"enforcement": {
          "driver": "${prioritizedDriver}",
          "fallback_driver": "hook",
          "target": "${prioritizedDriver === 'htaccess' ? 'root' : 'universal'}",
          "rollback_on_disable": true,
          "mappings": { "prot_enabled": "/* Code */" },
          "telemetry": { "log_events": true }
        },`}
        "ui_layout": { "multi_environment_display": ${isMultiEnv ? 'true' : 'false'} }
      }
      \`\`\`

      Feature: ${feature.label || 'Unnamed'} (${feature.id || 'N/A'})
      `;

      let qualifiedPrompt = finalPrompt;

      const domainPlaceholders = [/https?:\/\/(?:www\.)?(?:domain\.com|yourdomain\.com|example\.com|mysite\.com)/gi, /(?:www\.)?(?:domain\.com|yourdomain\.com|example\.com|mysite\.com)/gi];
      domainPlaceholders.forEach((regex, idx) => {
        if (idx === 0) qualifiedPrompt = qualifiedPrompt.replace(regex, homeUrl);
        else qualifiedPrompt = qualifiedPrompt.replace(regex, currentDomain);
      });

      const relativePaths = [
        /\b\/wp-admin\b/g,
        /\b\/wp-login\.php\b/g,
        /\b\/xmlrpc\.php\b/g,
        /\b\/wp-admin\/admin-ajax\.php\b/g,
        /\b\/wp-cron\.php\b/g,
        /\/\?author=\d+/g,
        /\/\?p=\d+/g
      ];

      relativePaths.forEach(regex => {
        qualifiedPrompt = qualifiedPrompt.replace(regex, (match, offset, fullText) => {
          const prevChar = fullText.substring(offset - 3, offset);
          if (prevChar === '://' || fullText.substring(offset - 7, offset).includes('http')) return match;
          return homeUrl + match;
        });
      });

      const personalizedPrompt = qualifiedPrompt;

      const copyToClipboard = (text) => {
        const fallbackCopy = (t) => {
          const textArea = document.createElement('textarea');
          textArea.value = t;
          textArea.style.position = 'fixed';
          textArea.style.left = '-9999px';
          textArea.style.top = '0';
          document.body.appendChild(textArea);
          textArea.focus();
          textArea.select();
          let success = false;
          try {
            success = document.execCommand('copy');
          } catch (err) {
            vaptLog.error('Fallback Copy failed', err);
          }
          document.body.removeChild(textArea);
          return success ? Promise.resolve() : Promise.reject('ExecCommand Failed');
        };

        if (navigator.clipboard && window.isSecureContext) {
          return navigator.clipboard.writeText(text).catch(err => {
            vaptLog.warn('navigator.clipboard failed, trying fallback...', err);
            return fallbackCopy(text);
          });
        }
        return fallbackCopy(text);
      };

      copyToClipboard(personalizedPrompt)
        .then(() => {
          setSaveStatus({ message: __('Design Prompt copied!', 'vaptsecure'), type: 'success' });
          setTimeout(() => setSaveStatus(null), 3000);
        })
        .catch(err => {
          vaptLog.error('All copy methods failed', err);
          setSaveStatus({ message: __('Copy failed. Please select and copy manually.', 'vaptsecure'), type: 'error' });
          setTimeout(() => setSaveStatus(null), 4000);
        });
    };

    const GeneratedInterface = window.VAPTSECURE_GeneratedInterface;

    return el(Modal, {
      title: el('div', { className: 'vapt-design-modal-header' }, [
        el('div', { className: 'vapt-flex-row', style: { gap: '10px', alignItems: 'center' } }, [
          el('span', null, sprintf(__('Design Implementation: %s', 'vaptsecure'), feature.label)),
        ]),
        el('span', {
          style: {
            display: 'inline-flex',
            alignItems: 'center',
            marginLeft: '15px',
            padding: '3px 10px',
            borderRadius: '12px',
            fontSize: '11px',
            fontWeight: '600',
            color: '#fff',
            textTransform: 'uppercase',
            letterSpacing: '0.5px',
            verticalAlign: 'middle',
            background: (() => {
              const s = (feature.status || 'Draft').toLowerCase();
              if (s === 'develop') return '#10b981';
              if (s === 'test') return '#eab308';
              if (s === 'release') return '#f97316';
              return '#94a3b8';
            })(),
            boxShadow: '0 1px 2px rgba(0,0,0,0.1)'
          }
        }, feature.status || 'Draft'),
        el(Button, {
          isDestructive: true,
          isSmall: true,
          onClick: () => setIsRemoveConfirmOpen(true),
          disabled: isSaving || !feature.generated_schema,
          icon: 'trash'
        }, __('Remove Implementation', 'vaptsecure'))
      ]),
      onRequestClose: onClose,
      className: 'vapt-design-modal',
      id: 'vapt-design-modal-root'
    }, [
      saveStatus && el('div', {
        id: 'vapt-design-modal-banner',
        className: `vapt - modal - banner is - ${saveStatus.type === 'error' ? 'error' : 'success'} `
      }, [
        el(Icon, { icon: saveStatus.type === 'error' ? 'warning' : 'yes', size: 20 }),
        saveStatus.message
      ]),

      el('form', {
        id: 'vapt-design-modal-form',
        onSubmit: (e) => e.preventDefault(),
        className: 'vapt-design-modal-inner-layout'
      }, [
        el('div', { id: 'vapt-design-modal-left-col' }, [
          (() => {
            const isAPlus = (parsedSchema?.metadata?.schema_grade === 'A+' || parsedSchema?.schema_grade === 'A+');
            return el('div', { id: 'vapt-design-modal-actions', className: 'vapt-flex-row' }, [
              !isAPlus && el(Button, { id: 'vapt-btn-copy-prompt', className: 'vapt-btn-flex-center', isSecondary: true, onClick: copyContext, icon: 'clipboard' }, __('Copy AI Design Prompt', 'vaptsecure')),
              el(Button, {
                isDestructive: true,
                icon: 'trash',
                onClick: () => {
                  setConfirmState({
                    message: __('Are you sure you want to reset the schema? This will wash away any changes.', 'vaptsecure'),
                    isDestructive: true,
                    onConfirm: () => {
                      setConfirmState(null);
                      onJsonChange(JSON.stringify(MEANINGFUL_DEFAULT, null, 2));
                      setSaveStatus({ message: __('Schema Reset!', 'vaptsecure'), type: 'success' });
                      setTimeout(() => setSaveStatus(null), 2000);
                    }
                  });
                }
              }, __('Reset', 'vaptsecure'))
            ]);
          })(),

          (() => {
            const isAPlus = (parsedSchema?.metadata?.schema_grade === 'A+' || parsedSchema?.schema_grade === 'A+');
            return el('div', { id: 'vapt-design-modal-toggles', className: 'vapt-flex-col' }, [
              el('div', { className: 'vapt-flex-row', style: { width: '100%', gap: '20px', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: '0px' } }, [
                el('div', { className: 'vapt-flex-col' }, [
                  el(ToggleControl, {
                    label: __('Add Operational Notes', 'vaptsecure'),
                    checked: includeNotes,
                    onChange: setIncludeNotes
                  }),
                  el(ToggleControl, {
                    label: __('Add Manual Verification Notes', 'vaptsecure'),
                    checked: includeProtocol,
                    onChange: setIncludeProtocol
                  })
                ]),
                el('div', { style: { paddingTop: '2px' } }, [
                  el(Button, {
                    isSecondary: true,
                    icon: 'update',
                    onClick: () => {
                      if (window.VAPTSECURE_APlusGenerator) {
                        const tempFeature = { ...feature, include_manual_protocol: includeProtocol, include_operational_notes: includeNotes };
                        const updatedSchema = window.VAPTSECURE_APlusGenerator.generate(tempFeature, customizationText);
                        onJsonChange(JSON.stringify(updatedSchema, null, 2));
                        setSaveStatus({ message: __('Schema Updated!', 'vaptsecure'), type: 'success' });
                        setTimeout(() => setSaveStatus(null), 2000);
                      }
                    }
                  }, __('Update Schema', 'vaptsecure'))
                ])
              ]),
              !isAPlus && el('div', { className: 'vapt-flex-row', style: { gap: '20px' } }, [
                el(ToggleControl, {
                  label: __('Enable A+ Client-Ready Multi-Env Mode (v3.2)', 'vaptsecure'),
                  checked: isMultiEnv,
                  onChange: setIsMultiEnv,
                  help: __('Mandates A+ runtime capability detection and client-ready fallback orchestration.', 'vaptsecure')
                }),
                el(ToggleControl, {
                  label: __('Enable A+ Adaptive Deployment (v4.0)', 'vaptsecure'),
                  checked: isAdaptiveDeployment,
                  onChange: setIsAdaptiveDeployment,
                  help: __('Automatically adapts enforcement to server environment (Apache, Nginx, PHP) with universal fallback.', 'vaptsecure')
                })
              ])
            ]);
          })(),

          el('div', {
            id: 'vapt-design-modal-schema-editor',
            className: 'vapt-flex-col',
            onMouseEnter: () => setIsHoveringSchema(true),
            onMouseLeave: () => setIsHoveringSchema(false)
          }, [
            el('label', { id: 'vapt-schema-editor-label', className: 'vapt-label-uppercase' }, __('A+ Adaptive Script (Source JSON)', 'vaptsecure')),
            el('div', { id: 'vapt-schema-editor-hint', className: 'vapt-text-hint' }, __('Hover and Ctrl+V to replace content.', 'vaptsecure')),
            el('textarea', {
              id: 'vapt-schema-textarea',
              className: 'vapt-textarea-code',
              value: schemaText,
              onChange: (e) => onJsonChange(e.target.value),
              style: {
                background: isHoveringSchema ? '#f0fdf4' : '#fcfcfc',
                minHeight: '300px'
              }
            }),
            el('div', { id: 'vapt-customization-textarea-wrap', className: 'vapt-flex-col', style: { marginTop: '15px' } }, [
              el('label', { className: 'vapt-label-uppercase' }, __('Workbench Customization Guidance', 'vaptsecure')),
              el('textarea', {
                id: 'vapt-customization-textarea',
                className: 'vapt-textarea-custom',
                placeholder: __('Enter custom instructions for A+ Adaptive Logic generation...', 'vaptsecure'),
                value: customizationText,
                onChange: (e) => setCustomizationText(e.target.value),
                style: {
                  minHeight: '120px',
                  background: '#fffbf0',
                  border: '1px solid #f97316'
                }
              })
            ])
          ]),

          (() => {
            const isAPlus = (parsedSchema?.metadata?.schema_grade === 'A+' || parsedSchema?.schema_grade === 'A+');
            if (isAPlus) return null;

            let displayInstruct = feature.dev_instruct || feature.devInstruct || feature.ai_agent_instructions || '';
            if (!displayInstruct && feature.generated_schema) {
              try {
                const schema = typeof feature.generated_schema === 'string' ? JSON.parse(feature.generated_schema) : feature.generated_schema;
                if (schema && schema.instruction) displayInstruct = schema.instruction;
              } catch (e) {
                vaptLog.warn('Failed to extract fallback instructions from schema', e);
              }
            }

            if (!displayInstruct) displayInstruct = __('No specific development guidance available for this feature transition.', 'vaptsecure');

            return el('div', { id: 'vapt-design-modal-guidance', className: 'vapt-flex-col', style: { marginBottom: '15px' } }, [
              el('label', { className: 'vapt-label-uppercase', style: { color: '#2271b1' } }, __('AI Development Guidance')),
              el('div', {
                className: 'vapt-guidance-box',
                style: {
                  background: '#f0f6fb',
                  borderLeft: '4px solid #2271b1',
                  padding: '12px',
                  fontSize: '12px',
                  maxHeight: '180px',
                  overflowY: 'auto',
                  whiteSpace: 'pre-wrap',
                  fontFamily: 'inherit'
                }
              }, (() => {
                const urlRegex = /(https?:\/\/[^\s]+)/g;
                if (!displayInstruct || typeof displayInstruct !== 'string') return displayInstruct;
                const parts = displayInstruct.split(urlRegex);
                return parts.map((part, i) =>
                  part.match(urlRegex)
                    ? el('a', { key: i, href: part, target: '_blank', rel: 'noopener noreferrer', style: { color: '#2271b1', textDecoration: 'underline' } }, part)
                    : part
                );
              })())
            ]);
          })(),
        ]),

        el('div', { id: 'vapt-design-modal-right-col' }, [
          el('div', { className: 'vapt-design-modal-preview-header' }, [
            el('div', { className: 'vapt-flex-row', style: { gap: '8px' } }, [
              el(Icon, { icon: 'visibility', size: 16 }),
              el('strong', { className: 'vapt-preview-title' }, __('Preview Panel: Effective Protections', 'vaptsecure'))
            ]),
            el('div', { className: 'vapt-flex-row', style: { gap: '10px' } }, [
              el(Button, {
                isPrimary: true,
                className: 'vapt-btn-deploy-aplus',
                onClick: handleSave,
                isBusy: isSaving,
                icon: 'cloud-upload',
                style: { background: '#10b981', borderColor: '#059669', fontWeight: 'bold' }
              }, __('Deploy', 'vaptsecure')),
              el(Button, { isSecondary: true, isSmall: true, onClick: onClose }, __('Cancel', 'vaptsecure')),
              el(Button, { isSecondary: true, isSmall: true, onClick: handleSave, isBusy: isSaving }, isAdaptiveDeployment ? __('Deploy', 'vaptsecure') : __('Implement', 'vaptsecure'))
            ])
          ]),
          el('div', { className: 'vapt-design-modal-preview-body' }, [
            (() => {
              const schema = parsedSchema || { controls: [] };
              return el('div', { id: 'vapt-design-modal-preview-stack', className: 'vapt-flex-col' }, [
                el('div', { className: 'vapt-card-box' }, [
                  el('h4', { className: 'vapt-card-title' }, __('Functional Implementation')),
                  GeneratedInterface
                    ? el(GeneratedInterface, {
                      feature: { ...feature, generated_schema: schema, implementation_data: localImplData },
                      onUpdate: (newData) => setLocalImplData(newData)
                    })
                    : el('p', null, __('Loading Preview Interface...', 'vaptsecure'))
                ])
              ]);
            })()
          ])
        ])
      ]),

      isRemoveConfirmOpen && el(Modal, {
        title: __('Confirm Removal', 'vaptsecure'),
        onRequestClose: () => setIsRemoveConfirmOpen(false),
        style: { maxWidth: '450px' }
      }, [
        el('div', { style: { padding: '25px', textAlign: 'center' } }, [
          el(Icon, { icon: 'warning', size: 42, style: { color: '#dc2626', marginBottom: '15px' } }),
          el('h3', null, __('Remove Implementation?', 'vaptsecure')),
          el('p', { style: { fontSize: '13px', color: '#6b7280' } }, __('Are you sure? This cannot be undone.', 'vaptsecure')),
          el('div', { style: { display: 'flex', gap: '12px', justifyContent: 'center', marginTop: '20px' } }, [
            el(Button, { isSecondary: true, onClick: () => setIsRemoveConfirmOpen(false) }, __('Cancel', 'vaptsecure')),
            el(Button, { isDestructive: true, onClick: handleRemoveConfirm, isBusy: isSaving }, __('Yes, Remove It', 'vaptsecure'))
          ])
        ])
      ]),

      alertState && el(VAPTSECURE_AlertModal, {
        isOpen: true,
        message: alertState.message,
        type: alertState.type,
        onClose: () => setAlertState(null)
      }),
      confirmState && el(VAPTSECURE_ConfirmModal, {
        isOpen: true,
        message: confirmState.message,
        isDestructive: confirmState.isDestructive,
        onConfirm: confirmState.onConfirm,
        onCancel: () => setConfirmState(null)
      })
    ]);
  };

  window.VAPTSECURE_HistoryModal = window.VAPTSECURE_HistoryModal || HistoryModal;
  window.VAPTSECURE_DesignModal = window.VAPTSECURE_DesignModal || DesignModal;
})();
